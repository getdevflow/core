<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Shared\Services\Registry;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Gravatar\Gravatar;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Random\RandomException;
use ReflectionException;
use RuntimeException;

use function abs;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\public_path;
use function Codefy\Framework\Helpers\storage_path;
use function Codefy\Framework\Helpers\trans_html;
use function dechex;
use function file_exists;
use function get_headers;
use function implode;
use function in_array;
use function ord;
use function preg_replace_callback;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Support\Helpers\concat_ws;
use function Qubus\Support\Helpers\is_null__;
use function str_replace;
use function str_split;
use function strlen;
use function substr;

/**
 * Checks if a filter exists.
 */
function has_filter(string $hook): bool
{
    return in_array($hook, __observer()->filter->getHooks());
}

/**
 * Sanitize meta value.
 *
 * @param string $metaKey Meta key.
 * @param mixed $metaValue Meta value to sanitize.
 * @param string $arrayType Type of object the meta is registered to.
 * @param string $arraySubtype Optional. The subtype of the object type.
 * @return mixed Sanitized $metaValue.
 * @throws Exception
 */
function sanitize_meta(string $metaKey, mixed $metaValue, string $arrayType, string $arraySubtype = ''): mixed
{
    if (!empty($arraySubtype) && has_filter("sanitize.{$arrayType}.meta.{$metaKey}.for.{$arraySubtype}")) {
        /**
         * Filters the sanitization of a specific meta key of a specific meta type and subtype.
         *
         * The dynamic portions of the hook name, `$arrayType`, `$metaKey`,
         * and `$arraySubtype`, refer to the metadata object type (content, user or site),
         * the meta key value, and the object subtype respectively.
         *
         * @file core/Shared/Helpers/hook.php
         * @param mixed  $metaValue    Meta value to sanitize.
         * @param string $metaKey      Meta key.
         * @param string $arrayType    Object type.
         * @param string $arraySubtype Object subtype.
         */
        return __observer()->filter->applyFilter(
            "sanitize.{$arrayType}.meta.{$metaKey}.for.{$arraySubtype}",
            $metaValue,
            $metaKey,
            $arrayType,
            $arraySubtype
        );
    }

    /**
     * Filters the sanitization of a specific meta key of a specific meta type.
     *
     * The dynamic portions of the hook name, `$arrayType` and `$metaKey`,
     * refer to the metadata object type (content, user or site), the meta key
     * value respectively.
     *
     * @file core/Shared/Helpers/hook.php
     * @param mixed  $metaValue Meta value to sanitize.
     * @param string $metaKey   Meta key.
     * @param string $arrayType Object type.
     */
    return __observer()->filter->applyFilter(
        "sanitize.{$arrayType}.meta.{$metaKey}",
        $metaValue,
        $metaKey,
        $arrayType
    );
}

/**
 * Prints release value.
 *
 * @file core/Shared/Helpers/hook.php
 * @return mixed
 * @throws Exception
 */
function cms_release_value(): mixed
{
    $release = concat_ws(esc_html('Devflow'), Devflow::release(), ' ') . "\n";

    return __observer()->filter->applyFilter('release.value', $release);
}

/**
 * Large logo. Filterable.
 *
 * @file core/Shared/Helpers/hook.php
 * @return string
 * @throws Exception
 */
function get_logo_large(): string
{
    $logo = '<strong>' . esc_html('Dev') . '</strong>' . esc_html('flow');
    return __observer()->filter->applyFilter('logo.large', $logo);
}

/**
 * Mini logo. Filterable.
 *
 * @file core/Shared/Helpers/hook.php
 * @return string
 * @throws Exception
 */
function get_logo_mini(): string
{
    $logo = '<strong>' . esc_html('Dev') . '</strong>' . esc_html('flow');
    return __observer()->filter->applyFilter('logo.mini', $logo);
}

