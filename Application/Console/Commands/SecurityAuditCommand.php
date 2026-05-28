<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Shared\Services\Security\ComposerAuditService;
use Codefy\Framework\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputOption;

class SecurityAuditCommand extends ConsoleCommand
{
    protected string $name = 'security:audit';

    protected string $description = 'Run composer audit and store security advisory results.';

    protected function configure(): void
    {
        $this
            ->addOption(
                'with-dev',
                null,
                InputOption::VALUE_NONE,
                'Include require-dev packages in the audit.'
            )
            ->addOption(
                'timeout',
                null,
                InputOption::VALUE_REQUIRED,
                'Composer audit timeout in seconds.',
                20
            );
    }

    /**
     * @return int
     * @throws \JsonException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Data\TypeException
     * @throws \ReflectionException
     */
    public function handle(): int
    {
        $service = new ComposerAuditService(
            basePath: $this->codefy->basePath(),
            timeoutSeconds: (int) $this->input->getOption('timeout'),
            auditDevDependencies: (bool) $this->input->getOption('with-dev')
        );

        $result = $service->run();

        if ($result->failed()) {
            $this->output->writeln('<error>Composer security audit failed.</error>');
            $this->output->writeln('<comment>' . $result->message . '</comment>');

            return self::FAILURE;
        }

        if ($result->hasAdvisories()) {
            $this->output->writeln(sprintf(
                '<error>%d security advisories found.</error>',
                $result->advisoryCount
            ));

            foreach ($result->packages as $package => $items) {
                $this->output->writeln('<comment>' . $package . '</comment>');

                foreach ($items as $item) {
                    $this->output->writeln(' - ' . ($item['title'] ?: 'Security advisory'));
                }
            }

            return self::FAILURE;
        }

        $this->output->writeln('<info>No known Composer security advisories found.</info>');

        return self::SUCCESS;

    }
}
