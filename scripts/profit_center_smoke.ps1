$ErrorActionPreference = 'Stop'

function Write-Step([string]$msg) {
    Write-Output ("[PROFIT_SMOKE] " + $msg)
}

function Write-PhpFile([string]$path, [string]$content) {
    $enc = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($path, $content, $enc)
}

function Invoke-ApiJson {
    param(
        [Parameter(Mandatory = $true)][string]$Method,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $false)]$Body,
        [Parameter(Mandatory = $true)][Microsoft.PowerShell.Commands.WebRequestSession]$Session
    )

    $uri = 'http://127.0.0.1:8004' + $Path
    $headers = @{
        'Accept' = 'application/json'
        'X-Requested-With' = 'XMLHttpRequest'
    }

    if ($Method -eq 'GET') {
        $resp = Invoke-WebRequest -Uri $uri -Method GET -Headers $headers -WebSession $Session -TimeoutSec 30
        return ($resp.Content | ConvertFrom-Json)
    }

    if ($Body -is [string]) {
        $resp = Invoke-WebRequest -Uri $uri -Method POST -Headers $headers -WebSession $Session -ContentType 'application/x-www-form-urlencoded; charset=UTF-8' -Body $Body -TimeoutSec 30
        return ($resp.Content | ConvertFrom-Json)
    }

    $payload = if ($null -eq $Body) { '{}' } else { $Body | ConvertTo-Json -Depth 10 -Compress }
    $resp = Invoke-WebRequest -Uri $uri -Method POST -Headers $headers -WebSession $Session -ContentType 'application/json; charset=UTF-8' -Body $payload -TimeoutSec 30
    return ($resp.Content | ConvertFrom-Json)
}

function Assert-Ok([string]$name, $resp) {
    if ($null -eq $resp -or [int]($resp.code) -ne 0) {
        $msg = if ($null -ne $resp) { [string]$resp.msg } else { 'null_response' }
        throw ("$name failed: $msg")
    }
    Write-Output ("PASS => " + $name)
}

$root = (Resolve-Path '.').Path
$runtimeDir = Join-Path $root 'runtime'
$seedFile = Join-Path $runtimeDir 'tmp_profit_seed.php'
$cleanupFile = Join-Path $runtimeDir 'tmp_profit_cleanup.php'
$downloadFile = Join-Path $runtimeDir 'tmp_profit_template.xlsx'