/**
 * Returns full base url of a site's public url.
 *
 * @file core/Shared/Helpers/hook.php
 * @param string $path
 * @return string Site's public base url.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function public_site_url(string $path = ''): string
{
    $siteKey = site_directory_key(Registry::getInstance()->get('siteKey'));
    $url = site_url('site/' . $siteKey . '/' . $path);
    return __observer()->filter->applyFilter("public.site.url.{$siteKey}", $url);
}

/**
 * Returns full base url of a site's public upload url.
 *
 * @file core/Shared/Helpers/hook.php
 * @param string $path
 * @return string Site's public upload base url.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function public_site_upload_url(string $path = ''): string
{
    $siteKey = site_directory_key(Registry::getInstance()->get('siteKey'));
    $url = public_site_url('uploads/' . $path);
    return __observer()->filter->applyFilter("public.site.upload.url.{$siteKey}", $url);
}

/**
 * Searches for plain email addresses in given $string and
 * encodes them (by default) with the help of cms_encode_email_str().
 *
 * @file core/Shared/Helpers/hook.php
 * @param string $string Text with email addresses to encode
 * @return string $string Given text with encoded email addresses
 * @throws Exception
 * @throws ReflectionException
 */
function cms_encode_email(string $string): string
{
    // abort if $string doesn't contain a @-sign
    if (__observer()->filter->applyFilter('encode.email.at.sign.check', true)) {
        if (!str_contains($string, '@')) {
            return $string;
        }
    }

    // override encoding function with the 'encode_email_method' filter
    $method = __observer()->filter->applyFilter(
        'encode.email.method',
            __NAMESPACE__ . '\\cms_encode_email_str'
    );

    if (!is_callable($method)) {
        throw new TypeException(
            'The encode.email.method filter must return a valid callable.'
        );
    }

    // override regex pattern with the 'encode_email_regexp' filter
    $regexp = __observer()->filter->applyFilter(
            'encode.email.regexp',
            <<<'REGEX'
{
    (?:mailto:)?
    (?:
        [-!#$%&*+/=?^_`.{|}~\w\x80-\xFF]+
        |
        ".*?"
    )
    \@
    (?:
        [-a-z0-9\x80-\xFF]+(?:\.[-a-z0-9\x80-\xFF]+)*\.[a-z]+
        |
        \[[\d.a-fA-F:]+\]
    )
}xi
REGEX
    );

    $encoded = preg_replace_callback(
        $regexp,
        static fn(array $matches): string => (string) $method($matches[0]),
        $string
    );

    if ($encoded === null) {
        throw new RuntimeException(
            'Email encoding failed: ' . preg_last_error_msg()
        );
    }

    return $encoded;
}

/**
 * Encodes each character of the given string as either a decimal
 * or hexadecimal entity, in the hopes of foiling most email address
 * harvesting bots.
 *
 * @file core/Shared/Helpers/hook.php
 * @param string $string Text with email addresses to encode
 * @return string $string Given text with encoded email addresses
 * @throws RandomException
 */
function cms_encode_email_str(string $string): string
{
    if ($string === '') {
        return '';
    }

    $chars = str_split($string);
    $seedMax = max(1, (int) abs(crc32($string) / max(1, strlen($string))));
    $seed = random_int(0, $seedMax);

    foreach ($chars as $key => $char) {
        $ord = ord($char);

        // Ignore non-ASCII bytes.
        if ($ord >= 128) {
            continue;
        }

        $r = ($seed * ($key + 1)) % 100;

        if ($r > 60 && $char !== '@') {
            continue;
        }

        $chars[$key] = $r < 45
            ? '&#x' . dechex($ord) . ';'
            : '&#' . $ord . ';';
    }

    return implode('', $chars);
}

/**
 * Renders an editor.
 *
 * @file core/Shared/Helpers/hook.php
 * @param string|null $selector HTML ID attribute value for the textarea and TinyMCE. Can only be /[a-z]+/.
 * @throws Exception
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function cms_editor(?string $selector = null): void
{
    cms_enqueue_js('default', '//cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.11/tinymce.min.js');

    if (is_null__($selector)) {
        $mceSelector = '#content_body';
    } else {
        $mceSelector = $selector;
    }

    /**
     * Filters the default theme for TinyMCE.
     * @param string $theme Theme used for TinyMCE.
     */
    $mceTheme = __observer()->filter->applyFilter('tiny.mce.theme', 'modern');

    $plugins = [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor',
        'searchreplace', 'visualblocks', 'code', 'codesample',
        'insertdatetime', 'media', 'table', 'contextmenu', 'paste', 'pagebreak'
    ];
    /**
     * Filters the list of default TinyMCE plugins.
     * @param array $plugins An array of default TinyMCE plugins.
     */
    $mcePlugins = __observer()->filter->applyFilter('tiny.mce.plugins', $plugins);

    $mceButtons = [
        'undo',
        'redo',
        'styleselect',
        'bold',
        'italic',
        'alignleft',
        'aligncenter',
        'alignright',
        'alignjustify',
        'bullist',
        'numlist',
        'blockquote',
        'outdent',
        'indent',
        'link',
        'image',
        'media',
        'codesample',
        'preview'
    ];
    /**
     * Filters the first-row list of TinyMCE buttons.
     * @param array  $buttons      First-row list of buttons.
     * @param string $mceSelector  Unique editor identifier, e.g. 'textarea'.
     */
    $mceButtons1 = __observer()->filter->applyFilter('tiny.mce.buttons.1', $mceButtons, $mceSelector);
    /**
     * Filters the second-row list of TinyMCE buttons.
     *
     * @param array  $buttons      First-row list of buttons.
     * @param string $mceSelector  Unique editor identifier, e.g. 'textarea'.
     */
    $mceButtons2 = __observer()->filter->applyFilter('tiny.mce.buttons.2', [], $mceSelector);
    /**
     * Filters the third-row list of TinyMCE buttons.
     *
     * @param array  $buttons      First-row list of buttons.
     * @param string $mceSelector  Unique editor identifier, e.g. 'textarea'.
     */
    $mceButtons3 = __observer()->filter->applyFilter('tiny.mce.buttons.3', [], $mceSelector);
    /**
     * Filters the default stylesheets.
     *
     * @param array  $css          CSS stylesheets to include.
     * @param string $mceSelector  Unique editor identifier, e.g. 'textarea'.
     */
    $mceCss = __observer()->filter->applyFilter(
        'tiny.mce.css',
        [
            '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i',
            site_url('static/assets/css/tinymce.css')
        ],
        $mceSelector
    );

    /**
     * Fires immediately before TinyMCE is printed.
     */
    __observer()->action->doAction('before_cms_tiny_mce'); ?>
    <script type="text/javascript">
        tinymce.init({
            selector: "<?= $mceSelector; ?>",
            theme: "<?= $mceTheme; ?>",
            browser_spellcheck: true,
            relative_urls: false,
            remove_script_host: false,
            height: 400,
            media_live_embeds: true,
            plugins: ["<?= implode(',', $mcePlugins); ?>"],
            toolbar: "pagebreak",
            pagebreak_separator: "<!--pagebreak-->",
            link_list: [
    <?php
    foreach (tinymce_link_list() as $link) :
        if (!empty($link)) {
            echo "{title: '" . $link['title'] . "', value: '" .
            home_url($link['relativeUrl']) . "'}," . "\n";
        }
    endforeach; ?>
            ],
            toolbar1: "<?= implode(separator: ' ', array: $mceButtons1); ?>",
            toolbar2: "<?= implode(separator: ' ', array: $mceButtons2); ?>",
            toolbar3: "<?= implode(separator: ' ', array: $mceButtons3); ?>",
            autosave_ask_before_unload: true,
            content_css: [<?= '"' . implode('", "', $mceCss) . '"'; ?>],
            file_picker_callback: elFinderBrowser
        });
        function elFinderBrowser(callback, value, meta) {
            tinymce.activeEditor.windowManager.open({
                file: "<?= admin_url(path: 'elfinder/'); ?>",
                title: "elFinder 2.1.67",
                width: 900,
                height: 425,
                resizable: "yes"
            }, {
                oninsert: function (file) {
                    // Provide file and text for the link dialog
                    if (meta.filetype === "file") {
                        //callback("mypage.html", {text: "My text"});
                        callback(file.url);
                    }

                    // Provide image and alt text for the image dialog
                    if (meta.filetype === "image") {
                        //callback("image.jpg", {alt: "My alt text"});
                        callback(file.url, {alt: file.name});
                    }

                    // Provide alternative source and posted for the media dialog
                    if (meta.filetype === "media") {
                        //callback("movie.mp4", {source2: "alt.ogg", poster: "image.jpg"});
                        callback(file.url, {alt: file.name});
                    }
                }
            });
            return false;
        }
    </script>
    <?php
    /**
     * Fires immediately after TinyMCE is printed.
     *
     * @file core/Shared/Helpers/hook.php
     */
    __observer()->action->doAction('after_cms_tiny_mce');
}

