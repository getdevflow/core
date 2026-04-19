<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Vihzhuo;

use App\Application\Devflow;
use DirectoryIterator;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;
use RuntimeException;
use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Extensions;
use Vihzhuo\ThemeBlock;
use Vihzhuo\ThemeLayout;

use function App\Shared\Helpers\get_theme;

final class VihzhuoTheme implements ThemeContract
{
    /**
     * @var array $config
     */
    protected array $config;

    /**
     * @var string $themeSlug
     */
    protected string $themeSlug;

    /**
     * @var array<string, ThemeBlock>
     */
    protected array $blocks;

    /**
     * @var array<string, ThemeLayout>
     */
    protected array $layouts;

    /**
     * Theme constructor.
     *
     * @param array<string, mixed> $config
     * @param string $themeSlug
     * @throws TypeException
     */
    public function __construct(array $config, string $themeSlug)
    {
        $this->config = $config;
        $this->themeSlug = $themeSlug;

        $this->assertValidInheritanceChain();
    }

    /**
     * Return the active theme slug.
     */
    public function getThemeSlug(): string
    {
        return $this->themeSlug;
    }

    /**
     * @inheritDoc
     */
    public function getThemeBlocks(): array
    {
        $this->loadThemeBlocks();
        return $this->blocks;
    }

    /**
     * @inheritDoc
     */
    public function getThemeLayouts(): array
    {
        $this->loadThemeLayouts();
        return $this->layouts;
    }

    /**
     * @inheritDoc
     */
    public function getFolder(): string
    {
        $baseFolder = $this->config['folder'] ?? null;

        if (!is_string($baseFolder) || $baseFolder === '') {
            throw new RuntimeException('Theme configuration is missing a valid "folder" path.');
        }

        return rtrim($baseFolder, '/\\') . Devflow::$PHP::DS . $this->themeSlug;
    }

    /**
     * Whether the current theme is a child theme.
     */
    public function isChildTheme(): bool
    {
        return $this->getParentThemeSlug() !== null;
    }

    /**
     * Return the parent theme slug, if any.
     *
     * Preferred resolution order:
     * 1. Theme-local theme.php file via discoverParentThemeSlug()
     * 2. Optional config fallback: ['parents' => ['child' => 'parent']]
     */
    public function getParentThemeSlug(): ?string
    {
        $discovered = $this->discoverParentThemeSlug();
        if ($discovered !== null) {
            return $discovered;
        }

        $parents = $this->config['parent'] ?? null;

        if (!is_array($parents)) {
            return null;
        }

        $parent = $parents[$this->themeSlug] ?? null;

        return is_string($parent) && $parent !== ''
            ? basename($parent)
            : null;
    }

    /**
     * Discover the parent theme slug from the theme's own metadata file.
     *
     * Recommended structure:
     *
     * themes/my-child/theme.php
     *
     * returning:
     *
     * [
     *     'parent' => 'my-parent',
     * ]
     */
    protected function discoverParentThemeSlug(): ?string
    {
        $themeConfigFile = $this->getFolder() . Devflow::$PHP::DS . 'theme.php';

        if (!file_exists($themeConfigFile)) {
            return null;
        }

        $data = require $themeConfigFile;

        if (!is_array($data)) {
            throw new RuntimeException(sprintf(
                'Theme metadata file "%s" must return an array.',
                $themeConfigFile
            ));
        }

        $parent = $data['parent'] ?? null;

        if (!is_string($parent) || $parent === '') {
            return null;
        }

        return basename($parent);
    }

    /**
     * Return the absolute folder path of the parent theme, if any.
     */
    public function getParentFolder(): ?string
    {
        $parentSlug = $this->getParentThemeSlug();

        if ($parentSlug === null) {
            return null;
        }

        $baseFolder = $this->config['folder'] ?? null;

        if (!is_string($baseFolder) || $baseFolder === '') {
            throw new RuntimeException('Theme configuration is missing a valid "folder" path.');
        }

        $parentFolder = rtrim($baseFolder, '/\\') . Devflow::$PHP::DS . $parentSlug;

        return is_dir($parentFolder) ? $parentFolder : null;
    }

