<?php
declare (strict_types = 1);

namespace app\service;

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;
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
        
        $this->accessKey = $config['access_key'];
        $this->secretKey = $config['secret_key'];
        $this->bucket = $config['bucket'];
        $this->domain = rtrim($config['domain'] ?? '', '/');
        
        try {
            $this->auth = new Auth($this->accessKey, $this->secretKey);
            $this->uploadManager = new UploadManager();
            $this->bucketManager = new BucketManager($this->auth);
            $this->enabled = true;
        } catch (\Exception $e) {
            Log::error('七牛云初始化失败: ' . $e->getMessage());
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

