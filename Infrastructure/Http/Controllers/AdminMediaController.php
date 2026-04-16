<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Shared\Services\Registry;
use Codefy\Framework\Http\BaseController;
use Codefy\Framework\Support\LocalStorage;
use elFinder;
use elFinderConnector;
use League\Flysystem\FilesystemOperator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\Session\SessionException;
use ReflectionException;

use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\is_user_logged_in;
use function App\Shared\Helpers\login_url;
use function App\Shared\Helpers\site_path;
use function App\Shared\Helpers\site_url;
use function array_merge;
use function Codefy\Framework\Helpers\view;
use function error_reporting;
use function Qubus\Security\Helpers\t__;

final class AdminMediaController extends BaseController
{
    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Exception
     */
    public function connector(): ResponseInterface
    {
        if (!is_user_logged_in()) {
            return JsonResponseFactory::create(data: 'invalid', status: 400);
        }

        if (current_user_can(perm: 'manage:media') === false) {
            return JsonResponseFactory::create(data: 'invalid', status: 400);
        }

        if (Devflow::$PHP->configContainer->boolean(key: 'elfinder.options.debug') === true) {
            error_reporting(error_level: 0);
        }

        $roots = Devflow::$PHP->configContainer->array(key: 'elfinder.roots', default: []);
        if (empty($roots)) {
            $dirs = Devflow::$PHP->configContainer->array(key: 'elfinder.dir');
            foreach ($dirs as $dir) {
                $roots[] = [
                    'driver' => 'LocalFileSystem',
                    'trashHash' => 't1_Lw',
                    'path' => site_path($dir),
                    'URL' => site_url(
                        path: 'sites/' . Registry::getInstance()->get('siteKey') . "/$dir"
                    ),
                    'alias' => t__(msgid: 'Media Library', domain: 'devflow'),
                    'accessControl' => Devflow::$PHP->configContainer->getConfigKey(key: 'elfinder.access'),
                    'tmbURL' => site_url('sites/' . Registry::getInstance()->get('siteKey') . "/{$dir}.tmb"),
                ];

                $roots[] = [
                    'id' => 1,
                    'driver' => 'Trash',
                    'path' => site_path('.trash'),
                    'tmbURL' => site_url('sites/' . Registry::getInstance()->get('siteKey') . '/.trash/.tmb/'),
                ];
            }

            $disks = Devflow::$PHP->configContainer->getConfigKey(key: 'elfinder.disks', default: []);
            foreach ($disks as $key => $root) {
                if (is_string($root)) {
                    $key = $root;
                    $root = [];
                }
                $disk = LocalStorage::disk($key);
                if ($disk instanceof FilesystemOperator) {
                    $defaults = [
                        'driver' => 'Flysystem',
                        'filesystem' => $disk,
                        'alias' => $key,
                        'accessControl' => Devflow::$PHP->configContainer->getConfigKey(key: 'elfinder.access')
                    ];
                    $root = array_merge($defaults, $root);
                    $roots[] = $root;
                }
            }
        }

        $rootOptions = Devflow::$PHP->configContainer->array(key: 'elfinder.root_options', default: []);
        foreach ($roots as $key => $root) {
            $roots[$key] = array_merge($rootOptions, $root);
        }

        $opts = Devflow::$PHP->configContainer->array(key: 'elfinder.options', default: []);
        $opts = array_merge($opts, ['roots' => $roots]);

        // run elFinder
        $connector = new elFinderConnector(new elFinder($opts));
        $connector->run();
        return JsonResponseFactory::create(data: 'ok');
    }

    /**
     * @return ResponseInterface|string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws SessionException
     * @throws \Exception
     */
    public function elFinder(): string|ResponseInterface
    {
        if (!is_user_logged_in()) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied.', domain: 'devflow')
            );

            return $this->redirect(login_url());
        }

        return view(
            template: 'framework::backend/elfinder',
            data: ['title' => t__(msgid: 'elFinder', domain: 'devflow')]
        );
    }
}
