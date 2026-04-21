<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Vihzhuo;

use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;
use Vihzhuo\Theme;

use function App\Shared\Helpers\get_theme;

final class VihzhuoTheme extends Theme
{
    /**
     * Resolve the theme slug/folder name from the stored DB identifier.
     *
     * Rules:
     * - If a fully qualified theme namespace is provided, use segment #2:
     *   Theme\BootstrapBusiness\BootstrapBusiness => BootstrapBusiness
     *   Theme\Vapor\VaporTheme => Vapor
     * - Otherwise treat the input as an already-normalized slug.
     *
     * @throws TypeException
     */
    protected static function extractThemeSlugFromStoredIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new TypeException('Theme identifier cannot be empty.');
        }

        $segments = preg_split('/[\\\\\/]+/', $identifier);
        $segments = is_array($segments)
                ? array_values(array_filter($segments, static fn ($segment): bool => $segment !== ''))
                : [];

        if (count($segments) >= 3 && $segments[0] === 'Theme') {
            return self::normalizeThemeName($segments[1]);
        }

        return self::normalizeThemeName($identifier);
    }

    /**
     * Normalize a theme name into the folder slug used by this loader.
     *
     * In your system, this should remain the theme namespace segment / folder name,
     * not the final class name.
     *
     * @throws TypeException
     */
    protected static function normalizeThemeName(string $themeName): string
    {
        $themeName = trim($themeName);

        if ($themeName === '') {
            throw new TypeException('Theme name cannot be empty.');
        }

        $segments = preg_split('/[\\\\\/]+/', $themeName);
        $segments = is_array($segments)
                ? array_values(array_filter($segments, static fn ($segment): bool => $segment !== ''))
                : [];

        if ($segments === []) {
            throw new TypeException('Theme name is invalid.');
        }

        // If someone accidentally passes Theme\Foo\Bar here, keep the actual theme name.
        if (count($segments) >= 2 && $segments[0] === 'Theme') {
            return basename($segments[1]);
        }

        return basename($segments[0]);
    }

    /**
     * @param string|null $themeName
     * @return string
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws TypeException
     */
    public static function activeTheme(?string $themeName = null): string
    {
        $identifier = get_theme();
        if(empty($identifier)) {
            $identifier = $themeName;
        }

        return self::extractThemeSlugFromStoredIdentifier($identifier);
    }
}
