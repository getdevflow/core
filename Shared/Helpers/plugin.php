<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Application\Devflow;
use JsonException;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Expressive\Database;
use App\Infrastructure\Services\Options;
use App\Shared\Services\PhpFileParser;
use Codefy\Framework\Application;
use Exception;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Identity\Ulid;
use Qubus\View\Renderer;
use ReflectionException;

use function class_exists;
use function Codefy\Framework\Helpers\app;
use function Codefy\Framework\Helpers\base_path;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\public_path;
use function dirname;
use function glob;
use function is_string;
use function ltrim;
use function preg_quote;
use function preg_replace;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Support\Helpers\add_trailing_slash;
use function sprintf;
use function trim;

/**
 * Extracts the file name of a specific plugin.
 *
 * @param string $filename Plugin's file name.
 */
function plugin_basename(string $filename): string
{
    $file = normalize_path($filename);

    $pluginDir = normalize_path(public_path('plugins'));

    $file = preg_replace(
        pattern: '#^' . preg_quote($pluginDir, delimiter: '#') . '/|^/#',
        replacement: '',
        subject: $file
    );

    $filename = trim($file, '/');
    return \basename($filename);
}

/**
 * Get the filesystem directory path (with trailing slash) for the plugin __FILE__ passed in.
 *
 * @param string $filename The filename of the plugin (__FILE__).
 * @return string The filesystem path of the directory that contains the plugin.
 */
function plugin_dir_path(string $filename): string
{
    return add_trailing_slash($filename);
}

/**
 * Returns a list of all plugins that have been activated.
 *
 * @return array|false
 */
function active_plugins(): array|false
{
    $dfdb = dfdb();

    try {
        return $dfdb->getResults(query: "SELECT * FROM {$dfdb->prefix}plugin");
    } catch (PDOException $e) {
    }

    return false;
}

/**
 * Activates a specific plugin that is called by $_GET['id'] variable.
 *
 * @param string $plugin ID of the plugin to activate
 */
function activate_plugin(string $plugin): void
{
    $dfdb = dfdb();

    try {
        $dfdb->transactional(function () use ($dfdb, $plugin) {
            $dfdb
                ->table(tableName: "{$dfdb->prefix}plugin")
                ->insert(
                    data: ['plugin_id' => Ulid::generateAsString(), 'plugin_classname' => $plugin]
                );
        });

        Devflow::$PHP->execute([$plugin, 'onActivation']);
    } catch (PDOException | Exception $ex) {
        logger(
            level: 'error',
            message: sprintf(
                'PLUGINACTIVATE[insert]: %s',
                $ex->getMessage()
            ),
            context: [
                'plugin' => 'activate'
            ]
        );
    }
}

/**
 * Deactivates a plugin.
 *
 * @param string $plugin
 * @return void
 */
function deactivate_plugin(string $plugin): void
{
    $dfdb = dfdb();

    try {
        $dfdb->transactional(function () use ($dfdb, $plugin) {
            $dfdb
                ->table(tableName: "{$dfdb->prefix}plugin")
                ->where(condition: 'plugin_classname = ?', parameters: $plugin)
                ->delete();
        });

        Devflow::$PHP->execute([$plugin, 'onDeactivation']);
    } catch (PDOException | Exception $ex) {
        logger(
            level: 'error',
            message: sprintf(
                'PLUGINDEACTIVATE[delete]: %s',
                $ex->getMessage()
            ),
            context: [
                'plugin' => 'deactivate'
            ]
        );
    }
}

/**
 * Checks if a particular plugin has been activated.
 *
 * @param string $plugin
 * @return bool
 */
function is_plugin_activated(string $plugin): bool
{
    $dfdb = dfdb();

    $prepare = $dfdb->prepare(
        "SELECT COUNT(*) FROM {$dfdb->prefix}plugin WHERE plugin_classname = ?",
        [
            $plugin
        ]
    );
    $count = $dfdb->getVar($prepare);

    return $count > 0;
}

/**
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws \Qubus\Exception\Exception
 */
function load_active_plugins(): void
{
    $activePlugins = active_plugins();

    if (!empty($activePlugins)) {
        foreach ($activePlugins as $plugin) {
            $class = esc_html($plugin->plugin_classname);
            // if the class does not exist,
            // deactivate it and move on
            if (!class_exists($class)) {
                deactivate_plugin($class);
                continue;
            }
            Devflow::$PHP->execute([$class, 'handle']);

            /**
             * Fires once a single activated plugin has loaded.
             *
             * @param $string $class Class name of the plugin that was loaded.
             */
            __observer()->action->doAction('plugin_loaded', $class);
        }
    }
}

