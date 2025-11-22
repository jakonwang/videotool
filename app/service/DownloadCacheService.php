<?php
declare(strict_types=1);

namespace app\service;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use think\facade\Config;

class DownloadCacheService
{
    private array $config;
    private string $root;

    public function __construct()
    {
        $this->config = Config::get('download_cache', []);
        $this->root = rtrim($this->config['root']
            ?? (runtime_path() . 'download_cache'), DIRECTORY_SEPARATOR);
    }

    public function isEnabled(): bool
    {
        return !empty($this->config['enabled']);
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    public function getStats(): array
    {
        $totalSize = 0;
        $totalFiles = 0;
        if (is_dir($this->root)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile() && $fileInfo->getExtension() !== 'lock') {
                    $totalSize += $fileInfo->getSize();
                    if ($fileInfo->getExtension() !== 'json') {
                        $totalFiles++;
                    }
                }
            }
        }

        return [
            'enabled' => $this->isEnabled(),
            'root' => $this->root,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
            'total_files' => $totalFiles,
            'expire_seconds' => $this->config['expire_seconds'] ?? null,
            'min_file_size' => $this->config['min_file_size'] ?? null,
        ];
    }

    public function listCaches(int $page = 1, int $perPage = 20, string $keyword = ''): array
    {
        $items = [];
        if (!is_dir($this->root)) {
            return [
                'items' => [],
                'total' => 0,
                'per_page' => $perPage,
                'page' => $page,
            ];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'json') {
                $meta = $this->safeDecode($fileInfo->getPathname());
                $hash = $meta['hash'] ?? pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME);
                $dataFile = $this->resolvePathFromHash($hash);
                if (!$dataFile || !file_exists($dataFile)) {
                    continue;
                }
                $size = filesize($dataFile);
                $cachedAt = $meta['cached_at'] ?? filemtime($dataFile);
                $record = [
                    'hash' => $hash,
                    'file_name' => $meta['file_name'] ?? basename($dataFile),
                    'type' => $meta['type'] ?? 'video',
                    'size' => $size,
                    'size_human' => $this->formatBytes($size),
                    'cached_at' => $cachedAt,
                    'cached_at_text' => date('Y-m-d H:i:s', $cachedAt),
                    'source_url' => $meta['source_url'] ?? '',
                    'video_id' => $meta['video_id'] ?? null,
                    'platform' => $meta['platform'] ?? null,
                    'path' => $dataFile,
                ];

                if ($keyword !== '') {
                    $keywordLower = mb_strtolower($keyword);
                    $haystack = mb_strtolower($record['file_name'] . ' ' . $record['source_url']);
                    if (mb_strpos($haystack, $keywordLower) === false) {
                        continue;
                    }
                }
                $items[] = $record;
            }
        }

        usort($items, function ($a, $b) {
            return $b['cached_at'] <=> $a['cached_at'];
        });

        $total = count($items);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $pagedItems = array_slice($items, $offset, $perPage);

        return [
            'items' => $pagedItems,
            'total' => $total,
            'per_page' => $perPage,
            'page' => $page,
        ];
    }

    public function deleteByHash(string $hash): bool
    {
        if (!$hash || !preg_match('/^[a-f0-9]{40}$/i', $hash)) {
            return false;
        }
        $path = $this->resolvePathFromHash($hash);
        if (!$path || !file_exists($path)) {
            return false;
        }
        @unlink($path);
        $meta = $this->resolveMetaPath($hash);
        if ($meta && file_exists($meta)) {
            @unlink($meta);
        }
        $lock = $this->resolveLockPath($hash);
        if ($lock && file_exists($lock)) {
            @unlink($lock);
        }
        return true;
    }

    public function clearAll(): void
    {
        if (!is_dir($this->root)) {
            return;
        }
        $this->deleteDirectory($this->root);
        @mkdir($this->root, 0775, true);
    }

    public function getFileForDownload(string $hash): ?array
    {
        $path = $this->resolvePathFromHash($hash);
        if (!$path || !file_exists($path)) {
            return null;
        }
        $meta = $this->resolveMetaPath($hash);
        $fileName = basename($path);
        if ($meta && file_exists($meta)) {
            $metaData = $this->safeDecode($meta);
            $fileName = $metaData['file_name'] ?? $fileName;
        }
        return [
            'path' => $path,
            'file_name' => $fileName,
        ];
    }

    private function resolvePathFromHash(string $hash): ?string
    {
        if (!$hash || !preg_match('/^[a-f0-9]{40}$/i', $hash)) {
            return null;
        }
        $subDir = substr($hash, 0, 2) . DIRECTORY_SEPARATOR . substr($hash, 2, 2);
        $dir = $this->root . DIRECTORY_SEPARATOR . $subDir;
        $files = glob($dir . DIRECTORY_SEPARATOR . $hash . '.*');
        if (!$files) {
            return null;
        }
        foreach ($files as $file) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if ($ext !== 'json' && $ext !== 'lock' && $ext !== 'part') {
                return $file;
            }
        }
        return null;
    }

    private function resolveMetaPath(string $hash): ?string
    {
        if (!$hash || !preg_match('/^[a-f0-9]{40}$/i', $hash)) {
            return null;
        }
        $subDir = substr($hash, 0, 2) . DIRECTORY_SEPARATOR . substr($hash, 2, 2);
        return $this->root . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . $hash . '.json';
    }

    private function resolveLockPath(string $hash): ?string
    {
        if (!$hash || !preg_match('/^[a-f0-9]{40}$/i', $hash)) {
            return null;
        }
        $subDir = substr($hash, 0, 2) . DIRECTORY_SEPARATOR . substr($hash, 2, 2);
        return $this->root . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . $hash . '.lock';
    }

    private function safeDecode(string $file): array
    {
        $raw = @file_get_contents($file);
        if (!$raw) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int)floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}

