<?php
declare (strict_types = 1);

namespace app\controller\api;

use app\BaseController;
use app\model\Video as VideoModel;
use app\model\Device;
use app\model\Platform;
use app\service\QiniuService;

/**
 * 视频API
 */
class Video extends BaseController
{
    /**
     * 获取客户端IP
     */
    private function getClientIP()
    {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        return $ip;
    }
    
    /**
     * 生成规范的下载文件名
     */
    private function generateDownloadFileName(string $title, string $type): string
    {
        $ext = $type === 'cover' ? '.jpg' : '.mp4';
        $sanitized = trim(preg_replace('/[^\w\s-]/u', '', $title));
        if ($sanitized === '') {
            $sanitized = 'videotool_' . time();
        }
        return $sanitized . $ext;
    }
    
    /**
     * 判断URL是否属于配置的CDN域
     */
    private function isCdnUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        
        $cdnDomains = [];
        $qiniuConfig = \think\facade\Config::get('qiniu');
        if (!empty($qiniuConfig['domain'])) {
            $cdnDomains[] = parse_url($qiniuConfig['domain'], PHP_URL_HOST) ?: $qiniuConfig['domain'];
        }
        if (!empty($qiniuConfig['cdn_domains'])) {
            $extraDomains = is_array($qiniuConfig['cdn_domains'])
                ? $qiniuConfig['cdn_domains']
                : preg_split('/[,;\s]+/', (string) $qiniuConfig['cdn_domains'], -1, PREG_SPLIT_NO_EMPTY);
            foreach ($extraDomains as $domain) {
                $cdnDomains[] = parse_url($domain, PHP_URL_HOST) ?: $domain;
            }
        }
        $cdnDomains = array_filter(array_unique($cdnDomains));
        if (empty($cdnDomains)) {
            return false;
        }
        