    /**
     * Load a single block from the current theme folder structure.
     */
    protected function attemptBlockRegistration(DirectoryIterator $entry): void
    {
        if (!$entry->isDir() || $entry->isDot()) {
            return;
        }

        $blockSlug = $entry->getFilename();
        $block = new ThemeBlock($this, $blockSlug);

        if ($this->isBlockActiveForCurrentUrl($block)) {
            $this->blocks[$blockSlug] = $block;
        }
    }

    /**
     * Load a single extension block entry.
     */
    protected function attemptExtensionBlockRegistration(string $slug, string $path): void
    {
        if ($slug === '' || $path === '') {
            return;
        }

        $block = new ThemeBlock($this, $path, true, $slug);

        if ($this->isBlockActiveForCurrentUrl($block)) {
            $this->blocks[$slug] = $block;
        }
    }

    /**
     * Load a single layout from the current theme folder structure.
     */
    protected function attemptLayoutRegistration(DirectoryIterator $entry): void
    {
        if (!$entry->isDir() || $entry->isDot()) {
            return;
        }

        $layoutSlug = $entry->getFilename();
        $layout = new ThemeLayout($this, $layoutSlug);

        $this->layouts[$layoutSlug] = $layout;
    }

    /**
     * Load a single extension layout entry.
     */
    protected function attemptExtensionLayoutRegistration(string $slug, string $path): void
    {
        if ($slug === '' || $path === '') {
            return;
        }

        $layout = new ThemeLayout($this, $path, true, $slug);
        $this->layouts[$slug] = $layout;
    }

    /**
     * Load a block from a specific theme folder.
     *
     * This is used for parent/child resolution.
     */
    protected function attemptBlockRegistrationFromFolder(DirectoryIterator $entry): void
    {
        if (!$entry->isDir() || $entry->isDot()) {
            return;
        }

        $blockSlug = $entry->getFilename();
        $blockPath = $entry->getPathname();

        $block = new ThemeBlock($this, $blockSlug, true, $blockPath);

        if ($this->isBlockActiveForCurrentUrl($block)) {
            $this->blocks[$blockSlug] = $block;
        }
    }

    /**
     * Load a layout from a specific theme folder.
     *
     * This is used for parent/child resolution.
     */
    protected function attemptLayoutRegistrationFromFolder(DirectoryIterator $entry): void
    {
        if (!$entry->isDir() || $entry->isDot()) {
            return;
        }

        $layoutSlug = $entry->getFilename();
        $layoutPath = $entry->getPathname();
        $layout = new ThemeLayout($this, $layoutSlug, true, $layoutPath);
        $this->layouts[$layoutSlug] = $layout;
    }

