<?php

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Infrastructure\Services\Options;
use App\Shared\Services\Items;
use App\Shared\Services\PhpFileParser;
use App\Shared\Services\TemplateRegistry;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\Framework\Factory\FileLoggerFactory;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
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
use function class_exists;
use function Codefy\Framework\Helpers\public_path;
use function count;
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
 * Returns the base directory for theme stylesheet.
 *
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
 * Activates a specific theme by namespace.
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
 * Deactivates an active theme.
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
 * Executes the active theme.
 *
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
        if (!class_exists($activeTheme)) {
            deactivate_theme();
            return;
        }
        Devflow::inst()::$APP->execute([$activeTheme, 'handle']);

        /**
         * Fires once the activated theme has loaded.
         *
         * @param $string $plugin Class name of the plugin that was loaded.
         */
        Action::getInstance()->doAction('theme_active', $activeTheme);
    }
}

/**
 * Checks whether there is content.
 *
 * @param string|null $type
 * @param int $limit
 * @param int|null $offset
 * @param string $status
 * @return bool
 * @throws CommandPropertyNotFoundException
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function has_content(?string $type = null, int $limit = 0, ?int $offset = null, string $status = 'published'): bool
{
    $filteredContent = get_all_content_with_filters(
        contentTypeSlug: $type,
        limit: $limit,
        offset: $offset,
        status: $status
    );

    $sortedContent = sort_list($filteredContent, 'published', 'DESC');

    $content = new Items(items: $sortedContent);
    TemplateRegistry::set(key: 'all-content', value: $content);

    return count($filteredContent) > 0;
}

/**
 * Loops through all content.
 *
 * @return bool
 */
function the_content(): bool
{
    $content = TemplateRegistry::get(key: 'all-content');
    if ($result = $content->valid()) {
        // register single content
        TemplateRegistry::set(key: 'content', value: $content->current());
        // move to next
        $content->next();
    } else {
        // back to the start
        $content->rewind();
    }
    return $result;
}

/**
 * The content's unique id.
 *
 * @return string
 */
function content_id(): string
{
    return TemplateRegistry::prop(object: 'content', key: 'id');
}

/**
 * The content's title.
 *
 * @return string
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function content_title(): string
{
    if ($title = TemplateRegistry::prop(object: 'content', key: 'title')) {
        return the_title(content_id());
    }
    return $title;
}

/**
 * The content's slug.
 *
 * @return string
 */
function content_slug(): string
{
    return TemplateRegistry::prop(object: 'content', key: 'slug');
}

/**
 * The content's body.
 *
 * @return string
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function content_body(): string
{
    if ($body = TemplateRegistry::prop(object: 'content', key: 'body')) {
        return the_body(content_id());
    }
    return $body;
}

/**
 * The content's author.
 *
 * @return string
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function content_author(): string
{
    if ($author = TemplateRegistry::prop(object: 'content', key: 'author')) {
        return get_content_author(content_id());
    }
    return $author;
}

/**
 * The content's featured image.
 *
 * @return string
 */
function content_featured_image(): string
{
    return TemplateRegistry::prop(object: 'content', key: 'featuredImage');
}

/**
 * The content's permalink.
 *
 * @return string
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function content_permalink(): string
{
    if ($permalink = TemplateRegistry::prop(object: 'content', key: 'author')) {
        return the_permalink(content_id());
    }
    return $permalink;
}

/**
 * The content's published date.
 *
 * @return string
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function content_published_date(): string
{
    if ($publishedDate = TemplateRegistry::prop(object: 'content', key: 'published')) {
        return the_published_date(content_id(), get_user_date_format());
    }
    return $publishedDate;
}

/**
 * The content's published time.
 *
 * @return string
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function content_published_time(): string
{
    if ($publishedTime = TemplateRegistry::prop(object: 'content', key: 'published')) {
        return the_published_time(content_id(), get_user_time_format());
    }
    return $publishedTime;
}

/**
 * The content's published datetime.
 *
 * @return string
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 */
function content_published_datetime(): string
{
    if ($publishedDatetime = TemplateRegistry::prop(object: 'content', key: 'published')) {
        return get_content_datetime(content_id());
    }
    return $publishedDatetime;
}

