<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Shared\Services\Registry;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Config\ConfigContainer;
use Qubus\Error\Error;
use Qubus\Exception\Data\TypeException;
use Qubus\Expressive\Connection;
use Qubus\Expressive\QueryBuilder;
use ReflectionException;

use function App\Shared\Helpers\is_multisite;
use function array_merge;
use function preg_match;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_null__;

final class NativePdoDatabase extends QueryBuilder
{
    protected ?string $connectionType = null;

    public string $sitePrefix = '';

    public string $basePrefix = '';

    public string $prefix = '';

    public ?string $siteKey = null;

    public array $siteTables = [
        'option',
        'plugin',
        'content',
        'contentmeta',
        'contenttype',
        'product',
        'productmeta',
    ];

    public array $globalTables = [
        'user',
        'site_user'
    ];

    public array $msGlobalTables = [
        'site'
    ];

    public string $option = '';

    public string $plugin = '';

    public string $content = '';

    public string $contenttype = '';

    public string $site = '';

    public string $user = '';

    public string $product = '';

    /**
     * @param Connection $connection
     * @param ConfigContainer $configContainer
     * @throws ContainerExceptionInterface
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

        $this->siteKey = Registry::getInstance()->has(id: 'siteKey') ?
        Registry::getInstance()->get('siteKey') :
        null;

        $this->sitePrefix = $this->siteKey ?? $this->basePrefix;
        $this->prefix = $this->sitePrefix;

        Registry::getInstance()->set('tblPrefix', $this->prefix);

        parent::__construct($this->connection);
    }

    public function qb(): ?QueryBuilder
    {
        return $this;
    }

    /**
     * Sets the table prefix for Devflow tables.
     *
     * @param ?string $prefix Alphanumeric name for the new prefix.
     * @param bool $setTableNames Optional. Whether the table names, e.g. Database::$post, should be updated or not.
     * @return string|Error Old prefix or Error on error
     * @throws TypeException
     */
    public function setPrefix(?string $prefix = null, bool $setTableNames = true): Error|string
    {
        if (is_null__($prefix) || empty($prefix)) {
            return new Error(t__(msgid: 'Database prefix cannot be null.', domain: 'devflow'), 'invalid_db_prefix');
        }

        if (preg_match('|[^a-z0-9_]|i', $prefix)) {
            return new Error(t__(msgid: 'Invalid database prefix.', domain: 'devflow'), 'invalid_db_prefix');
        }

        $oldPrefix = '';

        if ($this->basePrefix  !== '') {
            $oldPrefix = $this->basePrefix;
        }

        $this->basePrefix = $prefix;

        if ($setTableNames) {
            foreach ($this->tables('global') as $table => $prefixedTable) {
                $this->{$table} = $prefixedTable;
            }

            if (empty($this->siteKey)) {
                return $oldPrefix;
            }

            $this->sitePrefix = $this->getSitePrefix();

            foreach ($this->tables('site') as $table => $prefixedTable) {
                $this->{$table} = $prefixedTable;
            }
        }
        return $oldPrefix;
    }

    /**
     * Sets site key.
     *
     * @param string $siteKey Site id to use.
     * @return string Previous site id.
     * @throws TypeException
     */
    public function setSiteKey(string $siteKey): string
    {
        $oldSiteKey = $this->siteKey;
        $this->siteKey = $siteKey;

        $this->sitePrefix = $this->getSitePrefix();

        foreach ($this->tables(scope: 'site') as $table => $prefixedTable) {
            $this->{$table} = $prefixedTable;
        }

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
        if (is_multisite()) {
            if (null === $siteKey) {
                $siteKey = $this->siteKey;
            }

            if (is_multisite() && is_null__($siteKey)) {
                return $this->basePrefix;
            } else {
                return $siteKey;
            }
        } else {
            return $this->basePrefix;
        }
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
     */
    public function tables(string $scope = 'all', bool $prefix = true, ?string $siteKey = null): array
    {
        switch ($scope) {
            case 'all':
                $tables = array_merge($this->globalTables, $this->siteTables);
                if (is_multisite()) {
                    $tables = array_merge($tables, $this->msGlobalTables);
                }
                break;
            case 'site':
                $tables = $this->siteTables;
                break;
            case 'global':
                $tables = $this->globalTables;
                if (is_multisite()) {
                    $tables = array_merge($tables, $this->msGlobalTables);
                }
                break;
            case 'ms_global':
                $tables = $this->msGlobalTables;
                break;
            default:
                return [];
        }

        if ($prefix) {
            if (!is_null__($siteKey)) {
                $siteKey = $this->siteKey;
            }
            $sitePrefix = $this->getSitePrefix($siteKey);
            $basePrefix = $this->basePrefix;
            $globalTables = array_merge($this->globalTables, $this->msGlobalTables);
            foreach ($tables as $k => $table) {
                if (in_array($table, $globalTables, true)) {
                    $tables[$table] = $basePrefix . $table;
                } else {
                    $tables[$table] = $sitePrefix . $table;
                }
                unset($tables[$k]);
            }
        }
        return $tables;
    }
}
