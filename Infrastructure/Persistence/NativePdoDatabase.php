<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Shared\Services\Registry;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Config\ConfigContainer;
use Qubus\Error\Error;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Connection;
use Qubus\Expressive\QueryBuilder;
use ReflectionException;

use function App\Shared\Helpers\is_multisite;
use function array_merge;
use function Codefy\Framework\Helpers\trans_html;
use function preg_match;

final class NativePdoDatabase extends QueryBuilder
{
    private const string SCOPE_ALL = 'all';
    private const string SCOPE_SITE = 'site';
    private const string SCOPE_GLOBAL = 'global';
    private const string SCOPE_MS_GLOBAL = 'ms_global';

    protected ?string $connectionType = null;
    public string $sitePrefix = '';
    public string $basePrefix = '';
    public string $prefix = '';
    public ?string $siteKey = null;

    /**
     * @var list<string>
     */
    public array $siteTables = [
        'option',
        'plugin',
        'content',
        'content_comment',
        'content_notification',
        'content_type',
        'content_workflow_activity',
        'product',
        'elfinder_file',
        'elfinder_trash',
        'event_store',
        'pages',
        'page_translations',
        'settings',
        'uploads',
    ];
    /**
     * @var list<string>
     */
    public array $globalTables = [
        'global_option',
        'user',
        'site_user',
        'site_migration',
    ];
    /**
     * @var list<string>
     */
    public array $msGlobalTables = [
        'site'
    ];

    public string $option = '';
    public string $global_option = '';
    public string $plugin = '';
    public string $content = '';
    public string $content_comment = '';
    public string $content_notification = '';
    public string $content_type = '';
    public string $content_workflow_activity = '';
    public string $site = '';
    public string $user = '';
    public string $site_user = '';
    public string $site_migration = '';
    public string $product = '';
    public string $elfinder_file = '';
    public string $elfinder_trash = '';
    public string $event_store = '';
    public string $pages = '';
    public string $page_translations = '';
    public string $settings = '';
    public string $uploads = '';

    /**
     * @param Connection $connection
     * @param ConfigContainer $configContainer
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function __construct(
        protected Connection $connection,
        protected ConfigContainer $configContainer,
    ) {
        $this->connectionType = $this->configContainer->string(key: 'database.default');
        $this->basePrefix = $this->configContainer->string(
            key: "database.connections.{$this->connectionType}.prefix"
        );

        $registry = Registry::getInstance();

        $this->siteKey = $registry->has(id: 'siteKey') ?
        $registry->get('siteKey') :
        null;

        $this->sitePrefix = $this->resolveSitePrefix($this->siteKey);
        $this->prefix = $this->sitePrefix;

        $this->refreshTableNames();

        $registry->set('tblPrefix', $this->prefix);

        parent::__construct($this->connection);
    }

    /**
     * Sets the table prefix for Devflow tables.
     *
     * @param ?string $prefix Alphanumeric name for the new prefix.
     * @param bool $setTableNames Optional. Whether the table names, e.g. Database::$content, should be updated or not.
     * @return string|Error Old prefix or Error on error
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public function setPrefix(?string $prefix = null, bool $setTableNames = true): Error|string
    {
        $this->assertValidPrefix($prefix);

        $oldPrefix = $this->basePrefix;

        $this->basePrefix = $prefix;
        $this->sitePrefix = $this->resolveSitePrefix($this->siteKey);
        $this->prefix = $this->sitePrefix;

        if ($setTableNames) {
            $this->refreshTableNames();
        }

        Registry::getInstance()->set('tblPrefix', $this->prefix);

        return $oldPrefix;
    }

    /**
     * Sets site key.
     *
     * @param string $siteKey Site id to use.
     * @return string Previous site id.
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public function setSiteKey(string $siteKey): string
    {
        $oldSiteKey = $this->siteKey;

        $this->siteKey = $siteKey;
        $this->sitePrefix = $this->resolveSitePrefix($siteKey);
        $this->prefix = $this->sitePrefix;

        $this->refreshTableNames();

        Registry::getInstance()->set('siteKey', $siteKey);
        Registry::getInstance()->set('tblPrefix', $this->prefix);

        return $oldSiteKey;
    }

    /**
     * Gets site prefix.
     *
     * @param string|null $siteKey Optional.
     * @return string Site prefix.
     * @throws TypeException
     */
    public function getSitePrefix(?string $siteKey = null): string
    {
        return $this->resolveSitePrefix($siteKey ?? $this->siteKey);
    }

