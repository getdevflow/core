<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Infrastructure\Services\Updater;
use App\Shared\Services\Assets\AppAssets;
use App\Shared\Services\Assets\PluginAssets;
use App\Shared\Services\Assets\ThemeAssets;
use App\Shared\Services\DateTime;
use App\Shared\Services\ListUtil;
use App\Shared\Services\Registry;
use Codefy\Framework\Factory\FileLoggerFactory;
use DateInvalidTimeZoneException;
use DateMalformedStringException;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Error\Error;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Exception\Http\Client\NotFoundException;
use Qubus\Http\Request;
use Qubus\Support\DateTime\QubusDateTime;
use RandomLib\Factory;
use ReflectionException;
use SecurityLib\Strength;
use Spatie\ImageOptimizer\OptimizerChainFactory;

use function array_diff_assoc;
use function array_merge;
use function array_push;
use function array_slice;
use function array_unique;
use function array_values;
use function asort;
use function Codefy\Framework\Helpers\config;
use function Codefy\Framework\Helpers\public_path;
use function Codefy\Framework\Helpers\resource_path;
use function Codefy\Framework\Helpers\trans;
use function count;
use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function dirname;
use function file;
use function file_exists;
use function file_get_contents;
use function filesize;
use function floor;
use function get_defined_functions;
use function is_dir;
use function is_file;
use function is_readable;
use function json_decode;
use function json_encode;
use function json_validate;
use function ltrim;
use function mb_detect_encoding;
use function mb_strcut;
use function mb_strtolower;
use function md5;
use function pathinfo;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\purify_html;
use function Qubus\Support\Helpers\remove_trailing_slash;
use function realpath;
use function rmdir;
use function round;
use function scandir;
use function shell_exec;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;
use function strtotime;
use function substr;
use function trim;
use function ucfirst;
use function unlink;
use function urldecode;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_NOBODY;
use const JSON_PRETTY_PRINT;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 * Turn all URLs into clickable links.
 *
 * @file core/Shared/Helpers/core.php
 * @param string                $value      Input text.
 * @param list<string>          $protocols  Supported protocols, e.g. ['http', 'https', 'mail', 'x'].
 * @param array<string,string>  $attributes Extra HTML attributes to add to generated <a> tags.
 * @return string
 */
function make_clickable(string $value, array $protocols = ['http', 'mail'], array $attributes = []): string
{
    // Build safe attribute string
    $attr = '';
    foreach ($attributes as $key => $val) {
        $key = purify_html($key);
        $val = purify_html($val);
        $attr .= " {$key}=\"{$val}\"";
    }

    $links = [];

    // Temporarily replace existing links/tags to avoid double-wrapping
    $value = preg_replace_callback(
        '~(<a [^>]+>.*?</a>|<[^>]+>)~i',
        static fn($m) => '<' . array_push($links, $m[1]) . '>',
        $value
    ) ?? $value;

    foreach ($protocols as $protocol) {
        $value = match ($protocol) {
            'http', 'https' =>
            preg_replace_callback(
                '~(?:(?P<scheme>https?)://(?P<url>[^\s<]+)|(?P<www>www\.[^\s<]+?\.[^\s<]+))(?<![\.,:])~i',
                static function ($m) use (&$links, $attr) {
                    $scheme = $m['scheme'] ?: 'http';
                    $url    = $m['url'] ?: $m['www'];
                    $safe   = purify_html($url);
                    return '<' . array_push(
                        $links,
                        "<a{$attr} href=\"{$scheme}://{$safe}\">{$safe}</a>"
                    ) . '>';
                },
                $value
            ) ?? $value,

            'mail' =>
            preg_replace_callback(
                '~(?P<email>[^\s<]+@[^\s<]+?\.[^\s<]+)(?<![\.,:])~',
                static function ($m) use (&$links, $attr) {
                    $email = purify_html($m['email']);
                    return '<' . array_push(
                        $links,
                        "<a{$attr} href=\"mailto:{$email}\">{$email}</a>"
                    ) . '>';
                },
                $value
            ) ?? $value,

            'twitter', 'x' =>
            preg_replace_callback(
                '~(?<!\\w)(?P<symbol>[@#])(?P<handle>\\w++)~',
                static function ($m) use (&$links, $attr) {
                    $handle = purify_html($m['handle']);
                    $symbol = $m['symbol'];
                    $url    = $symbol === '@'
                            ? "https://x.com/{$handle}"
                            : "https://x.com/search/%23{$handle}";
                    $safe   = "{$symbol}{$handle}";
                    return '<' . array_push(
                        $links,
                        "<a{$attr} href=\"{$url}\">{$safe}</a>"
                    ) . '>';
                },
                $value
            ) ?? $value,

            default =>
            preg_replace_callback(
                '~' . preg_quote($protocol, '~') . '://([^\s<]+?)(?<![\.,:])~i',
                static function ($m) use (&$links, $attr, $protocol) {
                    $link = purify_html($m[1]);
                    return '<' . array_push(
                        $links,
                        "<a{$attr} href=\"{$protocol}://{$link}\">{$link}</a>"
                    ) . '>';
                },
                $value
            ) ?? $value,
        };
    }

    // Reinsert placeholders
    return preg_replace_callback(
        '/<(\\d+)>/',
        static fn($m) => $links[(int)$m[1] - 1] ?? $m[0],
        $value
    ) ?? $value;
}

