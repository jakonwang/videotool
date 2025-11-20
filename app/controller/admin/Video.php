<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Video as VideoModel;
use app\model\Device as DeviceModel;
use app\model\Platform as PlatformModel;
use app\service\QiniuService;
use think\facade\Filesystem;
use think\facade\View;

/**
 * 视频管理
 */
class Video extends BaseController
{
    // 视频列表
    public function index()
    {
        $platformId = $this->request->param('platform_id', 0);
        $deviceId = $this->request->param('device_id', 0);
        $isDownloaded = $this->request->param('is_downloaded', -1);
        
        $where = [];
        if ($platformId) $where[] = ['platform_id', '=', $platformId];
        if ($deviceId) $where[] = ['device_id', '=', $deviceId];
        if ($isDownloaded >= 0) $where[] = ['is_downloaded', '=', $isDownloaded];
        
        $list = VideoModel::where($where)
            ->with(['platform', 'device'])
            ->order('id', 'desc')
            ->paginate([
                'list_rows' => 20,
                'query' => $this->request->param()
            ]);
            
        $platforms = PlatformModel::select();
        $devices = DeviceModel::select();
        
        return View::fetch('admin/video/index', [
            'list' => $list,
            'platforms' => $platforms,
            'devices' => $devices,
            'platform_id' => $platformId,
            'device_id' => $deviceId,
            'is_downloaded' => $isDownloaded
        ]);
    }
    
