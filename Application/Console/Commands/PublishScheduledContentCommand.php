<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Infrastructure\Services\Content\Workflow\ContentWorkflowService;
use Codefy\Framework\Application;
use Codefy\Framework\Console\ConsoleCommand;
use Qubus\Expressive\Database;

use function App\Shared\Helpers\get_all_sites;
use function App\Shared\Helpers\restore_current_site;
use function App\Shared\Helpers\switch_to_site;

final class PublishScheduledContentCommand extends ConsoleCommand
{
    protected string $name = 'content:publish-scheduled';

    public function __construct(
            protected Application $codefy,
            private readonly Database $dfdb,
            private readonly ContentWorkflowService $workflow
    ) {
        parent::__construct($codefy);
    }

    protected function configure(): void
    {
        $this->setDescription('Publishes scheduled content whose publish date has passed.');
    }

    /**
     * @return int
     * @throws \Codefy\QueryBus\UnresolvableQueryHandlerException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     */
    protected function handle(): int
    {
        foreach (get_all_sites() as $site) {
            try {
                switch_to_site($site['key']);

                $this->output->writeln(sprintf(
                    '<info>Checking scheduled content for site: %s</info>',
                    $site['domain'] ?? $site['key']
                ));

                $this->publishScheduledForCurrentSite($site['key']);
            } catch (\Throwable $e) {
                $this->output->writeln(sprintf(
                    '<error>Scheduled publishing failed for site %s: %s</error>',
                    $site['domain'] ?? $site['key'] ?? 'unknown',
                    $e->getMessage()
                ));
            } finally {
                restore_current_site();
            }
        }

        return self::SUCCESS;
    }

    private function publishScheduledForCurrentSite(string $tablePrefix): void
    {
        $rows = $this->dfdb
            ->table($tablePrefix . 'content')
            ->where('content_status', 'scheduled')
            ->where('content_published_gmt <= ?', gmdate('Y-m-d H:i:s'))
            ->limit(50)
            ->find(callback: static fn(array $rows): array => $rows);

        foreach ($rows as $row) {
            try {
                $this->workflow->publishScheduled(
                    contentId: (string) $row['content_id']
                );

                $this->output->writeln(sprintf(
                    '<info>Published scheduled content: %s</info>',
                    $row['content_id']
                ));
            } catch (\Throwable $e) {
                $this->output->writeln(sprintf(
                    '<error>Failed publishing %s: %s</error>',
                    $row['content_id'],
                    $e->getMessage()
                ));
            }
        }
    }
}
