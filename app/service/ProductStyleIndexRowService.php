<?php
declare(strict_types=1);

namespace app\service;

use app\model\ProductStyleItem as ItemModel;
use think\facade\Db;
use think\facade\Log;

/**
 * 寻款索引单行写入；`syncGoogleProductSearchIndex` 保留供运维/脚本调用，导入任务已不触发。
 */
class ProductStyleIndexRowService
{
    /**
     * @return array{inserted:bool, updated:bool}
     */
    public static function upsertStyleItem(string $code, string $imageRef, string $hotType, array $embeddingVec, ?string $aiDescription = null): array
    {
        $code = trim($code);
        $embJson = json_encode($embeddingVec, JSON_UNESCAPED_UNICODE);
        $row = ItemModel::where('product_code', $code)->find();
        if ($row) {
            $data = [
                'image_ref' => $imageRef,
                'hot_type' => $hotType,
                'embedding' => $embJson,
                'status' => 1,
            ];
            if ($aiDescription !== null && $aiDescription !== '') {
                $data['ai_description'] = $aiDescription;
            }
            $row->save($data);

            return ['inserted' => false, 'updated' => true];
        }

        ItemModel::create([
            'product_code' => $code,
            'image_ref' => $imageRef,
            'hot_type' => $hotType,
            'ai_description' => $aiDescription ?? '',
            'embedding' => $embJson,
            'status' => 1,
        ]);

        return ['inserted' => true, 'updated' => false];
    }

    public static function syncProductAiDescription(string $productCode, ?string $desc): void
    {
        if ($desc === null || $desc === '') {
            return;
        }
        try {
            Db::name('products')->where('name', $productCode)->update(['ai_description' => $desc]);
        } catch (\Throwable $e) {
            Log::warning('syncProductAiDescription: ' . $e->getMessage());
        }
    }

    /**
     * @return array{action:'skip'|'synced'|'failed', error?:string}
     */
    public static function syncGoogleProductSearchIndex(string $productCode, string $localPath): array
    {
        if (!GoogleProductSearchConfig::get()['enabled']) {
            return ['action' => 'skip'];
        }
        $r = GoogleProductSearchService::syncReferenceFromLocalFile($productCode, $localPath);
        if (!empty($r['skipped'])) {
            return ['action' => 'skip'];
        }
        if ($r['ok'] ?? false) {
            return ['action' => 'synced'];
        }

        return ['action' => 'failed', 'error' => (string) ($r['error'] ?? '同步失败')];
    }
}
