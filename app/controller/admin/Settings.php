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
        ]);
    }
}