/**
 * Returns an optimized image for use.
 *
 * @file core/Shared/Helpers/hook.php
 * @param string $image Original image file.
 * @return string|null Optimized image file.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function cms_optimized_image_upload(string $image): ?string
{
    if ($image === '') {
        return null;
    }
    $siteKey = site_directory_key(Registry::getInstance()->get('siteKey'));
    $rawFilename = str_replace(site_url(), '', $image);
    $newFilename = str_replace(
        public_site_upload_url(),
        'site/' . $siteKey . '/uploads/__optimized__/',
        $image
    );
    if (!file_exists($newFilename)) {
        _cms_image_optimizer($rawFilename, $newFilename);
    }
    return __observer()->filter->applyFilter('optimized.image', $newFilename, $image, $rawFilename);
}

/**
 * Loads javascript for backend dashboard.
 *
 * @file core/Shared/Helpers/hook.php
 * @throws Exception
 */
function admin_dashboard_js(): void
{
    cms_enqueue_js('default', site_url('static/assets/js/pages/dashboard.js'));
}

/**
 * CMS charset.
 *
 * @file core/Shared/Helpers/hook.php
 * @param string|null $charset
 * @return mixed
 * @throws Exception
 */
function cms_charset(?string $charset = null): mixed
{
    $select = '<select class="form-control select2" name="charset" style="width: 100%;" required>
        <option value="">&nbsp;</option>
        <option value="UTF-32"' . selected($charset, 'UTF-32', false) . '>UTF-32</option>
        <option value="UTF-16"' . selected($charset, 'UTF-16', false) . '>UTF-16</option>
        <option value="UTF-7"' . selected($charset, 'UTF-7', false) . '>UTF-7</option>
        <option value="UTF-8"' . selected($charset, 'UTF-8', false) . '>UTF-8</option>
        <option value="ASCII"' . selected($charset, 'ASCII', false) . '>ASCII</option>
        <option value="ISO-8859-1"' . selected($charset, 'ISO-8859-1', false) . '>ISO-8859-1</option>
        <option value="ISO-8859-2"' . selected($charset, 'ISO-8859-2', false) . '>ISO-8859-2</option>
        <option value="ISO-8859-3"' . selected($charset, 'ISO-8859-3', false) . '>ISO-8859-3</option>
        <option value="ISO-8859-4"' . selected($charset, 'ISO-8859-4', false) . '>ISO-8859-4</option>
        <option value="ISO-8859-5"' . selected($charset, 'ISO-8859-5', false) . '>ISO-8859-5</option>
        <option value="ISO-8859-6"' . selected($charset, 'ISO-8859-6', false) . '>ISO-8859-6</option>
        <option value="ISO-8859-7"' . selected($charset, 'ISO-8859-7', false) . '>ISO-8859-7</option>
        <option value="ISO-8859-8"' . selected($charset, 'ISO-8859-8', false) . '>ISO-8859-8</option>
        <option value="ISO-8859-9"' . selected($charset, 'ISO-8859-9', false) . '>ISO-8859-9</option>
        <option value="ISO-8859-10"' . selected($charset, 'ISO-8859-10', false) . '>ISO-8859-10</option>
        <option value="ISO-8859-13"' . selected($charset, 'ISO-8859-13', false) . '>ISO-8859-13</option>
        <option value="ISO-8859-14"' . selected($charset, 'ISO-8859-14', false) . '>ISO-8859-14</option>
        <option value="ISO-8859-15"' . selected($charset, 'ISO-8859-15', false) . '>ISO-8859-15</option>
        <option value="ISO-8859-16"' . selected($charset, 'ISO-8859-16', false) . '>ISO-8859-16</option>
        <option value="Windows-1251"' . selected($charset, 'Windows-1251', false) . '>Windows-1251</option>
        <option value="Windows-1252"' . selected($charset, 'Windows-1252', false) . '>Windows-1252</option>
        <option value="SJIS-mac"' . selected($charset, 'SJIS-mac', false) . '>SJIS-mac</option>
        <option value="UTF-8-Mobile#DOCOMO"' . selected($charset, 'UTF-8-Mobile#DOCOMO', false) . '>
        UTF-8-Mobile#DOCOMO
        </option>
        <option value="UTF-8-Mobile#KDDI-A"' . selected($charset, 'UTF-8-Mobile#KDDI-A', false) . '>
        UTF-8-Mobile#KDDI-A
        </option>
        <option value="UTF-8-Mobile#KDDI-B"' . selected($charset, 'UTF-8-Mobile#KDDI-B', false) . '>
        UTF-8-Mobile#KDDI-B
        </option>
        <option value="UTF-8-Mobile#SOFTBANK"' . selected($charset, 'UTF-8-Mobile#SOFTBANK', false) . '>
        UTF-8-Mobile#SOFTBANK
        </option>
        <option value="ISO-2022-JP-MOBILE#KDDI"' . selected($charset, 'ISO-2022-JP-MOBILE#KDDI', false) . '>
        ISO-2022-JP-MOBILE#KDDI
        </option>
        <option value="GB18030"' . selected($charset, 'GB18030', false) . '>GB18030</option>
        </select>';
    return __observer()->filter->applyFilter('charset', $select, $charset);
}

