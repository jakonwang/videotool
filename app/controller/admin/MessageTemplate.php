<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\MessageTemplate as MessageTemplateModel;
use app\service\MessageOutreachService;
use think\facade\View;

/**
 * 达人联系话术模板
 */
class MessageTemplate extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null)
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }

    public function index()
    {
        return View::fetch('admin/message_template/index', []);
    }

    public function listJson()
    {
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 50);
        if ($pageSize <= 0) {
            $pageSize = 50;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }

        $query = MessageTemplateModel::order('sort_order', 'asc')->order('id', 'desc');
        $onlyEnabled = (string) $this->request->param('enabled', '');
        if ($onlyEnabled === '1') {
            $query->where('status', 1);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'body' => (string) ($row->body ?? ''),
                'sort_order' => (int) ($row->sort_order ?? 0),
                'status' => (int) ($row->status ?? 1),
                'updated_at' => (string) ($row->updated_at ?? ''),
            ];
        }

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ]);
    }

    public function save()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return $this->jsonErr('请填写模板名称');
        }
        $body = (string) ($payload['body'] ?? '');
        $sortOrder = (int) ($payload['sort_order'] ?? 0);
        $status = (int) ($payload['status'] ?? 1);
        $status = $status === 0 ? 0 : 1;

        if ($id > 0) {
            $row = MessageTemplateModel::find($id);
            if (!$row) {
                return $this->jsonErr('记录不存在');
            }
            $row->name = mb_substr($name, 0, 128);
            $row->body = $body;
            $row->sort_order = $sortOrder;
            $row->status = $status;
            $row->save();
        } else {
            MessageTemplateModel::create([
                'name' => mb_substr($name, 0, 128),
                'body' => $body,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);
        }

        return $this->jsonOk([], '已保存');
    }

    public function delete()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonErr('无效 id');
        }
        MessageTemplateModel::destroy($id);

        return $this->jsonOk([], '已删除');
    }

    /**
     * 渲染话术（供名录「一键联系」）
     */
    public function render()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $templateId = (int) ($payload['template_id'] ?? 0);
        $influencerId = (int) ($payload['influencer_id'] ?? 0);
        $productId = isset($payload['product_id']) ? (int) $payload['product_id'] : 0;
        if ($templateId <= 0 || $influencerId <= 0) {
            return $this->jsonErr('缺少 template_id 或 influencer_id');
        }
        $tpl = MessageTemplateModel::find($templateId);
        if (!$tpl || (int) $tpl->status !== 1) {
            return $this->jsonErr('模板不存在或未启用');
        }
        $baseUrl = MessageOutreachService::adminBaseUrl();
        $vars = MessageOutreachService::buildRenderVars(
            $influencerId,
            $productId > 0 ? $productId : null,
            $baseUrl
        );
        if ($vars === []) {
            return $this->jsonErr('达人不存在');
        }
        $text = MessageOutreachService::renderBody((string) $tpl->body, $vars);
        $wa = '';
        if (($vars['whatsapp'] ?? '') !== '') {
            $wa = MessageOutreachService::waMeWithText($vars['whatsapp'], $text);
        }
        $zalo = ($vars['zalo'] ?? '') !== '' ? ('https://zalo.me/' . $vars['zalo']) : '';

        return $this->jsonOk([
            'text' => $text,
            'wa_url' => $wa,
            'zalo_url' => $zalo,
            'vars' => $vars,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonOrPost(): array
    {
        $raw = (string) $this->request->getContent();
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                return $j;
            }
        }

        return $this->request->post();
    }
}