$seedPhp = @"
<?php
if (!function_exists('env')) {
    function env(?string `$name = null, `$default = null) { return `$default; }
}
`$cfg = require __DIR__ . '/../config/database.php';
`$m = `$cfg['connections']['mysql'];
`$dsn = 'mysql:host=' . `$m['hostname'] . ';port=' . `$m['hostport'] . ';dbname=' . `$m['database'] . ';charset=' . (`$m['charset'] ?? 'utf8mb4');
`$pdo = new PDO(`$dsn, `$m['username'], `$m['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

`$token = 'pc_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
`$username = 'smoke_admin_' . substr(`$token, -10);
`$password = 'Smoke@123456';
`$passHash = password_hash(`$password, PASSWORD_DEFAULT);

`$cols = [];
`$st = `$pdo->query('SHOW COLUMNS FROM admin_users');
foreach (`$st->fetchAll(PDO::FETCH_ASSOC) as `$col) {
    `$cols[] = (string)(`$col['Field'] ?? '');
}
`$hasTenant = in_array('tenant_id', `$cols, true);

`$st = `$pdo->prepare("SELECT id FROM admin_users WHERE username=? LIMIT 1");
`$st->execute([`$username]);
`$uid = (int)(`$st->fetchColumn() ?: 0);
if (`$uid > 0) {
    if (`$hasTenant) {
        `$up = `$pdo->prepare("UPDATE admin_users SET password_hash=?, status=1, tenant_id=1, updated_at=NOW() WHERE id=?");
        `$up->execute([`$passHash, `$uid]);
    } else {
        `$up = `$pdo->prepare("UPDATE admin_users SET password_hash=?, status=1, updated_at=NOW() WHERE id=?");
        `$up->execute([`$passHash, `$uid]);
    }
} else {
    if (`$hasTenant) {
        `$in = `$pdo->prepare("INSERT INTO admin_users(username,password_hash,status,tenant_id,created_at,updated_at) VALUES(?,?,1,1,NOW(),NOW())");
        `$in->execute([`$username, `$passHash]);
    } else {
        `$in = `$pdo->prepare("INSERT INTO admin_users(username,password_hash,status,created_at,updated_at) VALUES(?,?,1,NOW(),NOW())");
        `$in->execute([`$username, `$passHash]);
    }
    `$uid = (int)`$pdo->lastInsertId();
}

echo json_encode([
  'token' => `$token,
  'username' => `$username,
  'password' => `$password,
  'admin_user_id' => `$uid
], JSON_UNESCAPED_UNICODE);
"@

$cleanupPhp = @"
<?php
if (!function_exists('env')) {
    function env(?string `$name = null, `$default = null) { return `$default; }
}
`$cfg = require __DIR__ . '/../config/database.php';
`$m = `$cfg['connections']['mysql'];
`$dsn = 'mysql:host=' . `$m['hostname'] . ';port=' . `$m['hostport'] . ';dbname=' . `$m['database'] . ';charset=' . (`$m['charset'] ?? 'utf8mb4');
`$pdo = new PDO(`$dsn, `$m['username'], `$m['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

`$token = (string)(`$argv[1] ?? '');
`$username = (string)(`$argv[2] ?? '');
if (`$token !== '') {
    `$storeCodeLike = '__' . `$token . '_store_%';
    `$st = `$pdo->prepare('SELECT id FROM growth_profit_stores WHERE store_code LIKE ?');
    `$st->execute([`$storeCodeLike]);
    `$storeIds = array_map('intval', `$st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    if (!empty(`$storeIds)) {
        `$in = implode(',', array_fill(0, count(`$storeIds), '?'));
        `$pdo->prepare('DELETE FROM growth_profit_daily_entries WHERE store_id IN (' . `$in . ')')->execute(`$storeIds);
        `$pdo->prepare('DELETE FROM growth_profit_accounts WHERE store_id IN (' . `$in . ')')->execute(`$storeIds);
        `$pdo->prepare('DELETE FROM growth_profit_stores WHERE id IN (' . `$in . ')')->execute(`$storeIds);
    }
}
if (`$username !== '') {
    `$pdo->prepare('DELETE FROM admin_users WHERE username=?')->execute([`$username]);
}
"@

$seed = $null
$server = $null
$ws = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$token = ''
$username = ''
$store1 = $null
$store2 = $null
$account1 = $null
$account2 = $null
$today = Get-Date -Format 'yyyy-MM-dd'