/**
 * Returns the auth screen logo.
 *
 * @file core/Shared/Helpers/hook.php
 * @return string The auth screen logo.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_auth_screen_logo(): string
{
    $locations = [];
    $siteKey = Registry::getInstance()->has('siteKey') ? site_directory_key(Registry::getInstance()->get('siteKey')) : '';
    /**
     * First, check to see if a custom logo exists for a specific site.
     * @var string $locations['site'] Custom logo for a specific site.
     */
    $locations['site'] = [
        'path' => public_path('site/' . $siteKey . '/uploads/auth-logo.png'),
        'relative' => site_url('site/' . $siteKey . '/uploads/auth-logo.png'),
    ];
    /**
     * Second, check to see if a custom global logo exists for the system.
     * @var string $locations['global'] Custom logo for the whole system.
     */
    $locations['global'] = [
        'path' => storage_path('private/files/images/auth-logo.png'),
        'relative' => 'private/files/images/auth-logo.png',
    ];
    /**
     * Lastly, if the first two don't exist, then use the native Devflow
     * logo.
     * @var string $locations['native'] Native logo.
     */
    $locations['native'] = [
        'path' => public_path('static/assets/img/auth/auth-logo.png'),
        'relative' => site_url('static/assets/img/auth/auth-logo.png'),
    ];
    foreach ($locations as $location) {
        if (file_exists($location['path'])) {
            $authLogo = $location['relative'];
        }
    }
    /**
     * Filters the auth logo.
     *
     * @var string $logo The auth logo.
     */
    $logo = __observer()->filter->applyFilter('auth.logo', $authLogo);
    return '<img src="' . $logo . '" alt="auth-logo" title="auth-logo">';
}

