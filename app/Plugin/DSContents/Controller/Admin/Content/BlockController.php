<?php
/*
* This file is part of EC-CUBE
*
* Copyright(c) 2000-2016 LOCKON CO.,LTD. All Rights Reserved.
* http://www.lockon.co.jp/
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\DSContents\Controller\Admin\Content;

use Eccube\Application;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\DeviceType;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Util\Str;
use Plugin\DSContents\Event\PluginEvents;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BlockController extends AbstractController
{
    public function index(Application $app, Request $request)
    {
        $DeviceType = $app['eccube.repository.master.device_type']
            ->find(DeviceType::DEVICE_TYPE_SP);

        // 登録されているブロック一覧の取得
        $Blocks = $app['eccube.repository.block']->getList($DeviceType);

        $event = new EventArgs(
            array(
                'DeviceType' => $DeviceType,
                'Blocks' => $Blocks,
            ),
            $request
        );
        $app['eccube.event.dispatcher']->dispatch(EccubeEvents::ADMIN_CONTENT_BLOCK_INDEX_COMPLETE, $event);
        $app['eccube.event.dispatcher']->dispatch(PluginEvents::ADMIN_CONTENT_BLOCK_INDEX_COMPLETE, $event);

        return $app->render('DSContents/Resource/template/admin/Content/block.twig', array(
            'Blocks' => $Blocks,
        ));
    }

    public function edit(Application $app, Request $request, $id = null)
    {
        $DeviceType = $app['eccube.repository.master.device_type']
            ->find(DeviceType::DEVICE_TYPE_SP);

        $Block = $app['eccube.repository.block']
            ->findOrCreate($id, $DeviceType);

        if (!$Block) {
            throw new NotFoundHttpException();
        }

        $builder = $app['form.factory']
            ->createBuilder('plugin_DSContents_block', $Block);

        $html = '';
        $previous_filename = null;
        $deletable = $Block->getDeletableFlg();

        if ($id) {
            // テンプレートファイルの取得
            $previous_filename = $Block->getFileName();
            $file = $app['eccube.repository.block']
                ->getReadTemplateFile($previous_filename, $deletable);
            $html = $file['tpl_data'];
        }

        $event = new EventArgs(
            array(
                'builder' => $builder,
                'DeviceType' => $DeviceType,
                'Block' => $Block,
                'html' => $html,
            ),
            $request
        );
        $app['eccube.event.dispatcher']->dispatch(EccubeEvents::ADMIN_CONTENT_BLOCK_EDIT_INITIALIZE, $event);
        $app['eccube.event.dispatcher']->dispatch(PluginEvents::ADMIN_CONTENT_BLOCK_EDIT_INITIALIZE, $event);
        $html = $event->getArgument('html');

        $form = $builder->getForm();

        $form->get('block_html')->setData($html);

        if ($app['request']->getMethod() === 'POST') {
            $form->handleRequest($app['request']);
            if ($form->isValid()) {
                $Block = $form->getData();

                // DB登録
                $app['orm.em']->persist($Block);
                $app['orm.em']->flush();

                // ファイル生成・更新
                $tplDir = $app['config']['block_realdir'];

                $filePath = $tplDir . '/' . $Block->getFileName() . '.twig';

                $fs = new Filesystem();
                $blockData = $form->get('block_html')->getData();
                $blockData = Str::convertLineFeed($blockData);
                $fs->dumpFile($filePath, $blockData);
                // 更新でファイル名を変更した場合、以前のファイルを削除
                if ($Block->getFileName() != $previous_filename && !is_null($previous_filename)) {
                    $oldFilePath = $tplDir . '/' . $previous_filename . '.twig';
                    if ($fs->exists($oldFilePath)) {
                        $fs->remove($oldFilePath);
                    }
                }

                \Eccube\Util\Cache::clear($app, false);

                $event = new EventArgs(
                    array(
                        'form' => $form,
                        'Block' => $Block,
                    ),
                    $request
                );
                $app['eccube.event.dispatcher']->dispatch(EccubeEvents::ADMIN_CONTENT_BLOCK_EDIT_COMPLETE, $event);
                $app['eccube.event.dispatcher']->dispatch(PluginEvents::ADMIN_CONTENT_BLOCK_EDIT_COMPLETE, $event);

                $app->addSuccess('admin.register.complete', 'admin');

                return $app->redirect($app->url('plugin_DSContents_admin_content_block_edit', array('id' => $Block->getId())));
            }
        }


        return $app->render('DSContents/Resource/template/admin/Content/block_edit.twig', array(
            'form' => $form->createView(),
            'block_id' => $id,
            'deletable' => $deletable,
        ));
    }

    public function delete(Application $app, Request $request, $id)
    {
        $this->isTokenValid($app);

        $DeviceType = $app['eccube.repository.master.device_type']
            ->find(DeviceType::DEVICE_TYPE_SP);

        $Block = $app['eccube.repository.block']->findOneBy(array(
            'id' => $id,
            'DeviceType' => $DeviceType
        ));

        if (!$Block) {
            $app->deleteMessage();
            return $app->redirect($app->url('plugin_DSContents_admin_content_block'));
        }

        // ユーザーが作ったブロックのみ削除する
        // テンプレートが変更されていた場合、DBからはブロック削除されるがtwigファイルは残る
        if ($Block->getDeletableFlg() > 0) {
            $tplDir = $app['config']['block_realdir'];
            $file = $tplDir . '/' . $Block->getFileName() . '.twig';
            $fs = new Filesystem();
            if ($fs->exists($file)) {
                $fs->remove($file);
            }
            $app['orm.em']->remove($Block);
            $app['orm.em']->flush();

            $event = new EventArgs(
                array(
                    'Block' => $Block,
                ),
                $request
            );
            $app['eccube.event.dispatcher']->dispatch(EccubeEvents::ADMIN_CONTENT_BLOCK_DELETE_COMPLETE, $event);
            $app['eccube.event.dispatcher']->dispatch(PluginEvents::ADMIN_CONTENT_BLOCK_DELETE_COMPLETE, $event);

            $app->addSuccess('admin.delete.complete', 'admin');
            \Eccube\Util\Cache::clear($app, false);
        }


        return $app->redirect($app->url('plugin_DSContents_admin_content_block'));
    }
}
