$ErrorActionPreference = 'Stop'

function Write-Step([string]$msg) {
    Write-Output ("[SMOKE] " + $msg)
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

    $uri = 'http://127.0.0.1:8002' + $Path
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

$root = (Resolve-Path '.').Path
$runtimeDir = Join-Path $root 'runtime'
$seedFile = Join-Path $runtimeDir 'tmp_ops2_seed.php'
$verifyFile = Join-Path $runtimeDir 'tmp_ops2_verify.php'
$cleanupFile = Join-Path $runtimeDir 'tmp_ops2_cleanup.php'

$seedPhp = @"
<?php
if (!function_exists('env')) {
    function env(?string `$name = null, `$default = null) { return `$default; }
}
`$cfg = require __DIR__ . '/../config/database.php';
`$m = `$cfg['connections']['mysql'];
`$dsn = 'mysql:host=' . `$m['hostname'] . ';port=' . `$m['hostport'] . ';dbname=' . `$m['database'] . ';charset=' . (`$m['charset'] ?? 'utf8mb4');
`$pdo = new PDO(`$dsn, `$m['username'], `$m['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

`$token = 'smoke_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
`$username = 'smoke_admin_ops2';
`$password = 'Smoke@123456';
`$passHash = password_hash(`$password, PASSWORD_DEFAULT);

`$st = `$pdo->prepare("SELECT id FROM admin_users WHERE username=? LIMIT 1");
`$st->execute([`$username]);
`$uid = (int)(`$st->fetchColumn() ?: 0);
if (`$uid > 0) {
    `$up = `$pdo->prepare("UPDATE admin_users SET password_hash=?, status=1, updated_at=NOW() WHERE id=?");
    `$up->execute([`$passHash, `$uid]);
} else {
    `$in = `$pdo->prepare("INSERT INTO admin_users(username,password_hash,status,created_at,updated_at) VALUES(?,?,1,NOW(),NOW())");
    `$in->execute([`$username, `$passHash]);
    `$uid = (int)`$pdo->lastInsertId();
}

`$catName = '__' . `$token . '_cat';
`$st = `$pdo->prepare("INSERT INTO categories(name,type,sort_order,status,created_at,updated_at) VALUES(?, 'influencer', 0, 1, NOW(), NOW())");
`$st->execute([`$catName]);
`$categoryId = (int)`$pdo->lastInsertId();

`$productName = '__' . `$token . '_product';
`$st = `$pdo->prepare("INSERT INTO products(name,category_name,category_id,goods_url,thumb_url,tiktok_shop_url,status,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,1,0,NOW(),NOW())");
`$st->execute([`$productName, `$catName, `$categoryId, 'https://example.com/goods/' . `$token, 'https://example.com/thumb/' . `$token . '.jpg', 'https://shop.tiktok.com/' . `$token]);
`$productId = (int)`$pdo->lastInsertId();

`$tiktokId = '@' . `$token;
`$contact = json_encode(['whatsapp' => '84912345678', 'zalo' => '84912345678', 'text' => 'smoke'], JSON_UNESCAPED_UNICODE);
`$tags = json_encode(['smoke', 'ops2'], JSON_UNESCAPED_UNICODE);
`$st = `$pdo->prepare("INSERT INTO influencers(tiktok_id,category_name,category_id,nickname,avatar_url,follower_count,contact_info,region,status,sample_tracking_no,sample_status,tags_json,last_contacted_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,0,NULL,0,?,NULL,NOW(),NOW())");
`$st->execute([`$tiktokId, `$catName, `$categoryId, '__' . `$token . '_nick', 'https://example.com/avatar/' . `$token . '.jpg', 12345, `$contact, 'VN', `$tags]);
`$influencerId = (int)`$pdo->lastInsertId();

`$templateName = '__' . `$token . '_tpl';
`$templateKey = '__' . `$token . '_tpl_key';
`$body = '{{current_time_period}} {{nickname}} {{random_emoji}}';
`$st = `$pdo->prepare("INSERT INTO message_templates(name,template_key,lang,body,sort_order,status,created_at,updated_at) VALUES(?,?,?,?,0,1,NOW(),NOW())");
`$st->execute([`$templateName, `$templateKey, 'vi', `$body]);
`$templateId = (int)`$pdo->lastInsertId();

`$metricDate = date('Y-m-d');
`$st = `$pdo->prepare("INSERT INTO growth_industry_metrics(metric_date,country_code,category_name,heat_score,content_count,engagement_rate,cpc,cpm,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE heat_score=VALUES(heat_score),content_count=VALUES(content_count),engagement_rate=VALUES(engagement_rate),cpc=VALUES(cpc),cpm=VALUES(cpm),updated_at=NOW()");
`$st->execute([`$metricDate, 'VN', '__' . `$token . '_industry', 88.2, 15, 0.1234, 0.56, 7.89]);

`$compName = '__' . `$token . '_competitor';
`$st = `$pdo->prepare("INSERT INTO growth_competitors(name,platform,region,category_name,status,notes,created_at,updated_at) VALUES(?,?,?,?,1,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE region=VALUES(region),category_name=VALUES(category_name),notes=VALUES(notes),updated_at=NOW()");
`$st->execute([`$compName, 'tiktok', 'VN', '__' . `$token . '_cat', 'smoke']);
`$st = `$pdo->prepare("SELECT id FROM growth_competitors WHERE name=? AND platform='tiktok' LIMIT 1");
`$st->execute([`$compName]);
`$competitorId = (int)`$st->fetchColumn();
`$st = `$pdo->prepare("INSERT INTO growth_competitor_metrics(competitor_id,metric_date,followers,engagement_rate,content_count,conversion_proxy,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE followers=VALUES(followers),engagement_rate=VALUES(engagement_rate),content_count=VALUES(content_count),conversion_proxy=VALUES(conversion_proxy),updated_at=NOW()");
`$st->execute([`$competitorId, `$metricDate, 1000, 0.11, 7, 1.23]);

`$creativeCode = '__' . `$token . '_creative';
`$st = `$pdo->prepare("INSERT INTO growth_ad_creatives(creative_code,title,platform,region,category_name,landing_url,first_seen_at,last_seen_at,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title),region=VALUES(region),category_name=VALUES(category_name),landing_url=VALUES(landing_url),last_seen_at=VALUES(last_seen_at),updated_at=NOW()");
`$st->execute([`$creativeCode, '__' . `$token . '_ad', 'tiktok', 'VN', '__' . `$token . '_cat', 'https://example.com/landing/' . `$token, `$metricDate, `$metricDate]);
`$st = `$pdo->prepare("SELECT id FROM growth_ad_creatives WHERE creative_code=? LIMIT 1");
`$st->execute([`$creativeCode]);
`$creativeId = (int)`$st->fetchColumn();
`$st = `$pdo->prepare("INSERT INTO growth_ad_metrics(creative_id,metric_date,impressions,clicks,ctr,cpc,cpm,est_spend,active_days,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE impressions=VALUES(impressions),clicks=VALUES(clicks),ctr=VALUES(ctr),cpc=VALUES(cpc),cpm=VALUES(cpm),est_spend=VALUES(est_spend),active_days=VALUES(active_days),updated_at=NOW()");
`$st->execute([`$creativeId, `$metricDate, 9999, 222, 2.22, 0.44, 8.88, 66.6, 3]);

`$sourceCode = '__' . `$token . '_source';
`$st = `$pdo->prepare("INSERT INTO data_sources(code,name,source_type,adapter_key,status,config_json,created_at,updated_at) VALUES(?,?, 'csv', NULL, 1, NULL, NOW(), NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),status=1,updated_at=NOW()");
`$st->execute([`$sourceCode, '__' . `$token . '_source_name']);
`$st = `$pdo->prepare("SELECT id FROM data_sources WHERE code=? LIMIT 1");
`$st->execute([`$sourceCode]);
`$sourceId = (int)`$st->fetchColumn();

