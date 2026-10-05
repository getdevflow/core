<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Vihzhuo;

use App\Shared\Services\PhpFileParser;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;
use RuntimeException;
use Vihzhuo\Theme;
use Vihzhuo\ThemeResource;

use function App\Shared\Helpers\get_parent_theme;
use function App\Shared\Helpers\get_theme;
use function glob;
use function sprintf;

final class VihzhuoTheme extends Theme
{
    /** @var array<string, class-string> */
    private array $themeClasses = [];

    /**
     * The configured slug is a fallback; the current site's selection takes precedence.
     * Supply previewTheme explicitly when previewing a different installed theme.
     *
     * @param array<string, mixed> $config
     * @param string $themeSlug
     * @param string|null $previewTheme
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws TypeException
     */
    public function __construct(array $config, string $themeSlug, ?string $previewTheme = null)
    {
        $identifier = $previewTheme ?? get_theme();
        if ($identifier === '') {
            $identifier = $themeSlug;
        }

        $slug = self::extractThemeSlugFromStoredIdentifier($identifier);
        if (str_contains($identifier, '\\')) {
            $this->themeClasses[$slug] = ltrim(trim($identifier), '\\');
        }

        parent::__construct($config, $slug);
    }

    /**
     * @throws TypeException
     */
    protected function getParentThemeSlug(string $themeSlug): ?string
    {
        $themeClass = $this->themeClasses[$themeSlug] ?? null;
        if ($themeClass === null) {
            // Theme entry points use *Theme.php, but the class name need not match its folder.
            $folder = $this->config['folder'] . '/' . $themeSlug;
            $files = glob($folder . '/*Theme.php') ?: [];
            if (count($files) !== 1) {
                throw new RuntimeException(sprintf('Cannot resolve Devflow theme declaration in %s.', $folder));
            }
            ThemeResource::assertContained($this->config['folder'], $files[0]);
            $themeClass = PhpFileParser::classFullNameFromFile($files[0]);
            $this->themeClasses[$themeSlug] = $themeClass;
        }

        $parent = get_parent_theme($themeClass);
        if ($parent === null) {
            return null;
        }

        $parentSlug = self::extractThemeSlugFromStoredIdentifier($parent);
        $this->themeClasses[$parentSlug] = $parent;

        return $parentSlug;
    }

    public function inheritsResourceFiles(): bool
    {
        return false;
    }

    /** Devflow serves a theme's public/ directory at its folder URL. */
    public function getAssetPath(string $path): string
    {
        $filePath = rawurldecode(preg_split('/[?#]/', $path, 2)[0] ?? '');
        ThemeResource::validatePath($filePath);
        $slug = $this->themeSlug;
        foreach ($this->getThemeFolders() as $candidate => $folder) {
            if (
                $filePath !== ''
                && (ThemeResource::findInFolder($folder, 'public/' . $filePath) !== null
                || ThemeResource::findInFolder($folder, $filePath) !== null)
            ) {
                $slug = $candidate;
                break;
            }
        }

        return rtrim($this->config['folder_url'] ?? '/themes', '/') . '/' . $slug . '/' . $path;
    }

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
            ThemeResource::validateSlug($segments[1]);
            return $segments[1];
        }

        ThemeResource::validateSlug($themeName);
        return $themeName;
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
        if ($identifier === '') {
            $identifier = $themeName ?? '';
        }

        return self::extractThemeSlugFromStoredIdentifier($identifier);
    }
}
