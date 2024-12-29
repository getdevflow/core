<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Devflow;
use App\Infrastructure\Persistence\Database;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Exception\Exception;
use Qubus\View\Native\NativeLoader;
use Qubus\View\Renderer;
use ReflectionException;

use function App\Shared\Helpers\dfdb;
use function sprintf;

abstract class Plugin implements Extension
{
    protected ?Options $option = null;

    protected ?Database $dfdb = null;

    protected ?Renderer $view = null;

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface|Exception
     */
    public function __construct(?Options $option = null, Database $dfdb = null, ?Renderer $view = null)
    {
        $this->option = $option ?? Options::factory();
        $this->dfdb = $dfdb ?? dfdb();
        $this->view = $view ?? new NativeLoader(Devflow::inst()::$APP->configContainer->getConfigKey(key: 'view.path'));
    }

    /**
     * Plugin's id.
     */
    protected function id(): string
    {
        return $this->meta()['id'];
    }

    /**
     * Plugin's name
     */
    protected function name(): string
    {
        return $this->meta()['name'];
    }

    /**
     * The plugin's directory path.
     *
     * @return string
     */
    protected function path(): string
    {
        return $this->meta()['path'];
    }

    /**
     * Plugin's route for submenu.
     */
    protected function route(): string
    {
        return sprintf('plugin/%s/', $this->meta()['id']);
    }

    /**
     * Plugin's url.
     */
    protected function url(): string
    {
        return $this->meta()['url'];
    }
}
