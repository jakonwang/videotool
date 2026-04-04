<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\model\Product as ProductModel;
use app\model\ProductStyleItem as ItemModel;
use app\service\AliyunImageSearchConfig;
use app\service\AliyunImageSearchService;
use app\service\GoogleProductSearchConfig;
use app\service\GoogleProductSearchService;
use app\service\VisionOpenAIConfig;
use app\service\VisionSearchService;

/**
 * 以图搜款：已启用时优先 Google Product Search，其次 OpenAI Vision，再回退阿里云图像搜索
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

        if (GoogleProductSearchConfig::get()['enabled']) {
            return $this->searchByGoogleProductSearch($tmp);
        }

        $visionCfg = VisionOpenAIConfig::get();
        if ($visionCfg['enabled']) {
            return $this->searchByOpenAiVision($tmp, $visionCfg);
        }

        if (AliyunImageSearchConfig::get()['enabled']) {
            return $this->searchByAliyun($tmp);
        }

        return $this->jsonOut([
            'code' => 1,
            'msg' => '未配置寻款引擎：请在后台「设置」启用 Google Product Search，或填写 OpenAI Key，或启用阿里云图像搜索',
            'data' => null,
        ]);
    }

    private function searchByGoogleProductSearch(string $tmp)
    {
        $gr = GoogleProductSearchService::searchByImageFile($tmp);
        if (!($gr['ok'] ?? false)) {
            return $this->jsonOut([
                'code' => 1,
                'msg' => (string) ($gr['error'] ?? 'Google 检索失败'),
                'data' => ['engine' => 'google_ps'],
            ]);
        }

        $hits = $gr['items'] ?? [];
        $low = !empty($gr['low_confidence']);
        $bestScore = (float) ($gr['best_score'] ?? 0);

        $items = [];
        foreach ($hits as $h) {
            $code = (string) ($h['product_code'] ?? '');
            if ($code === '') {
                continue;
            }
            $row = ItemModel::where('status', 1)->where('product_code', $code)->order('id', 'desc')->find();
            $product = ProductModel::where('name', $code)->where('status', 1)->find();
            if (!$product) {
                $product = ProductModel::whereLike('name', '%' . $code . '%')->where('status', 1)->order('id', 'desc')->find();
            }
            $score = (float) ($h['score'] ?? 0);
            if ($score > 1) {
                $score = 1.0;
            }
            if ($score < 0) {
                $score = 0.0;
            }

            $items[] = [
                'product_code' => $code,
                'image_ref' => $row ? (string) ($row->image_ref ?? '') : '',
                'hot_type' => $row ? (string) ($row->hot_type ?? '') : '',
                'similarity' => round($score, 4),
                'score' => round($score, 4),
                'product' => $product ? [
                    'id' => (int) $product->id,
                    'name' => (string) ($product->name ?? ''),
                    'status' => (int) ($product->status ?? 0),
                    'status_text' => ((int) ($product->status ?? 0)) === 1 ? '上架' : '停用',
                    'goods_url' => (string) ($product->goods_url ?? ''),
                ] : null,
            ];
        }

        $msg = 'ok';
        if ($items === []) {
            $msg = '未检索到相似商品，请确认索引已导入且与 Product Set 一致';
        } elseif ($low) {
            $msg = '未找到完全匹配款式';
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => $msg,
            'data' => [
                'items' => $items,
                'engine' => 'google_ps',
                'num' => count($items),
                'best_score' => round($bestScore, 4),
                'low_confidence' => $low,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $visionCfg
     */
    private function searchByOpenAiVision(string $tmp, array $visionCfg)
    {
        $limit = (int) ($visionCfg['max_catalog_items'] ?? 250);
        $rows = ItemModel::where('status', 1)
            ->order('id', 'desc')
            ->limit($limit)
            ->select();

        $catalog = [];
        $allowed = [];
        foreach ($rows as $row) {
            $desc = trim((string) ($row->ai_description ?? ''));
            if ($desc === '') {
                continue;
            }
            $code = (string) ($row->product_code ?? '');
            if ($code === '') {
                continue;
            }
            $catalog[] = [
                'code' => $code,
                'desc' => $desc,
                'hot' => (string) ($row->hot_type ?? ''),
            ];
            $allowed[$code] = true;
        }

        if ($catalog === []) {
            return $this->jsonOut([
                'code' => 1,
                'msg' => '库内尚无 AI 视觉描述。请在后台设置 OpenAI Key 并重新导入表格（或开启「导入时生成描述」），或检查是否已执行数据库迁移 ai_description 字段。',
                'data' => ['engine' => 'openai_vision', 'catalog_size' => 0],
            ]);
        }

        $match = VisionSearchService::matchPhotoToCatalog($tmp, $catalog);
        if (!($match['ok'] ?? false)) {
            return $this->jsonOut([
                'code' => 1,
                'msg' => (string) ($match['error'] ?? '识别失败'),
                'data' => ['engine' => 'openai_vision'],
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
            return $this->jsonOut([
                'code' => 1,
                'msg' => '未从实拍图中匹配到库内编号，请靠近拍摄、保证耳环清晰，或检查库内特征是否与实物一致。',
                'data' => ['engine' => 'openai_vision', 'catalog_size' => count($catalog)],
            ]);
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'items' => $items,
                'engine' => 'openai_vision',
                'num' => count($items),
                'catalog_size' => count($catalog),
            ],
        ]);
    }

    private function searchByAliyun(string $tmp)
    {
        $as = AliyunImageSearchService::searchImageByPic($tmp);
        if (!($as['ok'] ?? false)) {
            $msg = (string) ($as['error'] ?? '搜索失败');
            $payload = [
                'engine' => 'aliyun_is',
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
