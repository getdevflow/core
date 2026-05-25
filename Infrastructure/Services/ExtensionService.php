<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Devflow;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Qubus\Exception\Data\TypeException;
use ReflectionException;

use function App\Shared\Helpers\get_all_sites;
use function array_unique;
use function Codefy\Framework\Helpers\config;
use function is_iterable;
use function is_string;

final class ExtensionService
{
    /**
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    public function getActivePluginClassesAcrossSites(): array
    {
        $dfdb = Devflow::db();
        $sites = get_all_sites();
        $classes = [];

        if (! is_iterable($sites)) {
            return [];
        }

        foreach ($sites as $site) {
            $siteKey = $site['key'] ?? null;

            if (! is_string($siteKey) || $siteKey === '') {
                continue;
            }

            $table = $siteKey . 'plugin';

            if (! $this->tableExists($table)) {
                continue;
            }

            $rows = $dfdb->getResults(
                "SELECT plugin_classname FROM {$table}",
            );

            foreach ($rows as $row) {
                $class = $row->plugin_classname ?? null;

                if (is_string($class) && $class !== '') {
                    $classes[] = $class;
                }
            }
        }

        return array_unique($classes);
    }

    /**
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    public function getActiveThemeClassesAcrossSites(): array
    {
        $dfdb = Devflow::db();
        $sites = get_all_sites();
        $themes = [];

        if (! is_iterable($sites)) {
            return [];
        }

        foreach ($sites as $site) {
            $siteKey = $site['key'] ?? null;

            if (! is_string($siteKey) || $siteKey === '') {
                continue;
            }

            $table = $siteKey . 'option';

            if (! $this->tableExists($table)) {
                continue;
            }

            $rows = $dfdb->getResults("SELECT option_value FROM {$table} WHERE option_key = 'site_theme'");

            foreach ($rows as $row) {
                $theme = $row->option_value ?? null;

                if (is_string($theme) && $theme !== '') {
                    $themes[] = $theme;
                }
            }
        }

        return array_unique($themes);
    }

    /**
     * @param string $table
     * @return bool
     * @throws TypeException
     */
    private function tableExists(string $table): bool
    {
        $dfdb = Devflow::db();

        $prepare = config()->string(key: 'database.default') === 'sqlite'
                ? $dfdb->prepare("SELECT name FROM sqlite_master WHERE type IN ('table') AND name = ?", [$table])
                : $dfdb->prepare("SHOW TABLES LIKE ?", ["%$table%"]);

        $result = $dfdb->getVar($prepare);

        return $result !== null;
    }
}