/**
 * Retrieve the avatar `<img>` tag for user.
 *
 * @file core/Shared/Helpers/hook.php
 * @param string $email User's email address.
 * @param int $s Height and width of the avatar image file in pixels. Default 80.
 * @param string $class Class to add to `<img>` element.
 * @return string `<img>` tag for user's avatar or default otherwise.
 * @throws Exception
 */
function get_user_avatar(string $email, int $s = 80, string $class = ''): string
{
    $avatar = '<img src="' . Gravatar::image($email, $s)->url() .
    '" class="' . $class . '" alt="' . $email . '" />';

    return __observer()->filter->applyFilter('user.avatar', $avatar, $email, $s, $class);
}

/**
 * Retrieves the avatar url.
 *
 * @file core/Shared/Helpers/hook.php
 * @param string $email Email address of user.
 * @return string The url of the avatar that was found, or default if not found.
 * @throws Exception
 */
function get_user_avatar_url(string $email): string
{
    $avatar = Gravatar::image($email)->url();

    return __observer()->filter->applyFilter('user.avatar.url', $avatar, $email);
}


/**
 * Upload image button.
 *
 * @file core/Shared/Helpers/hook.php
 * @throws Exception
 */
function cms_upload_image()
{
    $elfinder = '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
            <link href="vendor/studio-42/elfinder/css/elfinder.full.css" type="text/css" rel="stylesheet" />
            <link href="vendor/studio-42/elfinder/css/theme.css" type="text/css" rel="stylesheet" />
            <script src="vendor/studio-42/elfinder/js/elfinder.full.js" type="text/javascript"></script>
            <script src="//cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.7/js/jquery.fancybox.min.js" type="text/javascript"></script>
            <script>
                $(document).ready(function () {

                    $("#remove_image").hide();
                    $("#set_image").show();

                    $("#set_image").click(function (e) {
                        var elfinder = $("#elfinder").elfinder({
                            url: "' . admin_url('connector/') . '",
                            resizable: false,
                            onlyMimes: ["image"],
                            uiOptions: {
                                // toolbar configuration
                                toolbar: [
                                    ["reload"],
                                    ["open", "download", "getfile"],
                                    ["duplicate", "rename", "edit", "resize"],
                                    ["quicklook", "info"],
                                    ["search"],
                                    ["view", "sort"]
                                ]
                            },
                            getfile: {
                                onlyURL: true,
                                multiple: false,
                                folders: false,
                                oncomplete: "destroy"
                            },
                            handlers: {
                                dblclick: function (event, elfinderInstance) {
                                    fileInfo = elfinderInstance.file(event.data.file);

                                    if (fileInfo.mime != "directory") {
                                        var imgURL = elfinderInstance.url(event.data.file);
                                        $("#upload_image").val(imgURL);

                                        var imgPath = "<img src=\'"+imgURL+"\' id=\"append-image\" style=\"width:260px;height:auto;background-size:contain;margin-bottom:.9em;background-repeat:no-repeat\"/>";
                                        $("#elfinder_image").append(imgPath); //add the image to a div so you can see the selected images

                                        $("#remove_image").show();
                                        $("#set_image").hide();

                                        elfinderInstance.destroy();
                                        return false; // stop elfinder
                                    };
                                },
                                destroy: function () {
                                    elfinder.dialog("close");
                                }
                            }
                        }).dialog({
                            title: "filemanager",
                            resizable: true,
                            width: 920,
                            height: 500
                        });
                        $("#remove_image").click(function () {

                            $("#upload_image").val("");
                            $("#elfinder_image").find("#append-image").remove(); //remove image from div when user clicks remove image button.

                            $("#remove_image").hide();
                            $("#set_image").show();

                            return false;
                        });
                    });
                });
            </script>';
    return __observer()->filter->applyFilter('cms.upload.image', $elfinder);
}

