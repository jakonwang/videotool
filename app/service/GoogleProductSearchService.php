<?php
declare(strict_types=1);

namespace app\service;

use Google\ApiCore\ApiException;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Vision\V1\ProductSearchClient;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Product;
use Google\Cloud\Vision\V1\ProductSearchParams;
use Google\Cloud\Vision\V1\ReferenceImage;
use think\facade\Log;

/**
 * Vision API Product Search：索引（GCS 参考图 + createProduct + createReferenceImage + addProductToProductSet）与拍照检索。
 */
class GoogleProductSearchService
{
    /** 与 Google 约束一致：仅字母数字、下划线、连字符，最长 128，且不能含 / */
    public static function sanitizeProductId(string $code): string
    {
        $code = trim($code);
        $s = preg_replace('/[^A-Za-z0-9_-]/', '_', $code);
        $s = trim((string) $s, '_');
        if ($s === '') {
            $s = 'p' . substr(md5($code), 0, 24);
        }
        if (strlen($s) > 128) {
            $s = substr($s, 0, 128);
        }

        return $s;
    }

    /**
     * 导入一行：上传参考图到 GCS，创建/更新 Product、加入 ProductSet、创建 ReferenceImage。
     *
     * @return array{ok:bool, error?:string, skipped?:bool}
     */
    public static function syncReferenceFromLocalFile(string $productCodeDisplay, string $localPath): array
    {
        $cfg = GoogleProductSearchConfig::get();
        if (!$cfg['enabled']) {
            return ['ok' => true, 'skipped' => true];
        }
        if (!is_file($localPath) || !is_readable($localPath)) {
            return ['ok' => false, 'error' => '参考图文件不可读'];
        }

        $pid = self::sanitizeProductId($productCodeDisplay);
        $keyPath = $cfg['key_file'];
        $bucketName = $cfg['gcs_bucket'];
        $prefix = $cfg['gcs_prefix'] !== '' ? $cfg['gcs_prefix'] . '/' : '';
        $objectName = $prefix . $pid . '/' . bin2hex(random_bytes(8)) . '.jpg';

        $storage = null;
        $psClient = null;

        try {
            $storage = new StorageClient(['credentials' => $keyPath]);
            $bucket = $storage->bucket($bucketName);
            $bucket->upload(fopen($localPath, 'rb'), ['name' => $objectName]);

            $gsUri = 'gs://' . $bucketName . '/' . $objectName;

            $psClient = new ProductSearchClient(['credentials' => $keyPath]);
            $locParent = ProductSearchClient::locationName($cfg['project_id'], $cfg['location']);
            $productName = ProductSearchClient::productName($cfg['project_id'], $cfg['location'], $pid);
            $setName = ProductSearchClient::productSetName($cfg['project_id'], $cfg['location'], $cfg['product_set_id']);

            $product = (new Product())
                ->setDisplayName(mb_substr($productCodeDisplay, 0, 4096))
                ->setProductCategory($cfg['product_category']);

            try {
                $psClient->createProduct($locParent, $product, ['productId' => $pid]);
            } catch (ApiException $e) {
                if ($e->getStatus() !== 'ALREADY_EXISTS' && $e->getCode() !== 6) {
                    throw $e;
                }
            }

            $psClient->addProductToProductSet($setName, $productName);

            $ref = (new ReferenceImage())->setUri($gsUri);
            $refId = 'ref_' . bin2hex(random_bytes(6));
            $psClient->createReferenceImage($productName, $ref, ['referenceImageId' => $refId]);
        } catch (\Throwable $e) {
            Log::error('google_ps syncReference: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            if ($psClient !== null) {
                $psClient->close();
            }
        }

        return ['ok' => true];
    }

    /**
     * @return array{
     *   ok:bool,
     *   error?:string,
     *   items?: list<array{product_code:string, score:float, google_product_id:string}>,
     *   best_score?: float,
     *   low_confidence?: bool
     * }
     */
    public static function searchByImageFile(string $localPath): array
    {
        $cfg = GoogleProductSearchConfig::get();
        if (!$cfg['enabled']) {
            return ['ok' => false, 'error' => '未启用 Google Product Search'];
        }
        if (!is_file($localPath) || !is_readable($localPath)) {
            return ['ok' => false, 'error' => '查询图不可读'];
        }

        $keyPath = $cfg['key_file'];
        $annotator = null;

        try {
            $bytes = file_get_contents($localPath);
            if ($bytes === false || $bytes === '') {
                return ['ok' => false, 'error' => '无法读取查询图'];
            }

            $setName = ProductSearchClient::productSetName(
                $cfg['project_id'],
                $cfg['location'],
                $cfg['product_set_id']
            );

            $params = (new ProductSearchParams())
                ->setProductSet($setName)
                ->setProductCategories([$cfg['product_category']]);

            $annotator = new ImageAnnotatorClient(['credentials' => $keyPath]);
            $image = $annotator->createImageObject($bytes);
            $response = $annotator->productSearch($image, $params);

            if ($response->hasError()) {
                $err = $response->getError();

                return ['ok' => false, 'error' => $err ? $err->getMessage() : 'Vision 返回错误'];
            }

            $psr = $response->getProductSearchResults();
            if (!$psr) {
                return [
                    'ok' => true,
                    'items' => [],
                    'best_score' => 0.0,
                    'low_confidence' => true,
                ];
            }

            $raw = [];
            foreach ($psr->getResults() as $r) {
                $p = $r->getProduct();
                if (!$p) {
                    continue;
                }
                $display = trim((string) $p->getDisplayName());
                if ($display === '') {
                    $display = self::productIdFromResourceName((string) $p->getName());
                }
                if ($display === '') {
                    continue;
                }
                $raw[] = [
                    'product_code' => $display,
                    'score' => $r->getScore(),
                    'google_product_id' => self::productIdFromResourceName((string) $p->getName()),
                ];
            }

            usort($raw, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
            $topK = $cfg['search_top_k'];
            $raw = array_slice($raw, 0, $topK);

            $best = $raw[0]['score'] ?? 0.0;
            $low = $best < $cfg['match_score_min'];

            return [
                'ok' => true,
                'items' => $raw,
                'best_score' => $best,
                'low_confidence' => $low,
            ];
        } catch (\Throwable $e) {
            Log::error('google_ps search: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            if ($annotator !== null) {
                $annotator->close();
            }
        }
    }

    private static function productIdFromResourceName(string $name): string
    {
        if (preg_match('#/products/([^/]+)$#', $name, $m)) {
            return $m[1];
        }

        return '';
    }
}