    // 批量上传视频
    public function batchUpload()
    {
        if ($this->request->isPost()) {
            // 检查上传大小限制
            $upload_max_filesize = ini_get('upload_max_filesize');
            $post_max_size = ini_get('post_max_size');
            
            $platformId = $this->request->post('platform_id');
            $deviceId = $this->request->post('device_id');
            $files = $this->request->file('videos');
            
            // 检查是否是 413 错误（文件过大）
            if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
                $content_length = $_SERVER['CONTENT_LENGTH'];
                $content_length_mb = number_format($content_length / 1024 / 1024, 2);
                
                // 检测 Web 服务器
                $web_server = 'Unknown';
                if (isset($_SERVER['SERVER_SOFTWARE'])) {
                    if (stripos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) {
                        $web_server = 'Nginx';
                    } elseif (stripos($_SERVER['SERVER_SOFTWARE'], 'apache') !== false) {
                        $web_server = 'Apache';
                    }
                }
                
                $error_msg = "上传文件过大（{$content_length_mb}MB），超过服务器限制。\n\n";
                $error_msg .= "PHP 配置：\n";
                $error_msg .= "- upload_max_filesize = {$upload_max_filesize}\n";
                $error_msg .= "- post_max_size = {$post_max_size}\n\n";
                
                if ($web_server === 'Nginx') {
                    $error_msg .= "⚠️ 检测到使用 Nginx，请检查 Nginx 的 client_max_body_size 配置！\n";
                    $error_msg .= "在 Nginx 配置文件中添加：client_max_body_size 100M;\n";
                    $error_msg .= "然后重启 Nginx。\n\n";
                } elseif ($web_server === 'Apache') {
                    $error_msg .= "⚠️ 检测到使用 Apache，请检查 LimitRequestBody 配置。\n\n";
                }
                
                $error_msg .= "请访问 /check_upload.php 查看详细配置和修复方法。";
                
                return json([
                    'code' => 1, 
                    'msg' => $error_msg
                ]);
            }
            
            if (!$files || !$platformId || !$deviceId) {
                return json(['code' => 1, 'msg' => '参数不完整：' . (empty($files) ? '未选择文件' : '') . (empty($platformId) ? '未选择平台' : '') . (empty($deviceId) ? '未选择设备' : '')]);
            }
            
            // 确保是数组
            if (!is_array($files)) {
                $files = [$files];
            }
            
            $success = 0;
            $errors = [];
            
            // 初始化七牛云服务（只初始化一次）
            $qiniuService = new QiniuService();
            $qiniuEnabled = $qiniuService->isEnabled();
            
            // 预先创建目录（只创建一次）
            $videoTargetDir = root_path() . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR;
            $coverTargetDir = root_path() . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'covers' . DIRECTORY_SEPARATOR;
            $dateStr = date('Ymd');
            $videoDateDir = $videoTargetDir . $dateStr . DIRECTORY_SEPARATOR;
            $coverDateDir = $coverTargetDir . $dateStr . DIRECTORY_SEPARATOR;
            
            if (!is_dir($videoTargetDir)) {
                mkdir($videoTargetDir, 0755, true);
            }
            if (!is_dir($videoDateDir)) {
                mkdir($videoDateDir, 0755, true);
            }
            if (!is_dir($coverTargetDir)) {
                mkdir($coverTargetDir, 0755, true);
            }
            if (!is_dir($coverDateDir)) {
                mkdir($coverDateDir, 0755, true);
            }
            
            // 获取标题数组（只获取一次）
            $titles = $this->request->post('titles', []);
            
            foreach ($files as $key => $file) {
                try {
                    if (!$file || !$file->isValid()) {
                        $errors[] = "文件 " . ($key + 1) . ": 文件无效";
                        continue;
                    }
                    
                    $timestamp = time() + $key; // 避免文件名冲突
                    $videoFileName = md5($key . '_video_' . $timestamp) . '_' . $file->getOriginalName();
                    
                    // 移动视频文件
                    $file->move($videoDateDir, $videoFileName);
                    $videoLocalPath = $videoDateDir . $videoFileName;
                    $videoLocalUrl = '/uploads/videos/' . $dateStr . '/' . $videoFileName;
                    
                    // 先使用本地URL，七牛云上传改为后台异步处理（避免阻塞）
                    $videoUrl = $videoLocalUrl;
                    
                    // 处理封面
                    $coverFile = $this->request->file('covers.' . $key);
                    $coverUrl = '';
                    if ($coverFile && $coverFile->isValid()) {
                        $coverFileName = md5($key . '_cover_' . $timestamp) . '_' . $coverFile->getOriginalName();
                        $coverFile->move($coverDateDir, $coverFileName);
                        $coverLocalPath = $coverDateDir . $coverFileName;
                        $coverLocalUrl = '/uploads/covers/' . $dateStr . '/' . $coverFileName;
                        $coverUrl = $coverLocalUrl;
                    }
                    
                    // 如果没有封面，使用视频URL作为默认封面
                    if (empty($coverUrl)) {
                        $coverUrl = $videoUrl;
                    }
                    
                    // 获取标题
                    $title = $titles[$key] ?? '视频标题 ' . ($key + 1);
                    
                    // 先保存到数据库（使用本地URL）
                    $videoModel = VideoModel::create([
                        'platform_id' => $platformId,
                        'device_id' => $deviceId,
                        'title' => $title,
                        'cover_url' => $coverUrl,
                        'video_url' => $videoUrl
                    ]);
                    
                    // 后台异步上传到七牛云（不阻塞主流程）
                    if ($qiniuEnabled && $videoModel) {
                        // 使用异步方式上传，避免阻塞
                        $this->uploadToQiniuAsync($qiniuService, $videoModel, $videoLocalPath, $coverLocalPath ?? null);
                    }
                    
                    $success++;
                } catch (\Exception $e) {
                    $errorMsg = $e->getMessage();
                    $errorFile = $e->getFile();
                    $errorLine = $e->getLine();
                    \think\facade\Log::error("批量上传文件失败 [文件" . ($key + 1) . "]: {$errorMsg} | {$errorFile}:{$errorLine}");
                    $errors[] = "文件 " . ($key + 1) . ": " . $errorMsg;
                }
            }
            
            return json([
                'code' => 0,
                'msg' => "成功上传 {$success} 个视频",
                'errors' => $errors
            ]);
        }
        
        $platforms = PlatformModel::select();
        $devices = DeviceModel::select();
        
