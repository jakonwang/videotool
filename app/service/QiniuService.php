<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Config;
use think\facade\Log;

/**
 * 七牛云存储服务类
 */
class QiniuService
{
    private $accessKey;
    private $secretKey;
    private $bucket;
    private $domain;
    private $region;
    private $auth;
    private $uploadManager;
    private $bucketManager;
    private $enabled = false;
    
    public function __construct()
    {
        $config = Config::get('qiniu');
        
        if (empty($config['access_key']) || empty($config['secret_key']) || empty($config['bucket'])) {
            $this->enabled = false;
            return;
        }
        
        // 检查七牛云SDK是否已加载
        if (!class_exists('Qiniu\Auth')) {
            Log::warning('七牛云SDK未加载，请运行 composer install 安装依赖。将使用本地存储。');
            $this->enabled = false;
            return;
        }
        
        $this->accessKey = $config['access_key'];
        $this->secretKey = $config['secret_key'];
        $this->bucket = $config['bucket'];
        $this->domain = rtrim($config['domain'] ?? '', '/');
        $this->region = $config['region'] ?? 'z2'; // 默认华东
        
        try {
            // 使用完整命名空间避免自动加载问题
            $authClass = 'Qiniu\\Auth';
            $uploadManagerClass = 'Qiniu\\Storage\\UploadManager';
            $bucketManagerClass = 'Qiniu\\Storage\\BucketManager';
            
            $this->auth = new $authClass($this->accessKey, $this->secretKey);
            $this->uploadManager = new $uploadManagerClass();
            $this->bucketManager = new $bucketManagerClass($this->auth);
            $this->enabled = true;
        } catch (\Exception $e) {
            Log::error('七牛云初始化失败: ' . $e->getMessage() . ' | 文件: ' . $e->getFile() . ' | 行号: ' . $e->getLine());
            $this->enabled = false;
        }
    }
    
    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * 生成前端直传Token（用于前端直接上传到七牛云）
     * @param string $key 文件key（可选，不指定则允许任意key）
     * @param int $expires 过期时间（秒），默认3600秒
     * @return array ['token' => string, 'domain' => string, 'bucket' => string]
     */
    public function generateUploadToken(string $key = null, int $expires = 3600): array
    {
        if (!$this->enabled) {
            return [
                'token' => '',
                'domain' => '',
                'bucket' => '',
                'msg' => '七牛云未配置或未启用'
            ];
        }
        
        try {
            // 生成上传策略（允许任意key，前端会指定）
            $policy = [
                'scope' => $this->bucket, // 允许上传到整个bucket
                'deadline' => time() + $expires,
                'returnBody' => json_encode([
                    'key' => '$(key)',
                    'hash' => '$(etag)',
                    'fsize' => '$(fsize)',
                    'mimeType' => '$(mimeType)',
                    'url' => $this->domain . '/$(key)'
                ], JSON_UNESCAPED_UNICODE)
            ];
            
            // 生成上传token（不指定key，允许前端指定任意key）
            $token = $this->auth->uploadToken($this->bucket, null, $expires, $policy);
            
            return [
                'token' => $token,
                'domain' => $this->domain,
                'bucket' => $this->bucket,
                'uploadUrl' => $this->getUploadUrl(),
                'region' => $this->region,
                'msg' => 'success'
            ];
        } catch (\Exception $e) {
            Log::error('生成七牛云上传Token失败: ' . $e->getMessage());
            return [
                'token' => '',
                'domain' => '',
                'bucket' => '',
                'msg' => '生成Token失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取上传地址
     * @return string
     */
    private function getUploadUrl(): string
    {
        // 根据bucket区域返回对应的上传地址
        $regionUrls = [
            'z0' => 'https://upload-z0.qiniup.com',  // 华南
            'z1' => 'https://upload-z1.qiniup.com',  // 华北
            'z2' => 'https://upload-z2.qiniup.com',  // 华东
            'na0' => 'https://upload-na0.qiniup.com', // 北美
            'as0' => 'https://upload-as0.qiniup.com'  // 东南亚
        ];
        
        $region = $this->region ?? 'z2';
        return $regionUrls[$region] ?? $regionUrls['z2'];
    }
    
    /**
     * 上传文件到七牛云
     * @param string $localFilePath 本地文件路径
     * @param string $key 七牛云文件key（路径）
     * @return array ['success' => bool, 'url' => string, 'msg' => string]
     */
    public function upload(string $localFilePath, string $key = null): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'url' => '',
                'msg' => '七牛云未配置或未启用'
            ];
        }
        
        if (!file_exists($localFilePath)) {
            return [
                'success' => false,
                'url' => '',
                'msg' => '本地文件不存在: ' . $localFilePath
            ];
        }
        
        try {
            // 如果没有指定key，根据文件路径生成
            if (empty($key)) {
                $key = $this->generateKey($localFilePath);
            }
            
            // 生成上传token
            $token = $this->auth->uploadToken($this->bucket, $key);
            
            // 上传文件
            list($ret, $err) = $this->uploadManager->putFile($token, $key, $localFilePath);
            
            if ($err !== null) {
                Log::error('七牛云上传失败: ' . $err->message() . ' | 文件: ' . $localFilePath);
                return [
                    'success' => false,
                    'url' => '',
                    'msg' => '上传失败: ' . $err->message()
                ];
            }
            
            // 生成访问URL
            $url = $this->getUrl($key);
            
            Log::info('七牛云上传成功: ' . $key . ' | URL: ' . $url);
            
            return [
                'success' => true,
                'url' => $url,
                'key' => $key,
                'msg' => '上传成功'
            ];
            
        } catch (\Exception $e) {
            Log::error('七牛云上传异常: ' . $e->getMessage() . ' | 文件: ' . $localFilePath);
            return [
                'success' => false,
                'url' => '',
                'msg' => '上传异常: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 删除七牛云文件
     * @param string $key 文件key
     * @return bool
     */
    public function delete(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $err = $this->bucketManager->delete($this->bucket, $key);
            if ($err !== null) {
                Log::error('七牛云删除失败: ' . $err->message() . ' | Key: ' . $key);
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error('七牛云删除异常: ' . $e->getMessage() . ' | Key: ' . $key);
            return false;
        }
    }
    
    /**
     * 获取文件URL
     * @param string $key 文件key
     * @return string
     */
    public function getUrl(string $key): string
    {
        if (empty($this->domain)) {
            return '';
        }
        
        // 如果key已经是完整URL，直接返回
        if (preg_match('/^https?:\/\//', $key)) {
            return $key;
        }
        
        // 移除key开头的斜杠
        $key = ltrim($key, '/');
        
        return $this->domain . '/' . $key;
    }
    
    /**
     * 生成文件key
     * @param string $localFilePath 本地文件路径
     * @return string
     */
    private function generateKey(string $localFilePath): string
    {
        // 根据文件路径生成key，保持目录结构
        // 例如: /uploads/videos/20241120/file.mp4 -> videos/20241120/file.mp4
        
        $path = str_replace('\\', '/', $localFilePath);
        
        // 查找 uploads 目录位置
        $pos = strpos($path, '/uploads/');
        if ($pos !== false) {
            $key = substr($path, $pos + 9); // 9 = strlen('/uploads/')
        } else {
            // 如果没有找到uploads，使用文件名+时间戳
            $fileName = basename($localFilePath);
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $name = pathinfo($fileName, PATHINFO_FILENAME);
            $key = date('Y/m/d/') . md5($name . time()) . '.' . $ext;
        }
        
        return $key;
    }
    
    /**
     * 检查文件是否存在
     * @param string $key 文件key
     * @return bool
     */
    public function exists(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            list($ret, $err) = $this->bucketManager->stat($this->bucket, $key);
            return $err === null;
        } catch (\Exception $e) {
            return false;
        }
    }
}

