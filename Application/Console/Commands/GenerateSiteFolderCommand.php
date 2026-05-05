<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Domain\Site\Model\Site;
use Codefy\Framework\Application;
use Codefy\Framework\Console\ConsoleCommand;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;
use Symfony\Component\Console\Input\InputArgument;

use function App\Shared\Helpers\create_site_directories;
use function App\Shared\Helpers\get_site_by;
use function App\Shared\Helpers\site_directory_key;
use function Codefy\Framework\Helpers\public_path;
use function is_dir;
use function Qubus\Support\Helpers\is_false__;

class GenerateSiteFolderCommand extends ConsoleCommand
{
    protected string $name = 'generate:site:folder';

    protected string $description = 'Generates site media folder based on site id.';

    public function __construct(protected Application $codefy)
    {
        parent::__construct(codefy: $codefy);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                name: '--siteId',
                shortcut: '-s',
                mode: InputArgument::OPTIONAL,
                description: 'The site id.'
            )
            ->setDescription(description: 'Generated the media folder for the specified site.')
            ->setHelp(
                    help: <<<EOT
The <info>generate:site:folder</info> creates the missing media folder if it wasn't created during site generation.
<info>php codex generate:site:folder -s 01KQTEBS60E6CVDR9ZVE541WR7</info>
EOT
                );
    }

    /**
     * @return int
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeException
     * @throws Exception
     * @throws ReflectionException
     */
    public function handle(): int
    {
        /** @var Site $site */
        $site = get_site_by('id', $this->getOptions(key: 'siteId'));

        if(is_false__($site)) {
            $this->terminalRaw(string: '<error>The site does not exist.</error>');
            return self::FAILURE;
        }

        $directoryExists = public_path('site/' . (string) site_directory_key($site->key));
        if(is_dir($directoryExists)) {
            $this->terminalRaw(string: '<comment>The directory already exists!</comment>');
            return self::SUCCESS;
        }

        if(create_site_directories($site) === true) {
            $this->terminalRaw(string: '<info>Success!</info>');
            return self::SUCCESS;
        }

        // return value is important when using CI
        // to fail the build when the command fails
        // 0 = success, other values = fail
        return self::FAILURE;
    }
}
