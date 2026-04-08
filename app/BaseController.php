<?php
declare (strict_types = 1);

namespace app;

use app\service\AdminAuthService;
use app\service\ModuleManagerService;
use think\App;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\View;
use think\Validate;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * @var array<string, bool>
     */
    private static array $TENANT_COLUMN_CACHE = [];

    /**
     * @var array<string, string>
     */
    private const ERROR_KEY_MAP = [
        '仅支持 POST' => 'common.onlyPost',
        'only_post' => 'common.onlyPost',
        '无效 id' => 'common.invalidId',
        '无效 task_id' => 'common.invalidId',
        '无效 influencer_id' => 'common.invalidId',
        'invalid_params' => 'common.invalidParams',
        '参数错误' => 'common.invalidParams',
        '参数错误' => 'common.invalidParams',
        '记录不存在' => 'common.notFound',
        '任务不存在' => 'common.notFound',
        'not_found' => 'common.notFound',
        '请上传文件' => 'common.pickFile',
        '请选择文件' => 'common.pickFile',
        'file_required' => 'common.pickFile',
        'csv_only' => 'page.dataImport.csvOnly',
        'save_failed' => 'common.saveFailed',
        '保存失败' => 'common.saveFailed',
        '删除失败' => 'common.deleteFailed',
        'loading_failed' => 'common.loadingFailed',
        '会话已过期' => 'common.sessionExpired',
        '未登录' => 'common.sessionExpired',
    ];
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // Provide common view vars for all controllers.
        // Avoid template compiling `$Think.config.app.version` into invalid PHP on some environments.
        if (class_exists(View::class)) {
            try {
                $ver = config('app.version');
                View::assign('app_version', $ver ? (string) $ver : '1.0.2');
                View::assign('app_year', date('Y'));
                $curController = strtolower((string) $this->request->controller());
                $curAction = strtolower((string) $this->request->action());
                View::assign('current_controller', $curController);
                View::assign('current_action', $curAction);
                View::assign('sidebar_sections', ModuleManagerService::getEnabledMenus($curController, $curAction));
            } catch (\Throwable $e) {
                View::assign('app_version', '1.0.2');
                View::assign('app_year', date('Y'));
                View::assign('current_controller', '');
                View::assign('current_action', '');
                View::assign('sidebar_sections', []);
            }
        }

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {}

    protected function apiJsonOk(array $data = [], string $msg = 'ok')
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    protected function apiJsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        $resolvedKey = trim($errorKey);
        if ($resolvedKey === '') {
            $resolvedKey = $this->inferErrorKey($msg);
        }

        return json([
            'code' => $code,
            'msg' => $msg,
            'error_key' => $resolvedKey,
            'data' => $data,
        ]);
    }

    protected function inferErrorKey(string $msg): string
    {
        $raw = trim($msg);
        if ($raw === '') {
            return '';
        }
        foreach (self::ERROR_KEY_MAP as $needle => $key) {
            if (str_contains($raw, $needle)) {
                return $key;
            }
        }

        return '';
    }

    protected function currentTenantId(): int
    {
        return AdminAuthService::tenantId();
    }

    protected function tableHasTenantId(string $table): bool
    {
        $name = strtolower(trim($table));
        if ($name === '') {
            return false;
        }
        if (array_key_exists($name, self::$TENANT_COLUMN_CACHE)) {
            return self::$TENANT_COLUMN_CACHE[$name];
        }
        try {
            $fields = Db::name($name)->getFields();
            $has = is_array($fields) && array_key_exists('tenant_id', $fields);
        } catch (\Throwable $e) {
            $has = false;
        }
        self::$TENANT_COLUMN_CACHE[$name] = $has;

        return $has;
    }

    protected function scopeTenant($query, string $table)
    {
        if ($this->tableHasTenantId($table)) {
            $query->where('tenant_id', $this->currentTenantId());
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function withTenantPayload(array $payload, string $table): array
    {
        if ($this->tableHasTenantId($table) && !array_key_exists('tenant_id', $payload)) {
            $payload['tenant_id'] = $this->currentTenantId();
        }

        return $payload;
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }
}