        foreach ($cdnDomains as $cdnHost) {
            if ($cdnHost && stripos($host, $cdnHost) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 为CDN直链追加下载文件名，便于浏览器直接保存
     */
    private function buildDirectDownloadUrl(string $url, string $fileName): string
    {
        $parsed = parse_url($url);
        if (!$parsed) {
            return $url;
        }
        
        $query = $parsed['query'] ?? '';
        parse_str($query, $params);
        $params['attname'] = $fileName;
        $newQuery = http_build_query($params);
        
        $rebuilt = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        if (!empty($parsed['port'])) {
            $rebuilt .= ':' . $parsed['port'];
        }
        $rebuilt .= $parsed['path'] ?? '';
        if ($newQuery !== '') {
            $rebuilt .= '?' . $newQuery;
        }
        if (!empty($parsed['fragment'])) {
            $rebuilt .= '#' . $parsed['fragment'];
        }
        return $rebuilt;
    }
    
    /**
     * 检测是否为APP客户端请求
     * 注意：要排除浏览器（浏览器User-Agent可能包含Android等关键词）
     */
    private function isAppClient(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // 先排除浏览器（浏览器优先判断）
        $browserPatterns = [
            '/Chrome/i',
            '/Firefox/i',
            '/Safari/i',
            '/Edge/i',
            '/Opera/i',
            '/MSIE/i',
            '/Trident/i',
            '/Mobile Safari/i',
            '/Version/i',  // iOS Safari通常包含Version
        ];
        
        // 如果包含浏览器标识，且没有明确的APP标识，则认为是浏览器
        $hasBrowser = false;
        foreach ($browserPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $hasBrowser = true;
                break;
            }
        }
        
        // 如果检测到浏览器标识，且没有明确的APP标识，则不是APP
        if ($hasBrowser) {
            // 检查是否有明确的APP标识
            $appSpecificPatterns = [
                '/okhttp/i',           // Android OkHttp库（明确的APP库）
                '/AFNetworking/i',     // iOS AFNetworking库（明确的APP库）
                '/VideoToolApp/i',     // 自定义APP标识
            ];
            
            $hasAppSpecific = false;
            foreach ($appSpecificPatterns as $pattern) {
                if (preg_match($pattern, $userAgent)) {
                    $hasAppSpecific = true;
                    break;
                }
            }
            
            // 有浏览器标识但没有APP特定标识，则认为是浏览器
            if (!$hasAppSpecific) {
                return false;
            }
        }
        
        // 检查明确的APP标识
        $appPatterns = [
            '/okhttp/i',           // Android OkHttp库
            '/AFNetworking/i',     // iOS AFNetworking库
            '/VideoToolApp/i',     // 自定义APP标识
        ];
        
        foreach ($appPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        // 通过自定义Header判断（最可靠的方式）
        $appHeader = $_SERVER['HTTP_X_APP_CLIENT'] ?? '';
        if (!empty($appHeader) && strtolower($appHeader) === 'true') {
            return true;
        }
        
        // 默认不是APP（避免误判浏览器）
        return false;
    }
    
    /**
     * 获取视频（根据IP和平台）
     */
    public function getVideo()
    {
        try {
            $ip = $this->getClientIP();
            $platformCode = $this->request->param('platform', 'tiktok');
            
            // 获取平台
            $platform = Platform::where('code', $platformCode)->find();
            if (!$platform) {
                return json(['code' => 1, 'msg' => '平台不存在，请先在后台添加平台'], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            // 获取或创建设备
            $device = Device::getOrCreate($ip, $platform->id);
            
            // 获取未下载的视频
            $video = VideoModel::getUndownloaded($device->id);
            
            if (!$video) {
                return json(['code' => 1, 'msg' => '暂无可用视频，请先在后台上传视频'], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            // 确保URL是绝对路径
            $coverUrl = $video->cover_url;
            $videoUrl = $video->video_url;
            
            // 如果没有封面URL，使用视频URL作为默认封面（视频第一帧）
            if (empty($coverUrl)) {
                $coverUrl = $videoUrl;
            }
            
            // 如果是相对路径，转换为绝对路径
            if ($coverUrl && !preg_match('/^https?:\/\//', $coverUrl)) {
                $coverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                           '://' . $_SERVER['HTTP_HOST'] . 
                           (strpos($coverUrl, '/') === 0 ? '' : '/') . $coverUrl;
            }
            
            if ($videoUrl && !preg_match('/^https?:\/\//', $videoUrl)) {
                $videoUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                           '://' . $_SERVER['HTTP_HOST'] . 
                           (strpos($videoUrl, '/') === 0 ? '' : '/') . $videoUrl;
            }
            
            return json([
                'code' => 0,
                'data' => [
                    'id' => $video->id,
                    'title' => $video->title,
                    'cover_url' => $coverUrl,
                    'video_url' => $videoUrl,
                ]
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        } catch (\Exception $e) {
            // 记录错误日志
            \think\facade\Log::error('API错误: ' . $e->getMessage());
            
            return json([
                'code' => 1,
                'msg' => '服务器错误：' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
    }
    
    /**
     * 获取平台列表
     */
    public function getPlatforms()
    {
        try {
            $platforms = Platform::where('status', 1)
                ->order('id', 'desc')
                ->field('id,name,code,icon')
                ->select();
            
            return json([
                'code' => 0,
                'data' => $platforms
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        } catch (\Exception $e) {
            return json([
                'code' => 1,
                'msg' => '服务器错误：' . $e->getMessage()
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
    }
    
    /**
     * 标记视频为已下载
     */
    public function markDownloaded()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $videoId = $input['video_id'] ?? $this->request->post('video_id');
            
            if (!$videoId) {
                return json(['code' => 1, 'msg' => '参数错误'], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            $video = VideoModel::find($videoId);
            if (!$video) {
                return json(['code' => 1, 'msg' => '视频不存在'], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            // 标记为已下载
            $video->is_downloaded = 1;
            $video->save();
            
            return json([
                'code' => 0,
                'msg' => '标记成功'
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        } catch (\Exception $e) {
            return json([
                'code' => 1,
                'msg' => '服务器错误：' . $e->getMessage()
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
    }
    
    /**
     * 获取七牛云上传Token（用于前端直传）
     */
    public function getQiniuUploadToken()
    {
        try {
            $qiniuService = new QiniuService();
            if (!$qiniuService->isEnabled()) {
                return json([
                    'code' => 1,
                    'msg' => '七牛云未配置或未启用'
                ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            $result = $qiniuService->generateUploadToken();
            
            if (empty($result['token'])) {
                return json([
                    'code' => 1,
                    'msg' => $result['msg'] ?? '获取上传Token失败'
                ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            return json([
                'code' => 0,
                'msg' => '获取成功',
                'data' => [
                    'token' => $result['token'],
                    'domain' => $result['domain'],
                    'bucket' => $result['bucket'],
                    'uploadUrl' => $result['uploadUrl'],
                    'region' => $result['region'] ?? 'z2'
                ]
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取七牛云上传Token错误: ' . $e->getMessage());
            return json([
                'code' => 1,
                'msg' => '服务器错误：' . $e->getMessage()
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
    }
    
    /**
     * 保存上传成功的文件信息（前端直传成功后调用）
     */
    public function saveUploadedFile()
    {
        try {
            $data = $this->request->post();
            
            // 验证必填字段
            if (empty($data['platform_id']) || empty($data['device_id']) || empty($data['video_url'])) {
                return json([
                    'code' => 1,
                    'msg' => '参数不完整：缺少平台ID、设备ID或视频URL'
                ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            // 创建视频记录
            $video = VideoModel::create([
                'platform_id' => (int)$data['platform_id'],
                'device_id' => (int)$data['device_id'],
                'title' => $data['title'] ?? '视频标题',
                'cover_url' => $data['cover_url'] ?? $data['video_url'],
                'video_url' => $data['video_url']
            ]);
            
            return json([
                'code' => 0,
                'msg' => '保存成功',
                'data' => [
                    'id' => $video->id,
                    'video_url' => $video->video_url,
                    'cover_url' => $video->cover_url
                ]
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        } catch (\Exception $e) {
            \think\facade\Log::error('保存上传文件信息错误: ' . $e->getMessage());
            return json([
                'code' => 1,
                'msg' => '保存失败：' . $e->getMessage()
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
    }
    
    /**
     * 代理下载文件（支持流式传输，解决跨域和大文件下载问题）
     * 支持APP调用：通过format=json参数返回JSON格式的下载URL
     */
    public function downloadProxy()
    {
        // 防止脚本超时导致意外中断
        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $videoId = $this->request->param('video_id');
            $type = $this->request->param('type', 'video'); // cover 或 video
            $format = $this->request->param('format', ''); // json 或空（流式下载/重定向）
            
            // 检测是否为APP请求（通过参数或User-Agent）
            // 优先检查format参数（最可靠），然后检查app参数，最后检查User-Agent
            // 注意：不要仅依赖User-Agent，因为可能误判浏览器为APP
            $isAppRequest = false;
            
            // 方式1：通过format参数明确指定（最可靠）
            if ($format === 'json') {
                $isAppRequest = true;
            }
            // 方式2：通过app参数指定
            elseif ($this->request->param('app', 0) == 1) {
                $isAppRequest = true;
            }
            // 方式3：通过User-Agent判断（保守策略，避免误判浏览器）
            elseif ($this->isAppClient()) {
                $isAppRequest = true;
            }
            
            // 默认：浏览器请求，返回文件流
            
            if (!$videoId) {
                if ($isAppRequest) {
                    return json([
                        'code' => 1,
                        'msg' => '参数错误：缺少视频ID'
                    ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
                } else {
                    // 浏览器请求，可能需要显示错误页面
                    return json([
                        'code' => 1,
                        'msg' => '参数错误：缺少视频ID'
                    ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
                }
            }
            
            // 获取视频信息
            $video = VideoModel::find($videoId);
            if (!$video) {
                if ($isAppRequest) {
                    return json([
                        'code' => 1,
                        'msg' => '视频不存在'
                    ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
                }
                return json([
                    'code' => 1,
                    'msg' => '视频不存在'
                ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            // 获取文件URL
            $fileUrl = $type === 'cover' ? $video->cover_url : $video->video_url;
            if (empty($fileUrl)) {
                if ($isAppRequest) {
                    return json([
                        'code' => 1,
                        'msg' => '文件URL不存在'
                    ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
                }
                return json([
                    'code' => 1,
                    'msg' => '文件URL不存在'
                ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            // 确保URL是绝对路径
            if (!preg_match('/^https?:\/\//', $fileUrl)) {
                $fileUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                           '://' . $_SERVER['HTTP_HOST'] . 
                           (strpos($fileUrl, '/') === 0 ? '' : '/') . $fileUrl;
            }
            
            $downloadFileName = $this->generateDownloadFileName($video->title ?? 'VideoTool', $type);
            $isCdnResource = $this->isCdnUrl($fileUrl);
            
            // 判断是本地文件还是远程文件
            $parsedUrl = parse_url($fileUrl);
            $isLocalFile = isset($parsedUrl['host']) && 
                          ($parsedUrl['host'] === $_SERVER['HTTP_HOST'] || 
                           $parsedUrl['host'] === 'localhost' ||
                           $parsedUrl['host'] === '127.0.0.1');
            
            // 下载缓存上下文（仅针对远程文件）
            $cacheContext = null;
            $cacheWriteContext = null;
            if (!$isLocalFile) {
                $cacheContext = $this->buildCacheContext($fileUrl, $type, $isLocalFile);
                if ($cacheContext && empty($cacheContext['ready'])) {
                    $cacheWriteContext = $this->acquireCacheLock($cacheContext);
                }
            }
            
            // APP请求：返回JSON格式的下载信息
            if ($isAppRequest) {
                // 生成代理下载URL（APP可以使用这个URL直接下载）
                $proxyUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                           '://' . $_SERVER['HTTP_HOST'] . 
                           '/api/video/download?video_id=' . $videoId . '&type=' . $type;
                
                // 如果是七牛云资源且缓存不存在，先同步预缓存（确保100%成功）
                $cachePrepared = false;
                if ($cacheContext && empty($cacheContext['ready']) && $cacheWriteContext && $isCdnResource) {
                    \think\facade\Log::info("APP请求：开始同步预缓存七牛云资源 - {$fileUrl}");
                    try {
                        $cachePath = $this->downloadRemoteToCache($fileUrl, $cacheWriteContext, [
                            'video_id' => $video->id,
                            'platform' => $video->platform_id ?? null,
                            'title' => $video->title,
                            'type' => $type,
                            'file_name' => $downloadFileName,
                            'source_url' => $fileUrl,
                        ]);
                        
                        // 如果缓存成功，更新状态
                        if ($cachePath && file_exists($cachePath) && filesize($cachePath) > 0) {
                            $cacheContext['ready'] = true;
                            $cachePrepared = true;
                            \think\facade\Log::info("APP请求：七牛云资源预缓存成功 - {$fileUrl} -> {$cachePath} (" . filesize($cachePath) . " bytes)");
                        } else {
                            // 预缓存失败，清理可能的临时文件
                            if (isset($cacheContext['temp_path']) && file_exists($cacheContext['temp_path'])) {
                                @unlink($cacheContext['temp_path']);
                            }
                            \think\facade\Log::warning("APP请求：七牛云资源预缓存失败（文件不存在或为空），将使用流式代理 - {$fileUrl}");
                        }
                    } catch (\Exception $e) {
                        // 异常时也清理临时文件
                        if (isset($cacheContext['temp_path']) && file_exists($cacheContext['temp_path'])) {
                            @unlink($cacheContext['temp_path']);
                        }
                        \think\facade\Log::error("APP请求：七牛云资源预缓存异常 - {$fileUrl} - " . $e->getMessage() . " | 文件: " . $e->getFile() . " | 行号: " . $e->getLine());
                    } finally {
                        // 确保释放锁
                        $this->releaseCacheLock($cacheWriteContext);
                        $cacheWriteContext = null; // 标记已处理，避免后续重复处理
                    }
                }
                
                // 关键优化：fallback_url也使用代理URL，避免直接访问七牛云被拦截
                // 这样APP即使主链接失败，备用链接也是通过服务器代理，不会被防盗链拦截
                return json([
                    'code' => 0,
                    'msg' => ($cachePrepared ? '缓存已就绪' : ($cacheContext && empty($cacheContext['ready']) ? '缓存准备中...' : '获取成功')),
                    'data' => [
                        'video_id' => $video->id,
                        'type' => $type,
                        'file_url' => $fileUrl,
                        'direct_url' => $proxyUrl, // 主链接：站内代理URL（强制走代理）
                        'fallback_url' => $proxyUrl, // 备用链接：也使用代理URL（不使用七牛云直链，避免被拦截）
                        'file_name' => $downloadFileName,
                        'is_cdn' => $isCdnResource,
                        'cache_hit' => (bool)($cacheContext && !empty($cacheContext['ready'])),
                        'cache_prepared' => $cachePrepared, // 标识是否刚完成预缓存
                        'file_size' => null,
                    ]
                ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            if ($isLocalFile) {
                // 本地文件：直接读取并流式输出
                $localPath = $this->urlToLocalPath($fileUrl);
                if ($localPath && file_exists($localPath)) {
                    return $this->streamLocalFile($localPath, $type, $downloadFileName);
                }
            }

            $localCachePath = null;
            if ($cacheContext) {
                if (!empty($cacheContext['ready']) && file_exists($cacheContext['path'])) {
                    $localCachePath = $cacheContext['path'];
                } elseif ($cacheWriteContext) {
                    $localCachePath = $this->downloadRemoteToCache($fileUrl, $cacheWriteContext, [
                        'video_id' => $video->id,
                        'platform' => $video->platform_id ?? null,
                        'title' => $video->title,
                        'type' => $type,
                        'file_name' => $downloadFileName,
                        'source_url' => $fileUrl,
                    ]);
                    $this->releaseCacheLock($cacheWriteContext);
                    if (!$localCachePath && file_exists($cacheContext['path'])) {
                        $localCachePath = $cacheContext['path'];
                    }
                }
            }

            if ($localCachePath && file_exists($localCachePath)) {
                return $this->streamLocalFile($localCachePath, $type, $downloadFileName);
            }

            // 远程文件统一通过代理下载，可选写入缓存
            // 注意：即使预缓存失败（$cacheWriteContext为null），也要允许流式传输
            // 尝试获取缓存锁（如果之前没有获取过）
            $streamCacheContext = null;
            if ($cacheContext && !$cacheWriteContext && empty($cacheContext['ready'])) {
                // 尝试获取锁，如果失败也不影响流式传输（说明其他进程正在缓存）
                $streamCacheContext = $this->acquireCacheLock($cacheContext);
            }
            
            // 确保即使缓存不存在，也能正常流式传输
            return $this->streamRemoteFile($fileUrl, $type, $downloadFileName, [
                'cache' => $streamCacheContext,
                'cache_meta' => [
                    'hash' => $streamCacheContext['hash'] ?? ($cacheContext['hash'] ?? null),
                    'video_id' => $video->id,
                    'platform' => $video->platform_id ?? null,
                    'title' => $video->title,
                    'type' => $type,
                    'file_name' => $downloadFileName,
                    'source_url' => $fileUrl,
                ]
            ]);
            
        } catch (\Exception $e) {
            // 详细记录错误信息
            $errorInfo = [
                'video_id' => $videoId ?? null,
                'type' => $type ?? null,
                'file_url' => $fileUrl ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 500), // 限制长度
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $this->getClientIP(),
                'time' => date('Y-m-d H:i:s'),
            ];
            
            \think\facade\Log::error('代理下载错误: ' . json_encode($errorInfo, JSON_UNESCAPED_UNICODE));
            
            // 对APP请求返回更详细的错误信息（便于调试）
            $errorMsg = '下载失败：' . $e->getMessage();
            if ($isAppRequest && (strpos($e->getMessage(), 'curl') !== false || strpos($e->getMessage(), 'HTTP') !== false)) {
                $errorMsg .= ' (请检查七牛云文件是否可访问)';
            }
            
            return json([
                'code' => 1,
                'msg' => $errorMsg,
                'error_code' => 'DOWNLOAD_ERROR',
                'video_id' => $videoId ?? null,
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
    }
    
    /**
     * URL转换为本地路径
     */
    private function urlToLocalPath($url)
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['path'])) {
            return null;
        }
        
        $path = $parsedUrl['path'];
        
        // 移除开头的 /public 或 / 前缀
        $path = preg_replace('#^/public/#', '/', $path);
        $path = ltrim($path, '/');
        
        // 转换为绝对路径 - 使用public_path助手函数
        if (function_exists('public_path')) {
            $localPath = public_path($path);
        } else {
            $rootPath = root_path() . 'public' . DIRECTORY_SEPARATOR;
            $localPath = $rootPath . str_replace('/', DIRECTORY_SEPARATOR, $path);
        }
        
        return $localPath;
    }
    
    /**
     * 流式输出本地文件
     */
    private function streamLocalFile($filePath, $type, $fileNameHint)
    {
        if (!file_exists($filePath)) {
            return json(['code' => 1, 'msg' => '文件不存在'], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
        
        // 获取文件信息
        $fileSize = filesize($filePath);
        $fileName = basename($filePath);
        
        // 如果没有扩展名，根据类型添加
        if (pathinfo($fileName, PATHINFO_EXTENSION) === '') {
            $ext = $type === 'cover' ? '.jpg' : '.mp4';
            $sanitized = trim(preg_replace('/[^\w\s-]/u', '', $fileNameHint));
            if ($sanitized === '') {
                $sanitized = 'videotool_' . time();
            }
            $fileName = $sanitized . $ext;
        }
        
        // 设置响应头
        $mimeType = $type === 'cover' ? 'image/jpeg' : 'video/mp4';
        if ($type === 'cover' && function_exists('mime_content_type')) {
            $detectedMime = @mime_content_type($filePath);
            if ($detectedMime) {
                $mimeType = $detectedMime;
            }
        }
        
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
        header('Content-Length: ' . $fileSize); // 本地文件有大小，设置Content-Length
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // 支持断点续传
        $range = $_SERVER['HTTP_RANGE'] ?? null;
        if ($range) {
            $this->handleRangeRequest($filePath, $fileSize, $range);
            return response();
        }
        
        // 流式输出
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return json(['code' => 1, 'msg' => '无法打开文件'], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
        
        // 设置输出缓冲
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // 分块读取并输出 - 增大块大小提高速度
        $chunkSize = 65536; // 64KB chunks（比8KB快8倍）
        while (!feof($handle)) {
            echo fread($handle, $chunkSize);
            if (connection_status() != CONNECTION_NORMAL) {
                break;
            }
            // 使用ob_flush和flush组合，确保及时输出
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }
        
        fclose($handle);
        exit;
    }
    
    /**
     * 流式输出远程文件（代理下载）
     * 优化：增加更大的缓冲区，提高传输速度，强制浏览器下载
     */
    private function streamRemoteFile($url, $type, $fileName, array $options = [])
    {
        $mimeType = $type === 'cover' ? 'image/jpeg' : 'video/mp4';
        $cacheContext = $options['cache'] ?? null;
        $cacheMeta = $options['cache_meta'] ?? [];
        $cacheTempResource = null;
        $cacheConfig = $cacheContext['config'] ?? [];
        $minCacheSize = $cacheConfig['min_file_size'] ?? 0;
        $cacheWrittenBytes = 0;
        
        // 清除所有输出缓冲，立即开始传输（关键：在设置响应头之前清除）
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // 禁用输出缓冲，立即发送响应头
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 0);
        
        // 设置响应头 - 关键：使用attachment强制浏览器下载而不是打开
        // 重要：不设置Content-Length，使用Transfer-Encoding: chunked，立即开始下载
        // 这样浏览器不会等待"计算文件大小"，会立即开始下载
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Accept-Ranges: bytes');
        // 关键：不设置Content-Length，使用chunked传输
        // 注意：不要显式设置Transfer-Encoding: chunked，让PHP自动处理
        // 如果服务器支持chunked，PHP会自动使用；如果不支持，设置可能会出错
        // header('Transfer-Encoding: chunked'); // 不要显式设置，让PHP自动处理
        
        // 刷新输出缓冲区，立即发送响应头（但不关闭连接）
        flush();
        
        // 如果启用了缓存，准备临时文件
        if ($cacheContext) {
            $cacheDir = dirname($cacheContext['temp_path']);
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0775, true);
            }
            $cacheTempResource = @fopen($cacheContext['temp_path'], 'wb');
            if ($cacheTempResource === false) {
                $this->releaseCacheLock($cacheContext);
                $cacheContext = null;
                $cacheTempResource = null;
            }
        }

        // 先测试URL可访问性（不发送响应头），避免响应头已发送后才发现错误
        $testCh = curl_init($url);
        if ($testCh) {
            curl_setopt_array($testCh, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true, // 只获取头部
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_REFERER => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/',
                CURLOPT_USERAGENT => 'VideoTool-Server-Proxy/1.0 (PHP/' . PHP_VERSION . ')',
            ]);
            curl_exec($testCh);
            $testHttpCode = curl_getinfo($testCh, CURLINFO_HTTP_CODE);
            $testError = curl_error($testCh);
            curl_close($testCh);
            
            // 如果URL无法访问，直接返回错误（此时响应头还未发送）
            if ($testHttpCode >= 400 || !empty($testError)) {
                $this->releaseCacheLock($cacheContext);
                \think\facade\Log::error("流式传输：URL不可访问 - {$url} - HTTP {$testHttpCode} - {$testError}");
                return json([
                    'code' => 1,
                    'msg' => '下载失败：' . ($testError ?: 'HTTP ' . $testHttpCode),
                    'error_code' => 'URL_NOT_ACCESSIBLE',
                    'http_code' => $testHttpCode,
                ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
        }
        
        // 初始化cURL进行实际下载
        $ch = curl_init($url);
        
        if ($ch === false) {
            $this->releaseCacheLock($cacheContext);
            return json(['code' => 1, 'msg' => '无法初始化下载'], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
        
        // 优化：增大缓冲区大小，提高传输速度
        $bufferSize = 131072; // 128KB buffer（增大缓冲区，减少系统调用次数）
        
        // 设置cURL选项 - 关键：立即开始传输，不等待完整响应头，优化传输速度
        // 重要：使用正确的Referer和User-Agent，避免七牛云防盗链拦截
        $referer = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
        $userAgent = 'VideoTool-Server-Proxy/1.0 (PHP/' . PHP_VERSION . ')';
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10, // 增加重定向次数
            CURLOPT_CONNECTTIMEOUT => 30, // 连接超时30秒（增加超时时间）
            CURLOPT_TIMEOUT => 0, // 无总超时限制
            CURLOPT_BUFFERSIZE => $bufferSize, // 设置缓冲区大小（128KB）
            CURLOPT_REFERER => $referer,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '', // 自动处理压缩
            CURLOPT_TCP_NODELAY => 1, // 禁用Nagle算法，立即发送数据
            CURLOPT_TCP_KEEPALIVE => 1, // 启用TCP keepalive
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // 强制使用HTTP/1.1，避免HTTP/2问题
            // 关键：立即开始写入数据，不等待完整响应
            // 优化：立即输出并刷新，确保浏览器立即开始下载
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$cacheTempResource, &$cacheWrittenBytes) {
                // 立即输出数据
                echo $data;
                // 立即刷新输出，确保浏览器立即开始下载
                // 每64KB刷新一次，平衡性能和及时性
                static $buffer = '';
                static $flushSize = 65536; // 64KB刷新一次
                $buffer .= $data;
                if (strlen($buffer) >= $flushSize) {
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                    $buffer = '';
                }
                if ($cacheTempResource) {
                    $written = fwrite($cacheTempResource, $data);
                    if ($written !== false) {
                        $cacheWrittenBytes += $written;
                    }
                }
                return strlen($data);
            },
            // 简化响应头处理，只转发必要的头
            CURLOPT_HEADERFUNCTION => function($ch, $headerLine) {
                if (strpos($headerLine, ':') !== false) {
                    list($headerName, $headerValue) = explode(':', $headerLine, 2);
                    $headerName = strtolower(trim($headerName));
                    $headerValue = trim($headerValue);
                    
                    if ($headerName === 'content-length') {
                        header('Content-Length: ' . $headerValue, true);
                    }
                    
                    // 转发 Content-Type
                    if ($headerName === 'content-type') {
                        header('Content-Type: ' . $headerValue, true);
                    }
                }
                return strlen($headerLine);
            }
        ]);
        
        // 支持断点续传
        if (isset($_SERVER['HTTP_RANGE'])) {
            curl_setopt($ch, CURLOPT_RANGE, str_replace('bytes=', '', $_SERVER['HTTP_RANGE']));
            // 如果请求了Range，需要转发Range响应
            header('Accept-Ranges: bytes', false);
        }
        
        // 注意：输出缓冲已在方法开始处清除，这里不需要重复清除
        
        // 执行下载 - 立即开始流式传输，不等待Content-Length
        // 数据会立即通过CURLOPT_WRITEFUNCTION输出，浏览器立即开始下载
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // 最后刷新缓冲区，确保所有数据已发送
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        
        curl_close($ch);
        
        if ($cacheTempResource) {
            fclose($cacheTempResource);
        }
        $cacheSuccess = false;
        if ($cacheContext) {
            if ($result !== false && empty($error) && $httpCode < 400 && $cacheWrittenBytes > 0) {
                @rename($cacheContext['temp_path'], $cacheContext['path']);
                if ($minCacheSize > 0 && file_exists($cacheContext['path']) && filesize($cacheContext['path']) < $minCacheSize) {
                    @unlink($cacheContext['path']);
                } else {
                    $cacheSuccess = file_exists($cacheContext['path']);
                }
            } else {
                @unlink($cacheContext['temp_path']);
            }
            if ($cacheSuccess && !empty($cacheContext['meta_path'])) {
                $metaPayload = [
                    'hash' => $cacheContext['hash'] ?? null,
                    'file_name' => $cacheMeta['file_name'] ?? $fileName,
                    'type' => $cacheMeta['type'] ?? $type,
                    'source_url' => $cacheMeta['source_url'] ?? $url,
                    'video_id' => $cacheMeta['video_id'] ?? null,
                    'platform' => $cacheMeta['platform'] ?? null,
                    'title' => $cacheMeta['title'] ?? null,
                    'size' => @filesize($cacheContext['path']) ?: $cacheWrittenBytes,
                    'cached_at' => time(),
                ];
                @file_put_contents(
                    $cacheContext['meta_path'],
                    json_encode($metaPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );
            }
        }
        $this->releaseCacheLock($cacheContext);
        
        // 检查cURL执行结果
        // 注意：此时响应头已发送，不能返回JSON，只能记录日志
        if ($result === false || !empty($error)) {
            $this->releaseCacheLock($cacheContext);
            \think\facade\Log::error("流式传输失败：{$url} - {$error} - HTTP {$httpCode}");
            // 响应头已发送，不能返回JSON，只能输出错误信息并退出
            echo "\n<!-- 下载失败: " . htmlspecialchars($error ?: 'HTTP ' . $httpCode) . " -->\n";
            exit;
        }
        
        if ($httpCode >= 400) {
            $this->releaseCacheLock($cacheContext);
            \think\facade\Log::error("流式传输失败：{$url} - HTTP {$httpCode}");
            // 响应头已发送，不能返回JSON，只能输出错误信息并退出
            echo "\n<!-- 下载失败: HTTP {$httpCode} -->\n";
            exit;
        }
        
        // 下载成功，正常退出
        exit;
    }
    
    /**
     * 处理断点续传请求
     */
    private function handleRangeRequest($filePath, $fileSize, $range)
    {
        // 解析Range头
        if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
            $start = intval($matches[1]);
            $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;
            
            if ($start > $end || $start >= $fileSize || $end >= $fileSize) {
                http_response_code(416); // Range Not Satisfiable
                header('Content-Range: bytes */' . $fileSize);
                exit;
            }
            
            $length = $end - $start + 1;
            
            http_response_code(206); // Partial Content
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
            header('Content-Length: ' . $length);
            header('Accept-Ranges: bytes');
            
            $handle = fopen($filePath, 'rb');
            if ($handle === false) {
                http_response_code(500);
                exit;
            }
            
            fseek($handle, $start);
            
            $remaining = $length;
            while ($remaining > 0 && !feof($handle)) {
                $chunkSize = min(8192, $remaining);
                echo fread($handle, $chunkSize);
                $remaining -= $chunkSize;
                if (connection_status() != CONNECTION_NORMAL) {
                    break;
                }
                flush();
            }
            
            fclose($handle);
            exit;
        }
    }

    /**
     * 构建缓存上下文
     */
    private function buildCacheContext(string $url, string $type, bool $isLocalFile): ?array
    {
        $config = \think\facade\Config::get('download_cache', []);
        if (empty($config['enabled'])) {
            return null;
        }
        if (!empty($config['remote_only']) && $isLocalFile) {
            return null;
        }
        $root = rtrim($config['root'] ?? (runtime_path() . 'download_cache'), DIRECTORY_SEPARATOR);
        if ($root === '') {
            return null;
        }
        $hash = sha1($url);
        $subDir = substr($hash, 0, 2) . DIRECTORY_SEPARATOR . substr($hash, 2, 2);
        $dir = $root . DIRECTORY_SEPARATOR . $subDir;
        $extension = $this->guessCacheExtension($url, $type);
        $path = $dir . DIRECTORY_SEPARATOR . $hash . '.' . $extension;
        $lockPath = $dir . DIRECTORY_SEPARATOR . $hash . '.lock';
        
        // 清理过期锁文件（超过5分钟或0字节）
        if (file_exists($lockPath)) {
            $lockAge = time() - filemtime($lockPath);
            $lockSize = filesize($lockPath);
            if ($lockAge > 300 || $lockSize == 0) {
                @unlink($lockPath);
            }
        }
        
        $context = [
            'config' => $config,
            'hash' => $hash,
            'dir' => $dir,
            'path' => $path,
            'temp_path' => $path . '.part',
            'lock_path' => $lockPath,
            'meta_path' => $dir . DIRECTORY_SEPARATOR . $hash . '.json',
        ];
        if ($this->isCacheValid($path, $config)) {
            $context['ready'] = true;
        } elseif (file_exists($path)) {
            @unlink($path);
            if (isset($context['meta_path']) && file_exists($context['meta_path'])) {
                @unlink($context['meta_path']);
            }
        }
        return $context;
    }

    private function acquireCacheLock(array $context): ?array
    {
        if (!$this->ensureDirectory($context['dir'])) {
            return null;
        }
        // 检查是否有过期锁（超过5分钟）
        if (file_exists($context['lock_path'])) {
            $lockAge = time() - filemtime($context['lock_path']);
            if ($lockAge > 300) { // 5分钟
                @unlink($context['lock_path']);
            }
        }
        
        $lockHandle = @fopen($context['lock_path'], 'c+');
        if (!$lockHandle) {
            return null;
        }
        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);
            return null;
        }
        
        // 写入锁信息（进程ID、时间戳）
        $lockInfo = [
            'pid' => getmypid(),
            'time' => time(),
        ];
        ftruncate($lockHandle, 0);
        rewind($lockHandle);
        fwrite($lockHandle, json_encode($lockInfo));
        fflush($lockHandle);
        
        if (isset($context['temp_path']) && file_exists($context['temp_path'])) {
            @unlink($context['temp_path']);
        }
        if (isset($context['meta_path']) && file_exists($context['meta_path'])) {
            @unlink($context['meta_path']);
        }
        $context['lock_handle'] = $lockHandle;
        return $context;
    }

    private function releaseCacheLock(?array $context): void
    {
        if (!empty($context['lock_handle'])) {
            @flock($context['lock_handle'], LOCK_UN);
            @fclose($context['lock_handle']);
        }
        // 释放锁后删除锁文件
        if (!empty($context['lock_path']) && file_exists($context['lock_path'])) {
            @unlink($context['lock_path']);
        }
    }

    private function downloadRemoteToCache(string $url, array $context, array $meta): ?string
    {
        if (empty($context['temp_path']) || empty($context['path'])) {
            return null;
        }
        $tempPath = $context['temp_path'];
        $targetPath = $context['path'];
        $tempDir = dirname($tempPath);
        if (!is_dir($tempDir)) {
            $this->ensureDirectory($tempDir);
        }
        
        $referer = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
            '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';
        $userAgent = 'VideoTool-Server-Downloader/1.0 (PHP/' . PHP_VERSION . ')';
        
        // 多重下载策略：最多重试5次，每次使用不同的策略
        $maxRetries = 5;
        $retryDelay = 2; // 重试间隔（秒）
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $fp = null;
            $ch = null;
            
            try {
                // 每次重试前清理可能存在的临时文件
                if ($attempt > 1 && file_exists($tempPath)) {
                    @unlink($tempPath);
                }
                
                // 如果上次下载部分失败，尝试断点续传
                $resumeFrom = 0;
                if (file_exists($tempPath)) {
                    $resumeFrom = filesize($tempPath);
                    if ($resumeFrom > 0) {
                        $fp = @fopen($tempPath, 'ab'); // 追加模式
                        \think\facade\Log::info("尝试断点续传: {$url} (从 {$resumeFrom} 字节继续)");
                    }
                }
                
                if (!$fp) {
                    $fp = @fopen($tempPath, 'wb');
                }
                
                if (!$fp) {
                    throw new \Exception('无法创建/打开临时缓存文件: ' . $tempPath);
                }
                
                $ch = curl_init($url);
                if ($ch === false) {
                    throw new \Exception('无法初始化cURL');
                }
                
                // 动态调整超时时间：第一次30秒，后续递增
                $connectTimeout = min(30 + ($attempt - 1) * 10, 60);
                
                $curlOptions = [
                    CURLOPT_FILE => $fp,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 10, // 增加重定向次数
                    CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                    CURLOPT_TIMEOUT => 0, // 无总超时限制
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_REFERER => $referer,
                    CURLOPT_USERAGENT => $userAgent,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_BUFFERSIZE => 262144, // 256KB buffer（增大缓冲区）
                    CURLOPT_TCP_NODELAY => 1,
                    CURLOPT_TCP_KEEPALIVE => 1,
                    CURLOPT_ENCODING => '', // 自动处理压缩
                    CURLOPT_HTTPHEADER => [
                        'Accept: */*',
                        'Accept-Encoding: identity', // 禁用压缩，避免解压问题
                        'Connection: keep-alive',
                    ],
                ];
                
                // 断点续传
                if ($resumeFrom > 0) {
                    $curlOptions[CURLOPT_RANGE] = $resumeFrom . '-';
                }
                
                curl_setopt_array($ch, $curlOptions);
                
                $startTime = microtime(true);
                $success = curl_exec($ch);
                $endTime = microtime(true);
                
                $error = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $downloadedSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
                $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                
                if ($ch) {
                    curl_close($ch);
                    $ch = null;
                }
                if ($fp) {
                    fclose($fp);
                    $fp = null;
                }
                
                // 验证下载结果
                if (!$success || !empty($error)) {
                    throw new \Exception("cURL执行失败 (尝试 {$attempt}/{$maxRetries}): " . ($error ?: '未知错误'));
                }
                
                if ($httpCode >= 400) {
                    throw new \Exception("HTTP错误 (尝试 {$attempt}/{$maxRetries}): HTTP {$httpCode}");
                }
                
                if (!file_exists($tempPath)) {
                    throw new \Exception("临时文件不存在 (尝试 {$attempt}/{$maxRetries})");
                }
                
                $actualSize = filesize($tempPath);
                if ($actualSize == 0) {
                    throw new \Exception("下载的文件为空 (尝试 {$attempt}/{$maxRetries})");
                }
                
                // 如果有Content-Length，验证文件大小
                if ($contentLength > 0) {
                    $expectedSize = $resumeFrom + $contentLength;
                    // 允许5%的误差（考虑网络传输中的一些变化）
                    if (abs($actualSize - $expectedSize) > ($expectedSize * 0.05)) {
                        \think\facade\Log::warning("文件大小不匹配 (尝试 {$attempt}/{$maxRetries}): 期望 {$expectedSize}, 实际 {$actualSize}");
                        // 如果误差太大且不是最后一次尝试，继续重试
                        if ($attempt < $maxRetries) {
                            @unlink($tempPath);
                            sleep($retryDelay);
                            continue;
                        }
                    }
                }
                
                // 文件下载成功，重命名为最终文件名
                if (@rename($tempPath, $targetPath)) {
                    $finalSize = filesize($targetPath);
                    
                    // 保存元数据
                    $metaPayload = [
                        'hash' => $context['hash'] ?? null,
                        'file_name' => $meta['file_name'] ?? basename($targetPath),
                        'type' => $meta['type'] ?? 'video',
                        'source_url' => $meta['source_url'] ?? $url,
                        'video_id' => $meta['video_id'] ?? null,
                        'platform' => $meta['platform'] ?? null,
                        'title' => $meta['title'] ?? null,
                        'size' => $finalSize,
                        'cached_at' => time(),
                        'download_time' => round($endTime - $startTime, 2),
                        'attempts' => $attempt,
                    ];
                    if (!empty($context['meta_path'])) {
                        @file_put_contents(
                            $context['meta_path'],
                            json_encode($metaPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                        );
                    }
                    
                    \think\facade\Log::info("缓存远程文件成功 (尝试 {$attempt}/{$maxRetries}): {$url} -> {$targetPath} ({$finalSize} bytes, 耗时 " . round($endTime - $startTime, 2) . "秒)");
                    return $targetPath;
                } else {
                    throw new \Exception('重命名缓存文件失败');
                }
                
            } catch (\Exception $e) {
                if ($ch) {
                    @curl_close($ch);
                }
                if ($fp) {
                    @fclose($fp);
                }
                
                $errorMsg = $e->getMessage();
                \think\facade\Log::warning("缓存远程文件失败 (尝试 {$attempt}/{$maxRetries}): {$errorMsg} - {$url}");
                
                // 如果是最后一次尝试，删除临时文件
                if ($attempt >= $maxRetries) {
                    if (file_exists($tempPath)) {
                        @unlink($tempPath);
                    }
                    return null;
                }
                
                // 等待后重试
                sleep($retryDelay * $attempt); // 指数退避
            }
        }
        
        return null;
    }

    private function isCacheValid(string $path, array $config): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        if (filesize($path) <= 0) {
            return false;
        }
        $expire = (int)($config['expire_seconds'] ?? 0);
        if ($expire > 0 && (time() - filemtime($path)) > $expire) {
            return false;
        }
        return true;
    }

    private function ensureDirectory(string $dir): bool
    {
        if (is_dir($dir)) {
            return true;
        }
        return @mkdir($dir, 0775, true);
    }

    private function guessCacheExtension(string $url, string $type): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext) {
                return preg_replace('/[^a-z0-9]/i', '', $ext);
            }
        }
        return $type === 'cover' ? 'jpg' : 'mp4';
    }
    
    /**
     * 公共方法：为指定URL触发预缓存（可在后台上传成功后调用）
     * @param string $url 要缓存的URL
     * @param string $type 类型：'video' 或 'cover'
     * @param array $meta 元数据（可选）
     * @return bool 是否成功
     */
    public static function triggerPreCache(string $url, string $type = 'video', array $meta = []): bool
    {
        try {
            $instance = new self();
            
            // 判断是否为本地文件
            $parsedUrl = parse_url($url);
            $isLocalFile = isset($parsedUrl['host']) && 
                          ($parsedUrl['host'] === ($_SERVER['HTTP_HOST'] ?? '') ||
                           $parsedUrl['host'] === 'localhost' ||
                           $parsedUrl['host'] === '127.0.0.1');
            
            // 只缓存远程文件
            if ($isLocalFile) {
                return false;
            }
            
            // 构建缓存上下文
            $cacheContext = $instance->buildCacheContext($url, $type, $isLocalFile);
            if (!$cacheContext) {
                return false;
            }
            
            // 如果已缓存，直接返回成功
            if (!empty($cacheContext['ready']) && file_exists($cacheContext['path'])) {
                \think\facade\Log::info("预缓存：文件已存在 - {$url}");
                return true;
            }
            
            // 尝试获取锁并下载
            $cacheWriteContext = $instance->acquireCacheLock($cacheContext);
            if (!$cacheWriteContext) {
                // 可能其他进程正在下载，等待后检查
                sleep(2);
                if (file_exists($cacheContext['path']) && filesize($cacheContext['path']) > 0) {
                    \think\facade\Log::info("预缓存：其他进程已缓存 - {$url}");
                    return true;
                }
                return false;
            }
            
            // 执行下载
            $cachePath = $instance->downloadRemoteToCache($url, $cacheWriteContext, array_merge([
                'type' => $type,
                'source_url' => $url,
                'file_name' => basename($url),
            ], $meta));
            
            $instance->releaseCacheLock($cacheWriteContext);
            
            if ($cachePath && file_exists($cachePath)) {
                \think\facade\Log::info("预缓存成功：{$url} -> {$cachePath}");
                return true;
            } else {
                \think\facade\Log::warning("预缓存失败：{$url}");
                return false;
            }
        } catch (\Exception $e) {
            \think\facade\Log::error("预缓存异常：{$url} - " . $e->getMessage());
            return false;
        }
    }
}