/**
 * Checks whether there are products.
 *
 * @param int $limit
 * @param int|null $offset
 * @param string $status
 * @return bool
 * @throws CommandPropertyNotFoundException
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function has_products(int $limit = 0, ?int $offset = null, string $status = 'published'): bool
{
    $filteredProducts = get_all_products_with_filters(limit: $limit, offset: $offset, status: $status);

    $products = new Items(items: $filteredProducts);
    TemplateRegistry::set(key: 'products', value: $products);

    return count($filteredProducts) > 0;
}

/**
 * Loops through all products.
 *
 * @return bool
 */
function the_product(): bool
{
    $product = TemplateRegistry::get(key: 'products');
    if ($result = $product->valid()) {
        // register single product
        TemplateRegistry::set(key: 'product', value: $product->current());
        // move to next
        $product->next();
    } else {
        // back to the start
        $product->rewind();
    }
    return $result;
}

/**
 * The product's unique id.
 *
 * @return string|null
 */
function product_id(): ?string
{
    return TemplateRegistry::prop(object: 'product', key: 'id');
}

/**
 * The product's title.
 *
 * @return string|null
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function product_title(): ?string
{
    if ($title = TemplateRegistry::prop(object: 'product', key: 'title')) {
        return get_product_title(product_id());
    }
    return $title;
}

/**
 * The product's slug.
 *
 * @return string|null
 */
function product_slug(): ?string
{
    return TemplateRegistry::prop(object: 'product', key: 'slug');
}

/**
 * The product's body.
 *
 * @return string
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function product_body(): string
{
    if ($body = TemplateRegistry::prop(object: 'product', key: 'body')) {
        return get_product_body(product_id());
    }
    return $body;
}

/**
 * The product's author.
 *
 * @return string|false
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function product_author(): string|false
{
    if ($author = TemplateRegistry::prop(object: 'product', key: 'author')) {
        return get_product_author(product_id());
    }
    return $author;
}

/**
 * The product's sku.
 *
 * @return string|false
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function product_sku(): string|false
{
    if ($sku = TemplateRegistry::prop(object: 'product', key: 'sku')) {
        return get_product_sku(product_id());
    }
    return $sku;
}

/**
 * The product's price.
 *
 * @return string|null
 */
function product_price(): ?string
{
    return TemplateRegistry::prop(object: 'product', key: 'price');
}

/**
 * The product's currency.
 *
 * @return string|null
 */
function product_currency(): ?string
{
    return TemplateRegistry::prop(object: 'product', key: 'currency');
}

/**
 * The product's purchase url.
 *
 * @return string|null
 */
function product_purchase_url(): ?string
{
    return TemplateRegistry::prop(object: 'product', key: 'purchaseUrl');
}

/**
 * The product's featured image.
 *
 * @return string|null
 */
function product_featured_image(): ?string
{
    return TemplateRegistry::prop(object: 'product', key: 'featuredImage');
}

/**
 * The product's permalink.
 *
 * @return string|null
 * @throws Exception
 * @throws ReflectionException
 */
function product_permalink(): ?string
{
    return home_url(TemplateRegistry::prop(object: 'product', key: 'relativeUrl'));
}

/**
 * The product's published date.
 *
 * @return string|null
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function product_published_date(): ?string
{
    if ($publishedDate = TemplateRegistry::prop(object: 'product', key: 'published')) {
        return get_product_published_date(product_id(), get_user_date_format());
    }
    return $publishedDate;
}

/**
 * The product's published time.
 *
 * @return string|null
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function product_published_time(): ?string
{
    if ($publishedTime = TemplateRegistry::prop(object: 'product', key: 'published')) {
        return get_product_published_time(product_id(), get_user_time_format());
    }
    return $publishedTime;
}

/**
 * The product's published datetime.
 *
 * @return string|null
 * @throws Exception
 * @throws ReflectionException
 */
function product_published_datetime(): ?string
{
    if ($publishedDatetime = TemplateRegistry::prop(object: 'product', key: 'published')) {
        return get_product_datetime(product_id());
    }
    return $publishedDatetime;
}
