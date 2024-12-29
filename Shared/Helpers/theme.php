<?php

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Infrastructure\Services\Options;
use App\Shared\Services\PhpFileParser;
use Codefy\Framework\Factory\FileLoggerFactory;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\EventDispatcher\ActionFilter\Filter;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function basename;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\public_path;
use function dirname;
use function glob;
use function is_string;
use function ltrim;
use function Qubus\Support\Helpers\add_trailing_slash;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;
use function sprintf;

/**
 * Retrieve name of the current theme.
 *
 * @file App/Shared/Helpers/theme.php
 * @return string Theme name.
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws InvalidArgumentException
 * @throws Exception
 * @throws ReflectionException
 */
function get_theme(): string
{
    $option = Options::factory();

    if ($option->exists(optionKey: 'site_theme')) {
        $siteTheme = $option->read(optionKey: 'site_theme');
    } else {
        $siteTheme = '';
    }
    /**
     * Filters the name of the current theme.
     *
     * @param string $theme Current theme's directory name.
     */
    return Filter::getInstance()->applyFilter('theme', $siteTheme);
}

/**
 * Retrieve active theme's name.
 *
 * @return mixed|string
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function theme_name(): mixed
{
    $activeTheme = get_theme();
    if (!$activeTheme) {
        return '';
    }

    $meta = Devflow::inst()::$APP->execute([$activeTheme, 'meta']);
    return $meta['basename'];
}

/**
 * Returns full base url of the themes' directory.
 *
 * @file App/Shared/Helpers/theme.php
 * @param string $path  Optional. Extra path appended to the end of the URL, including
 *                      the relative directory if $theme is supplied. Default empty.
 * @param string $theme Optional. A full path to a file inside a theme.
 *                      The URL will be relative to its directory. Default empty.
 *                      Typically, this is done by passing `__FILE__` as the argument.
 * @return string Themes' URL link with optional paths appended.
 * @throws Exception
 * @throws ReflectionException
 */
function theme_url(string $path = '', string $theme = ''): string
{
    $path = normalize_path($path ?? '');
    $theme = normalize_path($theme ?? '');

    $themeUrl = site_url('themes/');

    $url = set_url_scheme($themeUrl);

    if (!empty($theme) && is_string($theme)) {
        $folder = basename(dirname($theme));
        if ('.' != $folder) {
            $url .= ltrim($folder, '/');
        }
    }

    if ($path && is_string($path)) {
        $url .= '/' . ltrim($path, '/');
    }

    /**
     * Filters the URL to the themes' directory.
     *
     * @param string $url     The complete URL to the themes' directory including scheme and path.
     * @param string $path    Path relative to the URL to the themes' directory. Blank string
     *                        if no path is specified.
     * @param string $theme   The theme file path to be relative to. Blank string if no theme
     *                        is specified.
     */
    return Filter::getInstance()->applyFilter('themes_url', $url, $path, $theme);
}

/**
 * Returns theme directory URI.
 *
 * @file App/Shared/Helpers/theme.php
 * @uses \Qubus\EventDispatcher\ActionFilter\Filter::getInstance()->applyFilter() Calls 'theme_directory_uri' filter.
 * @return string Devflow theme directory uri.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function theme_directory_uri(): string
{
    if (!get_theme()) {
        return '';
    }

    $theme = str_replace('%2F', '/', rawurlencode(theme_name()));
    $themeRootUri = theme_url();
    $themeDirUri = $themeRootUri . $theme . '/';
    return Filter::getInstance()->applyFilter('theme_directory_uri', $themeDirUri, $theme, $themeRootUri);
}

/**
 * Returns javascript directory uri.
 *
 * @file App/Shared/Helpers/theme.php
 * @uses \Qubus\EventDispatcher\ActionFilter\Filter::getInstance()->applyFilter() Calls 'javascript_directory_uri' filter.
 * @return string Devflow javascript url.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function javascript_directory_uri(): string
{
    if (!get_theme()) {
        return '';
    }
    $theme = str_replace('%2F', '/', rawurlencode(theme_name()));
    $javascriptRootUri = theme_url();
    $javascriptDirUri = $javascriptRootUri . $theme . '/assets/js/';
    return Filter::getInstance()->applyFilter(
        'javascript_directory_uri',
        $javascriptDirUri,
        $theme,
        $javascriptRootUri
    );
}

/**
 * Returns raw theme root relative to the supplied path or filename.
 * @param string $pathOrFilename
 * @return string
 */
function raw_theme_root(string $pathOrFilename): string
{
    return dirname($pathOrFilename);
}

/**
 * Get the filesystem directory path (with trailing slash) for the theme __FILE__ passed in.
 *
 * @param string|null $filename The filename of the theme (__FILE__).
 * @return string The filesystem path of the directory that contains the theme.
 * @throws Exception
 * @throws ReflectionException
 */
function theme_root(?string $filename = ''): string
{
    $themeRoot = '';
    if ('' !== $filename) {
        $themeRoot = raw_theme_root($filename) . '/';
    }

    if ('' === $themeRoot) {
        $themeRoot = public_path('themes/');
    }
    /**
     * Filters the absolute path to the themes' directory.
     *
     * @param string $themeRoot Absolute path to themes' directory.
     */
    return Filter::getInstance()->applyFilter('theme_root', $themeRoot);
}