    /**
     * Returns an array of Devflow tables.
     *
     * The scope argument can take one of the following:
     *
     * 'all' - return all the 'global' and 'site' tables.
     * 'site' - returns the site level tables.
     * 'global' - returns global tables.
     * 'ms_global' - returns multisite global tables.
     *
     * @param string $scope (Optional) Can be all, site, global or ms_global. Default: all.
     * @param bool $prefix (Optional) Whether to include table prefixes. Default: true.
     * @param string|null $siteKey (Optional) The siteKey to prefix. Default: Database::siteKey
     * @return string[] Table names.
     * @throws TypeException
     * @throws Exception
     */
    public function tables(string $scope = self::SCOPE_ALL, bool $prefix = true, ?string $siteKey = null): array
    {
        $tables = match ($scope) {
            self::SCOPE_ALL => $this->allTables(),
            self::SCOPE_SITE => $this->siteTables,
            self::SCOPE_GLOBAL => $this->globalScopedTables(),
            self::SCOPE_MS_GLOBAL => $this->msGlobalTables,
            default => throw new TypeException(sprintf(trans_html('Invalid table scope [%s].'), $scope)),
        };

        if (!$prefix) {
            return array_combine($tables, $tables) ?: [];
        }

        $sitePrefix = $this->resolveSitePrefix($siteKey ?? $this->siteKey);
        $globalTables = array_merge($this->globalTables, $this->msGlobalTables);

        $prefixed = [];

        foreach ($tables as $table) {
            $prefixed[$table] = in_array($table, $globalTables, true)
                ? $this->basePrefix . $table
                : $sitePrefix . $table;
        }

        return $prefixed;
    }

    /**
     * Useful when you want `$db->forSite('site_2_')->option`.
     *
     * @param string|null $siteKey
     * @return NativePdoDatabase
     * @throws Exception
     * @throws TypeException
     */
    public function forSite(?string $siteKey = null): self
    {
        $clone = clone $this;
        $clone->siteKey = $siteKey;
        $clone->sitePrefix = $clone->resolveSitePrefix($siteKey);
        $clone->prefix = $clone->sitePrefix;
        $clone->refreshTableNames();

        return $clone;
    }

    /**
     * @throws Exception
     * @throws TypeException
     */
    private function refreshTableNames(): void
    {
        foreach ($this->tables(self::SCOPE_ALL) as $property => $tableName) {
            if (property_exists($this, $property)) {
                $this->{$property} = $tableName;
            }
        }
    }

    /**
     * @throws TypeException
     */
    private function resolveSitePrefix(?string $siteKey = null): string
    {
        if (!is_multisite()) {
            return $this->basePrefix;
        }

        return $siteKey ?: $this->basePrefix;
    }

    /**
     * @return list<string>
     * @throws TypeException
     */
    private function allTables(): array
    {
        $tables = array_merge($this->globalTables, $this->siteTables);

        if (is_multisite()) {
            $tables = array_merge($tables, $this->msGlobalTables);
        }

        return $tables;
    }

    /**
     * @return list<string>
     * @throws TypeException
     */
    private function globalScopedTables(): array
    {
        if (!is_multisite()) {
            return $this->globalTables;
        }

        return array_merge($this->globalTables, $this->msGlobalTables);
    }

    /**
     * @param string $prefix
     * @throws Exception
     * @throws TypeException
     */
    private function assertValidPrefix(string $prefix): void
    {
        if ($prefix === '') {
            throw new TypeException(trans_html('Database prefix cannot be empty.'));
        }

        if (preg_match('/[^a-z0-9_]/i', $prefix) === 1) {
            throw new TypeException(trans_html('Invalid database prefix.'));
        }
    }
}
