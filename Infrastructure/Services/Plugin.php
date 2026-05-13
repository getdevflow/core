<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use Qubus\Expressive\Database;
use Qubus\View\Renderer;

use function sprintf;

abstract class Plugin implements Extension
{
    public function __construct(protected Options $option, protected Database $dfdb, protected Renderer $view)
    {
    }

    /**
     * Code that should run on activation.
     *
     * @return void
     */
    public function onActivation(): void
    {
        return;
    }

    /**
     * Code that should run on deactivation.
     *
     * @return void
     */
    public function onDeactivation(): void
    {
        return;
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
