<?php
// 应用公共文件

/**
 * 获取客户端IP
 */
/**
 * 视频封面：无则默认图（供模板 {:video_cover_pick()} 使用）
 */
function video_cover_pick(?string $coverUrl): string
{
    return \app\service\VideoCoverService::pick($coverUrl);
}

function get_client_ip()
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