    /**
     * Determine whether a block should be active on the current URL.
     */
    protected function isBlockActiveForCurrentUrl(ThemeBlock $block): bool
    {
        $whitelist = $block->get('whitelist') ?? [];

        if (!is_array($whitelist) || $whitelist === []) {
            return true;
        }

        foreach ($whitelist as $whitelistDomain) {
            if (!is_string($whitelistDomain) || $whitelistDomain === '') {
                continue;
            }

            if (str_contains(phpb_current_full_url(), $whitelistDomain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load all blocks of the current theme, including parent theme fallback.
     *
     * Parent blocks are loaded first.
     * Child blocks are loaded second and override matching parent slugs.
     */
    protected function loadThemeBlocks(): void
    {
        $this->blocks = [];

        $folders = [
            '',
            '/archived',
            '/elements',
            '/php',
        ];

        foreach ($this->getThemeResolutionFolders() as $themeFolder) {
            foreach ($folders as $folder) {
                $blockRoot = $themeFolder . Devflow::$PHP::DS . 'blocks' . $folder;

                if (!is_dir($blockRoot)) {
                    continue;
                }

                $blocksDirectory = new DirectoryIterator($blockRoot);

                foreach ($blocksDirectory as $entry) {
                    if (!$entry->isDir() || $entry->isDot()) {
                        continue;
                    }

                    // Skip the organizational subfolders themselves.
                    if (in_array('/' . $entry->getFilename(), $folders, true)) {
                        continue;
                    }

                    $this->attemptBlockRegistrationFromFolder($entry);
                }
            }
        }

        foreach (Extensions::getBlocks() as $slug => $path) {
            if (is_string($slug) && is_string($path)) {
                $this->attemptExtensionBlockRegistration($slug, $path);
            }
        }
    }

    /**
     * Load all layouts of the current theme, including parent theme fallback.
     *
     * Parent layouts are loaded first.
     * Child layouts are loaded second and override matching parent slugs.
     */
    protected function loadThemeLayouts(): void
    {
        $this->layouts = [];

        foreach ($this->getThemeResolutionFolders() as $themeFolder) {
            $layoutRoot = $themeFolder . Devflow::$PHP::DS . 'layouts';

            if (!is_dir($layoutRoot)) {
                continue;
            }

            $layoutsDirectory = new DirectoryIterator($layoutRoot);

            foreach ($layoutsDirectory as $entry) {
                $this->attemptLayoutRegistrationFromFolder($entry);
            }
        }

        foreach (Extensions::getLayouts() as $slug => $path) {
            if (is_string($slug) && is_string($path)) {
                $this->attemptExtensionLayoutRegistration($slug, $path);
            }
        }
    }

    /**
     * Return theme folders in resolution order.
     *
     * Parent theme first, active child theme second.
     *
     * @return list<string>
     */
    protected function getThemeResolutionFolders(): array
    {
        $folders = [];

        $parentFolder = $this->getParentFolder();
        if ($parentFolder !== null) {
            $folders[] = $parentFolder;
        }

        $folders[] = $this->getFolder();

        return $folders;
    }

    /**
     * Validate inheritance chain and detect circular references early.
     * 
     * @throws TypeException
     */
    protected function assertValidInheritanceChain(): void
    {
        $seen = [];
        $currentSlug = $this->themeSlug;

        while ($currentSlug !== '') {
            if (array_key_exists($currentSlug, $seen)) {
                throw new TypeException(sprintf(
                    'Circular theme inheritance detected involving theme "%s".',
                    $currentSlug
                ));
            }

            $seen[$currentSlug] = true;

            $parentSlug = $this->resolveParentThemeSlugFor($currentSlug);

            if ($parentSlug === null) {
                return;
            }

            $currentSlug = $parentSlug;
        }
    }

    /**
     * Resolve a parent slug for any theme slug.
     *
     * Used internally for inheritance-chain validation.
     */
    protected function resolveParentThemeSlugFor(string $themeSlug): ?string
    {
        $baseFolder = $this->config['folder'] ?? null;

        if (!is_string($baseFolder) || $baseFolder === '') {
            throw new RuntimeException('Theme configuration is missing a valid "folder" path.');
        }

        $themeSlug = basename($themeSlug);
        $themeFolder = rtrim($baseFolder, '/\\') . Devflow::$PHP::DS . $themeSlug;
        $themeConfigFile = $themeFolder . Devflow::$PHP::DS . 'theme.php';

        if (file_exists($themeConfigFile)) {
            $data = require $themeConfigFile;

            if (!is_array($data)) {
                throw new RuntimeException(sprintf(
                    'Theme metadata file "%s" must return an array.',
                    $themeConfigFile
                ));
            }

            $parent = $data['parent'] ?? null;

            if (is_string($parent) && $parent !== '') {
                return basename($parent);
            }
        }

        $parents = $this->config['parent'] ?? null;

        if (!is_array($parents)) {
            return null;
        }

        $parent = $parents[$themeSlug] ?? null;

        return is_string($parent) && $parent !== ''
            ? basename($parent)
            : null;
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
