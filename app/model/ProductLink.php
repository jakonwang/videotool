<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 达人分发链接
 */
class ProductLink extends Model
{
    protected $name = 'product_links';

    protected $schema = [
        'id'         => 'int',
        'product_id' => 'int',
        'token'      => 'string',
        'label'      => 'string',
        'status'     => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * 根据 token 取有效链接（启用且商品启用）
     */
    public static function findActiveByToken(string $token): ?self
    {
        $link = self::with(['product'])->where('token', $token)->where('status', 1)->find();
        if (!$link || !$link->product || (int) $link->product->status !== 1) {
            return null;
        }
        return $link;
    }
}
