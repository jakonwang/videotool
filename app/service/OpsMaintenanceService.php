<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class OpsMaintenanceService
{
    /**
     * @return array<int, string>
     */
    public static function migrationScripts(): array
    {
        $preferred = [
            'run_migration_admin_users.php',
            'run_migration_client_app.php',
            'run_migration_extensions.php',
            'run_migration_module_governance.php',
            'run_migration_product_distribution.php',
            'run_migration_product_influencer_category.php',
            'run_migration_outreach.php',
            'run_migration_category_crm_outreach.php',
            'run_migration_influencers_crm.php',
            'run_migration_mobile_outreach.php',
            'run_migration_product_style_search.php',
            'run_migration_product_style_is_queue.php',
            'run_migration_product_style_unique_code.php',
            'run_migration_product_style_image_path.php',
            'run_migration_product_style_import_tasks.php',
            'run_migration_product_style_orders.php',
            'run_migration_product_style_price_levels.php',
            'run_migration_openai_vision_columns.php',
            'run_migration_tikstar_ops2.php',
            'run_migration_tenant_saas_material.php',
            'run_migration_profit_center.php',
            'run_migration_profit_store_currency.php',
            'run_migration_auto_dm_v1.php',
            'run_migration_auto_dm_v2.php',
        ];
        $all = [];
        $dbDir = self::databaseDir();
        if (is_dir($dbDir)) {
            $entries = scandir($dbDir);
            if (is_array($entries)) {
                foreach ($entries as $entry) {
                    if (!is_string($entry)) {
                        continue;
                    }
                    if (preg_match('/^run_migration.*\.php$/i', $entry)) {
                        $all[] = $entry;
                    }
                }
            }
        }
        $all = array_values(array_unique($all));
        sort($all);

        $ordered = [];
        foreach ($preferred as $name) {
            if (in_array($name, $all, true)) {
                $ordered[] = $name;
            }
        }
        foreach ($all as $name) {
            if (!in_array($name, $ordered, true)) {
                $ordered[] = $name;
            }
        }

        return $ordered;
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(): array
    {
        $scripts = self::migrationScripts();
        $historyMap = self::loadHistoryMap();
        $exists = [];
        $appliedCount = 0;
        $pendingCount = 0;
        foreach ($scripts as $script) {
            $history = $historyMap[$script] ?? null;
            $applied = is_array($history) && (int) ($history['status'] ?? 0) === 1 ? 1 : 0;
            if ($applied === 1) {
                ++$appliedCount;
            } else {
                ++$pendingCount;
            }
            $exists[] = [
                'name' => $script,
                'exists' => is_file(self::databaseDir() . DIRECTORY_SEPARATOR . $script) ? 1 : 0,
                'applied' => $applied,
                'last_run_at' => is_array($history) ? (string) ($history['last_run_at'] ?? '') : '',
            ];
        }
        $gitInfo = self::gitInfo();

        return [
            'php_bin' => self::resolvePhpBin(),
            'exec_available' => self::canExec() ? 1 : 0,
            'script_count' => count($exists),
            'script_applied_count' => $appliedCount,
            'script_pending_count' => $pendingCount,
            'scripts' => $exists,
            'git' => $gitInfo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function runMigrations(): array
    {
        if (!self::canExec()) {
            return [
                'ok' => false,
                'message' => 'exec_not_available',
                'results' => [],
                'success_count' => 0,
                'failed_count' => 0,
            ];
        }
        $phpBin = self::resolvePhpBin();
        $root = self::projectRoot();
        $historyMap = self::loadHistoryMap();
        $results = [];
        $successCount = 0; // actually executed and succeeded
        $skippedCount = 0;
        $failedCount = 0;

        foreach (self::migrationScripts() as $script) {
            $abs = self::databaseDir() . DIRECTORY_SEPARATOR . $script;
            if (!is_file($abs)) {
                $results[] = [
                    'script' => $script,
                    'code' => 127,
                    'ok' => false,
                    'output' => 'script_not_found',
                ];
                ++$failedCount;
                continue;
            }
            $checksum = self::fileChecksum($abs);
            $history = $historyMap[$script] ?? null;
            if (is_array($history)
                && (int) ($history['status'] ?? 0) === 1
                && (string) ($history['checksum'] ?? '') !== ''
                && (string) ($history['checksum'] ?? '') === $checksum
            ) {
                $results[] = [
                    'script' => $script,
                    'code' => 0,
                    'ok' => true,
                    'skipped' => true,
                    'output' => 'already_applied',
                ];
                ++$skippedCount;
                continue;
            }

            $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($abs);
            $ret = self::runCommand($cmd, $root);
            $ok = (int) ($ret['code'] ?? 1) === 0;
            if ($ok) {
                ++$successCount;
            } else {
                ++$failedCount;
            }
            self::saveHistory(
                $script,
                $checksum,
                $ok,
                (string) ($ret['output'] ?? '')
            );
            $historyMap[$script] = [
                'checksum' => $checksum,
                'status' => $ok ? 1 : 0,
                'last_run_at' => date('Y-m-d H:i:s'),
            ];
            $results[] = [
                'script' => $script,
                'code' => (int) ($ret['code'] ?? 1),
                'ok' => $ok,
                'skipped' => false,
                'output' => (string) ($ret['output'] ?? ''),
            ];
        }

        return [
            'ok' => $failedCount === 0,
            'message' => $failedCount === 0 ? 'ok' : 'partial_failed',
            'results' => $results,
            'success_count' => $successCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'php_bin' => $phpBin,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function gitPull(bool $allowDirty = false): array
    {
        if (!self::canExec()) {
            return [
                'ok' => false,
                'message' => 'exec_not_available',
            ];
        }
        $root = self::projectRoot();
        if (!is_dir($root . DIRECTORY_SEPARATOR . '.git')) {
            return [
                'ok' => false,
                'message' => 'git_repo_not_found',
            ];
        }

        $branch = trim((string) (self::runCommand('git rev-parse --abbrev-ref HEAD', $root)['output'] ?? ''));
        $before = trim((string) (self::runCommand('git rev-parse HEAD', $root)['output'] ?? ''));
        $dirtyOutput = (string) (self::runCommand('git status --porcelain', $root)['output'] ?? '');
        $dirtyCount = self::nonEmptyLines($dirtyOutput);
        if ($dirtyCount > 0 && !$allowDirty) {
            return [
                'ok' => false,
                'message' => 'git_worktree_dirty',
                'branch' => $branch,
                'dirty_count' => $dirtyCount,
                'before_commit' => $before,
            ];
        }

        $pullRet = self::runCommand('git pull --ff-only', $root);
        $after = trim((string) (self::runCommand('git rev-parse HEAD', $root)['output'] ?? ''));
        $ok = (int) ($pullRet['code'] ?? 1) === 0;

        return [
            'ok' => $ok,
            'message' => $ok ? 'ok' : 'git_pull_failed',
            'branch' => $branch,
            'dirty_count' => $dirtyCount,
            'before_commit' => $before,
            'after_commit' => $after,
            'updated' => ($before !== '' && $after !== '' && $before !== $after) ? 1 : 0,
            'output' => (string) ($pullRet['output'] ?? ''),
            'code' => (int) ($pullRet['code'] ?? 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function gitInfo(): array
    {
        $root = self::projectRoot();
        if (!is_dir($root . DIRECTORY_SEPARATOR . '.git') || !self::canExec()) {
            return [
                'available' => 0,
                'branch' => '',
                'dirty_count' => 0,
                'commit' => '',
            ];
        }

        $branch = trim((string) (self::runCommand('git rev-parse --abbrev-ref HEAD', $root)['output'] ?? ''));
        $commit = trim((string) (self::runCommand('git rev-parse HEAD', $root)['output'] ?? ''));
        $dirty = (string) (self::runCommand('git status --porcelain', $root)['output'] ?? '');

        return [
            'available' => 1,
            'branch' => $branch,
            'dirty_count' => self::nonEmptyLines($dirty),
            'commit' => $commit,
        ];
    }

    private static function projectRoot(): string
    {
        $root = (string) root_path();
        return rtrim($root, "\\/");
    }

    private static function databaseDir(): string
    {
        return self::projectRoot() . DIRECTORY_SEPARATOR . 'database';
    }

    private static function resolvePhpBin(): string
    {
        $candidates = [];

        $envPhp = trim((string) getenv('OPS_PHP_BIN'));
        if ($envPhp !== '') {
            $candidates[] = $envPhp;
        }

        $currentBin = trim((string) PHP_BINARY);
        if ($currentBin !== '' && !self::isFpmBinary($currentBin)) {
            $candidates[] = $currentBin;
        }

        $phpBindir = rtrim((string) PHP_BINDIR, "\\/");
        if ($phpBindir !== '') {
            $candidates[] = $phpBindir . DIRECTORY_SEPARATOR . 'php';
            if (DIRECTORY_SEPARATOR === '\\') {
                $candidates[] = $phpBindir . DIRECTORY_SEPARATOR . 'php.exe';
            }
        }

        $pathPhp = self::detectPhpFromPath();
        if ($pathPhp !== '') {
            $candidates[] = $pathPhp;
        }

        if (DIRECTORY_SEPARATOR === '/') {
            $candidates[] = '/usr/bin/php';
            $candidates[] = '/usr/local/bin/php';
            $candidates[] = '/opt/php/bin/php';
            $candidates[] = '/www/server/php/82/bin/php';
            $candidates[] = '/www/server/php/81/bin/php';
            $candidates[] = '/www/server/php/80/bin/php';
            $candidates[] = '/www/server/php/74/bin/php';
        }

        $seen = [];
        foreach ($candidates as $candidate) {
            $bin = trim((string) $candidate);
            if ($bin === '') {
                continue;
            }
            if (isset($seen[$bin])) {
                continue;
            }
            $seen[$bin] = 1;
            if (self::isUsableCliPhp($bin)) {
                return $bin;
            }
        }

        return 'php';
    }

    private static function canExec(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = trim((string) ini_get('disable_functions'));
        if ($disabled === '') {
            return true;
        }
        $parts = preg_split('/\s*,\s*/', $disabled) ?: [];
        foreach ($parts as $fn) {
            if (strtolower(trim((string) $fn)) === 'exec') {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array{code:int,output:string}
     */
    private static function runCommand(string $cmd, string $cwd): array
    {
        $oldCwd = getcwd();
        $output = [];
        $code = 1;
        try {
            if ($cwd !== '' && is_dir($cwd)) {
                @chdir($cwd);
            }
            @exec($cmd . ' 2>&1', $output, $code);
        } catch (\Throwable $e) {
            $output[] = $e->getMessage();
            $code = 1;
        } finally {
            if (is_string($oldCwd) && $oldCwd !== '') {
                @chdir($oldCwd);
            }
        }

        return [
            'code' => (int) $code,
            'output' => implode(PHP_EOL, is_array($output) ? $output : []),
        ];
    }

    private static function nonEmptyLines(string $raw): int
    {
        if (trim($raw) === '') {
            return 0;
        }
        $parts = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $count = 0;
        foreach ($parts as $line) {
            if (trim((string) $line) !== '') {
                ++$count;
            }
        }
        return $count;
    }

    private static function detectPhpFromPath(): string
    {
        $root = self::projectRoot();
        if (DIRECTORY_SEPARATOR === '\\') {
            $ret = self::runCommand('where php', $root);
            if ((int) ($ret['code'] ?? 1) !== 0) {
                return '';
            }
            $lines = preg_split('/\r\n|\r|\n/', (string) ($ret['output'] ?? '')) ?: [];
            foreach ($lines as $line) {
                $v = trim((string) $line);
                if ($v !== '') {
                    return $v;
                }
            }
            return '';
        }

        $ret = self::runCommand('command -v php', $root);
        if ((int) ($ret['code'] ?? 1) !== 0) {
            return '';
        }
        return trim((string) ($ret['output'] ?? ''));
    }

    private static function isUsableCliPhp(string $bin): bool
    {
        $candidate = trim($bin);
        if ($candidate === '') {
            return false;
        }
        if (self::isFpmBinary($candidate)) {
            return false;
        }

        $ret = self::runCommand(escapeshellarg($candidate) . ' -v', self::projectRoot());
        if ((int) ($ret['code'] ?? 1) !== 0) {
            return false;
        }
        $out = strtolower(trim((string) ($ret['output'] ?? '')));
        if ($out === '') {
            return false;
        }
        if (strpos($out, 'php-fpm') !== false) {
            return false;
        }
        return strpos($out, 'php ') !== false;
    }

    private static function isFpmBinary(string $bin): bool
    {
        $base = strtolower(basename($bin));
        return $base === 'php-fpm' || strpos($base, 'php-fpm') !== false;
    }

    private static function fileChecksum(string $path): string
    {
        $sum = @sha1_file($path);
        return is_string($sum) ? $sum : '';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function loadHistoryMap(): array
    {
        $map = [];
        if (!self::ensureHistoryTable()) {
            return $map;
        }
        try {
            $rows = Db::name('ops_migration_history')
                ->field('script_name,checksum,status,last_run_at')
                ->select()
                ->toArray();
            if (!is_array($rows)) {
                return $map;
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['script_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $map[$name] = $row;
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $map;
    }

    private static function saveHistory(string $script, string $checksum, bool $ok, string $message): void
    {
        if ($script === '' || !self::ensureHistoryTable()) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $data = [
            'script_name' => $script,
            'checksum' => $checksum,
            'status' => $ok ? 1 : 0,
            'last_run_at' => $now,
            'message' => substr($message, 0, 65535),
            'updated_at' => $now,
        ];
        try {
            $id = (int) Db::name('ops_migration_history')->where('script_name', $script)->value('id');
            if ($id > 0) {
                Db::name('ops_migration_history')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = $now;
                Db::name('ops_migration_history')->insert($data);
            }
        } catch (\Throwable $e) {
        }
    }

    private static function ensureHistoryTable(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $tableName = Db::name('ops_migration_history')->getTable();
            $sql = sprintf(
                "CREATE TABLE IF NOT EXISTS `%s` (
                    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                    `script_name` varchar(255) NOT NULL,
                    `checksum` varchar(64) NOT NULL DEFAULT '',
                    `status` tinyint NOT NULL DEFAULT 0,
                    `message` mediumtext NULL,
                    `last_run_at` datetime NULL,
                    `created_at` datetime NULL,
                    `updated_at` datetime NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq_script_name` (`script_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                str_replace('`', '``', (string) $tableName)
            );
            Db::execute($sql);
            $ready = true;
            return true;
        } catch (\Throwable $e) {
            $ready = false;
            return false;
        }
    }
}
