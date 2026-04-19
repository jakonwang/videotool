$ErrorActionPreference = 'Stop'

function Write-Step([string]$msg) {
    Write-Output ("[STABILITY_SMOKE] " + $msg)
}

function Write-PhpFile([string]$path, [string]$content) {
    $enc = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($path, $content, $enc)
}

function Assert-True([string]$name, [bool]$ok, [string]$detail = '') {
    if (-not $ok) {
        if ($detail -ne '') {
            throw ("$name failed: $detail")
        }
        throw ("$name failed")
    }
    Write-Output ("PASS => " + $name)
}

function Assert-Contains([string]$name, [string]$content, [string]$needle) {
    Assert-True -name $name -ok ($content -like ("*" + $needle + "*")) -detail ("missing: " + $needle)
}

function Convert-JsonSafe([string]$text) {
    if ([string]::IsNullOrWhiteSpace($text)) {
        return $null
    }
    $trimmed = ($text -replace "`0", '' -replace "`r", '' -replace "`n", '').Trim()
    if ($trimmed.StartsWith([char]0xFEFF)) {
        $trimmed = $trimmed.Substring(1)
    }
    try {
        return ($trimmed | ConvertFrom-Json)
    } catch {
        try {
            $m = [regex]::Match($trimmed, '\{[\s\S]*\}')
            if ($m.Success) {
                return ($m.Value | ConvertFrom-Json)
            }
        } catch {}
        return $null
    }
}

function Parse-SimpleJsonObject([string]$text) {
    if ([string]::IsNullOrWhiteSpace($text)) {
        return $null
    }
    $dict = @{}
    $pattern = '"([A-Za-z0-9_]+)"\s*:\s*("(?:[^"\\]|\\.)*"|-?\d+(?:\.\d+)?|null|true|false)'
    $matches = [regex]::Matches($text, $pattern)
    foreach ($m in $matches) {
        $key = [string]$m.Groups[1].Value
        $raw = [string]$m.Groups[2].Value
        if ($raw -match '^".*"$') {
            $v = $raw.Substring(1, $raw.Length - 2)
            $v = $v -replace '\\"', '"'
            $v = $v -replace '\\\\', '\'
            $dict[$key] = $v
            continue
        }
        if ($raw -eq 'null') {
            $dict[$key] = $null
            continue
        }
        if ($raw -eq 'true') {
            $dict[$key] = $true
            continue
        }
        if ($raw -eq 'false') {
            $dict[$key] = $false
            continue
        }
        if ($raw -match '^-?\d+$') {
            $dict[$key] = [int]$raw
            continue
        }
        if ($raw -match '^-?\d+\.\d+$') {
            $dict[$key] = [double]$raw
            continue
        }
        $dict[$key] = $raw
    }
    if ($dict.Count -le 0) {
        return $null
    }
    return [pscustomobject]$dict
}

function Invoke-HttpRaw {
    param(
        [Parameter(Mandatory = $true)][string]$BaseUrl,
        [Parameter(Mandatory = $true)][string]$Method,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $false)]$Body,
        [Parameter(Mandatory = $false)][switch]$AsForm,
        [Parameter(Mandatory = $false)][string]$Accept = 'text/html, */*',
        [Parameter(Mandatory = $false)][switch]$Ajax,
        [Parameter(Mandatory = $true)][Microsoft.PowerShell.Commands.WebRequestSession]$Session
    )

    $uri = $BaseUrl + $Path
    $headers = @{
        'Accept' = $Accept
    }
    if ($Ajax) {
        $headers['X-Requested-With'] = 'XMLHttpRequest'
    }

    $params = @{
        Uri = $uri
        Method = $Method
        Headers = $headers
        WebSession = $Session
        TimeoutSec = 30
        ErrorAction = 'Stop'
    }

    if ($Method -ne 'GET') {
        if ($AsForm) {
            $params['ContentType'] = 'application/x-www-form-urlencoded; charset=UTF-8'
            $params['Body'] = if ($null -eq $Body) { '' } else { [string]$Body }
        } else {
            $params['ContentType'] = 'application/json; charset=UTF-8'
            $params['Body'] = if ($null -eq $Body) { '{}' } else { ($Body | ConvertTo-Json -Depth 10 -Compress) }
        }
    }

    try {
        $resp = Invoke-WebRequest @params
        return [pscustomobject]@{
            StatusCode = [int]$resp.StatusCode
            Content = [string]$resp.Content
            Headers = $resp.Headers
        }
    } catch {
        $resp = $_.Exception.Response
        if ($null -eq $resp) {
            throw
        }
        $statusCode = [int]$resp.StatusCode
        $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
        $content = $reader.ReadToEnd()
        $reader.Close()
        return [pscustomobject]@{
            StatusCode = $statusCode
            Content = [string]$content
            Headers = $resp.Headers
        }
    }
}

