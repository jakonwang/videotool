<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Influencer as InfluencerModel;
use app\model\SampleShipment as SampleShipmentModel;
use app\service\InfluencerStatusFlowService;
use think\facade\Db;
use think\facade\View;

class Sample extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        return json(['code' => $code, 'msg' => $msg, 'error_key' => $errorKey, 'data' => $data]);
    }

    public function index()
    {
        return View::fetch('admin/sample/index', []);
    }

    public function listJson()
    {
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }
        $shipmentStatus = $this->request->param('shipment_status', '');
        $keyword = trim((string) $this->request->param('keyword', ''));
        $query = SampleShipmentModel::alias('s')
            ->leftJoin('influencers i', 'i.id = s.influencer_id')
            ->field('s.*,i.tiktok_id,i.nickname,i.status as influencer_status')
            ->order('s.id', 'desc');
        if ($shipmentStatus !== '' && $shipmentStatus !== null) {
            $query->where('s.shipment_status', (int) $shipmentStatus);
        }
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('i.tiktok_id', '%' . $keyword . '%')
                    ->whereOr('i.nickname', 'like', '%' . $keyword . '%')
                    ->whereOr('s.tracking_no', 'like', '%' . $keyword . '%');
            });
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
                'influencer_id' => (int) ($row->influencer_id ?? 0),
                'tiktok_id' => (string) ($row->tiktok_id ?? ''),
                'nickname' => (string) ($row->nickname ?? ''),
                'tracking_no' => (string) ($row->tracking_no ?? ''),
                'courier' => (string) ($row->courier ?? ''),
                'shipment_status' => (int) ($row->shipment_status ?? 0),
                'receipt_status' => (int) ($row->receipt_status ?? 0),
                'receipt_note' => (string) ($row->receipt_note ?? ''),
                'shipped_at' => (string) ($row->shipped_at ?? ''),
                'received_at' => (string) ($row->received_at ?? ''),
                'influencer_status' => (int) ($row->influencer_status ?? 0),
                'created_at' => (string) ($row->created_at ?? ''),
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
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $influencerId = (int) ($payload['influencer_id'] ?? 0);
        $trackingNo = trim((string) ($payload['tracking_no'] ?? ''));
        $courier = trim((string) ($payload['courier'] ?? ''));
        $shipmentStatus = (int) ($payload['shipment_status'] ?? 0);
        $receiptStatus = (int) ($payload['receipt_status'] ?? 0);
        $receiptNote = trim((string) ($payload['receipt_note'] ?? ''));
        if ($id <= 0 && ($influencerId <= 0 || $trackingNo === '')) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        if ($shipmentStatus < 0) {
            $shipmentStatus = 0;
        }
        if ($shipmentStatus > 3) {
            $shipmentStatus = 3;
        }
        if ($receiptStatus < 0) {
            $receiptStatus = 0;
        }
        if ($receiptStatus > 2) {
            $receiptStatus = 2;
        }

        if ($id > 0) {
            $row = SampleShipmentModel::find($id);
            if (!$row) {
                return $this->jsonErr('not_found', 1, null, 'common.notFound');
            }
        } else {
            $row = SampleShipmentModel::create([
                'influencer_id' => $influencerId,
                'tracking_no' => mb_substr($trackingNo, 0, 64),
                'shipment_status' => max(1, $shipmentStatus),
                'shipped_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $row->tracking_no = $trackingNo !== '' ? mb_substr($trackingNo, 0, 64) : (string) ($row->tracking_no ?? '');
        $row->courier = $courier !== '' ? mb_substr($courier, 0, 64) : null;
        $row->shipment_status = $shipmentStatus;
        $row->receipt_status = $receiptStatus;
        $row->receipt_note = $receiptNote !== '' ? mb_substr($receiptNote, 0, 255) : null;
        if ($shipmentStatus >= 1 && !$row->shipped_at) {
            $row->shipped_at = date('Y-m-d H:i:s');
        }
        if ($shipmentStatus === 2 && !$row->received_at) {
            $row->received_at = date('Y-m-d H:i:s');
        }
        $row->save();

        $inf = InfluencerModel::find((int) $row->influencer_id);
        if ($inf) {
            if ($shipmentStatus >= 1 && (int) ($inf->status ?? 0) < 4 && (int) ($inf->status ?? 0) !== 6) {
                InfluencerStatusFlowService::transition(
                    (int) $inf->id,
                    4,
                    'sample_sop',
                    '',
                    ['sample_shipment_id' => (int) $row->id],
                    true
                );
            }
            if ($shipmentStatus === 2 && (int) ($inf->status ?? 0) < 5 && (int) ($inf->status ?? 0) !== 6) {
                InfluencerStatusFlowService::transition(
                    (int) $inf->id,
                    5,
                    'sample_sop',
                    '',
                    ['sample_shipment_id' => (int) $row->id],
                    true
                );
            }
        }

        return $this->jsonOk([], 'saved');
    }

    public function createFromInfluencer()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $influencerId = (int) ($payload['influencer_id'] ?? 0);
        $trackingNo = trim((string) ($payload['tracking_no'] ?? ''));
        if ($influencerId <= 0 || $trackingNo === '') {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $exists = SampleShipmentModel::where('influencer_id', $influencerId)
            ->where('tracking_no', $trackingNo)
            ->find();
        if ($exists) {
            return $this->jsonErr('duplicate_tracking', 1, null, 'page.sample.duplicateTracking');
        }
        SampleShipmentModel::create([
            'influencer_id' => $influencerId,
            'tracking_no' => mb_substr($trackingNo, 0, 64),
            'courier' => trim((string) ($payload['courier'] ?? '')) ?: null,
            'shipment_status' => 1,
            'shipped_at' => date('Y-m-d H:i:s'),
        ]);
        $flow = InfluencerStatusFlowService::transition(
            $influencerId,
            4,
            'sample_sop',
            '',
            ['tracking_no' => $trackingNo],
            true
        );
        if (!$flow['ok']) {
            return $this->jsonErr((string) ($flow['message'] ?? 'save_failed'), 1, null, 'common.saveFailed');
        }
        return $this->jsonOk([], 'created');
    }

    public function markReceived()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $row = SampleShipmentModel::find($id);
        if (!$row) {
            return $this->jsonErr('not_found', 1, null, 'common.notFound');
        }
        $row->shipment_status = 2;
        $row->receipt_status = 1;
        $row->received_at = date('Y-m-d H:i:s');
        $row->receipt_note = trim((string) ($payload['receipt_note'] ?? '')) ?: null;
        $row->save();

        InfluencerStatusFlowService::transition(
            (int) ($row->influencer_id ?? 0),
            5,
            'sample_sop',
            '',
            ['sample_shipment_id' => (int) $row->id],
            true
        );

        return $this->jsonOk([], 'updated');
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

