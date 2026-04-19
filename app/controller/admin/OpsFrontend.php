<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\AdminAuthService;
use think\facade\Db;

class OpsFrontend extends BaseController
{
    public function healthSave()
    {
        if (!$this->request->isPost()) {
            return $this->apiJsonErr('only_post', 1, null, 'common.onlyPost');
        }

        $payload = $this->parseJsonOrPost();
        $page = $this->limitText((string) ($payload['page'] ?? ''), 191);
        $module = $this->limitText((string) ($payload['module'] ?? ''), 64);
        $event = $this->limitText((string) ($payload['event'] ?? ''), 64);
        $traceId = $this->limitText((string) ($payload['trace_id'] ?? ''), 96);
        $detail = $this->encodeDetail($payload['detail'] ?? null);

        if ($module === '') {
            $module = 'unknown';
        }
        if ($event === '') {
            $event = 'unknown';
        }

        try {
            Db::name('ops_frontend_health_logs')->insert([
                'tenant_id' => (int) AdminAuthService::tenantId(),
                'admin_id' => (int) AdminAuthService::userId(),
                'module' => $module,
                'page' => $page,
                'event' => $event,
                'trace_id' => $traceId,
                'detail_json' => $detail,
                'ip' => $this->limitText((string) $this->request->ip(), 64),
                'user_agent' => $this->limitText((string) $this->request->header('user-agent', ''), 255),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return $this->apiJsonErr('save_failed', 1, ['reason' => 'db_insert_failed'], 'common.saveFailed');
        }

        return $this->apiJsonOk(['saved' => 1], 'ok');
    }

    /**
     * @return array<string, mixed>
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

        $post = $this->request->post();
        return is_array($post) ? $post : [];
    }

    /**
     * @param mixed $detail
     */
    private function encodeDetail($detail): string
    {
        if ($detail === null || $detail === '') {
            return '';
        }

        if (is_scalar($detail)) {
            return $this->limitText((string) $detail, 2000);
        }

        try {
            return $this->limitText((string) json_encode($detail, JSON_UNESCAPED_UNICODE), 2000);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function limitText(string $text, int $maxLen): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $maxLen);
        }

        return substr($text, 0, $maxLen);
    }
}
