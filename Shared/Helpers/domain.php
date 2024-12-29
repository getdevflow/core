<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Infrastructure\Services\Options;
use Codefy\Framework\Codefy;
use Gettext\Loader\MoLoader;
use Gettext\Translator;
use Gettext\TranslatorFunctions;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Filter;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function array_column;
use function Codefy\Framework\Helpers\base_path;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\public_path;
use function file_exists;
use function file_get_contents;
use function in_array;
use function Qubus\Support\Helpers\is_false__;
use function sprintf;

/**
 * Retrieves a list of available locales.
 *
 * @file App/Shared/Helpers/domain.php
 * @param string $active
 */
function cms_dropdown_languages(string $active = ''): void
{
    $locales = file_get_contents(base_path('locale/locale.json'));
    $json = json_decode($locales, true);
    foreach ($json as $locale) {
        echo '<option value="' . $locale['language'] . '"' . selected($active, $locale['language'], false) . '>'
        . $locale['native_name'] . '</option>';
    }
}

/**
 * Load's core, themes, and plugins translated strings.
 *
 * @file App/Shared/Helpers/domain.php
 * @return bool
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function load_devflow_textdomain(): bool
{
    // Instantiate MoLoader for loading .mo files.
    $loader = new MoLoader();
    // Get meta data from all installed plugins.
    $pluginInfo = plugin_info(public_path('plugins/'));
    // Retrieve the current locale.
    $locale = load_core_locale();
    // Set translation array for push.
    $translations = [];
    // Add .mo files to $translations array if plugin exists and is active
    foreach ($pluginInfo as $info) {
        // Locale domain
        $domain = $info['id'];
        // Absolute path to the .mo file.
        $path = $info['path'] . 'locale' . Codefy::$PHP::DS . $domain . '-' . $locale . '.mo';
        /**
         * Filter .mo file path for loading translations for a specific plugin text domain.
         */
        $mofile = Filter::getInstance()->applyFilter('load_plugin_textdomain_mofile', $path, $domain);
        if (in_array($info['className'], array_column(active_plugins(), 'plugin_classname'))) {
            if (file_exists($path)) {
                $translations[] = $loader->loadFile($mofile)->setDomain($domain);
            }
        }
    }
    // Add .mo files to $translations array if theme exists and is active.
    $themeInfo = theme_info(public_path('themes/'));
    foreach ($themeInfo as $info) {
        // Locale domain
        $domain = $info['id'];
        // Absolute path to the .mo file.
        $path = $info['path'] . 'locale' . Codefy::$PHP::DS . $domain . '-' . $locale . '.mo';
        /**
         * Filter .mo file path for loading translations for a specific plugin text domain.
         */
        $mofile = Filter::getInstance()->applyFilter('load_theme_textdomain_mofile', $path, $domain);
        //if ($info['className'] === get_theme()) {
        if (file_exists($path)) {
            $translations[] = $loader->loadFile($mofile)->setDomain($domain);
        }
        //}
    }
    /**
     * Filter .mo file path for loading translations for the core text domain.
     */
    $mofile = Filter::getInstance()->applyFilter(
        'load_core_textdomain_mofile',
        base_path(sprintf('locale/devflow-%s.mo', $locale)),
        $domain = 'devflow'
    );

    $translations[] = $loader->loadFile($mofile)->setDomain($domain);
    $gettext = Translator::createFromTranslations(...$translations);

    TranslatorFunctions::register($gettext);

    return true;
}

/**
 * Loads the child theme's translated strings.
 *
 * @param string $domain
 * @param false|string $path
 * @return bool
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function load_child_theme_textdomain(string $domain, false|string $path = false): bool
{
    if (is_false__($path)) {
        $path = css_directory();
    }

    return load_theme_textdomain($domain, $path);
}

/**
 * Loads the current or default locale.
 *
 * @file App/Shared/Helpers/domain.php
 * @return string The locale.
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 */
function load_core_locale(): string
{
    $option = Options::factory();
    $locale = $option->read(optionKey: 'site_locale', default: config(key: 'app.locale'));
    return Filter::getInstance()->applyFilter('core_locale', $locale);
}