        return View::fetch('admin/video/batch_upload', [
            'platforms' => $platforms,
            'devices' => $devices
        ]);
    }
    
    /**
     * 异步上传到七牛云（后台任务，不阻塞主流程）
     */
    private function uploadToQiniuAsync($qiniuService, $videoModel, $videoLocalPath, $coverLocalPath = null)
    {
        try {
            // 上传视频到七牛云
            $videoKey = 'videos/' . date('Ymd') . '/' . basename($videoLocalPath);
            $qiniuResult = $qiniuService->upload($videoLocalPath, $videoKey);
            if ($qiniuResult['success']) {
                $videoModel->video_url = $qiniuResult['url'];
                $videoModel->save();
                \think\facade\Log::info('七牛云视频上传成功: ' . $qiniuResult['url'] . ' | 视频ID: ' . $videoModel->id);
            } else {
                \think\facade\Log::warning('七牛云视频上传失败: ' . $qiniuResult['msg'] . ' | 视频ID: ' . $videoModel->id . ' | 使用本地URL');
            }
            
            // 上传封面到七牛云
            if ($coverLocalPath && file_exists($coverLocalPath)) {
                $coverKey = 'covers/' . date('Ymd') . '/' . basename($coverLocalPath);
                $qiniuResult = $qiniuService->upload($coverLocalPath, $coverKey);
                if ($qiniuResult['success']) {
                    $videoModel->cover_url = $qiniuResult['url'];
                    $videoModel->save();
                    \think\facade\Log::info('七牛云封面上传成功: ' . $qiniuResult['url'] . ' | 视频ID: ' . $videoModel->id);
                } else {
                    \think\facade\Log::warning('七牛云封面上传失败: ' . $qiniuResult['msg'] . ' | 视频ID: ' . $videoModel->id . ' | 使用本地URL');
                }
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('七牛云异步上传异常: ' . $e->getMessage() . ' | 视频ID: ' . ($videoModel->id ?? '未知') . ' | 文件: ' . $e->getFile() . ' | 行号: ' . $e->getLine());
        }
    }
    
    // 批量编辑
    public function batchEdit()
    {
        if ($this->request->isPost()) {
            try {
                $ids = $this->request->post('ids', '');
                $data = $this->request->post();
                
                if (empty($ids)) {
                    return json(['code' => 1, 'msg' => '请选择要编辑的视频']);
                }
                
                // 处理ids字符串
                if (is_string($ids)) {
                    $ids = array_filter(explode(',', $ids));
                }
                
                if (empty($ids)) {
                    return json(['code' => 1, 'msg' => '请选择要编辑的视频']);
                }
                
                unset($data['ids']);
                
                // 只更新标题
                if (!isset($data['title']) || trim($data['title']) === '') {
                    return json(['code' => 1, 'msg' => '请输入要修改的标题']);
                }
                
                $updateData = [
                    'title' => trim($data['title'])
                ];
                
                VideoModel::whereIn('id', $ids)->update($updateData);
                
                return json(['code' => 0, 'msg' => '批量更新成功，共更新 ' . count($ids) . ' 条记录']);
            } catch (\Exception $e) {
                \think\facade\Log::error('批量编辑错误: ' . $e->getMessage());
                return json(['code' => 1, 'msg' => '批量更新失败：' . $e->getMessage()]);
            }
        }
        
        try {
            $ids = $this->request->param('ids', '');
            if (empty($ids)) {
                return '请选择要编辑的视频';
            }
            
            $ids = array_filter(explode(',', $ids));
            if (empty($ids)) {
                return '请选择要编辑的视频';
            }
            
            $list = VideoModel::whereIn('id', $ids)->with(['platform', 'device'])->select();
            
            if ($list->isEmpty()) {
                return '未找到要编辑的视频';
            }
            
            // 生成IDs字符串
            $idsStr = implode(',', array_column($list->toArray(), 'id'));
            
            return View::fetch('admin/video/batch_edit', [
                'list' => $list,
                'count' => $list->count(),
                'ids_str' => $idsStr
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('批量编辑页面加载错误: ' . $e->getMessage());
            return '加载失败：' . $e->getMessage();
        }
    }
    
    // 单个编辑
    public function edit()
    {
        $id = $this->request->param('id');
        
        if (empty($id)) {
            return json(['code' => 1, 'msg' => '视频ID不能为空']);
        }
        
        if ($this->request->isPost()) {
            try {
                // 获取原有视频信息
                $video = VideoModel::find($id);
                if (!$video) {
                    return json(['code' => 1, 'msg' => '视频不存在']);
                }
                
                $data = $this->request->post();
                
                // 移除 id 字段，避免更新主键
                unset($data['id']);
                
                // 验证必填字段
                if (empty($data['platform_id']) || empty($data['device_id']) || empty(trim($data['title'] ?? ''))) {
                    return json(['code' => 1, 'msg' => '平台、设备和标题为必填项']);
                }
                
                // 初始化七牛云服务
                $qiniuService = new QiniuService();
                $qiniuEnabled = $qiniuService->isEnabled();
                
                // 处理封面文件上传（优先级最高）
                // 先检查 $_FILES 中是否有文件，避免创建无效的文件对象
                if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                    try {
                        $coverFile = $this->request->file('cover');
                        if (!$coverFile || !$coverFile->isValid()) {
                            return json(['code' => 1, 'msg' => '封面上传失败：文件无效']);
                        }
                        
                        // 使用原生文件操作处理封面上传（先保存到本地）
                        $coverTargetDir = root_path() . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'covers' . DIRECTORY_SEPARATOR;
                        if (!is_dir($coverTargetDir)) {
                            mkdir($coverTargetDir, 0755, true);
                        }
                        
                        $coverDateDir = $coverTargetDir . date('Ymd') . DIRECTORY_SEPARATOR;
                        if (!is_dir($coverDateDir)) {
                            mkdir($coverDateDir, 0755, true);
                        }
                        
                        $coverFileName = md5($id . '_cover_' . time()) . '_' . $coverFile->getOriginalName();
                        
                        // 使用 ThinkPHP 文件对象的 move 方法移动文件
                        $coverFile->move($coverDateDir, $coverFileName);
                        $coverLocalPath = $coverDateDir . $coverFileName;
                        $coverLocalUrl = '/uploads/covers/' . date('Ymd') . '/' . $coverFileName;
                        
                        // 上传到七牛云（如果启用）
                        $data['cover_url'] = $coverLocalUrl; // 默认使用本地URL
                        if ($qiniuEnabled) {
                            $coverKey = 'covers/' . date('Ymd') . '/' . $coverFileName;
                            $qiniuResult = $qiniuService->upload($coverLocalPath, $coverKey);
                            if ($qiniuResult['success']) {
                                $data['cover_url'] = $qiniuResult['url']; // 使用七牛云URL
                            } else {
                                \think\facade\Log::warning('七牛云封面上传失败: ' . $qiniuResult['msg'] . ' | 使用本地URL');
                            }
                        }
                    } catch (\Exception $e) {
                        \think\facade\Log::error('封面上传错误: ' . $e->getMessage() . ' | 文件: ' . $e->getFile() . ' | 行号: ' . $e->getLine());
                        return json(['code' => 1, 'msg' => '封面上传失败：' . $e->getMessage()]);
                    }
                } elseif (isset($data['cover_url'])) {
                    // 如果输入了封面URL（包括空字符串），使用输入的URL
                    $data['cover_url'] = trim($data['cover_url']);
                } else {
                    // 如果既没有上传也没有输入，保持原有封面URL不变
                    unset($data['cover_url']);
                }
                
                // 处理视频文件上传（优先级最高）
                // 先检查 $_FILES 中是否有文件，避免创建无效的文件对象
                if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
                    try {
                        $videoFile = $this->request->file('video');
                        if (!$videoFile || !$videoFile->isValid()) {
                            return json(['code' => 1, 'msg' => '视频上传失败：文件无效']);
                        }
                        
                        // 使用原生文件操作处理视频上传（先保存到本地）
                        $videoTargetDir = root_path() . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR;
                        if (!is_dir($videoTargetDir)) {
                            mkdir($videoTargetDir, 0755, true);
                        }
                        
                        $videoDateDir = $videoTargetDir . date('Ymd') . DIRECTORY_SEPARATOR;
                        if (!is_dir($videoDateDir)) {
                            mkdir($videoDateDir, 0755, true);
                        }
                        
                        $videoFileName = md5($id . '_video_' . time()) . '_' . $videoFile->getOriginalName();
                        
                        // 使用 ThinkPHP 文件对象的 move 方法移动文件
                        $videoFile->move($videoDateDir, $videoFileName);
                        $videoLocalPath = $videoDateDir . $videoFileName;
                        $videoLocalUrl = '/uploads/videos/' . date('Ymd') . '/' . $videoFileName;
                        
                        // 上传到七牛云（如果启用）
                        $data['video_url'] = $videoLocalUrl; // 默认使用本地URL
                        if ($qiniuEnabled) {
                            $videoKey = 'videos/' . date('Ymd') . '/' . $videoFileName;
                            $qiniuResult = $qiniuService->upload($videoLocalPath, $videoKey);
                            if ($qiniuResult['success']) {
                                $data['video_url'] = $qiniuResult['url']; // 使用七牛云URL
                            } else {
                                \think\facade\Log::warning('七牛云视频上传失败: ' . $qiniuResult['msg'] . ' | 使用本地URL');
                            }
                        }
                    } catch (\Exception $e) {
                        \think\facade\Log::error('视频上传错误: ' . $e->getMessage() . ' | 文件: ' . $e->getFile() . ' | 行号: ' . $e->getLine());
                        return json(['code' => 1, 'msg' => '视频上传失败：' . $e->getMessage()]);
                    }
                } elseif (isset($data['video_url'])) {
                    // 如果输入了视频URL（包括空字符串），使用输入的URL
                    $data['video_url'] = trim($data['video_url']);
                } else {
                    // 如果既没有上传也没有输入，保持原有视频URL不变
                    unset($data['video_url']);
                }
                
                // 如果封面URL为空，使用视频URL作为默认封面
                if (isset($data['cover_url']) && empty($data['cover_url'])) {
                    if (isset($data['video_url']) && !empty($data['video_url'])) {
                        $data['cover_url'] = $data['video_url'];
                    } elseif ($video->video_url) {
                        $data['cover_url'] = $video->video_url;
                    }
                }
                
                // 处理排序字段
                if (isset($data['sort_order'])) {
                    $data['sort_order'] = (int)$data['sort_order'];
                } else {
                    $data['sort_order'] = 0;
                }
                
                // 处理下载状态
                if (isset($data['is_downloaded'])) {
                    $data['is_downloaded'] = (int)$data['is_downloaded'];
                } else {
                    $data['is_downloaded'] = 0;
                }
                
                // 处理标题（去除首尾空格）
                if (isset($data['title'])) {
                    $data['title'] = trim($data['title']);
                }
                
                // 确保platform_id和device_id为整数
                $data['platform_id'] = (int)$data['platform_id'];
                $data['device_id'] = (int)$data['device_id'];
                
                // 更新数据
                $result = VideoModel::where('id', $id)->update($data);
                
                if ($result === false) {
                    return json(['code' => 1, 'msg' => '更新失败，请重试']);
                }
                
                return json(['code' => 0, 'msg' => '修改成功']);
                
            } catch (\Exception $e) {
                // 记录错误日志
                \think\facade\Log::error('视频编辑错误: ' . $e->getMessage() . ' | 文件: ' . $e->getFile() . ' | 行号: ' . $e->getLine() . ' | 堆栈: ' . $e->getTraceAsString());
                return json(['code' => 1, 'msg' => '保存失败：' . $e->getMessage()]);
            }
        }
        
        // GET请求，显示编辑页面
        try {
            $info = VideoModel::with(['platform', 'device'])->find($id);
            if (!$info) {
                return '视频不存在';
            }
            
            $platforms = PlatformModel::select();
            $devices = DeviceModel::where('platform_id', $info->platform_id)->select();
            
            return View::fetch('admin/video/form', [
                'info' => $info,
                'platforms' => $platforms,
                'devices' => $devices
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('视频编辑页面加载错误: ' . $e->getMessage());
            return '加载失败：' . $e->getMessage();
        }
    }
    
    // 删除
    public function delete()
    {
        $id = $this->request->param('id');
        VideoModel::destroy($id);
        return json(['code' => 0, 'msg' => '删除成功']);
    }
    
    // 批量删除
    public function batchDelete()
    {
        $ids = $this->request->post('ids', []);
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        VideoModel::whereIn('id', $ids)->delete();
        return json(['code' => 0, 'msg' => '批量删除成功']);
    }
    
    // 分片上传
    public function uploadChunk()
    {
        try {
            $chunk = $this->request->file('chunk');
            $chunkIndex = (int)$this->request->post('chunkIndex', 0);
            $totalChunks = (int)$this->request->post('totalChunks', 1);
            $fileId = $this->request->post('fileId');
            $fileName = $this->request->post('fileName');
            $fileSize = (int)$this->request->post('fileSize', 0);
            $fileIndex = (int)$this->request->post('fileIndex', 0);
            $platformId = $this->request->post('platform_id');
            $deviceId = $this->request->post('device_id');
            $title = $this->request->post('title', '');
            $coverFile = $this->request->file('cover');
            
            if (!$chunk || !$fileId || !$platformId || !$deviceId) {
                return json(['code' => 1, 'msg' => '参数不完整：' . (!$chunk ? '缺少分片文件' : '') . (!$fileId ? '缺少文件ID' : '') . (!$platformId ? '缺少平台ID' : '') . (!$deviceId ? '缺少设备ID' : '')]);
            }
            
            // 检查分片文件是否有效
            if (!$chunk->isValid()) {
                return json(['code' => 1, 'msg' => '分片文件无效：' . $chunk->getError()]);
            }
            
            // 临时目录
            $tempDir = runtime_path() . 'temp' . DIRECTORY_SEPARATOR . $fileId . DIRECTORY_SEPARATOR;
            if (!is_dir($tempDir)) {
                if (!mkdir($tempDir, 0755, true)) {
                    return json(['code' => 1, 'msg' => '创建临时目录失败']);
                }
            }
            
            // 保存分片
            $chunkPath = $tempDir . 'chunk_' . $chunkIndex;
            if (!move_uploaded_file($chunk->getPathname(), $chunkPath)) {
                return json(['code' => 1, 'msg' => '保存分片失败']);
            }
            
            // 如果是最后一个分片，合并文件
            if ($chunkIndex == $totalChunks - 1) {
            // 合并所有分片
            $finalPath = $tempDir . 'final_' . $fileName;
            $fp = fopen($finalPath, 'wb');
            
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFile = $tempDir . 'chunk_' . $i;
                if (file_exists($chunkFile)) {
                    $chunkData = file_get_contents($chunkFile);
                    fwrite($fp, $chunkData);
                    unlink($chunkFile); // 删除分片文件
                }
            }
            fclose($fp);
            
            // 上传合并后的文件 - 直接移动到目标目录
            $targetDir = root_path() . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            $targetFileName = date('Ymd') . DIRECTORY_SEPARATOR . md5($fileId . time()) . '_' . $fileName;
            $targetPath = $targetDir . $targetFileName;
            $targetDirFull = dirname($targetPath);
            if (!is_dir($targetDirFull)) {
                mkdir($targetDirFull, 0755, true);
            }
            
            rename($finalPath, $targetPath);
            $videoLocalUrl = '/uploads/videos/' . str_replace(DIRECTORY_SEPARATOR, '/', $targetFileName);
            
            // 初始化七牛云服务并上传视频
            $qiniuService = new QiniuService();
            $qiniuEnabled = $qiniuService->isEnabled();
            $videoUrl = $videoLocalUrl; // 默认使用本地URL
            if ($qiniuEnabled) {
                $videoKey = 'videos/' . str_replace(DIRECTORY_SEPARATOR, '/', $targetFileName);
                $qiniuResult = $qiniuService->upload($targetPath, $videoKey);
                if ($qiniuResult['success']) {
                    $videoUrl = $qiniuResult['url']; // 使用七牛云URL
                } else {
                    \think\facade\Log::warning('七牛云视频上传失败: ' . $qiniuResult['msg'] . ' | 使用本地URL');
                }
            }
            
                // 处理封面
                $coverUrl = '';
                if ($coverFile) {
                    try {
                    // 检查文件是否有效（存在且上传成功）
                    if ($coverFile && $coverFile->isValid()) {
                        // 检查文件大小（封面文件不应该太大，比如限制为10MB）
                        if ($coverFile->getSize() > 10 * 1024 * 1024) {
                            \think\facade\Log::warning('封面上传失败：文件过大 - ' . $coverFile->getSize() . ' bytes');
                        } else {
                            // 检查文件类型（只允许图片）
                            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                            $mimeType = $coverFile->getMime();
                            if (!in_array($mimeType, $allowedTypes)) {
                                \think\facade\Log::warning('封面上传失败：不支持的文件类型 - ' . $mimeType);
                            } else {
                                // 使用原生文件操作处理封面上传
                                $coverTargetDir = root_path() . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'covers' . DIRECTORY_SEPARATOR;
                                if (!is_dir($coverTargetDir)) {
                                    mkdir($coverTargetDir, 0755, true);
                                }
                                
                                $coverDateDir = $coverTargetDir . date('Ymd') . DIRECTORY_SEPARATOR;
                                if (!is_dir($coverDateDir)) {
                                    mkdir($coverDateDir, 0755, true);
                                }
                                
                                $coverFileName = md5($fileId . '_cover_' . time()) . '_' . $coverFile->getOriginalName();
                                
                                // 使用 ThinkPHP 文件对象的 move 方法移动文件
                                try {
                                    $coverFile->move($coverDateDir, $coverFileName);
                                    $coverLocalPath = $coverDateDir . $coverFileName;
                                    $coverLocalUrl = '/uploads/covers/' . date('Ymd') . '/' . $coverFileName;
                                    
                                    // 先使用本地URL，七牛云上传改为异步
                                    $coverUrl = $coverLocalUrl;
                                } catch (\Exception $e) {
                                    \think\facade\Log::warning('封面上传失败：文件移动失败 - ' . $e->getMessage());
                                }
                            }
                        }
                    } else {
                        // 文件无效，记录日志但不中断上传
                        $errorMsg = $coverFile ? $coverFile->getError() : '文件不存在';
                        \think\facade\Log::warning('封面上传失败：文件无效 - ' . $errorMsg);
                    }
                    } catch (\Exception $e) {
                        // 封面上传失败，记录日志但不中断上传
                        \think\facade\Log::error('封面上传错误: ' . $e->getMessage() . ' | 文件: ' . $e->getFile() . ' | 行号: ' . $e->getLine());
                        // 继续执行，使用视频URL作为默认封面
                    }
                }
                
                // 保存到数据库（如果没有封面，使用视频URL作为默认封面）
                try {
                    $videoModel = VideoModel::create([
                        'platform_id' => (int)$platformId,
                        'device_id' => (int)$deviceId,
                        'title' => $title ?: $fileName,
                        'cover_url' => $coverUrl ?: $videoUrl,
                        'video_url' => $videoUrl
                    ]);
                    
                    // 异步上传到七牛云（不阻塞主流程）
                    if ($qiniuEnabled && $videoModel) {
                        $this->uploadToQiniuAsync($qiniuService, $videoModel, $targetPath, isset($coverLocalPath) ? $coverLocalPath : null);
                    }
                } catch (\Exception $e) {
                    // 数据库保存失败，删除已上传的文件
                    if (isset($targetPath) && file_exists($targetPath)) {
                        @unlink($targetPath);
                    }
                    // 如果有封面URL，删除封面文件
                    if (!empty($coverUrl)) {
                        $coverFilePath = root_path() . 'public' . str_replace('/', DIRECTORY_SEPARATOR, $coverUrl);
                        if (file_exists($coverFilePath)) {
                            @unlink($coverFilePath);
                        }
                    }
                    throw new \Exception('保存到数据库失败：' . $e->getMessage());
                }
                
                // 清理临时目录（finalPath 已经移动到目标位置，不需要删除）
                if (is_dir($tempDir)) {
                    // 尝试删除临时目录（应该已经为空）
                    @rmdir($tempDir);
                }
                
                return json(['code' => 0, 'msg' => '上传成功']);
            }
            
            return json(['code' => 0, 'msg' => '分片上传成功', 'chunkIndex' => $chunkIndex]);
            
        } catch (\Exception $e) {
            // 记录错误日志
            \think\facade\Log::error('分片上传错误: ' . $e->getMessage() . ' | 文件: ' . $e->getFile() . ' | 行号: ' . $e->getLine() . ' | 堆栈: ' . $e->getTraceAsString());
            return json(['code' => 1, 'msg' => '上传失败：' . $e->getMessage()]);
        }
    }
}

