<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\model\Product as ProductModel;
use app\model\ProductStyleItem as ItemModel;
use app\service\ProductStyleKeywordSearchService;
use app\service\VolcArkVisionConfig;
use app\service\VolcArkVisionService;

/**
 * 以图搜款：仅火山方舟豆包视觉（拍照寻款）。
 */
class Search extends BaseController
{
    private function jsonOut(array $payload, int $httpCode = 200)
    {
        return json($payload, $httpCode, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
    }

    /**
     * POST multipart: file 为查询图
     */
    public function searchByImage()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonOut(['code' => 1, 'msg' => '请上传图片 file', 'data' => null]);
        }
        $tmp = $file->getPathname();
        if (!is_readable($tmp)) {
            return $this->jsonOut(['code' => 1, 'msg' => '无法读取上传文件', 'data' => null]);
        }

        $hint = trim((string) $this->request->post('hint', ''));

        if (!VolcArkVisionConfig::get()['enabled']) {
            return $this->jsonOut([
                'code' => 1,
                'msg' => '未启用寻款引擎：请在后台「设置」启用火山方舟豆包并填写 Endpoint ID 与 API Key',
                'data' => null,
            ]);
        }

        return $this->searchByVolcArkVision($tmp, $hint);
    }

    /**
     * 火山方舟 Doubao-vision：清单含中文描述与爆款；失败时可配合 hint 走关键词回退。
     */
    private function searchByVolcArkVision(string $tmp, string $hint)
    {
        $cfg = VolcArkVisionConfig::get();
        $limit = (int) ($cfg['max_catalog_items'] ?? 250);
        $rows = ItemModel::where('status', 1)
            ->order('id', 'desc')
            ->limit($limit)
            ->select();

        $catalog = [];
        $allowed = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row->product_code ?? ''));
            if ($code === '') {
                continue;
            }
            $desc = trim((string) ($row->ai_description ?? ''));
            $hot = trim((string) ($row->hot_type ?? ''));
            if ($desc === '' && $hot === '') {
                continue;
            }
            $catalog[] = [
                'code' => $code,
                'desc' => $desc !== '' ? $desc : '（暂无特征描述）',
                'hot' => $hot,
            ];
            $allowed[$code] = true;
        }

        if ($catalog === []) {
            return $this->jsonOut([
                'code' => 1,
                'msg' => '库内无可用描述：请先在导入表格中填写「爆款类型」或通过豆包导入生成「视觉特征」，再使用寻款。',
                'data' => ['engine' => 'volc_ark', 'catalog_size' => 0],
            ]);
        }

        $hintArg = $hint !== '' ? $hint : null;
        $match = VolcArkVisionService::matchPhotoToCatalog($tmp, $catalog, $hintArg);

        if (!($match['ok'] ?? false)) {
            if (mb_strlen($hint) >= 2) {
                $kw = ProductStyleKeywordSearchService::searchByHint($hint, 12);
                $items = $this->buildItemsFromKeywordRows($kw);
                if ($items !== []) {
                    return $this->jsonOut([
                        'code' => 0,
                        'msg' => 'ok',
                        'data' => [
                            'items' => $items,
                            'engine' => 'volc_ark_keyword',
                            'catalog_size' => count($catalog),
                            'fallback' => true,
                            'fallback_reason' => '视觉接口未返回有效结果，已按补充关键词回退',
                        ],
                    ]);
                }
            }

            return $this->jsonOut([
                'code' => 1,
                'msg' => (string) ($match['error'] ?? '豆包视觉识别失败'),
                'data' => [
                    'engine' => 'volc_ark',
                    'catalog_size' => count($catalog),
                    'suggest_hint' => mb_strlen($hint) < 2 ? 1 : 0,
                ],
            ]);
        }

        $matches = $match['matches'] ?? [];
        $items = [];
        foreach ($matches as $m) {
            $code = (string) ($m['product_code'] ?? '');
            if ($code === '' || !isset($allowed[$code])) {
                continue;
            }
            $styleRow = ItemModel::where('status', 1)->where('product_code', $code)->order('id', 'desc')->find();
            $product = ProductModel::where('name', $code)->where('status', 1)->find();
            if (!$product) {
                $product = ProductModel::whereLike('name', '%' . $code . '%')->where('status', 1)->order('id', 'desc')->find();
            }
            $score = (float) ($m['score'] ?? 0);
            if ($score > 1) {
                $score = 1.0;
            }
            if ($score < 0) {
                $score = 0.0;
            }

            $items[] = [
                'product_code' => $code,
                'image_ref' => $styleRow ? (string) ($styleRow->image_ref ?? '') : '',
                'hot_type' => $styleRow ? (string) ($styleRow->hot_type ?? '') : '',
                'similarity' => round($score, 4),
                'match_reason' => (string) ($m['reason'] ?? ''),
                'product' => $product ? [
                    'id' => (int) $product->id,
                    'name' => (string) ($product->name ?? ''),
                    'status' => (int) ($product->status ?? 0),
                    'status_text' => ((int) ($product->status ?? 0)) === 1 ? '上架' : '停用',
                    'goods_url' => (string) ($product->goods_url ?? ''),
                ] : null,
            ];
        }

        if ($items === []) {
            if (mb_strlen($hint) >= 2) {
                $kw = ProductStyleKeywordSearchService::searchByHint($hint, 12);
                $kwItems = $this->buildItemsFromKeywordRows($kw);
                if ($kwItems !== []) {
                    return $this->jsonOut([
                        'code' => 0,
                        'msg' => 'ok',
                        'data' => [
                            'items' => $kwItems,
                            'engine' => 'volc_ark_keyword',
                            'catalog_size' => count($catalog),
                            'fallback' => true,
                            'fallback_reason' => '模型未选出清单内编号，已按关键词回退',
                        ],
                    ]);
                }
            }

            return $this->jsonOut([
                'code' => 1,
                'msg' => '未从实拍图中匹配到库内编号，可尝试补充文字说明（如形状、颜色）后重试',
                'data' => ['engine' => 'volc_ark', 'catalog_size' => count($catalog)],
            ]);
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'items' => $items,
                'engine' => 'volc_ark',
                'num' => count($items),
                'catalog_size' => count($catalog),
            ],
        ]);
    }

    /**
     * @param iterable<\app\model\ProductStyleItem>|\think\Collection $rows
     * @return list<array<string, mixed>>
     */
    private function buildItemsFromKeywordRows($rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row->product_code ?? ''));
            if ($code === '') {
                continue;
            }
            $product = ProductModel::where('name', $code)->where('status', 1)->find();
            if (!$product) {
                $product = ProductModel::whereLike('name', '%' . $code . '%')->where('status', 1)->order('id', 'desc')->find();
            }
            $items[] = [
                'product_code' => $code,
                'image_ref' => (string) ($row->image_ref ?? ''),
                'hot_type' => (string) ($row->hot_type ?? ''),
                'similarity' => 0.35,
                'match_reason' => '关键词回退匹配（非视觉打分）',
                'product' => $product ? [
                    'id' => (int) $product->id,
                    'name' => (string) ($product->name ?? ''),
                    'status' => (int) ($product->status ?? 0),
                    'status_text' => ((int) ($product->status ?? 0)) === 1 ? '上架' : '停用',
                    'goods_url' => (string) ($product->goods_url ?? ''),
                ] : null,
            ];
        }

        return $items;
    }
}
