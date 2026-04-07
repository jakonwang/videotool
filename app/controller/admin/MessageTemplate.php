<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Influencer as InfluencerModel;
use app\model\MessageTemplate as MessageTemplateModel;
use app\model\OutreachLog as OutreachLogModel;
use app\service\MessageOutreachService;
use think\facade\Db;
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
        $lang = trim((string) $this->request->param('lang', ''));
        if ($onlyEnabled === '1') {
            $query->where('status', 1);
        }
        if (in_array($lang, ['zh', 'en', 'vi'], true)) {
            $query->where('lang', $lang);
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
                'template_key' => (string) ($row->template_key ?? ''),
                'lang' => (string) ($row->lang ?? 'zh'),
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
        $lang = trim((string) ($payload['lang'] ?? 'zh'));
        if (!in_array($lang, ['zh', 'en', 'vi'], true)) {
            $lang = 'zh';
        }
        $templateKey = trim((string) ($payload['template_key'] ?? ''));
        $sortOrder = (int) ($payload['sort_order'] ?? 0);
        $status = (int) ($payload['status'] ?? 1);
        $status = $status === 0 ? 0 : 1;

        if ($id > 0) {
            $row = MessageTemplateModel::find($id);
            if (!$row) {
                return $this->jsonErr('记录不存在');
            }
            $row->name = mb_substr($name, 0, 128);
            $row->template_key = $templateKey !== '' ? mb_substr($templateKey, 0, 64) : (string) ($row->template_key ?? ('tpl_' . $row->id));
            $row->lang = $lang;
            $row->body = $body;
            $row->sort_order = $sortOrder;
            $row->status = $status;
            $row->save();
        } else {
            $new = MessageTemplateModel::create([
                'name' => mb_substr($name, 0, 128),
                'template_key' => $templateKey !== '' ? mb_substr($templateKey, 0, 64) : '',
                'lang' => $lang,
                'body' => $body,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);
            if ((string) ($new->template_key ?? '') === '') {
                $new->template_key = 'tpl_' . (int) $new->id;
                $new->save();
            }
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
        $inf = InfluencerModel::find($influencerId);
        if (!$inf) {
            return $this->jsonErr('达人不存在');
        }
        $pickedTpl = MessageOutreachService::pickTemplateVariantByRegion([
            'id' => (int) $tpl->id,
            'name' => (string) ($tpl->name ?? ''),
            'template_key' => (string) ($tpl->template_key ?? ''),
            'lang' => (string) ($tpl->lang ?? 'en'),
            'body' => (string) ($tpl->body ?? ''),
            'status' => (int) ($tpl->status ?? 1),
        ], (string) ($inf->region ?? ''));
        $baseUrl = MessageOutreachService::adminBaseUrl();
        $vars = MessageOutreachService::buildRenderVars(
            $influencerId,
            $productId > 0 ? $productId : null,
            $baseUrl
        );
        if ($vars === []) {
            return $this->jsonErr('达人不存在');
        }
        $text = MessageOutreachService::renderBody((string) ($pickedTpl['body'] ?? ''), $vars);
        $wa = MessageOutreachService::waMeWithText((string) ($vars['whatsapp'] ?? ''), $text);
        $zalo = MessageOutreachService::buildZaloUrl((string) ($vars['zalo'] ?? ''));

        // 渲染即视作一次联系动作，更新最后联系时间并记录历史
        $now = date('Y-m-d H:i:s');
        InfluencerModel::where('id', $influencerId)->update(['last_contacted_at' => $now]);
        $productName = '';
        if ($productId > 0) {
            $p = Db::name('products')->where('id', $productId)->find();
            if (is_array($p)) {
                $productName = (string) ($p['name'] ?? '');
            }
        }
        OutreachLogModel::create([
            'influencer_id' => $influencerId,
            'template_id' => (int) ($pickedTpl['id'] ?? 0),
            'template_name' => (string) ($pickedTpl['name'] ?? ''),
            'template_lang' => (string) ($pickedTpl['lang'] ?? 'en'),
            'product_id' => $productId > 0 ? $productId : null,
            'product_name' => $productName !== '' ? $productName : null,
            'channel' => 'render',
            'rendered_body' => $text,
        ]);

        return $this->jsonOk([
            'text' => $text,
            'wa_url' => $wa,
            'zalo_url' => $zalo,
            'vars' => $vars,
            'template_lang' => (string) ($pickedTpl['lang'] ?? 'en'),
            'template_id' => (int) ($pickedTpl['id'] ?? 0),
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