`$apiSourceCode = '__' . `$token . '_api_source';
`$apiPayload = json_encode([
  'domain' => 'industry',
  'rows' => [[
    'metric_date' => `$metricDate,
    'country_code' => 'VN',
    'category_name' => '__' . `$token . '_industry_api',
    'heat_score' => '91.2',
    'content_count' => '21',
    'engagement_rate' => '0.17',
    'cpc' => '0.71',
    'cpm' => '8.33'
  ]]
], JSON_UNESCAPED_UNICODE);
`$st = `$pdo->prepare("INSERT INTO data_sources(code,name,source_type,adapter_key,status,config_json,created_at,updated_at) VALUES(?,?, 'api', 'mock_static', 1, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),source_type='api',adapter_key='mock_static',status=1,config_json=VALUES(config_json),updated_at=NOW()");
`$st->execute([`$apiSourceCode, '__' . `$token . '_api_source_name', `$apiPayload]);
`$st = `$pdo->prepare("SELECT id FROM data_sources WHERE code=? LIMIT 1");
`$st->execute([`$apiSourceCode]);
`$apiSourceId = (int)`$st->fetchColumn();

`$st = `$pdo->prepare("INSERT INTO import_jobs(source_id,domain,job_type,file_name,status,total_rows,success_rows,failed_rows,error_message,payload_json,started_at,finished_at,created_at,updated_at) VALUES(?, 'industry', 'csv', ?, 2, 1, 1, 0, NULL, NULL, NOW(), NOW(), NOW(), NOW())");
`$st->execute([`$sourceId, '__' . `$token . '.csv']);
`$jobId = (int)`$pdo->lastInsertId();
`$st = `$pdo->prepare("INSERT INTO import_job_logs(job_id,level,message,context_json,created_at,updated_at) VALUES(?, 'info', 'smoke_log', ?, NOW(), NOW())");
`$st->execute([`$jobId, '{"smoke":1}']);

`$retryPayload = json_encode([
  'rows' => [[
    'metric_date' => `$metricDate,
    'country_code' => 'VN',
    'category_name' => '__' . `$token . '_industry_retry',
    'heat_score' => '90.1',
    'content_count' => '12',
    'engagement_rate' => '0.22',
    'cpc' => '0.66',
    'cpm' => '8.01'
  ]]
], JSON_UNESCAPED_UNICODE);
`$st = `$pdo->prepare("INSERT INTO import_jobs(source_id,domain,job_type,file_name,status,total_rows,success_rows,failed_rows,error_message,payload_json,started_at,finished_at,created_at,updated_at) VALUES(?, 'industry', 'csv', ?, 3, 1, 0, 1, 'seed_failed_for_retry', ?, NOW(), NOW(), NOW(), NOW())");
`$st->execute([`$sourceId, '__' . `$token . '_retry.csv', `$retryPayload]);
`$retryJobId = (int)`$pdo->lastInsertId();
`$st = `$pdo->prepare("INSERT INTO import_job_logs(job_id,level,message,context_json,created_at,updated_at) VALUES(?, 'error', 'seed_retry_fail', ?, NOW(), NOW())");
`$st->execute([`$retryJobId, '{"seed_retry":1}']);

echo json_encode([
  'token' => `$token,
  'username' => `$username,
  'password' => `$password,
  'admin_user_id' => `$uid,
  'category_id' => `$categoryId,
  'product_id' => `$productId,
  'influencer_id' => `$influencerId,
  'template_id' => `$templateId,
  'competitor_id' => `$competitorId,
  'creative_id' => `$creativeId,
  'source_id' => `$sourceId,
  'api_source_id' => `$apiSourceId,
  'job_id' => `$jobId,
  'retry_job_id' => `$retryJobId,
  'tiktok_id' => `$tiktokId,
  'creative_code' => `$creativeCode,
  'competitor_name' => `$compName,
  'source_code' => `$sourceCode,
  'api_source_code' => `$apiSourceCode,
  'industry_category_api' => '__' . `$token . '_industry_api',
  'industry_category' => '__' . `$token . '_industry'
], JSON_UNESCAPED_UNICODE);
"@

$verifyPhp = @"
<?php
if (!function_exists('env')) {
    function env(?string `$name = null, `$default = null) { return `$default; }
}
`$cfg = require __DIR__ . '/../config/database.php';
`$m = `$cfg['connections']['mysql'];
`$dsn = 'mysql:host=' . `$m['hostname'] . ';port=' . `$m['hostport'] . ';dbname=' . `$m['database'] . ';charset=' . (`$m['charset'] ?? 'utf8mb4');
`$pdo = new PDO(`$dsn, `$m['username'], `$m['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
`$influencerId = (int)`$argv[1];
`$token = (string)`$argv[2];
`$st = `$pdo->prepare('SELECT status,sample_tracking_no,last_contacted_at FROM influencers WHERE id=? LIMIT 1');
`$st->execute([`$influencerId]);
`$inf = `$st->fetch(PDO::FETCH_ASSOC) ?: [];
`$st = `$pdo->prepare('SELECT COUNT(*) FROM outreach_logs WHERE influencer_id=?');
`$st->execute([`$influencerId]);
`$outreachCount = (int)`$st->fetchColumn();
`$st = `$pdo->prepare('SELECT COUNT(*) FROM influencer_status_logs WHERE influencer_id=?');
`$st->execute([`$influencerId]);
`$statusLogCount = (int)`$st->fetchColumn();
`$st = `$pdo->prepare('SELECT COUNT(*) FROM sample_shipments WHERE influencer_id=? AND tracking_no LIKE ?');
`$st->execute([`$influencerId, '__' . `$token . '%']);
`$sampleCount = (int)`$st->fetchColumn();
echo json_encode([
  'influencer' => `$inf,
  'outreach_count' => `$outreachCount,
  'status_log_count' => `$statusLogCount,
  'sample_count' => `$sampleCount
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
`$token = (string)`$argv[1];
`$influencerId = (int)`$argv[2];
`$templateId = (int)`$argv[3];
`$productId = (int)`$argv[4];
`$categoryId = (int)`$argv[5];
`$competitorId = (int)`$argv[6];
`$creativeId = (int)`$argv[7];
`$sourceId = (int)`$argv[8];
`$jobId = (int)`$argv[9];
`$retryJobId = (int)`$argv[10];
`$apiSourceId = (int)`$argv[11];

`$pdo->prepare('DELETE FROM influencer_status_logs WHERE influencer_id=?')->execute([`$influencerId]);
`$pdo->prepare('DELETE FROM outreach_logs WHERE influencer_id=?')->execute([`$influencerId]);
`$pdo->prepare('DELETE FROM influencer_outreach_tasks WHERE influencer_id=?')->execute([`$influencerId]);
`$pdo->prepare('DELETE FROM sample_shipments WHERE influencer_id=?')->execute([`$influencerId]);
`$pdo->prepare('DELETE FROM influencers WHERE id=?')->execute([`$influencerId]);
`$pdo->prepare('DELETE FROM message_templates WHERE id=?')->execute([`$templateId]);
`$pdo->prepare('DELETE FROM product_links WHERE product_id=?')->execute([`$productId]);
`$pdo->prepare('DELETE FROM products WHERE id=?')->execute([`$productId]);
`$pdo->prepare('DELETE FROM categories WHERE id=?')->execute([`$categoryId]);
`$pdo->prepare('DELETE FROM growth_competitor_metrics WHERE competitor_id=?')->execute([`$competitorId]);
`$pdo->prepare('DELETE FROM growth_competitors WHERE id=?')->execute([`$competitorId]);
`$pdo->prepare('DELETE FROM growth_ad_metrics WHERE creative_id=?')->execute([`$creativeId]);
`$pdo->prepare('DELETE FROM growth_ad_creatives WHERE id=?')->execute([`$creativeId]);
`$pdo->prepare('DELETE FROM growth_industry_metrics WHERE category_name=?')->execute(['__' . `$token . '_industry']);
`$pdo->prepare('DELETE FROM growth_industry_metrics WHERE category_name=?')->execute(['__' . `$token . '_industry_retry']);
`$pdo->prepare('DELETE FROM growth_industry_metrics WHERE category_name=?')->execute(['__' . `$token . '_industry_api']);
`$pdo->prepare('DELETE FROM import_job_logs WHERE job_id IN (SELECT id FROM import_jobs WHERE source_id=?)')->execute([`$sourceId]);
`$pdo->prepare('DELETE FROM import_jobs WHERE source_id=?')->execute([`$sourceId]);
`$pdo->prepare('DELETE FROM import_job_logs WHERE job_id IN (SELECT id FROM import_jobs WHERE source_id=?)')->execute([`$apiSourceId]);
`$pdo->prepare('DELETE FROM import_jobs WHERE source_id=?')->execute([`$apiSourceId]);
`$pdo->prepare('DELETE FROM import_job_logs WHERE job_id=?')->execute([`$jobId]);
`$pdo->prepare('DELETE FROM import_jobs WHERE id=?')->execute([`$jobId]);
`$pdo->prepare('DELETE FROM import_job_logs WHERE job_id=?')->execute([`$retryJobId]);
`$pdo->prepare('DELETE FROM import_jobs WHERE id=?')->execute([`$retryJobId]);
`$pdo->prepare('DELETE FROM data_sources WHERE id=?')->execute([`$sourceId]);
`$pdo->prepare('DELETE FROM data_sources WHERE id=?')->execute([`$apiSourceId]);
"@

