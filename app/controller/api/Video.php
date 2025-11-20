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
}

