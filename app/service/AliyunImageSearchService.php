<?php
declare(strict_types=1);

namespace app\service;

use AlibabaCloud\Dara\Exception\DaraUnableRetryException;
use AlibabaCloud\Dara\Models\RuntimeOptions;
use AlibabaCloud\SDK\ImageSearch\V20201214\ImageSearch;
use AlibabaCloud\SDK\ImageSearch\V20201214\Models\AddImageAdvanceRequest;
use AlibabaCloud\SDK\ImageSearch\V20201214\Models\SearchImageByPicAdvanceRequest;
use Darabonba\OpenApi\Exceptions\ClientException;
use Darabonba\OpenApi\Models\Config;
use GuzzleHttp\Psr7\Stream;
use think\facade\Log;

/**
 * 阿里云图像搜索（商品实例）封装：AddImage / SearchImageByPic
 */
class AliyunImageSearchService
{
    /**
     * @return array{ok:bool, error?:string, throttle?:bool, request_id?:string}
     */
    public static function addImageFromPath(
        string $productId,
        string $picName,
        string $absolutePath,
        string $customContent,
        int $categoryId
    ): array {
        $cfg = AliyunImageSearchConfig::get();
        if (!$cfg['enabled']) {
            return ['ok' => false, 'error' => '阿里云图像搜索未启用或未配置完整'];
        }
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return ['ok' => false, 'error' => '图片文件不可读'];
        }

        $client = self::createClient($cfg);
        if ($client === null) {
            return ['ok' => false, 'error' => '无法初始化图像搜索客户端'];
        }