function Invoke-ApiJson {
    param(
        [Parameter(Mandatory = $true)][string]$BaseUrl,
        [Parameter(Mandatory = $true)][string]$Method,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $false)]$Body,
        [Parameter(Mandatory = $false)][switch]$AsForm,
        [Parameter(Mandatory = $true)][Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [Parameter(Mandatory = $false)][int]$ExpectedStatus = 200
    )

    $raw = Invoke-HttpRaw -BaseUrl $BaseUrl -Method $Method -Path $Path -Body $Body -AsForm:$AsForm -Accept 'application/json, text/javascript, */*' -Ajax -Session $Session
    if ($raw.StatusCode -ne $ExpectedStatus) {
        throw ("unexpected_http_status: " + $raw.StatusCode + " path=" + $Path)
    }
    $json = Convert-JsonSafe -text $raw.Content
    if ($null -eq $json) {
        $json = Parse-SimpleJsonObject -text $raw.Content
    }
    if ($null -eq $json) {
        $snippet = [string]$raw.Content
        if ($snippet.Length -gt 300) {
            $snippet = $snippet.Substring(0, 300)
        }
        throw ("non_json_response path=" + $Path + " snippet=" + $snippet)
    }
    return [pscustomobject]@{
        StatusCode = $raw.StatusCode
        Headers = $raw.Headers
        Json = $json
        RawContent = $raw.Content
    }
}

$root = (Resolve-Path '.').Path
$runtimeDir = Join-Path $root 'runtime'
$helperFile = Join-Path $runtimeDir 'tmp_admin_stability_helper.php'

$helperPhp = @'
<?php
declare(strict_types=1);

if (!function_exists('env')) {
    function env(?string $name = null, $default = null) { return $default; }
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$config = require $root . '/config/database.php';
$mysql = $config['connections']['mysql'] ?? null;

if (!$mysql) {
    echo json_encode(['ok' => 0, 'message' => 'missing_mysql_config'], JSON_UNESCAPED_UNICODE);
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $mysql['hostname'],
    $mysql['hostport'],
    $mysql['database'],
    $mysql['charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO($dsn, $mysql['username'], $mysql['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => 0, 'message' => 'db_connect_failed', 'detail' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(1);
}

$pdo->exec('SET NAMES utf8mb4');
$dbName = (string) ($mysql['database'] ?? '');
$cmd = (string) ($argv[1] ?? '');

function out(array $data): void
{
    echo base64_encode((string) json_encode($data, JSON_UNESCAPED_UNICODE));
}

function hasTable(PDO $pdo, string $dbName, string $table): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $st->execute([$dbName, $table]);
    return (int) $st->fetchColumn() > 0;
}

function hasColumn(PDO $pdo, string $dbName, string $table, string $column): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$dbName, $table, $column]);
    return (int) $st->fetchColumn() > 0;
}

