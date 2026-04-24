<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\AdminAuthService;
use app\service\GmvMaxCreativeInsightService;
use app\service\ProfitPluginTokenService;

class GmvMax extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return $this->apiJsonOk($data, $msg);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        return $this->apiJsonErr($msg, $code, $data, $errorKey);
    }

    public function creativeSync()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $auth = $this->resolvePluginTenantContext($payload);
        if (!($auth['ok'] ?? false)) {
            return $this->jsonErr((string) ($auth['message'] ?? 'token_required'), 401, null, 'common.forbidden');
        }

        try {
            $result = GmvMaxCreativeInsightService::sync((int) $auth['tenant_id'], $payload);
            if (!($result['ok'] ?? false)) {
                return $this->jsonErr((string) ($result['message'] ?? 'sync_failed'), 1, $result, 'common.operationFailed');
            }
            if ((int) ($auth['token_id'] ?? 0) > 0) {
                ProfitPluginTokenService::touchTokenUsage((int) $auth['token_id'], (string) $this->request->ip());
            }
            return $this->jsonOk($result, 'synced');
        } catch (\Throwable $e) {
            return $this->jsonErr('sync_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    public function creativeBaseline()
    {
        $storeId = (int) $this->request->param('store_id', 0);
        $windowDays = (int) $this->request->param('window_days', 30);
        return $this->jsonOk(GmvMaxCreativeInsightService::baseline($this->currentTenantId(), $storeId, $windowDays));
    }

    public function creativeRecommendation()
    {
        $storeId = (int) $this->request->param('store_id', 0);
        $campaignId = trim((string) $this->request->param('campaign_id', ''));
        return $this->jsonOk(GmvMaxCreativeInsightService::recommendation($this->currentTenantId(), $storeId, $campaignId));
    }

    public function creativeHistory()
    {
        $storeId = (int) $this->request->param('store_id', 0);
        $campaignId = trim((string) $this->request->param('campaign_id', ''));
        $dateFrom = trim((string) $this->request->param('date_from', ''));
        $dateTo = trim((string) $this->request->param('date_to', ''));
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 50);
        return $this->jsonOk(GmvMaxCreativeInsightService::history($this->currentTenantId(), $storeId, $campaignId, $dateFrom, $dateTo, $page, $pageSize));
    }

    public function creativeRanking()
    {
        $storeId = (int) $this->request->param('store_id', 0);
        $dateFrom = trim((string) $this->request->param('date_from', ''));
        $dateTo = trim((string) $this->request->param('date_to', ''));
        $limit = (int) $this->request->param('limit', 50);
        return $this->jsonOk(GmvMaxCreativeInsightService::ranking($this->currentTenantId(), $storeId, $dateFrom, $dateTo, $limit));
    }

    /**
     * @return array<string,mixed>
     */
    private function parseJsonOrPost(): array
    {
        $raw = (string) $this->request->getContent();
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }
        return $this->request->post();
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,tenant_id?:int,token_id?:int,message?:string}
     */
    private function resolvePluginTenantContext(array $payload): array
    {
        if (AdminAuthService::isLoggedIn()) {
            return [
                'ok' => true,
                'tenant_id' => $this->currentTenantId(),
                'token_id' => 0,
            ];
        }

        $token = ProfitPluginTokenService::extractTokenFromRequest($this->request, $payload);
        $verify = ProfitPluginTokenService::verifyToken($token, ProfitPluginTokenService::SCOPE_INGEST);
        if (!($verify['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($verify['reason'] ?? 'token_invalid')];
        }
        $row = is_array($verify['row'] ?? null) ? $verify['row'] : [];
        return [
            'ok' => true,
            'tenant_id' => max(1, (int) ($row['tenant_id'] ?? 1)),
            'token_id' => (int) ($row['id'] ?? 0),
        ];
    }
}
