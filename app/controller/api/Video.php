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
            $directUrl = $isCdnResource ? $this->buildDirectDownloadUrl($fileUrl, $downloadFileName) : $fileUrl;
            
            // APP请求：返回JSON格式的下载信息
            if ($isAppRequest) {
                // 生成代理下载URL（APP可以使用这个URL直接下载）
                $proxyUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                           '://' . $_SERVER['HTTP_HOST'] . 
                           '/api/video/download?video_id=' . $videoId . '&type=' . $type;
                
                return json([
                    'code' => 0,
                    'msg' => '获取成功',
                    'data' => [
                        'video_id' => $video->id,
                        'type' => $type,
                        'file_url' => $fileUrl,
                        'direct_url' => $directUrl,
                        'fallback_url' => $proxyUrl,
                        'file_name' => $downloadFileName,
                        'is_cdn' => $isCdnResource,
                        'file_size' => null,
                    ]
                ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            // 判断是本地文件还是远程文件
            $parsedUrl = parse_url($fileUrl);
            $isLocalFile = isset($parsedUrl['host']) && 
                          ($parsedUrl['host'] === $_SERVER['HTTP_HOST'] || 
                           $parsedUrl['host'] === 'localhost' ||
                           $parsedUrl['host'] === '127.0.0.1');
            
            if ($isLocalFile) {
                // 本地文件：直接读取并流式输出
                $localPath = $this->urlToLocalPath($fileUrl);
                if ($localPath && file_exists($localPath)) {
                    return $this->streamLocalFile($localPath, $type, $downloadFileName);
                }
            }
            
            // CDN资源优先直接下载
            if ($isCdnResource) {
                header('Cache-Control: no-cache');
                header('Pragma: no-cache');
                header('Location: ' . $directUrl, true, 302);
                exit;
            }
            
            // 其他远程文件统一使用代理下载，确保浏览器强制下载而不是打开
            return $this->streamRemoteFile($fileUrl, $type, $downloadFileName);
            
        } catch (\Exception $e) {
            \think\facade\Log::error('代理下载错误: ' . $e->getMessage());
            return json([
                'code' => 1,
                'msg' => '下载失败：' . $e->getMessage()
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
    private function streamRemoteFile($url, $type, $fileName)
    {
        $mimeType = $type === 'cover' ? 'image/jpeg' : 'video/mp4';
        
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
        
        // 初始化cURL
        $ch = curl_init($url);
        
        if ($ch === false) {
            return json(['code' => 1, 'msg' => '无法初始化下载'], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
        
        // 优化：增大缓冲区大小，提高传输速度
        $bufferSize = 131072; // 128KB buffer（增大缓冲区，减少系统调用次数）
        
        // 设置cURL选项 - 关键：立即开始传输，不等待完整响应头，优化传输速度
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10, // 连接超时10秒
            CURLOPT_TIMEOUT => 0, // 无超时限制
            CURLOPT_BUFFERSIZE => $bufferSize, // 设置缓冲区大小（128KB）
            CURLOPT_REFERER => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/',
            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'VideoTool-Server-Proxy',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '', // 自动处理压缩
            CURLOPT_TCP_NODELAY => 1, // 禁用Nagle算法，立即发送数据
            CURLOPT_TCP_KEEPALIVE => 1, // 启用TCP keepalive
            // 关键：立即开始写入数据，不等待完整响应
            // 优化：立即输出并刷新，确保浏览器立即开始下载
            CURLOPT_WRITEFUNCTION => function($ch, $data) {
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
                return strlen($data);
            },
            // 简化响应头处理，只转发必要的头，不转发Content-Length
            CURLOPT_HEADERFUNCTION => function($ch, $headerLine) {
                // 只处理Content-Type，忽略Content-Length让浏览器使用chunked传输
                if (strpos($headerLine, ':') !== false) {
                    list($headerName, $headerValue) = explode(':', $headerLine, 2);
                    $headerName = strtolower(trim($headerName));
                    $headerValue = trim($headerValue);
                    
                    // 只转发Content-Type（如果我们需要）
                    if ($headerName === 'content-type' && !empty($headerValue)) {
                        // 使用我们自己的Content-Type和Content-Disposition
                        // header('Content-Type: ' . $headerValue, false);
                    }
                    
                    // 忽略Content-Length和Content-Disposition
                    // 这样浏览器会使用chunked传输，立即开始下载
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
        
        // 检查cURL执行结果
        if ($result === false || !empty($error)) {
            return json([
                'code' => 1,
                'msg' => '下载失败：' . ($error ?: 'HTTP ' . $httpCode)
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
        
        if ($httpCode >= 400) {
            return json([
                'code' => 1,
                'msg' => '下载失败：HTTP ' . $httpCode
            ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
        
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
}