$seed = $null
$server = $null

try {
    Write-PhpFile -path $seedFile -content $seedPhp
    Write-PhpFile -path $verifyFile -content $verifyPhp
    Write-PhpFile -path $cleanupFile -content $cleanupPhp

    $seedRaw = php $seedFile 2>&1
    $seedText = ($seedRaw -join "`n").Trim()
    if (-not $seedText.StartsWith('{')) {
        throw ("seed php output is not json: " + $seedText)
    }
    $seed = $seedText | ConvertFrom-Json
    Write-Step ("seed created: token=" + $seed.token + ", influencer_id=" + $seed.influencer_id)

    $server = Start-Process -FilePath php -ArgumentList '-S', '127.0.0.1:8002', '-t', 'public' -WorkingDirectory $root -PassThru -WindowStyle Hidden
    Start-Sleep -Seconds 2
    Write-Step 'php built-in server started on 127.0.0.1:8002'

    $ws = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $pwdEscaped = [uri]::EscapeDataString([string]$seed.password)
    $loginBody = "username=$($seed.username)&password=$pwdEscaped&redirect=%2Fadmin.php"
    $login = Invoke-ApiJson -Method 'POST' -Path '/admin.php/auth/login' -Body $loginBody -Session $ws
    if ($login.code -ne 0) {
        throw ("login failed: " + $login.msg)
    }
    Write-Step 'login ok'

    $checks = New-Object System.Collections.Generic.List[string]
    function Assert-Ok($name, $response) {
        if ($response.code -ne 0) {
            throw ($name + ' failed: code=' + $response.code + ', msg=' + $response.msg)
        }
        $script:checks.Add($name)
    }

    Assert-Ok 'crm.category.options' (Invoke-ApiJson -Method 'GET' -Path '/admin.php/category/options?type=influencer' -Session $ws)
    Assert-Ok 'crm.influencer.list' (Invoke-ApiJson -Method 'GET' -Path ('/admin.php/influencer/listJson?keyword=' + [uri]::EscapeDataString([string]$seed.tiktok_id)) -Session $ws)
    Assert-Ok 'crm.outreach.generate' (Invoke-ApiJson -Method 'POST' -Path '/admin.php/outreach_workspace/generate' -Body @{ category_id = [int]$seed.category_id; template_id = [int]$seed.template_id; product_id = [int]$seed.product_id; influencer_status = 0; limit = 10; tags = @('smoke') } -Session $ws)

    $taskList = Invoke-ApiJson -Method 'GET' -Path ('/admin.php/outreach_workspace/listJson?keyword=' + [uri]::EscapeDataString([string]$seed.tiktok_id)) -Session $ws
    Assert-Ok 'crm.outreach.list' $taskList
    $taskId = 0
    if ($taskList.data -and $taskList.data.items -and $taskList.data.items.Count -gt 0) {
        $taskId = [int]$taskList.data.items[0].id
    }
    if ($taskId -le 0) {
        throw 'crm.outreach.list returned empty task set for smoke influencer'
    }

    $render = Invoke-ApiJson -Method 'POST' -Path '/admin.php/message_template/render' -Body @{ template_id = [int]$seed.template_id; influencer_id = [int]$seed.influencer_id; product_id = [int]$seed.product_id } -Session $ws
    Assert-Ok 'crm.message_template.render' $render
    if ([string]::IsNullOrWhiteSpace([string]$render.data.text)) {
        throw 'crm.message_template.render returned empty text'
    }

    Assert-Ok 'crm.outreach.action.copy' (Invoke-ApiJson -Method 'POST' -Path '/admin.php/outreach_workspace/action' -Body @{ task_id = $taskId; action = 'copy'; template_id = [int]$seed.template_id; product_id = [int]$seed.product_id; rendered_body = [string]$render.data.text } -Session $ws)
    Assert-Ok 'crm.influencer.logOutreachAction' (Invoke-ApiJson -Method 'POST' -Path '/admin.php/influencer/logOutreachAction' -Body @{ influencer_id = [int]$seed.influencer_id; template_id = [int]$seed.template_id; product_id = [int]$seed.product_id; action = 'copy'; rendered_body = [string]$render.data.text } -Session $ws)
    Assert-Ok 'crm.influencer.outreachHistory' (Invoke-ApiJson -Method 'GET' -Path ('/admin.php/influencer/outreachHistory?influencer_id=' + [int]$seed.influencer_id) -Session $ws)

    $trackingNo = '__' + [string]$seed.token + '_tracking'
    Assert-Ok 'crm.influencer.markSampleShipped' (Invoke-ApiJson -Method 'POST' -Path '/admin.php/influencer/markSampleShipped' -Body @{ id = [int]$seed.influencer_id; sample_tracking_no = $trackingNo; courier = 'SF' } -Session $ws)
    Assert-Ok 'crm.sample.list' (Invoke-ApiJson -Method 'GET' -Path ('/admin.php/sample/listJson?keyword=' + [uri]::EscapeDataString($trackingNo)) -Session $ws)

    Assert-Ok 'intel.industry.list' (Invoke-ApiJson -Method 'GET' -Path ('/admin.php/industry_trend/listJson?country=VN&category=' + [uri]::EscapeDataString([string]$seed.industry_category)) -Session $ws)
    Assert-Ok 'intel.industry.summary' (Invoke-ApiJson -Method 'GET' -Path ('/admin.php/industry_trend/summaryJson?country=VN&category=' + [uri]::EscapeDataString([string]$seed.industry_category)) -Session $ws)
    Assert-Ok 'intel.competitor.list' (Invoke-ApiJson -Method 'GET' -Path ('/admin.php/competitor_analysis/listJson?keyword=' + [uri]::EscapeDataString([string]$seed.competitor_name)) -Session $ws)
    Assert-Ok 'intel.ad.list' (Invoke-ApiJson -Method 'GET' -Path ('/admin.php/ad_insight/listJson?keyword=' + [uri]::EscapeDataString([string]$seed.creative_code)) -Session $ws)
    Assert-Ok 'intel.data_import.sourceList' (Invoke-ApiJson -Method 'GET' -Path '/admin.php/data_import/sourceListJson' -Session $ws)
    Assert-Ok 'intel.data_import.adapterList' (Invoke-ApiJson -Method 'GET' -Path '/admin.php/data_import/adapterListJson' -Session $ws)
    Assert-Ok 'intel.data_import.jobList' (Invoke-ApiJson -Method 'GET' -Path '/admin.php/data_import/jobListJson?domain=industry' -Session $ws)
    Assert-Ok 'intel.data_import.jobLogs' (Invoke-ApiJson -Method 'GET' -Path ('/admin.php/data_import/jobLogsJson?job_id=' + [int]$seed.job_id) -Session $ws)
    Assert-Ok 'intel.data_import.retryJob' (Invoke-ApiJson -Method 'POST' -Path '/admin.php/data_import/retryJob' -Body @{ job_id = [int]$seed.retry_job_id } -Session $ws)
    Assert-Ok 'intel.data_import.runSource' (Invoke-ApiJson -Method 'POST' -Path '/admin.php/data_import/runSource' -Body @{ source_id = [int]$seed.api_source_id } -Session $ws)
    Assert-Ok 'intel.industry.list.apiSource' (Invoke-ApiJson -Method 'GET' -Path ('/admin.php/industry_trend/listJson?country=VN&category=' + [uri]::EscapeDataString([string]$seed.industry_category_api)) -Session $ws)

    $verifyRaw = php $verifyFile $seed.influencer_id $seed.token
    $verify = $verifyRaw | ConvertFrom-Json
    if ([int]$verify.influencer.status -lt 1) { throw 'db.verify influencer status not advanced' }
    if ([string]::IsNullOrWhiteSpace([string]$verify.influencer.sample_tracking_no)) { throw 'db.verify sample_tracking_no empty' }
    if ([int]$verify.outreach_count -lt 1) { throw 'db.verify outreach_logs missing' }
    if ([int]$verify.status_log_count -lt 1) { throw 'db.verify influencer_status_logs missing' }
    if ([int]$verify.sample_count -lt 1) { throw 'db.verify sample_shipments missing' }

    Write-Step 'db verification ok'
    Write-Output ('SUMMARY => PASS (' + $checks.Count + ' checks)')
    foreach ($c in $checks) {
        Write-Output ('PASS => ' + $c)
    }
}
finally {
    if ($server -and -not $server.HasExited) {
        Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue
    }
    if ($seed) {
        php $cleanupFile $seed.token $seed.influencer_id $seed.template_id $seed.product_id $seed.category_id $seed.competitor_id $seed.creative_id $seed.source_id $seed.job_id $seed.retry_job_id $seed.api_source_id | Out-Null
        Write-Step 'cleanup done'
    }
    foreach ($f in @($seedFile, $verifyFile, $cleanupFile)) {
        if (Test-Path $f) { Remove-Item $f -Force -ErrorAction SilentlyContinue }
    }
}
