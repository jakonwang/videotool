<?php
declare(strict_types=1);

namespace app\service;

use app\model\ProductStyleItem as ItemModel;

/**
 * 寻款失败时的「特征关键词」回退：在编号 / AI 描述 / 爆款类型中模糊匹配。
 */
class ProductStyleKeywordSearchService
{
    public static function searchByHint(string $hint, int $limit = 15)
    {
        $hint = trim($hint);
        if ($hint === '' || mb_strlen($hint) > 200) {
            return new \think\Collection([]);
        }
        $like = '%' . addcslashes($hint, '%_\\') . '%';

        return ItemModel::where('status', 1)
            ->whereRaw('(product_code LIKE ? OR ai_description LIKE ? OR hot_type LIKE ?)', [$like, $like, $like])
            ->order('id', 'desc')
            ->limit($limit)
            ->select();
    }
}
