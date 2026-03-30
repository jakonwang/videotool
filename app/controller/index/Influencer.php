<?php
declare(strict_types=1);

namespace app\controller\index;

use app\BaseController;
use think\facade\View;

/**
 * 达人取片页（按分发 token）
 */
class Influencer extends BaseController
{
    public function index(string $token = '')
    {
        $token = $token !== '' ? $token : (string) $this->request->param('token', '');
        $token = trim($token);
        if ($token === '') {
            return response('链接无效', 404);
        }
        return View::fetch('index/influencer', [
            'token' => $token,
            'token_json' => json_encode($token, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
