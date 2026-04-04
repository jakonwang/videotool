<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\QiniuService;
use app\service\SystemConfigService;
use think\facade\View;

/**
 * 系统设置（存储方式、七牛、默认封面等）
 */
class Settings extends BaseController
{
    public function index()
    {
        if ($this->request->isPost()) {
            $storage = $this->request->post('storage', 'local');
            $storage = $storage === 'qiniu' ? 'qiniu' : 'local';
            SystemConfigService::set('storage', $storage);

            $defaultCover = trim((string) $this->request->post('default_cover_url', ''));
            SystemConfigService::set('default_cover_url', $defaultCover);

            $siteName = trim((string) $this->request->post('site_name', ''));
            SystemConfigService::set('site_name', $siteName);

            // 七牛：填写新值优先保存；留空且勾选「清空」则删除库中覆盖项
            $akNew = trim((string) $this->request->post('qiniu_access_key', ''));
            if ($akNew !== '') {
                SystemConfigService::set('qiniu_access_key', $akNew);
            } elseif ($this->request->post('qiniu_access_clear') === '1') {
                SystemConfigService::set('qiniu_access_key', '');
            }
            $skNew = trim((string) $this->request->post('qiniu_secret_key', ''));
            if ($skNew !== '') {
                SystemConfigService::set('qiniu_secret_key', $skNew);
            } elseif ($this->request->post('qiniu_secret_clear') === '1') {
                SystemConfigService::set('qiniu_secret_key', '');
            }
            foreach (['qiniu_bucket', 'qiniu_domain', 'qiniu_region', 'qiniu_cdn_domains'] as $k) {
                SystemConfigService::set($k, trim((string) $this->request->post($k, '')));
            }

            SystemConfigService::set('aliyun_is_enabled', $this->request->post('aliyun_is_enabled') === '1' ? '1' : '0');

            $aliyunAk = trim((string) $this->request->post('aliyun_is_access_key_id', ''));
            if ($aliyunAk !== '') {
                SystemConfigService::set('aliyun_is_access_key_id', $aliyunAk);
            } elseif ($this->request->post('aliyun_is_access_clear') === '1') {
                SystemConfigService::set('aliyun_is_access_key_id', '');
            }
            $aliyunSk = trim((string) $this->request->post('aliyun_is_access_key_secret', ''));
            if ($aliyunSk !== '') {
                SystemConfigService::set('aliyun_is_access_key_secret', $aliyunSk);
            } elseif ($this->request->post('aliyun_is_secret_clear') === '1') {
                SystemConfigService::set('aliyun_is_access_key_secret', '');
            }
            foreach (
                [
                    'aliyun_is_endpoint',
                    'aliyun_is_instance_name',
                    'aliyun_is_region_id',
                    'aliyun_is_category_id',
                    'aliyun_is_search_num',
                ] as $k
            ) {
                SystemConfigService::set($k, trim((string) $this->request->post($k, '')));
            }

            $openaiKeyNew = trim((string) $this->request->post('openai_api_key', ''));
            if ($openaiKeyNew !== '') {
                SystemConfigService::set('openai_api_key', $openaiKeyNew);
            } elseif ($this->request->post('openai_api_key_clear') === '1') {
                SystemConfigService::set('openai_api_key', '');
            }
            foreach (['openai_base_url', 'openai_model', 'openai_max_catalog'] as $k) {
                SystemConfigService::set($k, trim((string) $this->request->post($k, '')));
            }
            SystemConfigService::set('openai_describe_on_import', $this->request->post('openai_describe_on_import') === '1' ? '1' : '0');

            SystemConfigService::set('google_ps_enabled', $this->request->post('google_ps_enabled') === '1' ? '1' : '0');
            $gKey = trim((string) $this->request->post('google_ps_key_file', ''));
            if ($gKey !== '') {
                SystemConfigService::set('google_ps_key_file', $gKey);
            } elseif ($this->request->post('google_ps_key_file_clear') === '1') {
                SystemConfigService::set('google_ps_key_file', '');
            }
            foreach (
                [
                    'google_ps_project_id',
                    'google_ps_location',
                    'google_ps_product_set_id',
                    'google_ps_gcs_bucket',
                    'google_ps_gcs_prefix',
                    'google_ps_product_category',
                    'google_ps_match_score_min',
                    'google_ps_search_top_k',
                ] as $gk
            ) {
                SystemConfigService::set($gk, trim((string) $this->request->post($gk, '')));
            }

            SystemConfigService::clearCache();

            return json(['code' => 0, 'msg' => '已保存']);
        }

        $storage = SystemConfigService::get('storage', 'qiniu') ?: 'qiniu';
        $defaultCoverUrl = SystemConfigService::get('default_cover_url', '') ?: '';
        $siteName = SystemConfigService::get('site_name', '') ?: '';

        $qiniuAccessConfigured = trim((string) (SystemConfigService::get('qiniu_access_key', '') ?? '')) !== '';
        $qiniuSecretConfigured = trim((string) (SystemConfigService::get('qiniu_secret_key', '') ?? '')) !== '';
        $qiniuBucket = SystemConfigService::get('qiniu_bucket', '') ?? '';
        $qiniuDomain = SystemConfigService::get('qiniu_domain', '') ?? '';
        $qiniuRegion = SystemConfigService::get('qiniu_region', '') ?? '';
        $qiniuCdnDomains = SystemConfigService::get('qiniu_cdn_domains', '') ?? '';
        $aliyunIsEnabled = SystemConfigService::get('aliyun_is_enabled', '0') === '1';
        $aliyunAccessConfigured = trim((string) (SystemConfigService::get('aliyun_is_access_key_id', '') ?? '')) !== '';
        $aliyunSecretConfigured = trim((string) (SystemConfigService::get('aliyun_is_access_key_secret', '') ?? '')) !== '';
        $aliyunEndpoint = SystemConfigService::get('aliyun_is_endpoint', '') ?? '';
        $aliyunInstance = SystemConfigService::get('aliyun_is_instance_name', '') ?? '';
        $aliyunRegionId = SystemConfigService::get('aliyun_is_region_id', '') ?? '';
        $aliyunCategoryId = SystemConfigService::get('aliyun_is_category_id', '') ?? '';
        $aliyunSearchNum = SystemConfigService::get('aliyun_is_search_num', '') ?? '';
        $openaiKeyConfigured = trim((string) (SystemConfigService::get('openai_api_key', '') ?? '')) !== '';
        $openaiBaseUrl = SystemConfigService::get('openai_base_url', '') ?? '';
        $openaiModel = SystemConfigService::get('openai_model', '') ?? '';
        $openaiMaxCatalog = SystemConfigService::get('openai_max_catalog', '') ?? '';
        $openaiDescribeFlag = SystemConfigService::get('openai_describe_on_import', '');
        $openaiDescribeOnImport = $openaiDescribeFlag === '' || $openaiDescribeFlag === '1';
        $googlePsEnabled = SystemConfigService::get('google_ps_enabled', '0') === '1';
        $googlePsKeyConfigured = trim((string) (SystemConfigService::get('google_ps_key_file', '') ?? '')) !== ''
            || trim((string) (getenv('GOOGLE_APPLICATION_CREDENTIALS') ?: '')) !== '';
        $googlePsProject = SystemConfigService::get('google_ps_project_id', '') ?? '';
        $googlePsLocation = SystemConfigService::get('google_ps_location', '') ?? '';
        $googlePsSetId = SystemConfigService::get('google_ps_product_set_id', '') ?? '';
        $googlePsGcsBucket = SystemConfigService::get('google_ps_gcs_bucket', '') ?? '';
        $googlePsGcsPrefix = SystemConfigService::get('google_ps_gcs_prefix', '') ?? '';
        $googlePsCategory = SystemConfigService::get('google_ps_product_category', '') ?? '';
        $googlePsScoreMin = SystemConfigService::get('google_ps_match_score_min', '') ?? '';
        $googlePsTopK = SystemConfigService::get('google_ps_search_top_k', '') ?? '';
        $qiniuEffective = QiniuService::getMergedQiniuConfig();
        $qiniuEffectiveCdnLabel = '';
        if (!empty($qiniuEffective['cdn_domains']) && is_array($qiniuEffective['cdn_domains'])) {
            $qiniuEffectiveCdnLabel = implode('，', $qiniuEffective['cdn_domains']);
        }

        return View::fetch('admin/settings/index', [
            'storage' => $storage,
            'default_cover_url' => $defaultCoverUrl,
            'site_name' => $siteName,
            'qiniu_access_configured' => $qiniuAccessConfigured,
            'qiniu_secret_configured' => $qiniuSecretConfigured,
            'qiniu_bucket' => $qiniuBucket,
            'qiniu_domain' => $qiniuDomain,
            'qiniu_region' => $qiniuRegion,
            'qiniu_cdn_domains' => $qiniuCdnDomains,
            'qiniu_effective' => $qiniuEffective,
            'qiniu_effective_cdn_label' => $qiniuEffectiveCdnLabel,
            'aliyun_is_enabled' => $aliyunIsEnabled,
            'aliyun_access_configured' => $aliyunAccessConfigured,
            'aliyun_secret_configured' => $aliyunSecretConfigured,
            'aliyun_is_endpoint' => $aliyunEndpoint,
            'aliyun_is_instance_name' => $aliyunInstance,
            'aliyun_is_region_id' => $aliyunRegionId,
            'aliyun_is_category_id' => $aliyunCategoryId,
            'aliyun_is_search_num' => $aliyunSearchNum,
            'openai_key_configured' => $openaiKeyConfigured,
            'openai_base_url' => $openaiBaseUrl,
            'openai_model' => $openaiModel,
            'openai_max_catalog' => $openaiMaxCatalog,
            'openai_describe_on_import' => $openaiDescribeOnImport,
            'google_ps_enabled' => $googlePsEnabled,
            'google_ps_key_configured' => $googlePsKeyConfigured,
            'google_ps_project_id' => $googlePsProject,
            'google_ps_location' => $googlePsLocation,
            'google_ps_product_set_id' => $googlePsSetId,
            'google_ps_gcs_bucket' => $googlePsGcsBucket,
            'google_ps_gcs_prefix' => $googlePsGcsPrefix,
            'google_ps_product_category' => $googlePsCategory,
            'google_ps_match_score_min' => $googlePsScoreMin,
            'google_ps_search_top_k' => $googlePsTopK,
        ]);
    }
}
