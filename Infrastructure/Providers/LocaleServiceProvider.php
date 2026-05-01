<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Expressive\Database;
use Codefy\Framework\Support\CodefyServiceProvider;
use Gettext\Translator;
use Gettext\TranslatorFunctions;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Filter;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\load_devflow_textdomain;
use function Qubus\Security\Helpers\esc_html;

class LocaleServiceProvider extends CodefyServiceProvider
{
    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws TypeException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function register(): void
    {
        $translator = new Translator();
        TranslatorFunctions::register($translator);

        /** @var Database $database */
        $database = $this->codefy->make(name: Database::class);

        if (!$this->codefy->isRunningInConsole()) {
            Filter::getInstance()->removeFilter(hook: 'core_locale', callback: function ($locale) {
                return '';
            });

            Filter::getInstance()->addFilter(hook: 'core_locale', callback: function ($locale) use ($database) {
                $sql = "SELECT option_value FROM {$database->prefix}option WHERE option_key = 'site_locale' LIMIT 1";
                $locale = $database->getVar($sql);
                return esc_html($locale);
            });
        }

        /** Do not touch. */

        if (!$this->codefy->isRunningInConsole()) {
            load_devflow_textdomain();
        }
    }
}
