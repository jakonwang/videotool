<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\AiCommanderService;
use think\facade\View;

class AiCenter extends BaseController
{
    public function index()
    {
        return View::fetch('admin/ai_center/index', []);
    }

    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return $this->apiJsonOk($data, $msg);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        return $this->apiJsonErr($msg, $code, $data, $errorKey);
    }

    public function chat()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parsePayload();
        try {
            $result = AiCommanderService::chat($this->currentTenantId(), $payload);
            return $this->jsonOk($result);
        } catch (\Throwable $e) {
            return $this->jsonErr('ai_chat_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    public function planGenerate()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parsePayload();
        try {
            $result = AiCommanderService::generatePlan($this->currentTenantId(), $payload);
            return $this->jsonOk($result);
        } catch (\Throwable $e) {
            return $this->jsonErr('ai_plan_generate_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    public function planExecute()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parsePayload();
        try {
            $result = AiCommanderService::executePlan($this->currentTenantId(), $payload);
            if (!($result['ok'] ?? true)) {
                return $this->jsonErr((string) ($result['message'] ?? 'plan_execute_failed'), 1, $result, 'common.operationFailed');
            }
            return $this->jsonOk($result);
        } catch (\Throwable $e) {
            return $this->jsonErr('ai_plan_execute_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    public function planStatus()
    {
        $payload = [
            'store_id' => (int) $this->request->param('store_id', 0),
            'status' => trim((string) $this->request->param('status', '')),
            'page' => (int) $this->request->param('page', 1),
            'page_size' => (int) $this->request->param('page_size', 20),
        ];
        try {
            $result = AiCommanderService::planStatus($this->currentTenantId(), $payload);
            return $this->jsonOk($result);
        } catch (\Throwable $e) {
            return $this->jsonErr('ai_plan_status_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    public function feedback()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parsePayload();
        try {
            $result = AiCommanderService::feedback($this->currentTenantId(), $payload);
            return $this->jsonOk($result);
        } catch (\Throwable $e) {
            return $this->jsonErr('ai_feedback_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    public function dailyInsight()
    {
        $payload = [
            'store_id' => (int) $this->request->param('store_id', 0),
            'campaign_id' => trim((string) $this->request->param('campaign_id', '')),
        ];
        try {
            $result = AiCommanderService::dailyInsight($this->currentTenantId(), $payload);
            return $this->jsonOk($result);
        } catch (\Throwable $e) {
            return $this->jsonErr('ai_daily_insight_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function parsePayload(): array
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
}

