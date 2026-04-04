<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\model\Product as ProductModel;
use app\model\ProductStyleItem as ItemModel;
use app\service\AliyunImageSearchConfig;
use app\service\AliyunImageSearchService;

/**
 * 以图搜款（阿里云图像搜索）；原本地向量逻辑已废弃为此入口。
 */
class Search extends BaseController
{
    private function jsonOut(array $payload, int $httpCode = 200)
    {
        return json($payload, $httpCode, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
    }

    /**
     * POST multipart: file 为查询图；返回阿里云 Top5 ProductId，并合并本地索引与商品表信息。
     */
    public function searchByImage()
    {
        if (!AliyunImageSearchConfig::get()['enabled']) {
            return $this->jsonOut([
                'code' => 1,
                'msg' => '未启用阿里云图像搜索，请在后台「设置」中填写 AccessKey、Endpoint、实例名称并勾选启用',
                'data' => null,
            ]);
        }

        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonOut(['code' => 1, 'msg' => '请上传图片 file', 'data' => null]);
        }
        $tmp = $file->getPathname();
        if (!is_readable($tmp)) {
            return $this->jsonOut(['code' => 1, 'msg' => '无法读取上传文件', 'data' => null]);
        }

        $as = AliyunImageSearchService::searchImageByPic($tmp);
        if (!($as['ok'] ?? false)) {
            $msg = (string) ($as['error'] ?? '搜索失败');
            $payload = [
                'throttle' => !empty($as['throttle']),
                'timeout' => !empty($as['timeout']),
                'subject_hint' => $as['subject_hint'] ?? null,
                'request_id' => $as['request_id'] ?? null,
            ];
            $code = !empty($as['throttle']) ? 429 : (!empty($as['timeout']) ? 504 : 1);

            return $this->jsonOut(['code' => $code, 'msg' => $msg, 'data' => $payload]);
        }

        $hits = $as['items'] ?? [];
        $items = [];
        foreach ($hits as $h) {
            $code = (string) ($h['product_id'] ?? '');
            if ($code === '') {
                continue;
            }
            $row = ItemModel::where('status', 1)->where('product_code', $code)->order('id', 'desc')->find();
            $hotFromCustom = '';
            $cc = (string) ($h['custom_content'] ?? '');
            if ($cc !== '' && $cc[0] === '{') {
                $dec = json_decode($cc, true);
                if (is_array($dec) && isset($dec['hot_type'])) {
                    $hotFromCustom = (string) $dec['hot_type'];
                }
            }
            $product = ProductModel::where('name', $code)->where('status', 1)->find();
            if (!$product) {
                $product = ProductModel::whereLike('name', '%' . $code . '%')->where('status', 1)->order('id', 'desc')->find();
            }

            $score = (float) ($h['score'] ?? 0);
            $similarity = $score > 1.0 ? $score / 100.0 : $score;
            if ($similarity > 1.0) {
                $similarity = 1.0;
            }
            if ($similarity < 0) {
                $similarity = 0;
            }

            $items[] = [
                'product_code' => $code,
                'image_ref' => $row ? (string) ($row->image_ref ?? '') : '',
                'hot_type' => $row ? (string) ($row->hot_type ?? '') : $hotFromCustom,
                'similarity' => round($similarity, 4),
                'aliyun_pic_name' => (string) ($h['pic_name'] ?? ''),
                'product' => $product ? [
                    'id' => (int) $product->id,
                    'name' => (string) ($product->name ?? ''),
                    'status' => (int) ($product->status ?? 0),
                    'status_text' => ((int) ($product->status ?? 0)) === 1 ? '上架' : '停用',
                    'goods_url' => (string) ($product->goods_url ?? ''),
                ] : null,
            ];
        }

        $num = AliyunImageSearchConfig::get()['search_num'];

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'items' => $items,
                'engine' => 'aliyun_is',
                'request_id' => $as['request_id'] ?? '',
                'num' => $num,
            ],
        ]);
    }
}
