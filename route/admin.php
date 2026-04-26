<?php
use think\facade\Route;

// Admin routes are loaded only for admin.php entry.
if (defined('ENTRY_FILE') && ENTRY_FILE === 'admin') {
    Route::get('/', 'app\controller\admin\Index@index');

    // Auth
    Route::get('auth/login', 'app\controller\admin\Auth@login');
    Route::post('auth/login', 'app\controller\admin\Auth@login');
    Route::get('auth/logout', 'app\controller\admin\Auth@logoutPage');
    Route::post('auth/logout', 'app\controller\admin\Auth@logout');

    // Dashboard stats
    Route::get('stats/overview', 'app\controller\admin\Stats@overview');
    Route::get('stats/trends', 'app\controller\admin\Stats@trends');
    Route::get('stats/platformDistribution', 'app\controller\admin\Stats@platformDistribution');
    Route::get('stats/downloadErrorTrends', 'app\controller\admin\Stats@downloadErrorTrends');
    Route::get('stats/downloadErrorTop', 'app\controller\admin\Stats@downloadErrorTop');
    Route::get('stats/productDistribution', 'app\controller\admin\Stats@productDistribution');
    Route::get('stats/storageUsage', 'app\controller\admin\Stats@storageUsage');

    Route::get('settings', 'app\controller\admin\Settings@index');
    Route::post('settings', 'app\controller\admin\Settings@index');

    // Users
    Route::get('user/list', 'app\controller\admin\User@listJson');
    Route::post('user/create', 'app\controller\admin\User@create');
    Route::post('user/update', 'app\controller\admin\User@update');
    Route::post('user/toggle', 'app\controller\admin\User@toggle');
    Route::post('user/resetPassword', 'app\controller\admin\User@resetPassword');
    Route::post('user/delete', 'app\controller\admin\User@delete');
    Route::get('user', 'app\controller\admin\User@index');

    // Tenant SaaS operations (super_admin only)
    Route::get('tenant/list', 'app\controller\admin\Tenant@list');
    Route::post('tenant/save', 'app\controller\admin\Tenant@save');
    Route::post('tenant/status', 'app\controller\admin\Tenant@status');
    Route::get('tenant/admin/list', 'app\controller\admin\Tenant@adminList');
    Route::post('tenant/admin/save', 'app\controller\admin\Tenant@adminSave');
    Route::get('tenant/package/list', 'app\controller\admin\Tenant@packageList');
    Route::post('tenant/package/save', 'app\controller\admin\Tenant@packageSave');
    Route::post('tenant/subscription/save', 'app\controller\admin\Tenant@subscriptionSave');
    Route::get('tenant/subscription/modules', 'app\controller\admin\Tenant@subscriptionModules');
    Route::get('tenant/audit/list', 'app\controller\admin\Tenant@auditList');
    Route::post('tenant/switch', 'app\controller\admin\Tenant@switchTenant');
    Route::get('tenant', 'app\controller\admin\Tenant@index');

    // Platform
    Route::get('platform/list', 'app\controller\admin\Platform@listJson');
    Route::get('platform/edit/<id>', 'app\controller\admin\Platform@edit');
    Route::post('platform/edit/<id>', 'app\controller\admin\Platform@edit');
    Route::post('platform/delete/<id>', 'app\controller\admin\Platform@delete');
    Route::get('platform/add', 'app\controller\admin\Platform@add');
    Route::post('platform/add', 'app\controller\admin\Platform@add');
    Route::get('platform', 'app\controller\admin\Platform@index');

    // Device
    Route::get('device/list', 'app\controller\admin\Device@listJson');
    Route::get('device/edit/<id>', 'app\controller\admin\Device@edit');
    Route::post('device/edit/<id>', 'app\controller\admin\Device@edit');
    Route::post('device/delete/<id>', 'app\controller\admin\Device@delete');
    Route::get('device/getByPlatform', 'app\controller\admin\Device@getByPlatform');
    Route::get('device/add', 'app\controller\admin\Device@add');
    Route::post('device/add', 'app\controller\admin\Device@add');
    Route::get('device', 'app\controller\admin\Device@index');

    // Product
    Route::get('product/list', 'app\controller\admin\Product@listJson');
    Route::get('product/edit/<id>', 'app\controller\admin\Product@edit');
    Route::post('product/edit/<id>', 'app\controller\admin\Product@edit');
    Route::post('product/delete/<id>', 'app\controller\admin\Product@delete');
    Route::get('product/add', 'app\controller\admin\Product@add');
    Route::post('product/add', 'app\controller\admin\Product@add');
    Route::post('product/uploadThumb', 'app\controller\admin\Product@uploadThumb');
    Route::get('product', 'app\controller\admin\Product@index');

    // Distribute
    Route::get('distribute/list', 'app\controller\admin\Distribute@listJson');
    Route::get('distribute/add', 'app\controller\admin\Distribute@add');
    Route::post('distribute/add', 'app\controller\admin\Distribute@add');
    Route::post('distribute/delete/<id>', 'app\controller\admin\Distribute@delete');
    Route::post('distribute/toggle/<id>', 'app\controller\admin\Distribute@toggle');
    Route::get('distribute', 'app\controller\admin\Distribute@index');

    // Video
    Route::get('video/list', 'app\\controller\\admin\\Video@listJson');
    Route::post('video/mixSuggestion', 'app\\controller\\admin\\Video@mixSuggestion');
    Route::get('video/edit/<id>', 'app\controller\admin\Video@edit');
    Route::post('video/edit/<id>', 'app\controller\admin\Video@edit');
    Route::post('video/delete/<id>', 'app\controller\admin\Video@delete');
    Route::post('video/batchDelete', 'app\controller\admin\Video@batchDelete');
    Route::get('video/batchUpload', 'app\controller\admin\Video@batchUpload');
    Route::post('video/batchUpload', 'app\controller\admin\Video@batchUpload');
    Route::post('video/uploadChunk', 'app\controller\admin\Video@uploadChunk');
    Route::get('video/batchEdit', 'app\controller\admin\Video@batchEdit');
    Route::post('video/batchEdit', 'app\controller\admin\Video@batchEdit');
    Route::get('video', 'app\controller\admin\Video@index');

    // Cache
    Route::get('cache/list', 'app\controller\admin\Cache@listJson');
    Route::get('cache', 'app\controller\admin\Cache@index');
    Route::post('cache/delete/<hash>', 'app\controller\admin\Cache@delete');
    Route::post('cache/clear', 'app\controller\admin\Cache@clear');
    Route::get('cache/download/<hash>', 'app\controller\admin\Cache@download');

    // Download log
    Route::get('downloadLog/list', 'app\controller\admin\DownloadLog@listJson');
    Route::get('downloadLog', 'app\controller\admin\DownloadLog@index');
    Route::post('downloadLog/clear', 'app\controller\admin\DownloadLog@clear');

    // Client license/version + ops center
    Route::get('client_license/list', 'app\controller\admin\ClientLicense@listJson');
    Route::post('client_license/add', 'app\controller\admin\ClientLicense@add');
    Route::post('client_license/update/<id>', 'app\controller\admin\ClientLicense@update');
    Route::post('client_license/toggle/<id>', 'app\controller\admin\ClientLicense@toggle');
    Route::post('client_license/unbind/<id>', 'app\controller\admin\ClientLicense@unbind');
    Route::post('client_license/delete/<id>', 'app\controller\admin\ClientLicense@delete');
    Route::get('client_license', 'app\controller\admin\ClientLicense@index');

    Route::get('client_version/list', 'app\controller\admin\ClientVersion@listJson');
    Route::post('client_version/add', 'app\controller\admin\ClientVersion@add');
    Route::post('client_version/uploadPackage', 'app\controller\admin\ClientVersion@uploadPackage');
    Route::post('client_version/update/<id>', 'app\controller\admin\ClientVersion@update');
    Route::post('client_version/toggle/<id>', 'app\controller\admin\ClientVersion@toggle');
    Route::post('client_version/delete/<id>', 'app\controller\admin\ClientVersion@delete');
    Route::get('client_version', 'app\controller\admin\ClientVersion@index');
    Route::get('ops_center/status', 'app\controller\admin\OpsCenter@status');
    Route::post('ops_center/runMigrations', 'app\controller\admin\OpsCenter@runMigrations');
    Route::post('ops_center/gitPull', 'app\controller\admin\OpsCenter@gitPull');
    Route::get('ops_center', 'app\controller\admin\OpsCenter@index');
    Route::post('ops_frontend/health/save', 'app\controller\admin\OpsFrontend@healthSave');

    // Product search
    Route::get('product_search/list', 'app\controller\admin\ProductSearch@listJson');
    Route::post('product_search/importCsv', 'app\controller\admin\ProductSearch@importCsv');
    Route::get('product_search/importTaskStatus', 'app\controller\admin\ProductSearch@importTaskStatus');
    Route::post('product_search/importTaskTick', 'app\controller\admin\ProductSearch@importTaskTick');
    Route::post('product_search/syncAliyunQueue', 'app\controller\admin\ProductSearch@syncAliyunQueue');
    Route::post('product_search/batchDelete', 'app\controller\admin\ProductSearch@deleteBatch');
    Route::post('product_search/update/<id>', 'app\controller\admin\ProductSearch@updateItem');
    Route::post('product_search/delete/<id>', 'app\controller\admin\ProductSearch@delete');
    Route::post('product_search/generateCatalogToken', 'app\controller\admin\ProductSearch@generateCatalogToken');
    Route::get('product_search/sampleCsv', 'app\controller\admin\ProductSearch@sampleCsv');
    Route::get('product_search/exportCsv', 'app\controller\admin\ProductSearch@exportCsv');
    Route::get('product_search/live/stores', 'app\controller\admin\ProductSearchLive@storesJson');
    Route::get('product_search/live/store/list', 'app\controller\admin\ProductSearchLive@storeListJson');
    Route::post('product_search/live/store/save', 'app\controller\admin\ProductSearchLive@storeSave');
    Route::post('product_search/live/store/delete', 'app\controller\admin\ProductSearchLive@storeDelete');
    Route::get('product_search/live/catalog/list', 'app\controller\admin\ProductSearchLive@catalogListJson');
    Route::post('product_search/live/catalog/save', 'app\controller\admin\ProductSearchLive@catalogSave');
    Route::post('product_search/live/catalog/delete', 'app\controller\admin\ProductSearchLive@catalogDelete');
    Route::post('product_search/live/catalog/batchDelete', 'app\controller\admin\ProductSearchLive@catalogBatchDelete');
    Route::post('product_search/live/catalog/import', 'app\controller\admin\ProductSearchLive@catalogImport');
    Route::post('product_search/live/catalog/importChunk', 'app\controller\admin\ProductSearchLive@catalogImportChunk');
    Route::post('product_search/live/import/create', 'app\controller\admin\ProductSearchLive@importCreate');
    Route::post('product_search/live/session/save', 'app\controller\admin\ProductSearchLive@sessionSave');
    Route::post('product_search/live/session/delete', 'app\controller\admin\ProductSearchLive@sessionDelete');
    Route::post('product_search/live/session/batchDelete', 'app\controller\admin\ProductSearchLive@sessionBatchDelete');
    Route::get('product_search/live/sessions', 'app\controller\admin\ProductSearchLive@sessionsJson');
    Route::get('product_search/live/unmatched', 'app\controller\admin\ProductSearchLive@unmatchedJson');
    Route::post('product_search/live/unmatched/bind', 'app\controller\admin\ProductSearchLive@unmatchedBind');
    Route::get('product_search/live/rankings', 'app\controller\admin\ProductSearchLive@rankingsJson');
    // Stable detail/image APIs (query-style only).
    Route::get('product_search/live/styleDetail', 'app\controller\admin\ProductSearchLive@styleDetailJson');
    Route::post('product_search/live/styleImageUpdate', 'app\controller\admin\ProductSearchLive@styleImageUpdate');
    Route::get('product_search/live', 'app\controller\admin\ProductSearchLive@index');
    Route::get('product_search', 'app\controller\admin\ProductSearch@index');
    Route::get('offline_order/list', 'app\controller\admin\OfflineOrder@listJson');
    Route::post('offline_order/updateStatus', 'app\controller\admin\OfflineOrder@updateStatus');
    Route::get('offline_order/exportXlsx', 'app\controller\admin\OfflineOrder@exportXlsx');
    Route::get('offline_order', 'app\controller\admin\OfflineOrder@index');

    // Influencer CRM
    Route::get('influencer/list', 'app\controller\admin\Influencer@listJson');
    Route::get('influencer/search', 'app\controller\admin\Influencer@searchJson');
    Route::post('influencer/importCsv', 'app\controller\admin\Influencer@importCsv');
    Route::get('influencer/importTaskStatus', 'app\controller\admin\Influencer@importTaskStatus');
    Route::post('influencer/importTaskTick', 'app\controller\admin\Influencer@importTaskTick');
    Route::post('influencer/source/importPreview', 'app\controller\admin\Influencer@sourceImportPreview');
    Route::post('influencer/source/importCommit', 'app\controller\admin\Influencer@sourceImportCommit');
    Route::get('influencer/source/importBatches', 'app\controller\admin\Influencer@sourceImportBatches');
    Route::get('influencer/sampleCsv', 'app\controller\admin\Influencer@sampleCsv');
    Route::get('influencer/exportCsv', 'app\controller\admin\Influencer@exportCsv');
    Route::post('influencer/update', 'app\controller\admin\Influencer@update');
    Route::post('influencer/updateStatus', 'app\controller\admin\Influencer@updateStatus');
    Route::post('influencer/markSampleShipped', 'app\controller\admin\Influencer@markSampleShipped');
    Route::post('influencer/logOutreachAction', 'app\controller\admin\Influencer@logOutreachAction');
    Route::get('influencer/outreachHistory', 'app\controller\admin\Influencer@outreachHistory');
    Route::post('influencer/delete', 'app\controller\admin\Influencer@delete');
    Route::get('influencer', 'app\controller\admin\Influencer@index');

    // Category
    Route::get('category/list', 'app\controller\admin\Category@listJson');
    Route::get('category/options', 'app\controller\admin\Category@options');
    Route::post('category/save', 'app\controller\admin\Category@save');
    Route::post('category/delete', 'app\controller\admin\Category@delete');
    Route::get('category', 'app\controller\admin\Category@index');

    // Module manager
    Route::get('extension/list', 'app\controller\admin\Extension@listJson');
    Route::get('extension/logs', 'app\controller\admin\Extension@logsJson');
    Route::get('extension/permissionMatrix', 'app\controller\admin\Extension@permissionMatrix');
    Route::post('extension/install', 'app\controller\admin\Extension@install');
    Route::post('extension/uninstall', 'app\controller\admin\Extension@uninstall');
    Route::post('extension/toggle', 'app\controller\admin\Extension@toggle');
    Route::post('extension/savePermission', 'app\controller\admin\Extension@savePermission');
    Route::get('extension', 'app\controller\admin\Extension@index');

    // Message template
    Route::get('message_template/list', 'app\controller\admin\MessageTemplate@listJson');
    Route::post('message_template/save', 'app\controller\admin\MessageTemplate@save');
    Route::post('message_template/delete', 'app\controller\admin\MessageTemplate@delete');
    Route::post('message_template/render', 'app\controller\admin\MessageTemplate@render');
    Route::get('message_template', 'app\controller\admin\MessageTemplate@index');

    // Outreach workspace
    Route::get('outreach_workspace/list', 'app\controller\admin\OutreachWorkspace@listJson');
    Route::get('outreach_workspace/nextTask', 'app\controller\admin\OutreachWorkspace@nextTaskJson');
    Route::post('outreach_workspace/generate', 'app\controller\admin\OutreachWorkspace@generate');
    Route::post('outreach_workspace/action', 'app\controller\admin\OutreachWorkspace@action');
    Route::get('outreach_workspace', 'app\controller\admin\OutreachWorkspace@index');

    // Auto DM campaigns (unattended external IM)
    Route::post('auto_dm/campaign/create', 'app\controller\admin\AutoDm@create');
    Route::get('auto_dm/campaign/list', 'app\controller\admin\AutoDm@list');
    Route::post('auto_dm/campaign/rebuild_followups', 'app\controller\admin\AutoDm@rebuildFollowups');
    Route::post('auto_dm/campaign/pause', 'app\controller\admin\AutoDm@pause');
    Route::post('auto_dm/campaign/resume', 'app\controller\admin\AutoDm@resume');
    Route::get('auto_dm/reply_queue/list', 'app\controller\admin\AutoDm@replyQueueList');
    Route::post('auto_dm/reply_queue/confirm', 'app\controller\admin\AutoDm@replyQueueConfirm');
    Route::get('auto_dm', 'app\controller\admin\AutoDm@index');

    // Mobile outreach tasking (Android + Appium agent)
    Route::get('mobile_task/list', 'app\controller\admin\MobileTask@listJson');
    Route::post('mobile_task/create_batch', 'app\controller\admin\MobileTask@createBatch');
    Route::post('mobile_task/retry', 'app\controller\admin\MobileTask@retry');
    Route::post('mobile_task/update_status', 'app\controller\admin\MobileTask@updateStatus');

    Route::get('mobile_device/list', 'app\controller\admin\MobileDevice@listJson');
    Route::post('mobile_device/save', 'app\controller\admin\MobileDevice@save');
    Route::post('mobile_device/delete', 'app\controller\admin\MobileDevice@delete');
    Route::post('mobile_device/regenerateToken', 'app\controller\admin\MobileDevice@regenerateToken');
    Route::get('mobile_device', 'app\controller\admin\MobileDevice@index');

    Route::post('mobile_agent/pull', 'app\controller\admin\MobileAgent@pull');
    Route::post('mobile_agent/report', 'app\controller\admin\MobileAgent@report');
    Route::post('mobile_agent/pull_auto', 'app\controller\admin\MobileAgent@pullAuto');
    Route::post('mobile_agent/report_auto', 'app\controller\admin\MobileAgent@reportAuto');
    Route::post('desktop_agent/pull_auto', 'app\controller\admin\MobileAgent@pullAuto');
    Route::post('desktop_agent/report_auto', 'app\controller\admin\MobileAgent@reportAuto');
    Route::get('mobile_console/bootstrap', 'app\controller\admin\MobileConsole@bootstrap');

    // Sample management
    Route::get('sample/list', 'app\controller\admin\Sample@listJson');
    Route::post('sample/save', 'app\controller\admin\Sample@save');
    Route::post('sample/createFromInfluencer', 'app\controller\admin\Sample@createFromInfluencer');
    Route::post('sample/markReceived', 'app\controller\admin\Sample@markReceived');
    Route::get('sample', 'app\controller\admin\Sample@index');

    // Growth intelligence
    Route::get('industry_trend/list', 'app\controller\admin\IndustryTrend@listJson');
    Route::get('industry_trend/summary', 'app\controller\admin\IndustryTrend@summaryJson');
    Route::post('industry_trend/importCsv', 'app\controller\admin\IndustryTrend@importCsv');
    Route::get('industry_trend/exportCsv', 'app\controller\admin\IndustryTrend@exportCsv');
    Route::get('industry_trend', 'app\controller\admin\IndustryTrend@index');

    Route::get('competitor_analysis/list', 'app\controller\admin\CompetitorAnalysis@listJson');
    Route::post('competitor_analysis/saveCompetitor', 'app\controller\admin\CompetitorAnalysis@saveCompetitor');
    Route::post('competitor_analysis/importCsv', 'app\controller\admin\CompetitorAnalysis@importCsv');
    Route::get('competitor_analysis/exportCsv', 'app\controller\admin\CompetitorAnalysis@exportCsv');
    Route::get('competitor_analysis', 'app\controller\admin\CompetitorAnalysis@index');

    Route::get('ad_insight/list', 'app\controller\admin\AdInsight@listJson');
    Route::post('ad_insight/importCsv', 'app\controller\admin\AdInsight@importCsv');
    Route::get('ad_insight/exportCsv', 'app\controller\admin\AdInsight@exportCsv');
    Route::get('ad_insight', 'app\controller\admin\AdInsight@index');

    // GMV Max creative insight assistant
    Route::get('gmv_max', 'app\controller\admin\GmvMax@index');
    Route::post('gmv_max/creative/sync', 'app\controller\admin\GmvMax@creativeSync');
    Route::get('gmv_max/creative/baseline', 'app\controller\admin\GmvMax@creativeBaseline');
    Route::get('gmv_max/creative/recommendation', 'app\controller\admin\GmvMax@creativeRecommendation');
    Route::get('gmv_max/creative/history', 'app\controller\admin\GmvMax@creativeHistory');
    Route::get('gmv_max/creative/ranking', 'app\controller\admin\GmvMax@creativeRanking');
    Route::post('ai_center/chat', 'app\controller\admin\AiCenter@chat');
    Route::post('ai_center/plan/generate', 'app\controller\admin\AiCenter@planGenerate');
    Route::post('ai_center/plan/execute', 'app\controller\admin\AiCenter@planExecute');
    Route::get('ai_center/plan/status', 'app\controller\admin\AiCenter@planStatus');
    Route::post('ai_center/feedback', 'app\controller\admin\AiCenter@feedback');
    Route::get('ai_center/insight/daily', 'app\controller\admin\AiCenter@dailyInsight');
    Route::get('ai_center', 'app\controller\admin\AiCenter@index');

    // Profit center (multi-store multi-currency)
    Route::get('profit_center/summary', 'app\controller\admin\ProfitCenter@summaryJson');
    Route::get('profit_center/entryList', 'app\controller\admin\ProfitCenter@entryListJson');
    Route::post('profit_center/entrySave', 'app\controller\admin\ProfitCenter@entrySave');
    Route::post('profit_center/entryBatchSave', 'app\controller\admin\ProfitCenter@entryBatchSave');
    Route::post('profit_center/entryDelete', 'app\controller\admin\ProfitCenter@entryDelete');
    Route::get('profit_center/storeList', 'app\controller\admin\ProfitCenter@storeListJson');
    Route::post('profit_center/storeSave', 'app\controller\admin\ProfitCenter@storeSave');
    Route::post('profit_center/storeDelete', 'app\controller\admin\ProfitCenter@storeDelete');
    Route::get('profit_center/accountList', 'app\controller\admin\ProfitCenter@accountListJson');
    Route::post('profit_center/accountSave', 'app\controller\admin\ProfitCenter@accountSave');
    Route::post('profit_center/accountDelete', 'app\controller\admin\ProfitCenter@accountDelete');
    Route::get('profit_center/fxRateList', 'app\controller\admin\ProfitCenter@fxRateListJson');
    Route::post('profit_center/fxSync', 'app\controller\admin\ProfitCenter@fxSync');
    Route::get('profit_center/templateXlsx', 'app\controller\admin\ProfitCenter@templateXlsx');
    Route::post('profit_center/importXlsx', 'app\controller\admin\ProfitCenter@importXlsx');
    Route::get('profit_center/exportCsv', 'app\controller\admin\ProfitCenter@exportCsv');
    Route::get('profit_center/plugin/bootstrap', 'app\controller\admin\ProfitCenter@pluginBootstrap');
    Route::post('profit_center/plugin/tokenCreate', 'app\controller\admin\ProfitCenter@pluginTokenCreate');
    Route::post('profit_center/plugin/tokenRevoke', 'app\controller\admin\ProfitCenter@pluginTokenRevoke');
    Route::post('profit_center/plugin/ingestBatch', 'app\controller\admin\ProfitCenter@pluginIngestBatch');
    Route::get('profit_center/plugin/ingestLogs', 'app\controller\admin\ProfitCenter@pluginIngestLogs');
    Route::post('profit_center/plugin/mappingSave', 'app\controller\admin\ProfitCenter@pluginMappingSave');
    Route::post('profit_center/plugin/mappingDelete', 'app\controller\admin\ProfitCenter@pluginMappingDelete');
    Route::get('profit_center', 'app\controller\admin\ProfitCenter@index');

    // Data import center
    Route::get('data_import/sourceList', 'app\controller\admin\DataImport@sourceListJson');
    Route::get('data_import/adapterList', 'app\controller\admin\DataImport@adapterListJson');
    Route::post('data_import/sourceSave', 'app\controller\admin\DataImport@sourceSave');
    Route::post('data_import/sourceDelete', 'app\controller\admin\DataImport@sourceDelete');
    Route::post('data_import/runSource', 'app\controller\admin\DataImport@runSource');
    Route::get('data_import/jobList', 'app\controller\admin\DataImport@jobListJson');
    Route::get('data_import/jobLogs', 'app\controller\admin\DataImport@jobLogsJson');
    Route::post('data_import/retryJob', 'app\controller\admin\DataImport@retryJob');
    Route::get('data_import', 'app\controller\admin\DataImport@index');
}