        $productId = self::clip($productId, 256);
        $picName = self::clip($picName, 256);
        $customContent = self::clip($customContent, 4096);

        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return ['ok' => false, 'error' => '无法打开图片文件'];
        }
        $stream = new Stream($handle);

        $req = new AddImageAdvanceRequest();
        $req->instanceName = $cfg['instance_name'];
        $req->productId = $productId;
        $req->picName = $picName;
        $req->categoryId = $categoryId;
        $req->customContent = $customContent;
        $req->crop = $cfg['crop'];
        $req->picContentObject = $stream;

        $runtime = self::runtime($cfg);

        try {
            $resp = $client->addImageAdvance($req, $runtime);
            $body = $resp->body ?? null;
            if ($body !== null && ((isset($body->success) && $body->success) || (isset($body->code) && (int) $body->code === 0))) {
                return ['ok' => true, 'request_id' => (string) ($body->requestId ?? '')];
            }
            $msg = $body !== null ? (string) ($body->message ?? 'unknown') : 'empty body';

            return ['ok' => false, 'error' => $msg, 'request_id' => $body !== null ? (string) ($body->requestId ?? '') : ''];
        } catch (DaraUnableRetryException $e) {
            return self::mapTransportError($e);
        } catch (ClientException $e) {
            return self::mapClientException($e);
        } catch (\Throwable $e) {
            Log::error('aliyun_is addImage: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{
     *   ok:bool,
     *   items?: list<array{product_id:string,pic_name:string,score:float,custom_content:string}>,
     *   error?:string,
     *   throttle?:bool,
     *   timeout?:bool,
     *   subject_hint?:string,
     *   request_id?:string,
     *   raw_msg?:string
     * }
     */
    public static function searchImageByPic(string $absolutePath): array
    {
        $cfg = AliyunImageSearchConfig::get();
        if (!$cfg['enabled']) {
            return ['ok' => false, 'error' => '阿里云图像搜索未启用，请在后台设置中填写并开启'];
        }
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return ['ok' => false, 'error' => '无法读取上传图片'];
        }

        $client = self::createClient($cfg);
        if ($client === null) {
            return ['ok' => false, 'error' => '无法初始化图像搜索客户端'];
        }

        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return ['ok' => false, 'error' => '无法打开查询图片'];
        }
        $stream = new Stream($handle);

        $req = new SearchImageByPicAdvanceRequest();
        $req->instanceName = $cfg['instance_name'];
        $req->picContentObject = $stream;
        $req->categoryId = $cfg['category_id'];
        $req->crop = $cfg['crop'];
        $req->num = $cfg['search_num'];
        $req->distinctProductId = $cfg['distinct_product_id'];

        $runtime = self::runtime($cfg);

        try {
            $resp = $client->searchImageByPicAdvance($req, $runtime);
            $body = $resp->body ?? null;
            if ($body === null) {
                return ['ok' => false, 'error' => '空响应'];
            }

            $requestId = (string) ($body->requestId ?? '');
            $rawMsg = (string) ($body->msg ?? '');

            $bizOk = (isset($body->success) && $body->success) || (isset($body->code) && (int) $body->code === 0);
            if (!$bizOk) {
                $hint = self::subjectFailureHint($body);
                if ($hint !== '') {
                    return [
                        'ok' => false,
                        'error' => $hint,
                        'subject_hint' => $hint,
                        'raw_msg' => $rawMsg,
                        'request_id' => $requestId,
                    ];
                }

                return [
                    'ok' => false,
                    'error' => $rawMsg !== '' ? $rawMsg : '搜索失败',
                    'raw_msg' => $rawMsg,
                    'request_id' => $requestId,
                ];
            }

            $auctions = $body->auctions ?? null;
            if (!is_array($auctions) || $auctions === []) {
                $hint = '未识别到清晰主体或未找到相似款，请靠近拍摄、保持光线充足并正对饰品';
                if ($cfg['crop']) {
                    return [
                        'ok' => false,
                        'error' => $hint,
                        'subject_hint' => $hint,
                        'request_id' => $requestId,
                    ];
                }

                return ['ok' => false, 'error' => $hint, 'request_id' => $requestId];
            }

            $items = [];
            foreach ($auctions as $a) {
                if ($a === null) {
                    continue;
                }
                $pid = (string) ($a->productId ?? '');
                if ($pid === '') {
                    continue;
                }
                $items[] = [
                    'product_id' => $pid,
                    'pic_name' => (string) ($a->picName ?? ''),
                    'score' => (float) ($a->score ?? 0),
                    'custom_content' => (string) ($a->customContent ?? ''),
                ];
            }

            return ['ok' => true, 'items' => $items, 'request_id' => $requestId];
        } catch (DaraUnableRetryException $e) {
            return self::mapTransportError($e);
        } catch (ClientException $e) {
            return self::mapClientException($e);
        } catch (\Throwable $e) {
            Log::error('aliyun_is searchByPic: ' . $e->getMessage());
            $msg = $e->getMessage();
            if (stripos($msg, 'timed out') !== false || stripos($msg, 'timeout') !== false) {
                return ['ok' => false, 'error' => '请求超时，请稍后重试', 'timeout' => true];
            }

            return ['ok' => false, 'error' => $msg];
        }
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function createClient(array $cfg): ?ImageSearch
    {
        try {
            $c = new Config([]);
            $c->accessKeyId = $cfg['access_key_id'];
            $c->accessKeySecret = $cfg['access_key_secret'];
            $c->endpoint = $cfg['endpoint'];
            $c->regionId = $cfg['region_id'];

            return new ImageSearch($c);
        } catch (\Throwable $e) {
            Log::error('aliyun_is client: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function runtime(array $cfg): RuntimeOptions
    {
        $ro = new RuntimeOptions([]);
        $ro->connectTimeout = $cfg['connect_timeout_ms'];
        $ro->readTimeout = $cfg['read_timeout_ms'];
        $ro->maxIdleConns = 3;

        return $ro;
    }

    private static function clip(string $s, int $maxBytes): string
    {
        if (strlen($s) <= $maxBytes) {
            return $s;
        }

        return substr($s, 0, $maxBytes);
    }

    /**
     * @return array{ok:false, error:string, throttle?:bool, timeout?:bool}
     */
    private static function mapTransportError(DaraUnableRetryException $e): array
    {
        $inner = $e->getLastException();
        if ($inner === null && method_exists($e, 'getPrevious')) {
            $inner = $e->getPrevious();
        }
        $msg = $inner !== null ? $inner->getMessage() : $e->getMessage();
        $throttle = stripos($msg, 'Throttl') !== false
            || stripos($msg, 'QPS') !== false
            || stripos($msg, '限流') !== false;
        $timeout = stripos($msg, 'timed out') !== false || stripos($msg, 'timeout') !== false;

        Log::warning('aliyun_is transport: ' . $msg);

        if ($throttle) {
            return ['ok' => false, 'error' => '阿里云接口繁忙（限流），请稍后重试', 'throttle' => true];
        }
        if ($timeout) {
            return ['ok' => false, 'error' => '网络超时，请稍后重试', 'timeout' => true];
        }

        return ['ok' => false, 'error' => $msg];
    }

    /**
     * @return array{ok:false, error:string, throttle?:bool}
     */
    private static function mapClientException(ClientException $e): array
    {
        $msg = $e->getMessage();
        $throttle = stripos($msg, 'Throttl') !== false || stripos($msg, 'QPS') !== false;

        Log::warning('aliyun_is client: ' . $msg);

        if ($throttle) {
            return ['ok' => false, 'error' => '阿里云接口繁忙（限流），请稍后重试', 'throttle' => true];
        }

        return ['ok' => false, 'error' => $msg];
    }

    private static function subjectFailureHint(object $body): string
    {
        $code = isset($body->code) ? (int) $body->code : 0;
        $msg = strtolower((string) ($body->msg ?? ''));
        if ($code !== 0 && (str_contains($msg, 'region') || str_contains($msg, '主体') || str_contains($msg, 'crop'))) {
            return '图片主体识别失败，请靠近饰品、减少背景杂物并正对拍摄';
        }

        return '';
    }
}