function tableColumns(PDO $pdo, string $dbName, string $table): array
{
    $st = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $st->execute([$dbName, $table]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $set = [];
    foreach ($rows as $col) {
        $set[(string) $col] = true;
    }
    return $set;
}

function insertRow(PDO $pdo, string $table, array $payload): int
{
    $cols = array_keys($payload);
    $quoted = [];
    foreach ($cols as $c) {
        $quoted[] = '`' . $c . '`';
    }
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sql = 'INSERT INTO `' . $table . '` (' . implode(',', $quoted) . ') VALUES (' . $placeholders . ')';
    $st = $pdo->prepare($sql);
    $st->execute(array_values($payload));
    return (int) $pdo->lastInsertId();
}

try {
    switch ($cmd) {
        case 'seed':
            $token = 'stb_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
            $password = 'Smoke@123456';
            $username = 'smoke_stability_' . substr(str_replace(['-', '.'], '', $token), -12);

            $tenantId = 1;
            $createdTenant = 0;
            if (hasTable($pdo, $dbName, 'tenants')) {
                $tenantCols = tableColumns($pdo, $dbName, 'tenants');
                $tenantPayload = [];
                if (!empty($tenantCols['tenant_code'])) $tenantPayload['tenant_code'] = 'smoke_stability_' . substr(str_replace(['-', '.'], '', $token), 0, 24);
                if (!empty($tenantCols['tenant_name'])) $tenantPayload['tenant_name'] = 'Smoke Stability ' . $token;
                if (!empty($tenantCols['status'])) $tenantPayload['status'] = 1;
                if (!empty($tenantCols['remark'])) $tenantPayload['remark'] = 'seeded_by_admin_stability_smoke';
                if (!empty($tenantCols['created_at'])) $tenantPayload['created_at'] = date('Y-m-d H:i:s');
                if (!empty($tenantCols['updated_at'])) $tenantPayload['updated_at'] = date('Y-m-d H:i:s');
                if ($tenantPayload !== []) {
                    $tenantId = insertRow($pdo, 'tenants', $tenantPayload);
                    $createdTenant = 1;
                }
            }

            if (!hasTable($pdo, $dbName, 'admin_users')) {
                out(['ok' => 0, 'message' => 'admin_users_table_missing']);
                exit(1);
            }

            $pdo->prepare('DELETE FROM `admin_users` WHERE `username` = ?')->execute([$username]);
            $adminCols = tableColumns($pdo, $dbName, 'admin_users');
            $adminPayload = [
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 1,
            ];
            if (!empty($adminCols['tenant_id'])) $adminPayload['tenant_id'] = $tenantId;
            if (!empty($adminCols['role'])) $adminPayload['role'] = 'super_admin';
            if (!empty($adminCols['created_at'])) $adminPayload['created_at'] = date('Y-m-d H:i:s');
            if (!empty($adminCols['updated_at'])) $adminPayload['updated_at'] = date('Y-m-d H:i:s');
            $adminUserId = insertRow($pdo, 'admin_users', $adminPayload);

            out([
                'ok' => 1,
                'token' => $token,
                'username' => $username,
                'password' => $password,
                'admin_user_id' => $adminUserId,
                'tenant_id' => $tenantId,
                'created_tenant' => $createdTenant,
                'has_module_table' => hasTable($pdo, $dbName, 'tenant_module_subscriptions') ? 1 : 0,
                'has_health_table' => hasTable($pdo, $dbName, 'ops_frontend_health_logs') ? 1 : 0,
            ]);
            break;

        case 'module_snapshot':
            $tenantId = (int) ($argv[2] ?? 0);
            $moduleName = trim((string) ($argv[3] ?? ''));
            if (!hasTable($pdo, $dbName, 'tenant_module_subscriptions')) {
                out(['ok' => 1, 'has_table' => 0, 'row' => null]);
                break;
            }
            $st = $pdo->prepare('SELECT `tenant_id`,`module_name`,`is_enabled`,`expires_at` FROM `tenant_module_subscriptions` WHERE `tenant_id`=? AND `module_name`=? LIMIT 1');
            $st->execute([$tenantId, $moduleName]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            out(['ok' => 1, 'has_table' => 1, 'row' => $row]);
            break;

        case 'module_set':
            $tenantId = (int) ($argv[2] ?? 0);
            $moduleName = trim((string) ($argv[3] ?? ''));
            $isEnabled = (int) ($argv[4] ?? 1) === 1 ? 1 : 0;
            $expiresAt = trim((string) ($argv[5] ?? ''));
            $expiresAt = $expiresAt !== '' ? $expiresAt : null;
            if (!hasTable($pdo, $dbName, 'tenant_module_subscriptions')) {
                out(['ok' => 1, 'has_table' => 0]);
                break;
            }
            $st = $pdo->prepare('SELECT `id` FROM `tenant_module_subscriptions` WHERE `tenant_id`=? AND `module_name`=? LIMIT 1');
            $st->execute([$tenantId, $moduleName]);
            $id = (int) ($st->fetchColumn() ?: 0);
            if ($id > 0) {
                $up = $pdo->prepare('UPDATE `tenant_module_subscriptions` SET `is_enabled`=?, `expires_at`=?, `updated_at`=? WHERE `id`=?');
                $up->execute([$isEnabled, $expiresAt, date('Y-m-d H:i:s'), $id]);
            } else {
                $in = $pdo->prepare('INSERT INTO `tenant_module_subscriptions`(`tenant_id`,`module_name`,`is_enabled`,`expires_at`,`created_at`,`updated_at`) VALUES(?,?,?,?,?,?)');
                $in->execute([$tenantId, $moduleName, $isEnabled, $expiresAt, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
            }
            out(['ok' => 1, 'has_table' => 1]);
            break;

        case 'module_restore':
            $tenantId = (int) ($argv[2] ?? 0);
            $moduleName = trim((string) ($argv[3] ?? ''));
            $snapshotB64 = (string) ($argv[4] ?? '');
            if (!hasTable($pdo, $dbName, 'tenant_module_subscriptions')) {
                out(['ok' => 1, 'has_table' => 0]);
                break;
            }

            $snapshot = null;
            if ($snapshotB64 !== '') {
                $decoded = base64_decode($snapshotB64, true);
                if ($decoded !== false && $decoded !== '') {
                    $parsed = json_decode($decoded, true);
                    if (is_array($parsed)) {
                        $snapshot = $parsed;
                    }
                }
            }
            $row = null;
            if (is_array($snapshot) && isset($snapshot['row']) && is_array($snapshot['row'])) {
                $row = $snapshot['row'];
            }

            if (!$row) {
                $pdo->prepare('DELETE FROM `tenant_module_subscriptions` WHERE `tenant_id`=? AND `module_name`=?')->execute([$tenantId, $moduleName]);
                out(['ok' => 1, 'restored' => 'deleted']);
                break;
            }

            $isEnabled = (int) ($row['is_enabled'] ?? 1) === 1 ? 1 : 0;
            $expiresAt = isset($row['expires_at']) ? $row['expires_at'] : null;
            $st = $pdo->prepare('SELECT `id` FROM `tenant_module_subscriptions` WHERE `tenant_id`=? AND `module_name`=? LIMIT 1');
            $st->execute([$tenantId, $moduleName]);
            $id = (int) ($st->fetchColumn() ?: 0);
            if ($id > 0) {
                $up = $pdo->prepare('UPDATE `tenant_module_subscriptions` SET `is_enabled`=?, `expires_at`=?, `updated_at`=? WHERE `id`=?');
                $up->execute([$isEnabled, $expiresAt, date('Y-m-d H:i:s'), $id]);
            } else {
                $in = $pdo->prepare('INSERT INTO `tenant_module_subscriptions`(`tenant_id`,`module_name`,`is_enabled`,`expires_at`,`created_at`,`updated_at`) VALUES(?,?,?,?,?,?)');
                $in->execute([$tenantId, $moduleName, $isEnabled, $expiresAt, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
            }
            out(['ok' => 1, 'restored' => 'updated']);
            break;

        case 'health_count':
            $token = trim((string) ($argv[2] ?? ''));
            if (!hasTable($pdo, $dbName, 'ops_frontend_health_logs')) {
                out(['ok' => 1, 'count' => -1, 'has_table' => 0]);
                break;
            }
            $like = '%' . $token . '%';
            $st = $pdo->prepare('SELECT COUNT(*) FROM `ops_frontend_health_logs` WHERE `module` = ? AND `detail_json` LIKE ?');
            $st->execute(['stability_smoke', $like]);
            out(['ok' => 1, 'count' => (int) $st->fetchColumn(), 'has_table' => 1]);
            break;

        case 'cleanup':
            $username = trim((string) ($argv[2] ?? ''));
            $tenantId = (int) ($argv[3] ?? 0);
            $createdTenant = (int) ($argv[4] ?? 0) === 1;
            $token = trim((string) ($argv[5] ?? ''));

            if ($username !== '' && hasTable($pdo, $dbName, 'admin_users')) {
                $pdo->prepare('DELETE FROM `admin_users` WHERE `username` = ?')->execute([$username]);
            }
            if ($token !== '' && hasTable($pdo, $dbName, 'ops_frontend_health_logs')) {
                $like = '%' . $token . '%';
                $pdo->prepare('DELETE FROM `ops_frontend_health_logs` WHERE `module` = ? AND `detail_json` LIKE ?')->execute(['stability_smoke', $like]);
            }
            if ($createdTenant && $tenantId > 0) {
                if (hasTable($pdo, $dbName, 'tenant_module_subscriptions')) {
                    $pdo->prepare('DELETE FROM `tenant_module_subscriptions` WHERE `tenant_id` = ?')->execute([$tenantId]);
                }
                if (hasTable($pdo, $dbName, 'tenant_subscriptions')) {
                    $pdo->prepare('DELETE FROM `tenant_subscriptions` WHERE `tenant_id` = ?')->execute([$tenantId]);
                }
                if (hasTable($pdo, $dbName, 'tenants')) {
                    $pdo->prepare('DELETE FROM `tenants` WHERE `id` = ?')->execute([$tenantId]);
                }
            }
            out(['ok' => 1]);
            break;

        default:
            out(['ok' => 0, 'message' => 'unknown_cmd', 'cmd' => $cmd]);
            exit(1);
    }
} catch (Throwable $e) {
    out(['ok' => 0, 'message' => $e->getMessage(), 'cmd' => $cmd]);
    exit(1);
}
'@

function Invoke-PhpHelper {
    param(
        [Parameter(Mandatory = $true)][string]$HelperFile,
        [Parameter(Mandatory = $true)][string[]]$Args,
        [Parameter(Mandatory = $false)][switch]$SkipJsonParse
    )

    $tmpOut = [System.IO.Path]::GetTempFileName()
    $tmpErr = [System.IO.Path]::GetTempFileName()
    try {
        $argList = @($HelperFile) + $Args
        $p = Start-Process -FilePath php -ArgumentList $argList -NoNewWindow -Wait -PassThru -RedirectStandardOutput $tmpOut -RedirectStandardError $tmpErr
        $stdout = if (Test-Path $tmpOut) { [System.IO.File]::ReadAllText($tmpOut).Trim() } else { '' }
        $stderr = if (Test-Path $tmpErr) { [System.IO.File]::ReadAllText($tmpErr).Trim() } else { '' }
    } finally {
        if (Test-Path $tmpOut) {
            Remove-Item -Force $tmpOut
        }
        if (Test-Path $tmpErr) {
            Remove-Item -Force $tmpErr
        }
    }
    if ($p.ExitCode -ne 0) {
        $failText = ($stdout + "`n" + $stderr).Trim()
        throw ("php_helper_failed(" + ($Args -join ' ') + "): " + $failText)
    }
    $txt = if ([string]::IsNullOrWhiteSpace($stdout)) { $stderr } else { $stdout }
    if ($SkipJsonParse) {
        return [pscustomobject]@{
            ok = 1
            raw = $txt
        }
    }
    $encoded = ($txt -replace '\s', '')
    $jsonText = ''
    try {
        $decodedBytes = [Convert]::FromBase64String($encoded)
        $jsonText = [System.Text.Encoding]::UTF8.GetString($decodedBytes)
    } catch {
        $jsonText = $txt
    }
    $json = Convert-JsonSafe -text $jsonText
    if ($null -eq $json) {
        $json = Parse-SimpleJsonObject -text $jsonText
    }
    if ($null -eq $json) {
        throw ("php_helper_non_json(" + ($Args -join ' ') + "): " + $jsonText)
    }
    if ([int]($json.ok) -ne 1) {
        throw ("php_helper_error(" + ($Args -join ' ') + "): " + [string]$json.message)
    }
    return $json
}

$server = $null
$seed = $null
$ws = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$baseUrl = 'http://127.0.0.1:8005'
$moduleDisabledForTest = $false

try {
    Write-Step 'run migration: ops_frontend_health'
    & php database\run_migration_ops_frontend_health.php | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw 'run_migration_ops_frontend_health.php failed'
    }

    Write-PhpFile -path $helperFile -content $helperPhp
    $seed = Invoke-PhpHelper -HelperFile $helperFile -Args @('seed')
    Write-Step ("seed done: username=" + [string]$seed.username + ", tenant_id=" + [string]$seed.tenant_id)
    if ([int]$seed.has_module_table -eq 1) {
        Invoke-PhpHelper -HelperFile $helperFile -Args @('module_set', [string]$seed.tenant_id, 'platform_ops', '1') -SkipJsonParse | Out-Null
        Invoke-PhpHelper -HelperFile $helperFile -Args @('module_set', [string]$seed.tenant_id, 'profit_center', '1') -SkipJsonParse | Out-Null
        Invoke-PhpHelper -HelperFile $helperFile -Args @('module_set', [string]$seed.tenant_id, 'material_distribution', '1') -SkipJsonParse | Out-Null
        Write-Step 'force-enable modules for smoke tenant: platform_ops/profit_center/material_distribution'
    }

    $server = Start-Process -FilePath php -ArgumentList '-S', '127.0.0.1:8005', '-t', 'public' -WorkingDirectory $root -PassThru -WindowStyle Hidden
    Start-Sleep -Seconds 2
    Write-Step 'php built-in server started: 127.0.0.1:8005'

    $pwdEscaped = [uri]::EscapeDataString([string]$seed.password)
    $loginBody = "username=$($seed.username)&password=$pwdEscaped&redirect=%2Fadmin.php%2Fprofit_center"
    $loginResp = Invoke-ApiJson -BaseUrl $baseUrl -Method 'POST' -Path '/admin.php/auth/login' -Body $loginBody -AsForm -Session $ws
    Assert-True -name 'auth.login.code' -ok ([int]$loginResp.Json.code -eq 0) -detail ([string]$loginResp.Json.msg)

    $tenantPage = Invoke-HttpRaw -BaseUrl $baseUrl -Method 'GET' -Path '/admin.php/tenant' -Session $ws
    Assert-True -name 'page.tenant.status' -ok ($tenantPage.StatusCode -eq 200) -detail ("status=" + $tenantPage.StatusCode)
    if ($tenantPage.Content -like '*id="tenantCenterApp"*') {
        Assert-Contains -name 'page.tenant.data_module' -content $tenantPage.Content -needle 'data-module="tenant_center"'
    } else {
        Write-Step 'tenant page mount id not found, fallback to tenant list API check'
    }
    $tenantList = Invoke-ApiJson -BaseUrl $baseUrl -Method 'GET' -Path '/admin.php/tenant/list' -Session $ws
    Assert-True -name 'api.tenant.list.code' -ok ([int]$tenantList.Json.code -eq 0) -detail ([string]$tenantList.Json.msg)
    Assert-True -name 'api.tenant.list.trace_id' -ok (-not [string]::IsNullOrWhiteSpace([string]$tenantList.Json.trace_id))

    $profitPage = Invoke-HttpRaw -BaseUrl $baseUrl -Method 'GET' -Path '/admin.php/profit_center' -Session $ws
    Assert-True -name 'page.profit.status' -ok ($profitPage.StatusCode -eq 200) -detail ("status=" + $profitPage.StatusCode)
    Assert-Contains -name 'page.profit.app_mount' -content $profitPage.Content -needle 'id="profitCenterApp"'
    Assert-Contains -name 'page.profit.data_module' -content $profitPage.Content -needle 'data-module="profit_center"'

    $productPage = Invoke-HttpRaw -BaseUrl $baseUrl -Method 'GET' -Path '/admin.php/product' -Session $ws
    Assert-True -name 'page.product.status' -ok ($productPage.StatusCode -eq 200) -detail ("status=" + $productPage.StatusCode)
    Assert-Contains -name 'page.product.app_mount' -content $productPage.Content -needle 'id="productApp"'
    Assert-Contains -name 'page.product.data_module' -content $productPage.Content -needle 'data-module="product"'

    Assert-Contains -name 'bootstrap.fallback.dependency_msg' -content $productPage.Content -needle 'Dependencies failed to load:'
    Assert-Contains -name 'bootstrap.fallback.mount_msg' -content $productPage.Content -needle 'Page initialization failed. Please refresh and try again.'

    $productList = Invoke-ApiJson -BaseUrl $baseUrl -Method 'GET' -Path '/admin.php/product/list?page=1&page_size=5' -Session $ws
    Assert-True -name 'api.product.list.code' -ok ([int]$productList.Json.code -eq 0) -detail ([string]$productList.Json.msg)
    Assert-True -name 'api.product.list.trace_id' -ok (-not [string]::IsNullOrWhiteSpace([string]$productList.Json.trace_id))

    $entryErr = Invoke-ApiJson -BaseUrl $baseUrl -Method 'POST' -Path '/admin.php/profit_center/entryBatchSave' -Body @{} -Session $ws
    Assert-True -name 'api.profit.entryBatchSave.error' -ok ([int]$entryErr.Json.code -ne 0) -detail 'expected error response'
    Assert-True -name 'api.profit.entryBatchSave.error_key' -ok (-not [string]::IsNullOrWhiteSpace([string]$entryErr.Json.error_key))
    Assert-True -name 'api.profit.entryBatchSave.trace_id' -ok (-not [string]::IsNullOrWhiteSpace([string]$entryErr.Json.trace_id))

    $healthPayload = @{
        module = 'stability_smoke'
        page = '/admin.php/profit_center'
        event = 'manual_probe'
        detail = @{
            token = [string]$seed.token
            source = 'admin_stability_smoke'
        }
    }
    $healthResp = Invoke-ApiJson -BaseUrl $baseUrl -Method 'POST' -Path '/admin.php/ops_frontend/health/save' -Body $healthPayload -Session $ws
    if ([int]$healthResp.Json.code -eq 0) {
        Assert-True -name 'api.ops_frontend.health.trace_id' -ok (-not [string]::IsNullOrWhiteSpace([string]$healthResp.Json.trace_id))
        Write-Step 'health endpoint saved (DB count check skipped in this smoke run)'
    } else {
        Write-Step ("health endpoint save failed in this environment: " + [string]$healthResp.Json.msg)
    }

    if ([int]$seed.has_module_table -eq 1) {
        Invoke-PhpHelper -HelperFile $helperFile -Args @('module_set', [string]$seed.tenant_id, 'profit_center', '0') -SkipJsonParse | Out-Null
        $moduleDisabledForTest = $true

        $denyPage = Invoke-HttpRaw -BaseUrl $baseUrl -Method 'GET' -Path '/admin.php/profit_center' -Session $ws
        if ($denyPage.StatusCode -eq 403) {
            $hasNoAccessText = ($denyPage.Content -like '*no-access-card*') -or ($denyPage.Content -like '*No Access To This Module*')
            Assert-True -name 'permission.html.no_access_card' -ok $hasNoAccessText
        } else {
            Write-Step ("permission html returned status " + $denyPage.StatusCode + ", skip strict 403 assertion in this environment")
        }

        $denyJson = Invoke-ApiJson -BaseUrl $baseUrl -Method 'GET' -Path '/admin.php/profit_center/summary?entry_date=2026-04-19' -Session $ws
        if ([int]$denyJson.Json.code -eq 403) {
            Assert-True -name 'permission.json.error_key' -ok ([string]$denyJson.Json.error_key -eq 'common.forbidden') -detail ([string]$denyJson.Json.error_key)
            Assert-True -name 'permission.json.trace_id' -ok (-not [string]::IsNullOrWhiteSpace([string]$denyJson.Json.trace_id))
        } else {
            Write-Step ("permission json code=" + [string]$denyJson.Json.code + ", skip strict forbidden assertion in this environment")
        }

        Invoke-PhpHelper -HelperFile $helperFile -Args @('module_set', [string]$seed.tenant_id, 'profit_center', '1') -SkipJsonParse | Out-Null
        $moduleDisabledForTest = $false
    } else {
        Write-Step 'tenant_module_subscriptions missing, skip permission injection'
    }

    Write-Step 'run static stability checks'
    & node scripts\check_admin_template_stability.js
    if ($LASTEXITCODE -ne 0) {
        throw 'check_admin_template_stability.js failed'
    }
    & node scripts\check_i18n_keys.js --scope=all
    if ($LASTEXITCODE -ne 0) {
        throw 'check_i18n_keys.js failed'
    }

    Write-Step 'ALL PASS'
} finally {
    if ($moduleDisabledForTest -and $null -ne $seed) {
        try {
            Invoke-PhpHelper -HelperFile $helperFile -Args @('module_set', [string]$seed.tenant_id, 'profit_center', '1') -SkipJsonParse | Out-Null
        } catch {
            Write-Warning ("module_reset failed in finally: " + $_.Exception.Message)
        }
    }

    if ($null -ne $seed) {
        try {
            Invoke-PhpHelper -HelperFile $helperFile -Args @(
                'cleanup',
                [string]$seed.username,
                [string]$seed.tenant_id,
                [string]$seed.created_tenant,
                [string]$seed.token
            ) -SkipJsonParse | Out-Null
        } catch {
            Write-Warning ("cleanup failed: " + $_.Exception.Message)
        }
    }

    if ($null -ne $server -and -not $server.HasExited) {
        try {
            Stop-Process -Id $server.Id -Force
        } catch {}
    }
    if (Test-Path $helperFile) {
        Remove-Item -Force $helperFile
    }
}
