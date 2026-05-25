<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Codefy\Framework\Application;
use Codefy\Framework\Console\ConsoleCommand;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use PDO;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use ReflectionException;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

use function App\Shared\Helpers\get_all_sites;

final class SiteMigrationCommand extends ConsoleCommand
{
    protected string $name = 'site:migrate';

    protected string $description = 'Run pending site-level migrations across one or all subsites.';

    public function __construct(Application $codefy, private readonly Database $dfdb)
    {
        parent::__construct($codefy);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'site',
                shortcut: null,
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Run migrations for one site ID only.'
            )
            ->addOption(
                name: 'dry-run',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Show pending migrations without running them.'
            );
    }


    /**
     * @return int
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws UnresolvableQueryHandlerException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function handle(): int
    {
        $siteId = $this->input->getOption('site');
        $dryRun = (bool) $this->input->getOption('dry-run');

        $sites = $siteId
            ? $this->findSite($siteId)
            : get_all_sites();

        if ($sites === []) {
            $this->output->writeln('<comment>No sites found.</comment>');
            return self::SUCCESS;
        }

        $migrations = $this->discoverMigrations();

        foreach ($sites as $site) {
            $prefix = $site['key'];

            $this->output->writeln('');
            $this->output->writeln("<info>Site:</info> {$site['id']} <comment>({$prefix})</comment>");

            foreach ($migrations as $migrationName => $migrationClass) {
                if ($this->hasRun($site['id'], $migrationName)) {
                    continue;
                }

                $this->output->writeln("  - Pending: {$migrationName}");

                if ($dryRun) {
                    continue;
                }

                try {
                    $migration = new $migrationClass();
                    $migration->up($this->dfdb, $prefix);

                    $this->recordMigration($site['id'], $migrationName);

                    $this->output->writeln("    <info>Done</info>");
                } catch (Throwable $e) {
                    $this->output->writeln("    <error>Failed: {$e->getMessage()}</error>");
                    return self::FAILURE;
                }
            }
        }

        return self::SUCCESS;
    }

    private function findSite(string $siteId): array
    {
        $stmt = $this->dfdb->getConnection()->pdo
            ->prepare("SELECT site_id AS `id`, site_key AS `key` FROM `{$this->dfdb->basePrefix}site` WHERE `site_id` = ?");

        $stmt->execute([$siteId]);

        $site = $stmt->fetch(PDO::FETCH_ASSOC);

        return $site ? [$site] : [];
    }

    private function discoverMigrations(): array
    {
        $migrations = [];

        foreach (glob($this->codefy->databasePath() . '/migrations/updates/*.php') ?: [] as $file) {
            require_once $file;

            $name = basename($file, '.php');
            $class = preg_replace('/^\d+_/', '', $name);

            if (class_exists($class)) {
                $migrations[$name] = $class;
            }
        }

        ksort($migrations);

        return $migrations;
    }

    private function hasRun(string $siteId, string $migration): bool
    {
        $table = $this->dfdb->basePrefix . 'site_migration';

        $stmt = $this->dfdb->getConnection()->pdo
                ->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `site_id` = ? AND `migration` = ?");

        $stmt->execute([$siteId, $migration]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function recordMigration(string $siteId, string $migration): void
    {
        $table = $this->dfdb->basePrefix . 'site_migration';

        $stmt = $this->dfdb->getConnection()->pdo
            ->prepare("INSERT INTO `{$table}` (`site_id`, `migration`, `recorded_on`) VALUES (?, ?, ?)");

        $stmt->execute([$siteId, $migration, date('Y-m-d H:i:s')]);
    }
}