/**
 * Checks if a remote file exists.
 *
 * @file core/Shared/Helpers/core.php
 * @return bool True on success, false otherwise.
 */
function remote_file_exists(string $url): bool
{
    $curl = curl_init(url: $url);
    //don't fetch the actual page, you only want to check the connection is ok
    curl_setopt(handle: $curl, option: CURLOPT_NOBODY, value: true);
    //do request
    $result = curl_exec(handle: $curl);
    $ret = false;
    //if request did not fail
    if ($result !== false) {
        //if request was ok, check response code
        $statusCode = curl_getinfo(handle: $curl, option: CURLINFO_HTTP_CODE);

        if ($statusCode === 200) {
            $ret = true;
        }
    }
    curl_close(handle: $curl);

    return $ret;
}

/**
 * @file core/Shared/Helpers/core.php
 * @param string $file Filepath
 * @param int $digits Digits to display.
 * @return string|bool Size (KB, MB, GB, TB) on success or false otherwise.
 */
function get_file_size(string $file, int $digits = 2): bool|string
{
    if (is_file($file)) {
        $fileSize = filesize($file);
        $sizes = ["TB", "GB", "MB", "KB", "B"];
        $total = count($sizes);
        while ($total-- && $fileSize > 1024) {
            $fileSize /= 1024;
        }
        return round($fileSize, $digits) . " " . $sizes[$total];
    }
    return false;
}

/**
 * Outputs the HTML checked attribute.
 *
 * Compares the first two arguments and if identical marks as checked.
 *
 * @file core/Shared/Helpers/core.php
 * @param mixed $checked One of the values to compare
 * @param mixed $current (true) The other value to compare if not just true.
 * @param bool $echo Whether to echo or just return the string.
 * @return string HTML attribute or empty string
 */
function checked(mixed $checked, mixed $current = true, bool $echo = true): string
{
    return checked_selected_helper($checked, $current, $echo, 'checked');
}

/**
 * Outputs the HTML selected attribute.
 *
 * Compares the first two arguments and if identical marks as selected.
 *
 * @file core/Shared/Helpers/core.php
 * @param mixed $selected One of the values to compare.
 * @param mixed $current (true) The other value to compare if not just true.
 * @param bool $echo Whether to echo or just return the string.
 * @return string HTML attribute or empty string
 */
function selected(mixed $selected, mixed $current = true, bool $echo = true): string
{
    return checked_selected_helper($selected, $current, $echo, 'selected');
}

/**
 * Outputs the html disabled attribute.
 *
 * Compares the first two arguments and if identical marks as disabled.
 *
 * @file core/Shared/Helpers/core.php
 * @param mixed $disabled One of the values to compare.
 * @param mixed $current (true) The other value to compare if not just true.
 * @param bool $echo Whether to echo or just return the string.
 * @return string HTML attribute or empty string
 */
function disabled(mixed $disabled, mixed $current = true, bool $echo = true): string
{
    return checked_selected_helper($disabled, $current, $echo, 'disabled');
}

/**
 * Private helper function for checked, selected, and disabled.
 *
 * Compares the first two arguments and if identical marks as $type.
 *
 * @access private
 *
 * @file core/Shared/Helpers/core.php
 * @param mixed $helper One of the values to compare.
 * @param mixed $current (true) The other value to compare if not just true.
 * @param bool $echo Whether to echo or just return the string.
 * @param string $type The type of checked|selected|disabled we are doing.
 * @return string HTML attribute or empty string
 */
function checked_selected_helper(mixed $helper, mixed $current, bool $echo, string $type): string
{
    if ($helper === $current) {
        $result = " $type='$type'";
    } else {
        $result = '';
    }

    if ($echo) {
        echo $result;
    }

    return $result;
}

/**
 * @file core/Shared/Helpers/core.php
 * @param float|int $seconds
 * @return string
 */
function seconds_to_minutes(float|int $seconds): string
{
    // get minutes
    $minResult = floor(num: $seconds / 60);

    // if minutes is between 0-9, add a "0" --> 00-09
    if ($minResult < 10) {
        $minResult = 0 . $minResult;
    }

    // get sec
    $secResult = ($seconds / 60 - $minResult) * 60;

    // if seconds is between 0-9, add a "0" --> 00-09
    if ($secResult < 10) {
        $secResult = 0 . $secResult;
    }

    // return result
    return $minResult . ":" . $secResult;
}

/**
 * Determines if SSL is used.
 *
 * @file core/Shared/Helpers/core.php
 * @return bool True if SSL, false otherwise.
 */
function is_ssl(): bool
{
    if (isset($_SERVER['HTTPS'])) {
        if ('on' === strtolower($_SERVER['HTTPS'])) {
            return true;
        }
        if ('1' === $_SERVER['HTTPS']) {
            return true;
        }
    } elseif (isset($_SERVER['SERVER_PORT']) && ('443' === $_SERVER['SERVER_PORT'])) {
        return true;
    }
    return false;
}

/**
 * Normalize a filesystem path.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $path Path to normalize.
 * @return array|string|null Normalized path.
 */
