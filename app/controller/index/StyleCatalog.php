<?php
declare(strict_types=1);

namespace app\controller\index;

use app\BaseController;
use think\facade\View;

/**
 * Mobile style catalog for customer sharing.
 */
class StyleCatalog extends BaseController
{
    public function index()
    {
        $entry = $this->request->baseFile();
        if ($entry === '' || $entry === '/') {
            $entry = '/index.php';
        }

        return View::fetch('index/style_catalog', [
            'h5_api_entry_js' => \json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
