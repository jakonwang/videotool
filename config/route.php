<?php
// 路由配置文件
return [
    // 路由使用完整匹配
    'route_complete_match' => false,
    // 是否强制使用路由
    'url_route_must'        => false,
    // 合并路由规则
    'route_rule_merge'      => true,
    // 是否去除URL中的.html后缀
    'url_html_suffix'       => 'html',
    // 访问控制器层名称
    'controller_layer'      => 'controller',
    // 空控制器名
    'empty_controller'      => 'Error',
    // 操作方法后缀
    'action_suffix'         => '',
    // 默认的路由变量规则
    'default_route_pattern' => '[\w\.]+',
];

