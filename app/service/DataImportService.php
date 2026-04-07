<?php
declare(strict_types=1);

namespace app\service;

use app\model\ImportJob as ImportJobModel;
use app\model\ImportJobLog as ImportJobLogModel;

class DataImportService
{
    public const JOB_QUEUED = 0;
    public const JOB_RUNNING = 1;
    public const JOB_SUCCESS = 2;
    public const JOB_FAILED = 3;
    public const JOB_PARTIAL = 4;

    /**
     * @param array<string, mixed> $payload
     */
    public static function createJob(string $domain, string $jobType, string $fileName = '', ?int $sourceId = null, array $payload = []): int
    {
        $row = ImportJobModel::create([
            'source_id' => $sourceId,
            'domain' => mb_substr(trim($domain) !== '' ? trim($domain) : 'generic', 0, 32),
            'job_type' => mb_substr(trim($jobType) !== '' ? trim($jobType) : 'csv', 0, 32),
            'file_name' => $fileName !== '' ? mb_substr($fileName, 0, 255) : null,
            'status' => self::JOB_RUNNING,
            'started_at' => date('Y-m-d H:i:s'),
            'payload_json' => $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ]);

        return (int) $row->id;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function addJobLog(int $jobId, string $level, string $message, array $context = []): void
    {
        if ($jobId <= 0) {
            return;
        }
        ImportJobLogModel::create([
            'job_id' => $jobId,
            'level' => mb_substr(trim($level) !== '' ? trim($level) : 'info', 0, 16),
            'message' => mb_substr($message, 0, 255),
            'context_json' => $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    public static function finishJob(int $jobId, int $status, int $total, int $success, int $failed, string $errorMessage = ''): void
    {
        if ($jobId <= 0) {
            return;
        }
        ImportJobModel::where('id', $jobId)->update([
            'status' => $status,
            'total_rows' => max(0, $total),
            'success_rows' => max(0, $success),
            'failed_rows' => max(0, $failed),
            'error_message' => $errorMessage !== '' ? mb_substr($errorMessage, 0, 255) : null,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Parse uploaded CSV to normalized rows.
     *
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    public static function parseCsvFile(string $filePath): array
    {
        $headers = [];
        $rows = [];
        $f = new \SplFileObject($filePath, 'r');
        $f->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $f->setCsvControl(',');
        $lineNo = 0;
        while (!$f->eof()) {
            $line = $f->fgetcsv();
            if (!is_array($line)) {
                continue;
            }
            $lineNo++;
            if ($lineNo === 1) {
                $headers = self::normalizeHeaders($line);
                continue;
            }
            if ($headers === []) {
                continue;
            }
            $row = [];
            $hasValue = false;
            foreach ($headers as $idx => $key) {
                $v = isset($line[$idx]) ? trim((string) $line[$idx]) : '';
                $row[$key] = $v;
                if ($v !== '') {
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $rows[] = $row;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param array<int, mixed> $headers
     * @return list<string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $h) {
            $k = mb_strtolower(trim((string) $h), 'UTF-8');
            $k = preg_replace('/^\xEF\xBB\xBF/u', '', $k) ?? $k;
            $k = preg_replace('/[^a-z0-9_]+/i', '_', $k) ?? $k;
            $k = trim((string) $k, '_');
            $out[] = $k !== '' ? $k : ('col_' . (count($out) + 1));
        }
        return $out;
    }
}

