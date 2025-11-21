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
     */
    public function downloadProxy()
    {
        try {
            $videoId = $this->request->param('video_id');
            $type = $this->request->param('type', 'video'); // cover 或 video
            
            if (!$videoId) {
                return json([
                    'code' => 1,
                    'msg' => '参数错误：缺少视频ID'
                ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            // 获取视频信息
            $video = VideoModel::find($videoId);
            if (!$video) {
                return json([
                    'code' => 1,
                    'msg' => '视频不存在'
                ], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
            }
            
            // 获取文件URL
            $fileUrl = $type === 'cover' ? $video->cover_url : $video->video_url;
            if (empty($fileUrl)) {
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
                    return $this->streamLocalFile($localPath, $type, $video->title);
                }
            }
            
            // 远程文件（七牛云等）：使用代理下载
            return $this->streamRemoteFile($fileUrl, $type, $video->title);
            
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
    private function streamLocalFile($filePath, $type, $title)
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
            $fileName = preg_replace('/[^\w\s-]/', '', $title) . $ext;
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
        header('Content-Length: ' . $fileSize);
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache');
        
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
        
        // 分块读取并输出
        $chunkSize = 8192; // 8KB chunks
        while (!feof($handle)) {
            echo fread($handle, $chunkSize);
            if (connection_status() != CONNECTION_NORMAL) {
                break;
            }
            flush();
        }
        
        fclose($handle);
        exit;
    }
    
    /**
     * 流式输出远程文件（代理下载）
     */
    private function streamRemoteFile($url, $type, $title)
    {
        // 获取文件名
        $ext = $type === 'cover' ? '.jpg' : '.mp4';
        $fileName = preg_replace('/[^\w\s-]/', '', $title) . $ext;
        $mimeType = $type === 'cover' ? 'image/jpeg' : 'video/mp4';
        
        // 设置默认响应头
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
        header('Cache-Control: no-cache');
        header('Accept-Ranges: bytes');
        
        // 初始化cURL
        $ch = curl_init($url);
        
        if ($ch === false) {
            return json(['code' => 1, 'msg' => '无法初始化下载'], 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
        }
        
        // 使用变量存储header信息
        $headerSize = 0;
        $contentLength = 0;
        $isFirstLine = true;
        
        // 设置cURL选项
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 0, // 无超时限制
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_WRITEFUNCTION => function($ch, $data) {
                echo $data;
                flush();
                return strlen($data);
            },
            CURLOPT_HEADERFUNCTION => function($ch, $headerLine) use (&$headerSize, &$contentLength, &$isFirstLine, $type, $fileName, $mimeType) {
                $headerSize += strlen($headerLine);
                
                // 处理HTTP状态行
                if ($isFirstLine && stripos($headerLine, 'HTTP/') === 0) {
                    $isFirstLine = false;
                    // 可以在这里设置状态码
                    return strlen($headerLine);
                }
                
                // 处理响应头
                if (strpos($headerLine, ':') !== false) {
                    list($headerName, $headerValue) = explode(':', $headerLine, 2);
                    $headerName = strtolower(trim($headerName));
                    $headerValue = trim($headerValue);
                    
                    // 记录Content-Length
                    if ($headerName === 'content-length') {
                        $contentLength = intval($headerValue);
                    }
                    
                    // 转发Content-Type（如果存在）
                    if ($headerName === 'content-type' && !empty($headerValue)) {
                        header('Content-Type: ' . $headerValue, false);
                        return strlen($headerLine);
                    }
                    
                    // 忽略Content-Disposition（使用我们自己的）
                    if ($headerName === 'content-disposition') {
                        return strlen($headerLine);
                    }
                    
                    // 转发其他有用的头
                    if (in_array($headerName, ['accept-ranges', 'content-range'])) {
                        header($headerName . ': ' . $headerValue, false);
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
        
        // 清除输出缓冲
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // 执行下载
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
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

