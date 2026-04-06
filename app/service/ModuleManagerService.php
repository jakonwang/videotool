<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 模块管理服务
 */
class ModuleManagerService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private static function builtInModules(): array
    {
        return [
            'overview' => [
                'name' => 'overview',
                'title' => '概览',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
            ],
            'style_search' => [
                'name' => 'style_search',
                'title' => '寻款',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
            ],
            'creator_crm' => [
                'name' => 'creator_crm',
                'title' => '达人CRM',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
            ],
            'material_distribution' => [
                'name' => 'material_distribution',
                'title' => '素材分发',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
            ],
            'terminal_devices' => [
                'name' => 'terminal_devices',
                'title' => '终端设备',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
            ],
            'system_ops' => [
                'name' => 'system_ops',
                'title' => '系统管理',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
            ],
        ];
    }

    private static function extensionTableExists(): bool
    {
        try {
            Db::name('extensions')->where('id', 0)->find();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 扫描模块目录 + 内置模块并同步到数据库（若表存在）
     *
     * @return array<int, array<string, mixed>>
     */
    public static function scanModules(): array
    {
        $builtIns = self::builtInModules();
        $modules = $builtIns;

        $root = rtrim((string) root_path(), DIRECTORY_SEPARATOR);
        $dir = $root . DIRECTORY_SEPARATOR . 'extensions';
        if (is_dir($dir)) {
            $entries = scandir($dir);
            if (is_array($entries)) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $metaFile = $dir . DIRECTORY_SEPARATOR . $entry . DIRECTORY_SEPARATOR . 'module.json';
                    if (!is_file($metaFile)) {
                        continue;
                    }
                    $raw = @file_get_contents($metaFile);
                    if (!is_string($raw) || $raw === '') {
                        continue;
                    }
                    $json = json_decode($raw, true);
                    if (!is_array($json)) {
                        continue;
                    }
                    $name = isset($json['name']) ? trim((string) $json['name']) : '';
                    if ($name === '') {
                        $name = trim((string) $entry);
                    }
                    if ($name === '') {
                        continue;
                    }
                    $modules[$name] = [
                        'name' => $name,
                        'title' => (string) ($json['title'] ?? $name),
                        'version' => (string) ($json['version'] ?? '1.0.0'),
                        'default_enabled' => (int) ($json['default_enabled'] ?? 0),
                        'can_uninstall' => (int) ($json['can_uninstall'] ?? 1),
                    ];
                }
            }
        }

        if (self::extensionTableExists()) {
            foreach ($modules as $name => $meta) {
                $exists = Db::name('extensions')->where('name', $name)->find();
                if ($exists) {
                    Db::name('extensions')->where('name', $name)->update([
                        'title' => (string) $meta['title'],
                        'version' => (string) $meta['version'],
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    continue;
                }
                Db::name('extensions')->insert([
                    'name' => $name,
                    'title' => (string) $meta['title'],
                    'version' => (string) $meta['version'],
                    'is_enabled' => (int) $meta['default_enabled'],
                    'config_json' => json_encode([
                        'can_uninstall' => (int) $meta['can_uninstall'],
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $rows = [];
        foreach ($modules as $meta) {
            $meta['is_enabled'] = (int) $meta['default_enabled'];
            $rows[] = $meta;
        }

        if (self::extensionTableExists()) {
            $dbRows = Db::name('extensions')->order('id asc')->select()->toArray();
            foreach ($dbRows as &$row) {
                $cfg = [];
                if (!empty($row['config_json'])) {
                    $tmp = json_decode((string) $row['config_json'], true);
                    if (is_array($tmp)) {
                        $cfg = $tmp;
                    }
                }
                $row['can_uninstall'] = (int) ($cfg['can_uninstall'] ?? (isset($builtIns[$row['name']]) ? 0 : 1));
            }
            return $dbRows;
        }

        return $rows;
    }

    private static function runScriptIfExists(string $name, string $action): void
    {
        $root = rtrim((string) root_path(), DIRECTORY_SEPARATOR);
        $phpFile = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'extensions'
            . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $action . '.php';
        if (is_file($phpFile)) {
            $runner = include $phpFile;
            if (is_callable($runner)) {
                $runner();
            }
            return;
        }

        $sqlFile = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'extensions'
            . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $action . '.sql';
        if (is_file($sqlFile)) {
            $sql = (string) @file_get_contents($sqlFile);
            if ($sql !== '') {
                $parts = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
                foreach ($parts as $stmt) {
                    $stmt = trim((string) $stmt);
                    if ($stmt !== '') {
                        Db::execute($stmt);
                    }
                }
            }
        }
    }

    public static function install(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'message' => 'module name is required'];
        }
        self::scanModules();
        if (!self::extensionTableExists()) {
            return ['ok' => false, 'message' => 'extensions table not found'];
        }
        $row = Db::name('extensions')->where('name', $name)->find();
        if (!$row) {
            return ['ok' => false, 'message' => 'module not found'];
        }

        try {
            self::runScriptIfExists($name, 'install');
            Db::name('extensions')->where('name', $name)->update([
                'is_enabled' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public static function uninstall(string $name, bool $purgeData = false): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'message' => 'module name is required'];
        }
        self::scanModules();
        if (!self::extensionTableExists()) {
            return ['ok' => false, 'message' => 'extensions table not found'];
        }
        $row = Db::name('extensions')->where('name', $name)->find();
        if (!$row) {
            return ['ok' => false, 'message' => 'module not found'];
        }

        $cfg = [];
        if (!empty($row['config_json'])) {
            $cfg = json_decode((string) $row['config_json'], true) ?: [];
        }
        $canUninstall = (int) ($cfg['can_uninstall'] ?? 1);
        if ($canUninstall !== 1) {
            return ['ok' => false, 'message' => 'core module cannot be uninstalled'];
        }

        try {
            if ($purgeData) {
                self::runScriptIfExists($name, 'uninstall');
            }
            Db::name('extensions')->where('name', $name)->update([
                'is_enabled' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public static function toggle(string $name, int $isEnabled): array
    {
        $name = trim($name);
        $isEnabled = $isEnabled === 1 ? 1 : 0;
        if ($name === '') {
            return ['ok' => false, 'message' => 'module name is required'];
        }
        self::scanModules();
        if (!self::extensionTableExists()) {
            return ['ok' => false, 'message' => 'extensions table not found'];
        }
        $row = Db::name('extensions')->where('name', $name)->find();
        if (!$row) {
            return ['ok' => false, 'message' => 'module not found'];
        }
        Db::name('extensions')->where('name', $name)->update([
            'is_enabled' => $isEnabled,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true];
    }

    /**
     * @return array<string, int>
     */
    private static function enabledModuleMap(): array
    {
        $map = [];
        $rows = self::scanModules();
        foreach ($rows as $row) {
            $map[(string) $row['name']] = (int) ($row['is_enabled'] ?? 0);
        }
        return $map;
    }

    /**
     * 后台侧栏（仅返回已启用模块相关菜单）
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getEnabledMenus(string $currentController = '', string $currentAction = ''): array
    {
        $currentController = strtolower(trim($currentController));
        $currentAction = strtolower(trim($currentAction));
        $isBatchUploadAction = in_array($currentAction, ['batchupload', 'batch_upload', 'batch-upload'], true);
        $enabled = self::enabledModuleMap();

        $menus = [];

        if (($enabled['overview'] ?? 0) === 1) {
            $menus[] = [
                'section_i18n' => 'admin.menu.overview',
                'items' => [
                    [
                        'kind' => 'link',
                        'href' => '/admin.php',
                        'icon' => 'layout-dashboard',
                        'text_i18n' => 'admin.menu.dashboard',
                        'active' => $currentController === 'index',
                    ],
                ],
            ];
        }

        if (($enabled['style_search'] ?? 0) === 1) {
            $menus[] = [
                'section_i18n' => 'admin.menu.groupSearch',
                'items' => [
                    [
                        'kind' => 'link',
                        'href' => '/admin.php/product_search',
                        'icon' => 'camera',
                        'text_i18n' => 'admin.menu.styleSearch',
                        'active' => $currentController === 'productsearch',
                    ],
                ],
            ];
        }

        if (($enabled['creator_crm'] ?? 0) === 1) {
            $creatorChildren = [
                [
                    'href' => '/admin.php/influencer',
                    'icon' => 'users',
                    'text_i18n' => 'admin.menu.influencerList',
                    'active' => $currentController === 'influencer',
                ],
                [
                    'href' => '/admin.php/distribute',
                    'icon' => 'link-2',
                    'text_i18n' => 'admin.menu.distribute',
                    'active' => $currentController === 'distribute',
                ],
                [
                    'href' => '/admin.php/category',
                    'icon' => 'tag',
                    'text_i18n' => 'admin.menu.category',
                    'active' => $currentController === 'category',
                ],
                [
                    'href' => '/admin.php/message_template',
                    'icon' => 'message-circle',
                    'text_i18n' => 'admin.menu.messageTemplates',
                    'active' => $currentController === 'messagetemplate',
                ],
            ];
            $expanded = false;
            foreach ($creatorChildren as $child) {
                if (!empty($child['active'])) {
                    $expanded = true;
                    break;
                }
            }
            $menus[] = [
                'section_i18n' => 'admin.menu.groupCreator',
                'items' => [
                    [
                        'kind' => 'group',
                        'id' => 'creator',
                        'icon' => 'users',
                        'text_i18n' => 'admin.menu.groupCreatorMenu',
                        'expanded' => $expanded,
                        'children' => $creatorChildren,
                    ],
                ],
            ];
        }

        if (($enabled['material_distribution'] ?? 0) === 1) {
            $materialChildren = [
                [
                    'href' => '/admin.php/video',
                    'icon' => 'video',
                    'text_i18n' => 'admin.menu.video',
                    'active' => $currentController === 'video' && !$isBatchUploadAction,
                ],
                [
                    'href' => '/admin.php/video/batchUpload',
                    'icon' => 'cloud-upload',
                    'text_i18n' => 'admin.menu.upload',
                    'active' => $currentController === 'video' && $isBatchUploadAction,
                ],
                [
                    'href' => '/admin.php/product',
                    'icon' => 'shopping-bag',
                    'text_i18n' => 'admin.menu.product',
                    'active' => $currentController === 'product',
                ],
            ];
            $expanded = false;
            foreach ($materialChildren as $child) {
                if (!empty($child['active'])) {
                    $expanded = true;
                    break;
                }
            }
            $menus[] = [
                'section_i18n' => 'admin.menu.material',
                'items' => [
                    [
                        'kind' => 'group',
                        'id' => 'material',
                        'icon' => 'folder',
                        'text_i18n' => 'admin.menu.materialMenu',
                        'expanded' => $expanded,
                        'children' => $materialChildren,
                    ],
                ],
            ];
        }

        if (($enabled['terminal_devices'] ?? 0) === 1) {
            $terminalChildren = [
                [
                    'href' => '/admin.php/platform',
                    'icon' => 'layers',
                    'text_i18n' => 'admin.menu.platform',
                    'active' => $currentController === 'platform',
                ],
                [
                    'href' => '/admin.php/device',
                    'icon' => 'smartphone',
                    'text_i18n' => 'admin.menu.device',
                    'active' => $currentController === 'device',
                ],
            ];
            $expanded = false;
            foreach ($terminalChildren as $child) {
                if (!empty($child['active'])) {
                    $expanded = true;
                    break;
                }
            }
            $menus[] = [
                'section_i18n' => 'admin.menu.terminalSection',
                'items' => [
                    [
                        'kind' => 'group',
                        'id' => 'terminal',
                        'icon' => 'monitor-smartphone',
                        'text_i18n' => 'admin.menu.terminal',
                        'expanded' => $expanded,
                        'children' => $terminalChildren,
                    ],
                ],
            ];
        }

        if (($enabled['system_ops'] ?? 0) === 1) {
            $systemChildren = [
                [
                    'href' => '/admin.php/settings',
                    'icon' => 'sliders-horizontal',
                    'text_i18n' => 'admin.menu.settings',
                    'active' => $currentController === 'settings',
                ],
                [
                    'href' => '/admin.php/user',
                    'icon' => 'users',
                    'text_i18n' => 'admin.menu.user',
                    'active' => $currentController === 'user',
                ],
                [
                    'href' => '/admin.php/client_license',
                    'icon' => 'key',
                    'text_i18n' => 'admin.menu.clientLicense',
                    'active' => $currentController === 'clientlicense',
                ],
                [
                    'href' => '/admin.php/client_version',
                    'icon' => 'package',
                    'text_i18n' => 'admin.menu.clientVersion',
                    'active' => $currentController === 'clientversion',
                ],
                [
                    'href' => '/admin.php/cache',
                    'icon' => 'database',
                    'text_i18n' => 'admin.menu.cache',
                    'active' => $currentController === 'cache',
                ],
                [
                    'href' => '/admin.php/downloadLog',
                    'icon' => 'triangle-alert',
                    'text_i18n' => 'admin.menu.errors',
                    'active' => $currentController === 'downloadlog',
                ],
                [
                    'href' => '/admin.php/extension',
                    'icon' => 'puzzle',
                    'text_i18n' => 'admin.menu.extensionManager',
                    'active' => $currentController === 'extension',
                ],
                [
                    'href' => '/admin.php/auth/logout',
                    'icon' => 'log-out',
                    'text_i18n' => 'admin.menu.logout',
                    'active' => false,
                ],
            ];
            $expanded = false;
            foreach ($systemChildren as $child) {
                if (!empty($child['active'])) {
                    $expanded = true;
                    break;
                }
            }
            $menus[] = [
                'section_i18n' => 'admin.menu.system',
                'items' => [
                    [
                        'kind' => 'group',
                        'id' => 'system',
                        'icon' => 'settings',
                        'text_i18n' => 'admin.menu.system',
                        'expanded' => $expanded,
                        'children' => $systemChildren,
                    ],
                ],
            ];
        }

        return $menus;
    }
}

