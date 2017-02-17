<?php
/*
 * This file is part of the ProductSortColumn
 *
 * Copyright(c) 2017 izayoi256 All Rights Reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\ProductSortColumn\ServiceProvider;

use Eccube\Application;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Plugin\ProductSortColumn\Event\Event;
use Plugin\ProductSortColumn\Event\LegacyEvent;
use Silex\Application as BaseApplication;
use Silex\ServiceProviderInterface;

require_once(__DIR__ . '/../log.php');

class PluginServiceProvider implements ServiceProviderInterface
{
    public function register(BaseApplication $app)
    {
        $app->mount('', new \Plugin\ProductSortColumn\ControllerProvider\FrontControllerProvider());
        $app->mount(sprintf('/%s/', trim($app['config']['admin_route'])) , new \Plugin\ProductSortColumn\ControllerProvider\AdminControllerProvider());

        $app['eccube.plugin.product_sort_column.repository.info'] = $app->share(function () use ($app) {
            return $app['orm.em']->getRepository('Plugin\ProductSortColumn\Entity\Info');
        });

        $app['eccube.plugin.product_sort_column.repository.product_sort'] = $app->share(function () use ($app) {
            return $app['orm.em']->getRepository('Plugin\ProductSortColumn\Entity\ProductSort');
        });

        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new \Plugin\ProductSortColumn\Form\Type\Admin\ProductSortType($app);
            return $types;
        }));

        $app['form.type.extensions'] = $app->share($app->extend('form.type.extensions', function ($extensions) use ($app) {
            $extensions[] = new \Plugin\ProductSortColumn\Form\Extension\Admin\ProductTypeExtension($app);
            return $extensions;
        }));

        $app['eccube.plugin.product_sort_column.service.product_sort_column'] = $app->share(function () use ($app) {
            return new \Plugin\ProductSortColumn\Service\PluginService($app);
        });

        $app['eccube.plugin.product_sort_column.event.event'] = $app->share(function () use ($app) {
            return new Event($app);
        });

        $app['eccube.plugin.product_sort_column.event.legacy_event'] = $app->share(function () use ($app) {
            return new LegacyEvent($app);
        });

        $file = sprintf('%s/../Resource/locale/message.%s.yml', __DIR__, $app['locale']);
        $app['translator']->addResource('yaml', $file, $app['locale']);

        if (isset($app['config']['ProductSortColumn']['const'])) {
            $const = $app['config']['ProductSortColumn']['const'];
            $app['product_sort_column_config'] = $app->share(function () use ($const) {
                return $const;
            });
        }

        $app['monolog.logger.product_sort_column'] = $app->share(function ($app) {

            /** @var \Symfony\Bridge\Monolog\Logger $logger */
            $logger = new $app['monolog.logger.class']('product_sort_column');

            $filename = sprintf('%s/app/log/product_sort_column.log', $app['config']['root_dir']);
            $RotateHandler = new RotatingFileHandler($filename, $app['config']['log']['max_files'], Logger::INFO);
            $RotateHandler->setFilenameFormat('product_sort_column_{date}', 'Y-m-d');

            $logger->pushHandler(
                new FingersCrossedHandler(
                    $RotateHandler,
                    new ErrorLevelActivationStrategy(Logger::ERROR),
                    0,
                    true,
                    true,
                    Logger::INFO
                )
            );

            return $logger;
        });

        if (isset($app['console'])) {
            $app['console']->add(new \Plugin\ProductSortColumn\Command\PluginCommand());
        }
    }

    public function boot(BaseApplication $app)
    {
    }
}
