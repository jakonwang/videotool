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

            /** 导入时是否调用豆包生成 ai_description（键名沿用 openai_describe_on_import；表单含 hidden=0 + checkbox=1 避免未勾选时不提交导致误关） */
            $postDescribe = $this->request->post('openai_describe_on_import');
            $describeOn = \is_array($postDescribe)
                ? \in_array('1', $postDescribe, true)
                : ($postDescribe === '1' || $postDescribe === 1);
            SystemConfigService::set('openai_describe_on_import', $describeOn ? '1' : '0');

            $postVolc = $this->request->post('volc_ark_enabled');
            $volcOn = \is_array($postVolc)
                ? \in_array('1', $postVolc, true)
                : ($postVolc === '1' || $postVolc === 1);
            SystemConfigService::set('volc_ark_enabled', $volcOn ? '1' : '0');
            $vAk = trim((string) $this->request->post('volc_ark_access_key', ''));
            if ($vAk !== '') {
                SystemConfigService::set('volc_ark_access_key', $vAk);
            } elseif ($this->request->post('volc_ark_access_clear') === '1') {
                SystemConfigService::set('volc_ark_access_key', '');
            }
            $vSk = trim((string) $this->request->post('volc_ark_secret_key', ''));
            if ($vSk !== '') {
                SystemConfigService::set('volc_ark_secret_key', $vSk);
            } elseif ($this->request->post('volc_ark_secret_clear') === '1') {
                SystemConfigService::set('volc_ark_secret_key', '');
            }
            foreach (
                [
                    'volc_ark_endpoint_id',
                    'volc_ark_base_url',
                    'volc_ark_max_catalog',
                ] as $vk
            ) {
                SystemConfigService::set($vk, trim((string) $this->request->post($vk, '')));
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
        $openaiDescribeFlag = SystemConfigService::get('openai_describe_on_import', '');
        $openaiDescribeOnImport = $openaiDescribeFlag === '' || $openaiDescribeFlag === '1';
        $volcArkEnabled = SystemConfigService::get('volc_ark_enabled', '0') === '1';
        $volcArkAccessConfigured = trim((string) (SystemConfigService::get('volc_ark_access_key', '') ?? '')) !== ''
            || trim((string) (getenv('VOLC_ACCESS_KEY') ?: '')) !== ''
            || trim((string) (SystemConfigService::get('volc_ark_secret_key', '') ?? '')) !== ''
            || trim((string) (getenv('VOLC_SECRET_KEY') ?: '')) !== '';
        $volcArkSecretConfigured = trim((string) (SystemConfigService::get('volc_ark_secret_key', '') ?? '')) !== '';
        $volcArkEndpoint = SystemConfigService::get('volc_ark_endpoint_id', '') ?? '';
        $volcArkBaseUrl = SystemConfigService::get('volc_ark_base_url', '') ?? '';
        $volcArkMaxCatalog = SystemConfigService::get('volc_ark_max_catalog', '') ?? '';
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
            'openai_describe_on_import' => $openaiDescribeOnImport,
            'volc_ark_enabled' => $volcArkEnabled,
            'volc_ark_access_configured' => $volcArkAccessConfigured,
            'volc_ark_secret_configured' => $volcArkSecretConfigured,
            'volc_ark_endpoint_id' => $volcArkEndpoint,
            'volc_ark_base_url' => $volcArkBaseUrl,
            'volc_ark_max_catalog' => $volcArkMaxCatalog,
        ]);
    }
}