try {
    Write-PhpFile -path $seedFile -content $seedPhp
    Write-PhpFile -path $cleanupFile -content $cleanupPhp
    $seed = php $seedFile | ConvertFrom-Json
    if ($null -eq $seed -or [string]::IsNullOrWhiteSpace([string]$seed.username)) {
        throw 'seed failed'
    }
    $token = [string]$seed.token
    $username = [string]$seed.username
    Write-Step ("seed created: token=" + $token + ", username=" + $username)

    $server = Start-Process -FilePath php -ArgumentList '-S', '127.0.0.1:8004', '-t', 'public' -WorkingDirectory $root -PassThru -WindowStyle Hidden
    Start-Sleep -Seconds 2
    Write-Step 'php built-in server started on 127.0.0.1:8004'

    $pwdEscaped = [uri]::EscapeDataString([string]$seed.password)
    $loginBody = "username=$($seed.username)&password=$pwdEscaped&redirect=%2Fadmin.php%2Fprofit_center"
    $login = Invoke-ApiJson -Method 'POST' -Path '/admin.php/auth/login' -Body $loginBody -Session $ws
    Assert-Ok 'auth.login' $login

    $storePayload1 = @{
        store_code = "__${token}_store_1"
        store_name = "SmokeStore1_${token}"
        default_sale_price_cny = 109
        default_product_cost_cny = 34
        default_cancel_rate = 0.08
        default_cancel_rate_live = 0.09
        default_cancel_rate_video = 0.07
        default_cancel_rate_influencer = 0.05
        default_platform_fee_rate = 0.04
        default_influencer_commission_rate = 0.1
        default_live_wage_hourly_cny = 15
        default_timezone = 'Asia/Bangkok'
        status = 1
    }
    $store1 = Invoke-ApiJson -Method 'POST' -Path '/admin.php/profit_center/storeSave' -Body $storePayload1 -Session $ws
    Assert-Ok 'profit.storeSave.1' $store1
    $store1Id = [int]$store1.data.id

    $storePayload2 = @{
        store_code = "__${token}_store_2"
        store_name = "SmokeStore2_${token}"
        default_sale_price_cny = 129
        default_product_cost_cny = 42
        default_cancel_rate = 0.06
        default_cancel_rate_live = 0.06
        default_cancel_rate_video = 0.06
        default_cancel_rate_influencer = 0.06
        default_platform_fee_rate = 0.05
        default_influencer_commission_rate = 0.12
        default_live_wage_hourly_cny = 16
        default_timezone = 'Asia/Bangkok'
        status = 1
    }
    $store2 = Invoke-ApiJson -Method 'POST' -Path '/admin.php/profit_center/storeSave' -Body $storePayload2 -Session $ws
    Assert-Ok 'profit.storeSave.2' $store2
    $store2Id = [int]$store2.data.id

    $accountPayload1 = @{
        store_id = $store1Id
        account_name = "GMVMAX_1_${token}"
        account_code = "__${token}_acc_1"
        account_currency = 'USD'
        default_gmv_currency = 'VND'
        status = 1
    }
    $account1 = Invoke-ApiJson -Method 'POST' -Path '/admin.php/profit_center/accountSave' -Body $accountPayload1 -Session $ws
    Assert-Ok 'profit.accountSave.1' $account1
    $account1Id = [int]$account1.data.id

    # 验证“每店单账户”：同店重复新增将更新同一账户 id
    $accountPayload1b = @{
        store_id = $store1Id
        account_name = "GMVMAX_1B_${token}"
        account_code = "__${token}_acc_1b"
        account_currency = 'USD'
        default_gmv_currency = 'VND'
        status = 1
    }
    $account1b = Invoke-ApiJson -Method 'POST' -Path '/admin.php/profit_center/accountSave' -Body $accountPayload1b -Session $ws
    Assert-Ok 'profit.accountSave.1.repeat' $account1b
    if ([int]$account1b.data.id -ne $account1Id) {
        throw 'single account per store rule failed'
    }

    $accountPayload2 = @{
        store_id = $store2Id
        account_name = "GMVMAX_2_${token}"
        account_code = "__${token}_acc_2"
        account_currency = 'CNY'
        default_gmv_currency = 'CNY'
        status = 1
    }
    $account2 = Invoke-ApiJson -Method 'POST' -Path '/admin.php/profit_center/accountSave' -Body $accountPayload2 -Session $ws
    Assert-Ok 'profit.accountSave.2' $account2
    $account2Id = [int]$account2.data.id

    $entry1 = Invoke-ApiJson -Method 'POST' -Path '/admin.php/profit_center/entrySave' -Body @{
        entry_date = $today
        store_id = $store1Id
        account_id = $account1Id
        channel_type = 'live'
        sale_price_cny = 109
        product_cost_cny = 34
        cancel_rate = 0.09
        platform_fee_rate = 0.04
        influencer_commission_rate = 0.1
        live_hours = 2
        wage_hourly_cny = 15
        ad_spend_amount = 60
        ad_spend_currency = 'USD'
        ad_compensation_amount = 28
        ad_compensation_currency = 'CNY'
        gmv_amount = 1100000
        gmv_currency = 'VND'
        order_count = 22
    } -Session $ws
    Assert-Ok 'profit.entrySave.live' $entry1

    $batch = Invoke-ApiJson -Method 'POST' -Path '/admin.php/profit_center/entryBatchSave' -Body @{
        items = @(
            @{
                entry_date = $today
                store_id = $store1Id
                account_id = $account1Id
                channel_type = 'video'
                ad_spend_amount = 40
                ad_spend_currency = 'USD'
                ad_compensation_amount = 10
                ad_compensation_currency = 'CNY'
                gmv_amount = 780000
                gmv_currency = 'VND'
                order_count = 18
            },
            @{
                entry_date = $today
                store_id = $store2Id
                account_id = $account2Id
                channel_type = 'live'
                ad_spend_amount = 260
                ad_spend_currency = 'CNY'
                ad_compensation_amount = 16
                ad_compensation_currency = 'CNY'
                gmv_amount = 980
                gmv_currency = 'CNY'
                order_count = 12
                live_hours = 1.5
            }
        )
    } -Session $ws
    Assert-Ok 'profit.entryBatchSave' $batch

    $summary = Invoke-ApiJson -Method 'GET' -Path ("/admin.php/profit_center/summary?date_from=$today&date_to=$today") -Session $ws
    Assert-Ok 'profit.summaryJson' $summary
    if ([int]($summary.data.kpi.entry_count) -lt 3) {
        throw 'summary entry_count is smaller than expected'
    }
    if ([double]($summary.data.kpi.ad_compensation_cny) -le 0) {
        throw 'summary ad_compensation_cny should be > 0'
    }
    Write-Output ('PASS => profit.summary.kpi entry_count=' + [string]$summary.data.kpi.entry_count)

    $entryList = Invoke-ApiJson -Method 'GET' -Path ("/admin.php/profit_center/entryList?date_from=$today&date_to=$today&page=1&page_size=50") -Session $ws
    Assert-Ok 'profit.entryListJson' $entryList
    $items = @()
    if ($entryList.data -and $entryList.data.items) { $items = @($entryList.data.items) }
    if ($items.Count -lt 3) {
        throw 'entryListJson returned too few rows'
    }
    $hasComp = $false
    foreach ($it in $items) {
        if ([double]($it.ad_compensation_cny) -gt 0) { $hasComp = $true; break }
    }
    if (-not $hasComp) {
        throw 'entryListJson missing ad_compensation_cny values'
    }
    Write-Output ('PASS => profit.entryList.count=' + [string]$items.Count)

    $fxSync = Invoke-ApiJson -Method 'POST' -Path '/admin.php/profit_center/fxSync' -Body @{
        rate_date = $today
        currencies = @('USD','VND')
    } -Session $ws
    Assert-Ok 'profit.fxSync' $fxSync
    $fxCount = if ($fxSync.data -and $fxSync.data.items) { @($fxSync.data.items).Count } else { 0 }
    if ($fxCount -lt 2) {
        throw 'fxSync returned unexpected items count'
    }
    Write-Output ('PASS => profit.fxSync.items=' + [string]$fxCount)

    $headers = @{
        'Accept' = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,*/*'
        'X-Requested-With' = 'XMLHttpRequest'
    }
    $tplResp = Invoke-WebRequest -Uri 'http://127.0.0.1:8004/admin.php/profit_center/templateXlsx' -Method GET -Headers $headers -WebSession $ws -TimeoutSec 30 -OutFile $downloadFile -PassThru
    if ($tplResp.StatusCode -ne 200) {
        throw 'template download status is not 200'
    }
    $tplSize = (Get-Item $downloadFile).Length
    if ($tplSize -lt 1500) {
        throw 'template download file is too small'
    }
    Write-Output ('PASS => profit.templateXlsx.bytes=' + [string]$tplSize)

    Write-Output 'SUMMARY => PASS'
} finally {
    if ($server -and -not $server.HasExited) {
        try { Stop-Process -Id $server.Id -Force } catch {}
    }
    if ($token -ne '' -and $username -ne '') {
        try {
            php $cleanupFile $token $username | Out-Null
            Write-Step 'cleanup done'
        } catch {}
    }
    Remove-Item -ErrorAction SilentlyContinue $seedFile, $cleanupFile, $downloadFile
}

