<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Persistence\Database;
use App\Infrastructure\Services\NativePhpCookies;
use App\Infrastructure\Services\Options;
use App\Shared\Services\Image;
use Codefy\Framework\Application;
use Codefy\Framework\Codefy;
use Qubus\Exception\Data\TypeException;
use Qubus\View\Renderer;

/**
 * @property-read Database $dfdb
 * @property-read Image $image
 * @property-read Options $option
 * @property-read NativePhpCookies $cookies
 * @property-read Renderer $view
 */
final class Devflow extends Codefy
{
    public static Application|null|Devflow $APP = null;

    /**
     * @throws TypeException
     */
    private function __construct()
    {
        Devflow::$APP = Application::getInstance();
        $this->init();
    }

    public static function inst(): Application|Devflow
    {
        return new self();
    }

    private function init(): void
    {
        $aliases = [
            'dfdb' => Database::class,
            'image' => Image::class,
            'option' => Options::class,
            'cookies' => NativePhpCookies::class,
            'view' => Renderer::class,
        ];

        foreach ($aliases as $property => $name) {
            $this->{$property} = Application::$APP->make(name: $name);
        }
    }

    public function release(): string
    {
        return '2.0.0-beta.1';
    }
}