function normalize_path(string $path): array|string|null
{
    $path = str_replace(search: '\\', replace: '/', subject: $path);
    $path = preg_replace(pattern: '|(?<=.)/+|', replacement: '/', subject: $path);
    if (':' === substr(string: $path, offset: 1, length: 1)) {
        $path = ucfirst(string: $path);
    }
    return $path;
}

/**
 * Beautifies a filename for use.
 *
 * Uses `beautified.filename` filter hook.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $filename Filename to beautify.
 * @return string Beautified filename.
 * @throws Exception
 */
function beautify_filename(string $filename): string
{
    $filenameRaw = $filename;

    // reduce consecutive characters
    $filename = preg_replace([
        // "file   name.zip" becomes "file-name.zip"
            '/ +/',
        // "file___name.zip" becomes "file-name.zip"
            '/_+/',
        // "file---name.zip" becomes "file-name.zip"
            '/-+/'
    ], '-', $filenameRaw);
    $filename = preg_replace([
        // "file--.--.-.--name.zip" becomes "file.name.zip"
            '/-*\.-*/',
        // "file...name..zip" becomes "file.name.zip"
            '/\.{2,}/'
    ], '.', $filename);

    /**
     * Filters a beautified filename.
     *
     * @param string $filename     Beautified filename.
     * @param string $filenameRaw The filename prior to beautification.
     */
    $filename = __observer()->filter->applyFilter('beautified.filename', $filename, $filenameRaw);

    // lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
    $filename = mb_strtolower($filename, mb_detect_encoding($filename));
    // ".file-name.-" becomes "file-name"
    $filename = trim($filename, '.-');
    return $filename;
}

/**
 * Sanitizes a filename.
 *
 * Uses `sanitized.filename` filter hook.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $filename Name of file to sanitize.
 * @param bool $beautify Whether to beautify the sanitized filename.
 * @return string Sanitized filename for use.
 * @throws Exception
 */