/**
 * Compares release values.
 *
 * @file core/Shared/Helpers/hook.php
 * @param string $current Current installed release.
 * @param string $latest The latest cms release.
 * @param string $operator Operand use to compare current and latest release values.
 * @return bool
 * @throws Exception
 */
function compare_releases(string $current, string $latest, string $operator = '>'): bool
{
    $phpFunction = version_compare($current, $latest, $operator);
    /**
     * Filters the comparison between two releases.
     *
     * @file core/Shared/Helpers/hook.php
     * @param bool|int $phpFunction PHP function for comparing two release values.
     */
    $release = __observer()->filter->applyFilter('compare.releases', $phpFunction);

    if ($release) {
        return (bool) $latest;
    } else {
        return false;
    }
}

/**
 * Retrieves a response code from the header
 * of a given resource.
 *
 * @file core/Shared/Helpers/hook.php
 * @param string $url URL of resource/website.
 * @return int HTTP response code.
 * @throws Exception
 */
function get_http_response_code(string $url): int
{
    $headers = @get_headers($url);
    $status = substr($headers[0], 9, 3);
    /**
     * Filters the http response code.
     *
     * @file core/Shared/Helpers/hook.php
     * @param int $status The http response code from external resource.
     */
    return __observer()->filter->applyFilter('http.response.code', (int) $status);
}

