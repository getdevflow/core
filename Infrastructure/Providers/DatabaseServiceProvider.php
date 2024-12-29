<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Infrastructure\Persistence\Database;
use App\Infrastructure\Persistence\NativePdoDatabase;
use App\Shared\Services\Registry;
use Codefy\Framework\Support\CodefyServiceProvider;
use Gettext\Translator;
use Gettext\TranslatorFunctions;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Filter;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\load_devflow_textdomain;

class DatabaseServiceProvider extends CodefyServiceProvider
{
    /**
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeException
     * @throws Exception
     */
    public function register(): void
    {
        $this->codefy->alias(original: Database::class, alias: NativePdoDatabase::class);
        $this->codefy->share(nameOrInstance: Database::class);

        /** @var Database $database */
        $database = $this->codefy->make(Database::class);

        if (!$this->codefy->isRunningInConsole()) {
            Filter::getInstance()->removeFilter('core_locale', function ($locale) {
                return '';
            });

            Filter::getInstance()->addFilter('core_locale', function ($locale) use ($database) {
                $sql = "SELECT option_value FROM {$database->prefix}option WHERE option_key = 'site_locale' LIMIT 1";
                $locale = $database->getVar($sql);
                return $locale;
            });
        }

        $translator = new Translator();
        TranslatorFunctions::register($translator);

        /** Do not touch. */
        Registry::getInstance()->set('dfdb', $database);

        if (!$this->codefy->isRunningInConsole()) {
            load_devflow_textdomain();
        }
    }
}