/**
 * Retrieve less directory uri.
 *
 * @file App/Shared/Helpers/theme.php
 * @uses \Qubus\EventDispatcher\ActionFilter\Filter::getInstance()->applyFilter() Calls 'less_directory_uri' filter.
 * @return string Devflow less url.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function less_directory_uri(): string
{
    if (!get_theme()) {
        return '';
    }
    $theme = str_replace('%2F', '/', rawurlencode(theme_name()));
    $lessRootUri = theme_url();
    $lessDirUri = $lessRootUri . $theme . '/assets/less/';
    return Filter::getInstance()->applyFilter('less_directory_uri', $lessDirUri, $theme, $lessRootUri);
}

/**
 * @return string
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function css_directory(): string
{
    $style = theme_name();
    $themeRoot = theme_root($style);
    $styleDir = add_trailing_slash("$themeRoot/$style");
    /**
     * Filters the stylesheet directory path for the active theme.
     *
     * @param string $styleDir  Absolute path to the active theme.
     * @param string $style     Directory name of the active theme.
     * @param string $themeRoot Absolute path to themes directory.
     */
    return Filter::getInstance()->applyFilter('stylesheet_directory', $styleDir, $style, $themeRoot);
}

/**
 * Return css directory uri.
 *
 * @file App/Shared/Helpers/theme.php
 * @uses \Qubus\EventDispatcher\ActionFilter\Filter::getInstance()->applyFilter() Calls 'css_directory_uri' filter.
 * @return string Devflow css url.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function css_directory_uri(): string
{
    if (!get_theme()) {
        return '';
    }
    $theme = str_replace('%2F', '/', rawurlencode(theme_name()));
    $cssRootUri = theme_url();
    $cssDirUri = $cssRootUri . $theme . '/assets/css/';
    return Filter::getInstance()->applyFilter('css_directory_uri', $cssDirUri, $theme, $cssRootUri);
}

/**
 * Retrieve image directory uri.
 *
 * @file App/Shared/Helpers/theme.php
 * @uses \Qubus\EventDispatcher\ActionFilter\Filter::getInstance()->applyFilter() Calls 'image_directory_uri' filter.
 * @return string Devflow image url.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function image_directory_uri(): string
{
    if (!get_theme()) {
        return '';
    }
    $theme = str_replace('%2F', '/', rawurlencode(theme_name()));
    $imageRootUri = theme_url();
    $imageDirUri = $imageRootUri . $theme . '/assets/images/';
    return Filter::getInstance()->applyFilter('image_directory_uri', $imageDirUri, $theme, $imageRootUri);
}

/**
 * Retrieves metadata about a theme.
 *
 * @file App/Shared/Helpers/theme.php
 * @access private
 * @param string $themesDir
 * @return array
 * @throws TypeException
 */
function theme_info(string $themesDir = ''): array
{
    $info = [];
    $dir = glob($themesDir . '*/*Theme.php');
    foreach ($dir as $theme) {
        $class = PhpFileParser::classObjectFromFile($theme);
        $info[] = Devflow::inst()::$APP->execute([$class, 'meta']);
    }

    return $info;
}

/**
 * Activates a specific theme that is called by $_GET['id'] variable.
 *
 * @file App/Shared/Helpers/theme.php
 * @param string $theme ID of the theme to activate
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException|InvalidArgumentException
 */
function activate_theme(string $theme): void
{
    try {
        Options::factory()->update(optionKey: 'site_theme', newvalue: $theme);
    } catch (PDOException | \Exception $ex) {
        FileLoggerFactory::getLogger()->error(
            sprintf(
                'THEMEACTIVATE[insert]: %s',
                $ex->getMessage()
            ),
            [
                'theme' => 'activate'
            ]
        );
    }
}

/**
 * Deactivates a theme.
 *
 * @file App/Shared/Helpers/theme.php
 * @return void
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function deactivate_theme(): void
{
    try {
        Options::factory()->delete(name: 'site_theme');
    } catch (PDOException | \Exception $ex) {
        FileLoggerFactory::getLogger()->error(
            sprintf(
                'THEMEDEACTIVATE[delete]: %s',
                $ex->getMessage()
            ),
            [
                'theme' => 'deactivate'
            ]
        );
    }
}

/**
 * Checks if a theme is active.
 *
 * @param string $theme
 * @return bool
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws Exception
 */
function is_theme_active(string $theme = ''): bool
{
    if ('' === $theme) {
        return false;
    }

    $option = Options::factory();

    if ($option->exists(optionKey: 'site_theme') && $option->read(optionKey: 'site_theme') === $theme) {
        return true;
    }

    return false;
}

/**
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws Exception
 */
function load_active_theme(): void
{
    $activeTheme = Options::factory()->read('site_theme');

    if ('' !== $activeTheme && !is_null__($activeTheme) && !is_false__($activeTheme)) {
        Devflow::inst()::$APP->execute([$activeTheme, 'handle']);

        /**
         * Fires once the activated theme has loaded.
         *
         * @param $string $plugin Class name of the plugin that was loaded.
         */
        Action::getInstance()->doAction('theme_active', $activeTheme);
    }
}