/**
 * Retrieves a URL within the plugins or mu-plugins directory.
 *
 * Defaults to the plugins directory URL if no arguments are supplied.
 *
 * @param string $path   Optional. Extra path appended to the end of the URL, including
 *                       the relative directory if $plugin is supplied. Default empty.
 * @param string $plugin Optional. A full path to a file inside a plugin or mu-plugin.
 *                       The URL will be relative to its directory. Default empty.
 *                       Typically, this is done by passing `__FILE__` as the argument.
 * @return string Plugins URL link with optional paths appended.
 * @throws \Qubus\Exception\Exception
 */
function plugin_url(string $path = '', string $plugin = ''): string
{
    $path = normalize_path($path);
    $plugin = normalize_path($plugin);

    $pluginUrl = rtrim(site_url('plugins'), '/');

    $url = set_url_scheme($pluginUrl);

    if (!empty($plugin) && is_string($plugin)) {
        $folder = plugin_basename(dirname($plugin));
        if ('.' !== $folder) {
            $url .= '/' . trim($folder, '/');
        }
    }

    if ($path && is_string($path)) {
        $url .= '/' . ltrim($path, '/');
    }

    /**
     * Filters the URL to the plugins or mu-plugins directory.
     *
     * @param string $url     The complete URL to the plugins directory including scheme and path.
     * @param string $path    Path relative to the URL to the plugins directory. Blank string
     *                        if no path is specified.
     * @param string $plugin  The plugin file path to be relative to. Blank string if no plugin
     *                        is specified.
     */
    return __observer()->filter->applyFilter('plugin.url', $url, $path, $plugin);
}

/**
 * Get the URL directory path (with trailing slash) for the plugin __FILE__ passed in.
 *
 * @param string $file The filename of the plugin (__FILE__).
 * @return string the URL path of the directory that contains the plugin.
 * @throws \Qubus\Exception\Exception
 */
function plugin_dir_url(string $file): string
{
    $url = add_trailing_slash(plugin_url('', $file));
    return __observer()->filter->applyFilter('plugin.dir.url', $url, $file);
}

/**
 * Retrieves meta data about a plugin.
 *
 * @access private
 * @param string $pluginsDir
 * @return array
 * @throws TypeException
 */
function plugin_info(string $pluginsDir = ''): array
{
    /** @var Application $app */
    $app = app();

    $info = [];
    $dir = glob($pluginsDir . '*/*Plugin.php');
    foreach ($dir as $plugin) {
        $class = PhpFileParser::classObjectFromFile(
            $plugin,
            $app->make(name: Options::class),
            $app->make(name: Database::class),
            $app->make(name: Renderer::class)
        );
        $info[] = $app->execute([$class, 'meta']);
    }

    return $info;
}

/**
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws ContainerExceptionInterface
 * @throws TypeException
 * @throws InvalidArgumentException
 */
function plugin_available_for_subsites(string $className): bool
{
    $plugins = get_global_option('available_plugins', []);

    return is_array($plugins) && in_array($className, $plugins, true);
}

/**
 * @throws NotFoundExceptionInterface
 * @throws ContainerExceptionInterface
 * @throws InvalidArgumentException
 * @throws JsonException
 * @throws ReflectionException
 * @throws TypeException
 */
function set_plugin_available_for_subsites(string $className, bool $available): void
{
    $plugins = get_global_option('available_plugins', []);

    if (! is_array($plugins)) {
        $plugins = [];
    }

    if ($available) {
        $plugins[] = $className;
        $plugins = array_values(array_unique($plugins));
    } else {
        $plugins = array_values(array_filter(
            $plugins,
            static fn (string $plugin): bool => $plugin !== $className
        ));
    }

    update_global_option('available_plugins', $plugins);
}

/**
 * @param string $siteId
 * @return array
 */
function site_plugin_manifest(string $siteId): array
{
    $manifest = base_path("Cms/site/{$siteId}/plugins.php");

    if (!is_file($manifest)) {
        return [];
    }

    $plugins = require $manifest;

    return is_array($plugins) ? $plugins : [];
}

/**
 * @return void
 * @throws ContainerExceptionInterface
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws \Qubus\Exception\Exception
 */
function load_site_plugins(): void
{
    $site = get_site_by('id', get_current_site_id());

    if (!$site) {
        return;
    }

    $siteId = $site->id;

    foreach (site_plugin_manifest($siteId) as $class) {
        if (!class_exists($class)) {
            logger('error', sprintf('SITEPLUGIN[missing]: %s', $class), [
                'site_id' => $siteId,
            ]);
            continue;
        }

        Devflow::$PHP->execute([$class, 'handle']);

        __observer()->action->doAction('site_plugin_loaded', $class, $siteId);
    }

    __observer()->action->doAction('site_plugins_loaded', $siteId);
}
