<?php
/*
 * This file is part of the ProductSortColumn
 *
 * Copyright(c) 2017 izayoi256 All Rights Reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\ProductSortColumn\ControllerProvider;

use Silex\Application;
use Silex\ControllerProviderInterface;

class AdminControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $c = $app['controllers_factory'];

        // 強制SSL
        if ($app['config']['force_ssl'] == \Eccube\Common\Constant::ENABLED) {
            $c->requireHttps();
        }

        $c->match('/plugin/ProductSortColumn/config', 'Plugin\ProductSortColumn\Controller\Admin\ConfigController::index')->bind('plugin_product_sort_column_config');

        return $c;
    }
}