function sanitize_filename(string $filename, bool $beautify = true): string
{
    $filenameRaw = $filename;
    // sanitize filename
    $filename = preg_replace(
        '~
        [<>:"/\\|?*]|            # file system reserved
        [\x00-\x1F]|             # control characters
        [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        [#\[\]@!$&\'()+,;=]|     # URI reserved
        [{}^\~`]                 # URL unsafe characters
        ~x',
        '-',
        $filenameRaw
    );
    // avoids ".", ".." or ".hiddenFiles"
    $filename = ltrim($filename, '.-');
    // avoids %20
    $filename = urldecode($filename);
    // optional beautification
    if ($beautify) {
        $filename = beautify_filename($filename);
    }

    /**
     * Filters a sanitized filename.
     *
     * @file core/Shared/Helpers/core.php
     * @param string $filename     Sanitized filename.
     * @param string $filenameRaw The filename prior to sanitization.
     */
    $filename = __observer()->filter->applyFilter('sanitized.filename', $filename, $filenameRaw);

    // maximize filename length to 255 bytes http://serverfault.com/a/9548/44086
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $filename = mb_strcut(
        pathinfo(
            $filename,
            PATHINFO_FILENAME
        ),
        0,
        255 - ($ext ? strlen($ext) + 1 : 0),
        mb_detect_encoding($filename)
    ) . ($ext ? '.' . $ext : '');
    return $filename;
}

/**
 * Returns an array of function names in a file.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $filename The path to the file.
 * @param bool $sort If true, sort results by function name.
 */
function get_functions(string $filename, bool $sort = false): array
{
    $file = file($filename);
    $functions = [];
    foreach ($file as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'function')) {
            $functions[] = strtolower(substr($line, 9, strpos($line, '(') - 9));
        }
    }
    if ($sort) {
        asort($functions);
        $functions = array_values($functions);
    }
    return $functions;
}

/**
 * Checks a given file for any duplicated named user functions.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $filename
 * @return Error|false
 */
function is_duplicate_function(string $filename): Error|false
{
    if (!file_exists($filename)) {
        return new Error(
            message: sprintf('Invalid file name: %s.', $filename),
            code: 'duplicate_function_error'
        );
    }

    $plugin = get_functions($filename);
    $functions = get_defined_functions();
    $merge = array_merge($plugin, $functions['user']);
    if (count($merge) !== count(array_unique($merge))) {
        $dupe = array_unique(array_diff_assoc($merge, array_unique($merge)));
        foreach ($dupe as $value) {
            return new Error(
                sprintf(
                    'The following function is already defined elsewhere: <strong>%s</strong>',
                    $value
                )
            );
        }
    }
    return false;
}

/**
 * Performs a check within a php script and returns any other files
 * that might have been required or included.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $filename PHP script to check.
 */
function check_includes(string $filename): array|string
{
    if (!file_exists($filename)) {
        return sprintf('Invalid file name: %s.', $filename);
    }

    // NOTE that any file coming into this function has already passed the syntax check, so
    // we can assume things like proper line terminations
    $includes = [];
    // Get the directory name of the file so we can prepend it to relative paths
    $dir = dirname($filename);

    // Split the contents of $fileName about requires and includes
    // We need to slice off the first element since that is the text up to the first include/require
    $requireSplit = array_slice(preg_split('/require|include/i', file_get_contents($filename)), 1);

    // For each match
    foreach ($requireSplit as $string) {
        // Substring up to the end of the first line, i.e. the line that the require is on
        $string = substr($string, 0, strpos($string, ";"));

        // If the line contains a reference to a variable, then we cannot analyse it
        // so skip this iteration
        if (str_contains($string, "$") !== false) {
            continue;
        }

        // Split the string about single and double quotes
        $quoteSplit = preg_split('/[\'"]/', $string);

        // The value of include is the second element of the array
        // Putting this in an if statement enforces the presence of '' or "" somewhere in the include
        // includes with any kind of run-time variable in have been excluded earlier
        // this just leaves includes with constants in, which we can't do much about
        if ($include = $quoteSplit[1]) {
            // If the path is not absolute, add the dir and separator
            // Then call realpath to chop out extra separators
            if (str_contains($include, ':') === false) {
                $include = realpath($dir . Devflow::$PHP::DS . $include);
            }

            $includes[] = $include;
        }
    }

    return $includes;
}

/**
 * Performs a syntax and error check of a given PHP script.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $filename PHP script/file to check.
 * @param bool $checkIncludes If set to true, will check if other files have been included.
 * @return Error
 * @throws NotFoundException If file does not exist or is not readable.
 * @throws Exception If file contains duplicate function names.
 */
function check_syntax(string $filename, bool $checkIncludes = true): Error
{
    // If file does not exist, or it is not readable, throw an exception
    if (!is_file($filename) || !is_readable($filename)) {
        throw new NotFoundException(sprintf('"%s" is not found or is not a regular file.', $filename), 404);
    }

    $dupeFunction = is_duplicate_function($filename);

    if ($dupeFunction instanceof Error) {
        throw new Exception($dupeFunction->getMessage());
    }

    // Sort out the formatting of the filename
    $filename = realpath($filename);

    // Get the shell output from the syntax check command
    $output = shell_exec('php -l "' . $filename . '"');

    // Try to find the parse error text and chop it off
    $syntaxError = preg_replace("/Errors parsing.*$/", "", $output, - 1, $count);

    // If the error text above was matched, throw an exception containing the syntax error
    if ($count > 0) {
        return new Error(trim($syntaxError), 'php_check_syntax');
    }

    // If we are going to check the files includes
    if ($checkIncludes) {
        foreach (check_includes($filename) as $include) {
            // Check the syntax for each include
            if (is_file($include)) {
                check_syntax($include);
            }
        }
    }

    return new Error('Nothing to check.', 'php_check_syntax');
}

/**
 * Removes directory recursively along with any files.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $dir Directory that should be removed.
 */
function rmdir__(string $dir): void
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . Devflow::$PHP::DS . $object)) {
                    rmdir__($dir . Devflow::$PHP::DS . $object);
                } else {
                    unlink($dir . Devflow::$PHP::DS . $object);
                }
            }
        }
        rmdir($dir);
    }
}

/**
 * Prints a list of timezones which includes
 * current time.
 *
 * @file core/Shared/Helpers/core.php
 * @return array Timezone list.
 * @throws DateInvalidTimeZoneException
 * @throws DateMalformedStringException
 */
function generate_timezone_list(): array
{
    static $regions = array(
        \DateTimeZone::AFRICA,
        \DateTimeZone::AMERICA,
        \DateTimeZone::ANTARCTICA,
        \DateTimeZone::ASIA,
        \DateTimeZone::ATLANTIC,
        \DateTimeZone::AUSTRALIA,
        \DateTimeZone::EUROPE,
        \DateTimeZone::INDIAN,
        \DateTimeZone::PACIFIC
    );

    $timezones = [];
    foreach ($regions as $region) {
        $timezones = array_merge($timezones, \DateTimeZone::listIdentifiers($region));
    }

    $timezoneOffsets = [];
    foreach ($timezones as $timezone) {
        $tz = new \DateTimeZone($timezone);
        $timezoneOffsets[$timezone] = $tz->getOffset(new \DateTime());
    }

    // sort timezone by timezone name
    ksort($timezoneOffsets);

    $timezoneList = [];
    foreach ($timezoneOffsets as $timezone => $offset) {
        $offsetPrefix = $offset < 0 ? '-' : '+';
        $offsetFormatted = gmdate('H:i', abs($offset));

        $prettyOffset = "UTC{$offsetPrefix}{$offsetFormatted}";

        $t = new \DateTimeZone($timezone);
        $c = new \DateTime('', $t);
        $currentTime = $c->format('g:i A');

        $timezoneList[$timezone] = "({$prettyOffset}) $timezone - $currentTime";
    }

    return $timezoneList;
}

/**
 * Get age by birthdate.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $birthdate User's birthdate.
 * @return string|int
 */
function get_age(string $birthdate = '0000-00-00'): string|int
{
    $birth = new QubusDateTime($birthdate);
    $age = $birth->age;

    if ($birthdate <= '0000-00-00' || $age <= 0) {
        return trans('Unknown');
    }
    return $age;
}

/**
 * Generates a unique key.
 *
 * @file core/Shared/Helpers/core.php
 * @param int $length
 * @return string
 */
function generate_unique_key(int $length = 6): string
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $uniqueKey = '';
    for ($i = 0; $i < $length; $i++) {
        $uniqueKey .= $characters[rand(0, $charactersLength - 1)];
    }
    return $uniqueKey;
}

/**
 * Converts date to GMT format.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $string The date to be converted.
 * @param string $format The format string for the converted date.
 * @return string
 */
function convert_date_to_gmt(string $string = 'now', string $format = 'Y-m-d H:i:s'): string
{
    $date = str_replace(['AM', 'PM'], '', $string);
    return new DateTime(time: $date, timezone: 'GMT')->format(format: $format);
}

/**
 * Converts seconds to time format.
 *
 * @file core/Shared/Helpers/core.php
 * @param int $seconds
 * @return string
 */
function convert_seconds_to_time(int $seconds): string
{
    $ret = "";

    /** get the days */
    $days = intval(($seconds) / (3600 * 24));
    if ($days > 0) {
        $ret .= "$days days ";
    }

    /** get the hours */
    $hours = (($seconds) / 3600) % 24;
    if ($hours > 0) {
        $ret .= "$hours hours ";
    }

    /** get the minutes */
    $minutes = (($seconds) / 60) % 60;
    if ($minutes > 0) {
        $ret .= "$minutes minutes ";
    }

    /** get the seconds */
    $seconds = ($seconds) % 60;
    if ($seconds > 0) {
        $ret .= "$seconds seconds";
    }

    return $ret;
}

/**
 * Add the template to the message body.
 *
 * Looks for {{content}} or {{notification_message}} in the template and replaces it with the message.
 *
 * Uses `email.template` filter hook.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $body The message to templatize.
 * @return string $email The email surrounded by template.
 * @throws Exception
 */
function set_email_template(string $body): string
{
    $tpl = file_get_contents(resource_path('tpl' . Devflow::$PHP::DS . 'notification-email.html'));

    $template = __observer()->filter->applyFilter('email.template', $tpl);

    return str_replace(['{{content}}', '{{notification_message}}'], $body, $template);
}

/**
 * Replace variables in the template.
 *
 * Uses `email.template.tags` filter hook.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $template Template with variables.
 * @return string Template with variables replaced.
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 */
function replace_template_vars(string $template): string
{
    $varArray = [
        'site_name' => get_option(key: 'sitename'),
        'site_url' => site_url(),
        'site_description' => get_option(key: 'site_description'),
        'admin_email' => get_option(key: 'admin_email'),
        'date_format' => get_option(key: 'date_format'),
        'time_format' => get_option(key: 'time_format')
    ];

    $toReplace = __observer()->filter->applyFilter('email.template.tags', $varArray);

    foreach ($toReplace as $tag => $var) {
        $template = str_replace(search: '{{' . $tag . '}}', replace: $var, subject: $template);
    }

    return $template;
}

/**
 * Process the HTML version of the text.
 *
 * Uses `email.template.body` filter hook.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $text
 * @param string $title
 * @return string
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 */
function process_email_html(string $text, string $title): string
{
    // Add template to message
    $template = set_email_template($text);

    // Replace title tag with $title.
    $body = str_replace('{{title}}', $title, $template);

    // Replace variables in email
    return __observer()->filter->applyFilter('email.template.body', replace_template_vars($body));
}

/**
 * Retrieve the domain name.
 *
 * @file core/Shared/Helpers/core.php
 * @return string
 */
function get_domain_name(): string
{
    $request = new Request();
    $serverName = strtolower($request->getServerName());
    if (str_starts_with($serverName, 'www.')) {
        $serverName = substr($serverName, 4);
    }
    return $serverName;
}

/**
 * Uses `default.css.pipeline`, `plugin.css.pipeline` and `theme.css.pipeline`
 *  filter hooks.
 *
 * @param string $config
 * @param string|array $asset
 * @param bool|string $minify
 * @param string|null $slug
 * @return string
 * @throws Exception
 */
function cms_render_css(
    string $config,
    string|array $asset,
    bool|string $minify = false,
    ?string $slug = null
): string {
    if ($config === 'default') {
        $options = [
            'public_dir' => remove_trailing_slash(public_path()),
            'css_dir' => 'static' . Devflow::$PHP::DS . 'assets' . Devflow::$PHP::DS  . 'css',
            'pipeline' => __observer()->filter->applyFilter('default.css.pipeline', $minify),
            'pipeline_dir' => 'minify',
        ];
        $default = new AppAssets($options);
        $default->reset()->add($asset);
    } elseif ($config === 'plugin') {
        $options = [
            'public_dir' => remove_trailing_slash(public_path()),
            'css_dir' => sprintf('static/tmp/plugin/%s/css', $slug),
            'pipeline' => __observer()->filter->applyFilter('plugin.css.pipeline', $minify),
            'pipeline_dir' => 'minify'
        ];
        $default = new PluginAssets($options);
        $default->reset()->add($asset);
    } elseif ($config === 'theme') {
        $options = [
            'public_dir' => remove_trailing_slash(public_path()),
            'css_dir' => sprintf('static/tmp/theme/%s/css', $slug),
            'pipeline' => __observer()->filter->applyFilter('theme.css.pipeline', $minify),
            'pipeline_dir' => 'minify'
        ];
        $default = new ThemeAssets($options);
        $default->reset()->add($asset);
    }
    return $default->css();
}

/**
 * Uses `default.js.pipeline`, `plugin.js.pipeline` and `theme.js.pipeline`
 *  filter hooks.
 *
 * @param string $config
 * @param array|string $asset
 * @param bool|string $minify
 * @param string|null $slug
 * @return string
 * @throws Exception
 */
function cms_render_js(
    string $config,
    array|string $asset,
    bool|string $minify = false,
    ?string $slug = null
): string {
    if ($config === 'default') {
        $options = [
            'public_dir' => remove_trailing_slash(public_path()),
            'js_dir' => 'static' . Devflow::$PHP::DS  . 'assets' . Devflow::$PHP::DS  . 'js',
            'pipeline' => __observer()->filter->applyFilter('default.js.pipeline', $minify),
            'pipeline_dir' => 'minify',
        ];
        $default = new AppAssets($options);
        $default->reset()->add($asset);
    } elseif ($config === 'plugin') {
        $options = [
            'public_dir' => remove_trailing_slash(public_path()),
            'js_dir' => sprintf('static/tmp/plugin/%s/js', $slug),
            'pipeline' => __observer()->filter->applyFilter('plugin.js.pipeline', $minify),
            'pipeline_dir' => 'minify'
        ];
        $default = new PluginAssets($options);
        $default->reset()->add($asset);
    } elseif ($config === 'theme') {
        $options = [
            'public_dir' => remove_trailing_slash(public_path()),
            'js_dir' => sprintf('static/tmp/theme/%s/js', $slug),
            'pipeline' => __observer()->filter->applyFilter('theme.js.pipeline', $minify),
            'pipeline_dir' => 'minify'
        ];
        $default = new ThemeAssets($options);
        $default->reset()->add($asset);
    }
    return $default->js();
}

/**
 * Registers assets and avoids duplications.
 *
 * @param string $type
 * @param string $config
 * @param array|string $asset
 * @param bool|string $minify
 * @param string|null $slug
 * @param string $location
 * @return void
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function cms_register_asset(
    string $type,
    string $config,
    array|string $asset,
    bool|string $minify = false,
    ?string $slug = null,
    string $location = 'default'
): void {
    $queue = Registry::getInstance()->has('cms.asset.queue')
        ? Registry::getInstance()->get('cms.asset.queue')
        : [
            'css' => [],
            'js' => [
                'head' => [],
                'footer' => [],
            ],
        ];

    $key = md5(json_encode([$type, $location, $config, $asset, $minify, $slug]));

    if ($type === 'js') {
        $queue['js'][$location][$key] = [
            'config' => $config,
            'asset' => $asset,
            'minify' => $minify,
            'slug' => $slug,
        ];
    } else {
        $queue['css'][$key] = [
            'config' => $config,
            'asset' => $asset,
            'minify' => $minify,
            'slug' => $slug,
        ];
    }

    Registry::getInstance()->set('cms.asset.queue', $queue);
}

/**
 * Prints the enqueued asset in the proper location.
 *
 * @param string $type
 * @param string $location
 * @return void
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function cms_print_registered_assets(string $type, string $location = 'default'): void
{
    $queue = Registry::getInstance()->has('cms.asset.queue')
        ? Registry::getInstance()->get('cms.asset.queue')
        : [
            'css' => [],
            'js' => [
                'head' => [],
                'footer' => [],
            ],
        ];

    if ($type === 'js') {
        foreach ($queue['js'][$location] ?? [] as $key => $item) {
            echo cms_render_js($item['config'], $item['asset'], $item['minify'], $item['slug']);
            unset($queue['js'][$location][$key]);
        }
    }

    if ($type === 'css') {
        foreach ($queue['css'] ?? [] as $key => $item) {
            echo cms_render_css($item['config'], $item['asset'], $item['minify'], $item['slug']);
            unset($queue['css'][$key]);
        }
    }

    Registry::getInstance()->set('cms.asset.queue', $queue);
}

/**
 * Enqueues stylesheets.
 *
 * Example Usage:
 *
 *      cms_enqueue_css('default', '//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css')
 *      cms_enqueue_css('plugin', ['fontawesome','select2-css'], false, plugin_basename( dirname(__FILE__) ))
 *      cms_enqueue_css('theme', 'theme-slug/assets/css/style.css')
 *
 * @file core/Shared/Helpers/core.php
 * @param string $config Set whether to use `default` config or `plugin` config.
 * @param string|array $asset Relative path or URL to stylesheet(s) to enqueue.
 * @param bool|string $minify Enable CSS assets pipeline (concatenation and minification).
 *                            Use a string that evaluates to `true` to provide the salt of the pipeline hash.
 *                            Use 'auto' to automatically calculate the salt from your assets last modification time.
 * @param string|null $slug   Slug to set asset location
 * @return void
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function cms_enqueue_css(
    string $config,
    string|array $asset,
    bool|string $minify = false,
    ?string $slug = null
): void {
    cms_register_asset('css', $config, $asset, $minify, $slug);
}

/**
 * Enqueues JavaScript.
 *
 * Example Usage:
 *
 *      cms_enqueue_js('default', 'jquery-ui')
 *      cms_enqueue_js('plugin', 'select2-js', false, plugin_basename( dirname(__FILE__) ))
 *      cms_enqueue_js('theme', 'theme-slug/assets/js/config.js')
 *
 * @file core/Shared/Helpers/core.php
 * @param string $config Set whether to use `default`, `plugin`  or `theme` config.
 * @param string|array $asset Relative path or URL to JavaScript(s) to enqueue.
 * @param bool|string $minify Enable js assets pipeline (concatenation and minification).
 *                            Use a string that evaluates to `true` to provide the salt of the pipeline hash.
 *                            Use 'auto' to automatically calculate the salt from your assets last modification time.
 * @param string|null $slug   Slug to set asset location.
 * @param string $location    Javascript should be in head or footer.
 * @return void
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function cms_enqueue_js(
    string $config,
    array|string $asset,
    bool|string $minify = false,
    ?string $slug = null,
    string $location = 'footer'
): void {
    cms_register_asset('js', $config, $asset, $minify, $slug, $location);
}


/**
 * Generates a random username.
 *
 * @file core/Shared/Helpers/core.php
 * @param int $length
 * @return string
 */
function generate_random_username(int $length = 6): string
{
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $username = '';
    for ($i = 0; $i < $length; $i++) {
        $username .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $username;
}

/**
 * Generates a random password drawn from the defined set of characters.
 *
 * Uses `random_lib` library to create passwords with far less predictability.
 *
 * @file core/Shared/Helpers/core.php
 * @param int $length Optional. The length of password to generate. Default 12.
 * @param bool $specialChars Optional. Whether to include standard special characters.
 *                           Default true.
 * @param bool $extraSpecialChars Optional. Whether to include other special characters.
 *                                Default false.
 * @return string The system generated password.
 * @throws Exception
 */
function generate_random_password(
    int $length = 12,
    bool $specialChars = true,
    bool $extraSpecialChars = false
): string {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    if ($specialChars) {
        $chars .= '!@#$%^&*()';
    }

    if ($extraSpecialChars) {
        $chars .= '-_ []{}<>~`+=,.;:/?|';
    }

    $password = new Factory()->getGenerator(new Strength(Strength::MEDIUM))->generateString($length, $chars);

    /**
     * Filters the system generated password.
     *
     * @file core/Shared/Helpers/core.php
     * @param string $password          The generated password.
     * @param int    $length            The length of password to generate.
     * @param bool   $specialChars      Whether to include standard special characters.
     * @param bool   $extraSpecialChars Whether to include other special characters.
     */
    return __observer()->filter->applyFilter(
        'random.password',
        $password,
        $length,
        $specialChars,
        $extraSpecialChars
    );
}

/**
 * Get the current screen.
 *
 * @file core/Shared/Helpers/core.php
 * @return string|null Current screen or null if screen is not defined.
 * @throws ReflectionException
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 */
function get_current_screen(): ?string
{
    $currentScreen = Registry::getInstance()->has('screen_child') ?
    Registry::getInstance()->get('screen_child') :
    Registry::getInstance()->get('screen_parent');

    if (!isset($currentScreen)) {
        return null;
    }

    return $currentScreen;
}

/**
 * Image optimizer.
 *
 * @access private
 *
 * @file core/Shared/Helpers/core.php
 * @param string $pathToImage Path to original image.
 * @param string $pathToOptimized Path to where optimized image should be saved.
 * @return void Optimized image.
 */
function _cms_image_optimizer(string $pathToImage, string $pathToOptimized): void
{
    $optimizerChain = OptimizerChainFactory::create();
    $optimizerChain
        ->setTimeout(timeoutInSeconds: 30)
        ->optimize(pathToImage: $pathToImage, pathToOutput: $pathToOptimized);
}

/**
 * Sort array of objects by field.
 *
 * Example Usage:
 *
 *      sort_list($content,'content_id','ASC', false);
 *
 * @file core/Shared/Helpers/core.php
 * @param array $objects        Array of objects to sort.
 * @param array|string $orderby Name of field or array of fields to filter by.
 * @param string $order         (ASC|DESC)
 * @param bool $preserveKeys    Whether to preserve keys.
 * @return array Returns a sorted array.
 */
function sort_list(
    array &$objects,
    array|string $orderby = [],
    string $order = 'ASC',
    bool $preserveKeys = false
): array {
    if (empty($objects)) {
        return [];
    }

    $util = new ListUtil($objects);
    return $util->sort($orderby, $order, $preserveKeys);
}

/**
 * Site path.
 *
 * @file core/Shared/Helpers/core.php
 * @param string|null $path
 * @return string
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function site_path(?string $path = null): string
{
    $siteDirectoryKey = site_directory_key(Registry::getInstance()->get('siteKey'));

    return public_path(
        'site' . Devflow::$PHP::DS . $siteDirectoryKey . Devflow::$PHP::DS . $path
    );
}

/**
 * Encrypt function.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $string
 * @return string
 * @throws BadFormatException
 * @throws EnvironmentIsBrokenException
 * @throws TypeException
 */
function encrypt(string $string): string
{
    return Crypto::encrypt($string, Key::loadFromAsciiSafeString(config()->string(key: 'app.crypto_key')));
}

/**
 * Decrypt function.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $string
 * @return string
 * @throws BadFormatException
 * @throws EnvironmentIsBrokenException
 * @throws TypeException
 * @throws WrongKeyOrModifiedCiphertextException
 */
function decrypt(string $string): string
{
    return Crypto::decrypt($string, Key::loadFromAsciiSafeString(config()->string(key: 'app.crypto_key')));
}

/**
 * Serializes data if necessary.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $data Data to be serialized.
 * @return mixed Serialized data or original string.
 * @throws TypeException
 */
function maybe_serialize(mixed $data): mixed
{
    if (is_resource($data)) {
        throw new TypeException(
            "PHP resources are not serializable."
        );
    }

    if (is_array($data) || is_object($data)) {
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    return $data;
}

/**
 * Unserializes data if necessary.
 *
 * @file core/Shared/Helpers/core.php
 * @param mixed $data Data that should be unserialized.
 * @return mixed Unserialized data or original input.
 */
function maybe_unserialize(mixed $data): mixed
{
    /**
     * Check data first to make sure it can be unserialized.
     */
    if (is_serialized($data)) {
        return json_decode($data, true);
    }

    return $data;
}

/**
 * Checks if data is serialized.
 *
 * @file core/Shared/Helpers/core.php
 * @param object|array|string $data
 * @return bool
 */
function is_serialized(object|array|string $data): bool
{
    return json_validate($data);
}

/**
 * Sets a registry parameter.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $key
 * @param mixed $value
 * @return void
 * @throws ReflectionException
 */
function set_registry_entry(string $key, mixed $value): void
{
    Registry::getInstance()->set($key, $value);
}

/**
 * Finds an entry of the registry by its identifier and returns it.
 *
 * @file core/Shared/Helpers/core.php
 * @param string $key
 * @return mixed
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_registry_entry(string $key): mixed
{
    return Registry::getInstance()->get($key);
}

/**
 * @param string $key
 * @param string $value
 * @return string
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function current_screen(string $key, string $value): string
{
    if (Registry::getInstance()->has($key)) {
        if (Registry::getInstance()->get($key) === $value) {
            return ' active';
        }
    }

    return '';
}

/**
 * Whether multisite is enabled.
 *
 * @return bool
 * @throws TypeException
 */
function is_multisite(): bool
{
    return config()->boolean(key: 'cms.multisite');
}

/**
 * Prints elapsed time based on datetime.
 */
function time_ago(string $original): string
{
    // array of time period chunks
    $chunks = [
        [60 * 60 * 24 * (date('z', mktime(0, 0, 0, 12, 31, (int) date('Y'))) + 1), 'year'],
        [60 * 60 * 24 * date('t'), 'month'],
        [60 * 60 * 24 * 7, 'week'],
        [60 * 60 * 24, 'day'],
        [60 * 60, 'hour'],
        [60, 'min'],
        [1, 'sec'],
    ];

    $today = time(); /* Current unix time  */
    $since = $today - strtotime($original);

    // $j saves performing the count function each time around the loop
    for ($i = 0, $j = count($chunks); $i < $j; $i++) {
        $seconds = $chunks[$i][0];
        $name = $chunks[$i][1];

        // finding the biggest chunk (if the chunk fits, break)
        if (($count = floor($since / $seconds)) != 0) {
            break;
        }
    }

    $print = ($count == 1) ? '1 ' . $name : "$count {$name}s";

    if ($i + 1 < $j) {
        // now getting the second item
        $seconds2 = $chunks[$i + 1][0];
        $name2 = $chunks[$i + 1][1];

        // add second item if it's greater than 0
        if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) {
            $print .= ($count2 == 1) ? ', 1 ' . $name2 : " $count2 {$name2}s";
        }
    }
    return $print . ' ago';
}

/**
 * @throws Exception
 */
function updater_server_url(): string
{
    /**
     * Filters the update api version.
     */
    $apiVersion = __observer()->filter->applyFilter('updater.api.version', 'api/2.0');
    /**
     * Filters the update base server url.
     */
    $updateBaseUrl = __observer()->filter->applyFilter(
        'updater.base.url',
        'https://devflow-cmf.s3.amazonaws.com'
    );
    /**
     * Filters the updater url where update.json and
     * releases.json are located.
     */
    return __observer()->filter->applyFilter(
        'updater.url',
        sprintf('%s/%s', $updateBaseUrl, $apiVersion)
    );
}

/**
 * @throws NotFoundExceptionInterface
 * @throws ContainerExceptionInterface
 * @throws ReflectionException
 * @throws InvalidArgumentException
 * @throws Exception
 */
function show_update_message(): void
{
    $currentUserId = get_current_user_id();

    if (
            get_user_option('role', $currentUserId) === 'super' ||
            get_user_option('role', $currentUserId) === 'admin'
    ) {
        $update = new Updater();
        $update->setCurrentVersion(Devflow::release());
        $update->setUpdateUrl(updater_server_url() . '/update-check');

        try {
            if ($update->checkUpdate() !== false) {
                if ($update->newVersionAvailable()) {
                    $alert = '<div class="alert alert-dismissible show alert-info center" role="alert">';
                    $alert .= sprintf(
                        trans(
                            'Devflow release %s is available for download or upgrade. Before upgrading, make sure to backup your system.',
                        ),
                        $update->latestVersion
                    );
                    $alert .= '</div>';

                    echo __observer()->filter->applyFilter('update.message', $alert);
                }
            }
        } catch (Exception $e) {
            FileLoggerFactory::error($e->getMessage(), ['Core Functions' => 'show_update_message']);
            return;
        }
    }
}
