<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Video as VideoModel;
use think\facade\View;
use think\facade\Log;

/**
 * 下载错误监控
 */
class DownloadLog extends BaseController
{
    /**
     * 下载错误列表
     */
    public function index()
    {
        $page = (int)$this->request->param('page', 1);
        $keyword = trim((string)$this->request->param('keyword', ''));
        $date = trim((string)$this->request->param('date', date('Ymd')));
        
        // 解析日志文件
        $logFile = runtime_path() . 'log' . DIRECTORY_SEPARATOR . substr($date, 0, 6) . DIRECTORY_SEPARATOR . substr($date, 6, 2) . '.log';
        
        $errors = [];
        $stats = [
            'total_errors' => 0,
            'download_errors' => 0,
            'cache_errors' => 0,
            'other_errors' => 0,
        ];
        
        if (file_exists($logFile)) {
            $lines = file($logFile);
            
            foreach ($lines as $line) {
                // 查找下载相关错误
                if (preg_match('/代理下载错误|下载失败|预缓存失败|缓存.*失败/i', $line)) {
                    $stats['total_errors']++;
                    if (preg_match('/下载失败|代理下载错误/i', $line)) {
                        $stats['download_errors']++;
                    } elseif (preg_match('/预缓存|缓存.*失败/i', $line)) {
                        $stats['cache_errors']++;
                    } else {
                        $stats['other_errors']++;
                    }
                    
                    // 提取视频ID
                    $videoId = null;
                    if (preg_match('/video_id["\']?\s*:\s*(\d+)/', $line, $matches)) {
                        $videoId = (int)$matches[1];
                    }
                    
                    // 提取错误信息
                    $errorMsg = '';
                    if (preg_match('/"error"["\']?\s*:\s*"([^"]+)"/', $line, $matches)) {
                        $errorMsg = $matches[1];
                    } elseif (preg_match('/下载失败[：:]\s*(.+?)(?:\s|$)/', $line, $matches)) {
                        $errorMsg = trim($matches[1]);
                    }
                    
                    // 提取URL
                    $fileUrl = null;
                    if (preg_match('/"file_url"["\']?\s*:\s*"([^"]+)"/', $line, $matches)) {
                        $fileUrl = $matches[1];
                    }
                    
                    // 提取时间
                    $time = '';
                    if (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $line, $matches)) {
                        $time = $matches[1];
                    }
                    
                    // 筛选关键词
                    if ($keyword && stripos($line, $keyword) === false) {
                        continue;
                    }
                    
                    $errors[] = [
                        'video_id' => $videoId,
                        'error' => $errorMsg ?: substr(trim($line), 0, 200),
                        'url' => $fileUrl,
                        'time' => $time ?: date('Y-m-d H:i:s'),
                        'raw' => substr(trim($line), 0, 500),
                    ];
                }
            }
            
            // 按时间倒序
            usort($errors, function($a, $b) {
                return strcmp($b['time'], $a['time']);
            });
        }
        
        // 分页
        $perPage = 20;
        $total = count($errors);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $errors = array_slice($errors, $offset, $perPage);
        
        // 获取视频信息
        foreach ($errors as &$error) {
            if ($error['video_id']) {
                $video = VideoModel::find($error['video_id']);
                if ($video) {
                    $error['video_title'] = $video->title;
                }
            }
            // 格式化时间显示（只显示前16个字符：YYYY-MM-DD HH:MM）
            if (!empty($error['time']) && strlen($error['time']) > 16) {
                $error['time_display'] = substr($error['time'], 0, 16);
            } else {
                $error['time_display'] = $error['time'] ?? '';
            }
        }
        
        // 获取可用的日志日期
        $availableDates = $this->getAvailableLogDates();
        
        // 格式化日期用于显示
        $dateFormatted = '';
        if (strlen($date) == 8) {
            $dateFormatted = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
        } else {
            $dateFormatted = date('Y-m-d');
            $date = date('Ymd');
        }
        
        // URL参数（用于分页链接）
        $urlParams = [];
        if ($date) {
            $urlParams[] = 'date=' . $date;
        }
        if ($keyword) {
            $urlParams[] = 'keyword=' . urlencode($keyword);
        }
        $urlSuffix = !empty($urlParams) ? '&' . implode('&', $urlParams) : '';
        
        return View::fetch('admin/download_log/index', [
            'errors' => $errors,
            'stats' => $stats,
            'page' => $page,
            'total_pages' => $totalPages,
            'keyword' => $keyword,
            'date' => $date,
            'date_formatted' => $dateFormatted,
            'url_suffix' => $urlSuffix,
            'available_dates' => $availableDates,
        ]);
    }
    
    /**
     * 获取可用的日志日期
     */
    private function getAvailableLogDates(): array
    {
        $dates = [];
        $logDir = runtime_path() . 'log';
        
        if (is_dir($logDir)) {
            $yearMonths = glob($logDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
            foreach ($yearMonths as $ymDir) {
                $yearMonth = basename($ymDir);
                $logFiles = glob($ymDir . DIRECTORY_SEPARATOR . '*.log');
                foreach ($logFiles as $logFile) {
                    $day = pathinfo($logFile, PATHINFO_FILENAME);
                    $dates[] = $yearMonth . $day;
                }
            }
        }
        
        rsort($dates);
        return array_slice($dates, 0, 30); // 最近30天
    }
    
    /**
     * 清除错误日志（标记为已处理）
     */
    public function clear()
    {
        // 这里可以实现清除逻辑，暂时只返回成功
        return json(['code' => 0, 'msg' => '功能开发中']);
    }
}

