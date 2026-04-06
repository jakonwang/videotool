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
                'msg' => '未启用寻款引擎：请在后台「设置」启用豆包并填写 API Key 与 model（或 ep-）',
                'data' => null,
            ]);
        }

        return $this->searchByVolcArkVision($tmp, $hint);
    }

    /**
     * 火山方舟 Doubao-vision：全自动「仓库扫描器」单编号输出 + 库内详情闭环；失败时可配合 hint 走关键词回退。
     */
    private function searchByVolcArkVision(string $tmp, string $hint)
    {
        $cfg = VolcArkVisionConfig::get();
        $limit = (int) ($cfg['auto_match_catalog_limit'] ?? 50);
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
            $imageRef = trim((string) ($row->image_ref ?? ''));

            $product = ProductModel::where('name', $code)->where('status', 1)->find();
            if ($desc === '' && $product) {
                $desc = trim((string) ($product->ai_description ?? ''));
            }

            if ($desc === '' && $hot === '') {
                if ($imageRef === '') {
                    continue;
                }
                $desc = '（编号' . $code . '：已入库参考图，文字特征未填）';
            }
            $thumbUrl = $this->resolvePublicImageUrl($imageRef);

            $catalog[] = [
                'code' => $code,
                'desc' => $desc !== '' ? $desc : '（暂无特征描述）',
                'hot' => $hot,
                'thumb_url' => $thumbUrl,
            ];
            $allowed[$code] = true;
        }

        if ($catalog === []) {
            return $this->jsonOut([
                'code' => 1,
                'msg' => '库内无可用索引：上架款式需至少有「AI 描述」「爆款类型」或「参考图」之一；请检查导入是否成功、商品是否上架（status=1）。',
                'data' => ['engine' => 'volc_ark', 'catalog_size' => 0],
            ]);
        }

        $hintArg = $hint !== '' ? $hint : null;
        $match = VolcArkVisionService::matchPhotoAutoWarehouse($tmp, $catalog, $hintArg);
        $n = count($catalog);

        if (!($match['ok'] ?? false)) {
            return $this->tryKeywordFallbackOrError($hint, $match, $n);
        }

        if (!empty($match['is_null'])) {
            return $this->tryKeywordFallbackOrNoMatch($hint, $n, '豆包判定库内无匹配（NULL）');
        }

        $code = trim((string) ($match['code'] ?? ''));
        if ($code === '' || !isset($allowed[$code])) {
            return $this->tryKeywordFallbackOrNoMatch($hint, $n, '豆包返回的编号不在当前候选清单内');
        }

        $item = $this->buildVolcArkItemForCode($code);
        if ($item === null) {
            return $this->tryKeywordFallbackOrNoMatch($hint, $n, '已识别编号但索引中无对应上架记录');
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'items' => [$item],
                'engine' => 'volc_ark',
                'auto_match' => true,
                'matched_code' => $code,
                'num' => 1,
                'catalog_size' => $n,
            ],
        ]);
    }

    /**
     * 站内路径或外链 → 可供豆包 system 文本引用的完整参考图 URL（无法解析则空，对应字段填「-」由上层处理）。
     */
    private function resolvePublicImageUrl(string $imageRef): string
    {
        $ref = trim($imageRef);
        if ($ref === '' || str_starts_with($ref, '(')) {
            return '';
        }
        if (preg_match('#^https?://#i', $ref)) {
            return $ref;
        }
        $path = str_starts_with($ref, '/') ? $ref : '/' . $ref;
        $req = $this->request;

        return rtrim($req->domain(), '/') . $req->rootUrl() . $path;
    }

    /**
     * @param array{ok?:bool, error?:string, raw?:string} $match
     */
    private function tryKeywordFallbackOrError(string $hint, array $match, int $catalogSize)
    {
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
                        'catalog_size' => $catalogSize,
                        'fallback' => true,
                        'fallback_reason' => '视觉接口未返回有效编号，已按补充关键词回退',
                    ],
                ]);
            }
        }

        return $this->jsonOut([
            'code' => 1,
            'msg' => (string) ($match['error'] ?? '豆包视觉识别失败'),
            'data' => [
                'engine' => 'volc_ark',
                'catalog_size' => $catalogSize,
            ],
        ]);
    }

    private function tryKeywordFallbackOrNoMatch(string $hint, int $catalogSize, string $msg)
    {
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
                        'catalog_size' => $catalogSize,
                        'fallback' => true,
                        'fallback_reason' => $msg . '；已按关键词回退',
                    ],
                ]);
            }
        }

        return $this->jsonOut([
            'code' => 1,
            'msg' => $msg,
            'data' => ['engine' => 'volc_ark', 'catalog_size' => $catalogSize],
        ]);
    }

    private function buildVolcArkItemForCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $styleRow = ItemModel::where('status', 1)->where('product_code', $code)->order('id', 'desc')->find();
        if (!$styleRow) {
            return null;
        }
        $product = ProductModel::where('name', $code)->where('status', 1)->find();
        if (!$product) {
            $product = ProductModel::whereLike('name', '%' . $code . '%')->where('status', 1)->order('id', 'desc')->find();
        }

        return [
            'product_code' => $code,
            'image_ref' => (string) ($styleRow->image_ref ?? ''),
            'hot_type' => (string) ($styleRow->hot_type ?? ''),
            'similarity' => 1.0,
            'match_reason' => '豆包自动识别',
            'product' => $product ? [
                'id' => (int) $product->id,
                'name' => (string) ($product->name ?? ''),
                'status' => (int) ($product->status ?? 0),
                'status_text' => ((int) ($product->status ?? 0)) === 1 ? '上架' : '停用',
                'goods_url' => (string) ($product->goods_url ?? ''),
            ] : null,
        ];
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
