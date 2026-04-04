<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\model\ProductStyleItem as ItemModel;
use app\service\ProductStyleEmbeddingService;
use think\facade\Config;

/**
 * 图片搜款式（开放接口，供 H5/移动端）
 */
class ProductSearch extends BaseController
{
    private function jsonOut(array $payload, int $httpCode = 200)
    {
        return json($payload, $httpCode, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
    }

    /**
     * POST multipart: file 字段为查询图
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

        $qvec = ProductStyleEmbeddingService::embedFile($tmp);
        if (!is_array($qvec)) {
            return $this->jsonOut(['code' => 1, 'msg' => '特征提取失败，请确认服务器已安装 Python+torch 并完成索引导入', 'data' => null]);
        }

        $topK = (int) (Config::get('product_search.top_k') ?: 3);
        if ($topK < 1) {
            $topK = 3;
        }
        if ($topK > 20) {
            $topK = 20;
        }

        $rows = ItemModel::where('status', 1)->select();
        $scored = [];
        foreach ($rows as $row) {
            $embJson = (string) ($row->embedding ?? '');
            if ($embJson === '' || $embJson[0] !== '[') {
                continue;
            }
            $emb = json_decode($embJson, true);
            if (!is_array($emb) || !isset($emb[0])) {
                continue;
            }
            $sim = ProductStyleEmbeddingService::cosineSimilarity($qvec, array_map('floatval', $emb));
            $scored[] = [
                'score' => $sim,
                'product_code' => (string) ($row->product_code ?? ''),
                'image_ref' => (string) ($row->image_ref ?? ''),
                'hot_type' => (string) ($row->hot_type ?? ''),
                'id' => (int) $row->id,
            ];
        }
        usort($scored, static function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $bestByCode = [];
        foreach ($scored as $item) {
            $c = $item['product_code'];
            if (!isset($bestByCode[$c]) || $item['score'] > $bestByCode[$c]['score']) {
                $bestByCode[$c] = $item;
            }
        }
        $dedup = array_values($bestByCode);
        usort($dedup, static function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $items = array_slice($dedup, 0, $topK);
        $out = [];
        foreach ($items as $it) {
            $out[] = [
                'product_code' => $it['product_code'],
                'image_ref' => $it['image_ref'],
                'hot_type' => $it['hot_type'],
                'similarity' => round($it['score'], 4),
            ];
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => ['items' => $out],
        ]);
    }

    /**
     * GET q= 编号模糊查（无向量）
     */
    public function searchByCode()
    {
        $q = trim((string) $this->request->param('q', ''));
        if ($q === '') {
            return $this->jsonOut(['code' => 1, 'msg' => '缺少 q', 'data' => null]);
        }
        $limit = (int) $this->request->param('limit', 10);
        if ($limit < 1) {
            $limit = 10;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $list = ItemModel::where('status', 1)
            ->whereLike('product_code', '%' . $q . '%')
            ->order('id', 'desc')
            ->limit($limit)
            ->select();

        $items = [];
        foreach ($list as $row) {
            $items[] = [
                'product_code' => (string) ($row->product_code ?? ''),
                'image_ref' => (string) ($row->image_ref ?? ''),
                'hot_type' => (string) ($row->hot_type ?? ''),
            ];
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => ['items' => $items],
        ]);
    }
}
