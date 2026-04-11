<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use Qubus\Expressive\Database;
use Qubus\View\Renderer;

use function sprintf;

abstract class Theme implements Extension
{
    public function __construct(protected Options $option, protected Database $dfdb, protected Renderer $view)
    {
    }

    /**
     * Theme's id.
     */
    protected function id(): string
    {
        return $this->meta()['id'];
    }

    /**
     * Theme's name
     */
    protected function name(): string
    {
        return $this->meta()['name'];
    }

    /**
     * The theme's directory path.
     *
     * @return string
     */
    protected function path(): string
    {
        return $this->meta()['path'];
    }

    /**
     * Theme's route for submenu.
     */
    protected function route(): string
    {
        return sprintf('theme/%s/', $this->meta()['id']);
    }

    /**
     * Theme's url.
     */
    protected function url(): string
    {
        return $this->meta()['url'];
    }
}