/**
 * Shows an error message when system is in DEV mode.
 *
 * @file core/Shared/Helpers/hook.php
 * @throws TypeException
 * @throws Exception
 */
function cms_dev_mode(): void
{
    if (config(key: 'app.env') === 'development') {
        echo '<div class="alert dismissable alert-danger center sticky">' . trans_html(
            string: 'Your system is currently in dev mode. Please remember to set your system to prod mode after testing. When prod mode is set, this warning message will disappear.',
        ) . '</div>';
    }
}

/**
 * Fires the admin_head action.
 *
 * @access private
 * @file core/Shared/Helpers/hook.php
 * @throws Exception
 */
function admin_head(): void
{
    /**
     * Registers & enqueues a stylesheet to be printed in backend head section.
     */
    __observer()->action->doAction('enqueue_admin_css');
    /**
     * Fires in head section of all admin screens.
     */
    __observer()->action->doAction('cms_admin_head');
}

/**
 * Fires the frontend cms_head action.
 *
 * @file core/Shared/Helpers/hook.php
 * @throws Exception
 */
function cms_head(): void
{
    /**
     * Registers & enqueues a stylesheet to be printed in frontend head section.
     */
    __observer()->action->doAction('enqueue_css');
    /**
     * Prints scripts and/or data in the head of the front end.
     */
    __observer()->action->doAction('cms_head');
}

/**
 * Fires the admin_footer action via backend.
 *
 * @access private
 * @file core/Shared/Helpers/hook.php
 * @throws Exception
 */
function admin_footer(): void
{
    /**
     * Registers & enqueues JavaScript to be printed in backend footer section.
     */
    __observer()->action->doAction('enqueue_admin_js');
    /**
     * Prints scripts and/or data before the ending body tag of the backend.
     */
    __observer()->action->doAction('cms_admin_footer');
}

/**
 * Fires the cms_footer action via the admin.
 *
 * @file core/Shared/Helpers/hook.php
 * @throws Exception
 */
function cms_footer(): void
{
    /**
     * Registers & enqueues JavaScript to be printed in frontend footer section.
     */
    __observer()->action->doAction('enqueue_js');
    /**
     * Prints scripts and/or data before the ending body tag
     * of the front end.
     */
    __observer()->action->doAction('cms_footer');
}

/**
 * @access private
 * @throws Exception
 */
function admin_enqueue_head(): void
{
    cms_enqueue_js(config: 'default', asset: 'jquery-ui');
    cms_enqueue_js(
        config: 'default',
        asset: '//cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js'
    );

    cms_enqueue_css(config: 'default', asset: 'admin-styles.css');
}

/**
 * @access private
 * @throws Exception
 */
function admin_enqueue_footer(): void
{
    cms_enqueue_js(config: 'default', asset: 'bootstrap/bootstrap.min.js');
    cms_enqueue_js(config: 'default', asset: 'plugins/bootstrap-validator/validator.js');
    cms_enqueue_js(config: 'default', asset: '//cdnjs.cloudflare.com/ajax/libs/admin-lte/2.3.7/js/app.min.js');
    cms_enqueue_js(config: 'default', asset: 'switchery-js');
    cms_enqueue_js(config: 'default', asset: 'select2-js');
    cms_enqueue_js(config: 'default', asset: 'daterangepicker/moment.min.js');
    cms_enqueue_js(
        config: 'default',
        asset: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/js/bootstrap-datetimepicker.min.js'
    );
    cms_enqueue_js(config: 'default', asset: 'pages/datetime.js');
    cms_enqueue_js(config: 'default', asset: 'iCheck/icheck.min.js');
    cms_enqueue_js(config: 'default', asset: 'pages/iCheck.js');
    cms_enqueue_js(
        config: 'default',
        asset: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js'
    );
    cms_enqueue_js(config: 'default', asset: 'demo.js');
    cms_enqueue_js(config: 'default', asset: 'datatables-js');
}
