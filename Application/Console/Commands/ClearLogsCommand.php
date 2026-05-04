<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Codefy\Framework\Application;
use Codefy\Framework\Console\ConsoleCommand;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use DirectoryIterator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use Qubus\Http\Session\SessionException;
use ReflectionException;

use function Codefy\Framework\Helpers\storage_path;
use function unlink;

class ClearLogsCommand extends ConsoleCommand
{
    protected string $name = 'logs:clear';

    protected string $description = 'Delete old log files.';

    public function __construct(protected Application $codefy)
    {
        parent::__construct(codefy: $codefy);
    }

    /**
     * @return int
     * @throws ContainerExceptionInterface
     * @throws EnvironmentIsBrokenException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     * @throws UnresolvableQueryHandlerException
     */
    public function handle(): int
    {
        $this->terminalRaw('<comment>Old logs are being deleted...</comment>');

        $directory = storage_path('logs/');

        $cutoff = time() - ($this->codefy->configContainer->integer(key: 'cms.remove_logs_after') * 86400);
        $iterator = new DirectoryIterator($directory);

        foreach ($iterator as $file) {
            if ($file->isDot() || ! $file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();
            $path     = $file->getPathname();

            // Suffix filter
            if (!str_ends_with($filename, '.log')) {
                continue;
            }

            // Extra safety: only delete writable files
            if (! is_writable($path)) {
                continue;
            }

            if ($file->getMTime() < $cutoff) {
                @unlink($path);
            }
        }

        $this->terminalRaw('<comment>Deleted logs complete.</comment>');

        // return value is important when using CI
        // to fail the build when the command fails
        // 0 = success, other values = fail
        return self::SUCCESS;
    }
}
