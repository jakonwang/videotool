<?php
declare(strict_types=1);

namespace app\service;

class ChunkUploadService
{
    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        $base = $baseDir ?: (runtime_path() . 'upload_chunks');
        $this->baseDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base), DIRECTORY_SEPARATOR);
    }

    public function normalizeUploadId(string $uploadId): string
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($uploadId));
        $id = (string) $id;
        if ($id === '') {
            throw new \InvalidArgumentException('invalid_upload_id');
        }
        if (strlen($id) > 64) {
            $id = substr($id, 0, 64);
        }
        return $id;
    }

    public function saveChunk(string $scope, int $tenantId, string $uploadId, int $chunkIndex, string $sourcePath): string
    {
        if ($chunkIndex < 0) {
            throw new \InvalidArgumentException('invalid_chunk_index');
        }
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new \RuntimeException('chunk_unreadable');
        }
        $dir = $this->chunkDir($scope, $tenantId, $uploadId);
        $this->ensureDir($dir);
        $target = $this->chunkPath($dir, $chunkIndex);
        if (!@copy($sourcePath, $target)) {
            throw new \RuntimeException('save_chunk_failed');
        }
        return $target;
    }

    public function countUploadedChunks(string $scope, int $tenantId, string $uploadId): int
    {
        $dir = $this->chunkDir($scope, $tenantId, $uploadId);
        if (!is_dir($dir)) {
            return 0;
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . 'chunk_*.part');
        return is_array($files) ? count($files) : 0;
    }

    public function hasAllChunks(string $scope, int $tenantId, string $uploadId, int $totalChunks): bool
    {
        if ($totalChunks <= 0) {
            return false;
        }
        $dir = $this->chunkDir($scope, $tenantId, $uploadId);
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!is_file($this->chunkPath($dir, $i))) {
                return false;
            }
        }
        return true;
    }

    public function buildMergedFilePath(string $scope, int $tenantId, string $uploadId, string $ext): string
    {
        $safeExt = strtolower(trim($ext));
        if (!preg_match('/^[a-z0-9]{1,8}$/', $safeExt)) {
            $safeExt = 'tmp';
        }
        $scopeSafe = $this->normalizeScope($scope);
        $mergeDir = $this->baseDir
            . DIRECTORY_SEPARATOR . 'merged'
            . DIRECTORY_SEPARATOR . $scopeSafe
            . DIRECTORY_SEPARATOR . max(1, $tenantId);
        $this->ensureDir($mergeDir);
        return $mergeDir . DIRECTORY_SEPARATOR . $uploadId . '.' . $safeExt;
    }

    public function mergeChunks(
        string $scope,
        int $tenantId,
        string $uploadId,
        int $totalChunks,
        string $targetPath
    ): void {
        if ($totalChunks <= 0) {
            throw new \InvalidArgumentException('invalid_total_chunks');
        }
        $dir = $this->chunkDir($scope, $tenantId, $uploadId);
        $targetDir = dirname($targetPath);
        $this->ensureDir($targetDir);

        $out = @fopen($targetPath, 'wb');
        if (!$out) {
            throw new \RuntimeException('create_target_failed');
        }

        $bufferSize = 1024 * 1024;
        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $part = $this->chunkPath($dir, $i);
                if (!is_file($part) || !is_readable($part)) {
                    throw new \RuntimeException('chunk_missing_' . $i);
                }

                $in = @fopen($part, 'rb');
                if (!$in) {
                    throw new \RuntimeException('open_chunk_failed_' . $i);
                }
                try {
                    while (!feof($in)) {
                        $data = fread($in, $bufferSize);
                        if ($data === false) {
                            throw new \RuntimeException('read_chunk_failed_' . $i);
                        }
                        if ($data !== '' && fwrite($out, $data) === false) {
                            throw new \RuntimeException('write_target_failed');
                        }
                    }
                } finally {
                    fclose($in);
                }
            }
        } catch (\Throwable $e) {
            @fclose($out);
            @unlink($targetPath);
            throw $e;
        }

        fclose($out);
    }

    public function cleanup(string $scope, int $tenantId, string $uploadId): void
    {
        $dir = $this->chunkDir($scope, $tenantId, $uploadId);
        $this->removeDirRecursive($dir);
    }

    private function chunkDir(string $scope, int $tenantId, string $uploadId): string
    {
        $scopeSafe = $this->normalizeScope($scope);
        return $this->baseDir
            . DIRECTORY_SEPARATOR . $scopeSafe
            . DIRECTORY_SEPARATOR . max(1, $tenantId)
            . DIRECTORY_SEPARATOR . $uploadId;
    }

    private function chunkPath(string $dir, int $chunkIndex): string
    {
        $index = str_pad((string) $chunkIndex, 8, '0', STR_PAD_LEFT);
        return $dir . DIRECTORY_SEPARATOR . 'chunk_' . $index . '.part';
    }

    private function normalizeScope(string $scope): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($scope));
        $safe = (string) $safe;
        if ($safe === '') {
            throw new \InvalidArgumentException('invalid_scope');
        }
        if (strlen($safe) > 32) {
            $safe = substr($safe, 0, 32);
        }
        return $safe;
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('mkdir_failed');
        }
    }

    private function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}

