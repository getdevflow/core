<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Qubus\Expressive\Database;
use App\Shared\Services\ItemPoolObjectCacheFactory;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Codefy\Framework\Application;
use Codefy\Framework\Console\ConsoleCommand;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

class ClearCacheCommand extends ConsoleCommand
{
    protected string $name = 'cache:clear';

    protected string $description = 'Clears cache except for user cookies.';

    public function __construct(protected Application $codefy, protected Database $dfdb)
    {
        parent::__construct(codefy: $codefy);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    public function handle(): int
    {
        $namespaces = [
                $this->dfdb->prefix . 'content',
                $this->dfdb->prefix . 'contentslug',
                $this->dfdb->prefix . 'contenttype',
                $this->dfdb->prefix . 'contentmeta',
                $this->dfdb->prefix . 'products',
                $this->dfdb->prefix . 'productslug',
                $this->dfdb->prefix . 'productsku',
                $this->dfdb->prefix . 'productmeta',
                'useremail',
                'userlogin',
                'users',
                'usertoken',
                'sites',
                'sitekey',
                'siteslug',
                $this->dfdb->prefix . 'options',
                $this->dfdb->prefix . 'database'
        ];

        if (true === SimpleCacheObjectCacheFactory::make(namespace: $this->dfdb->prefix . 'usermeta')->clear()) {
            ItemPoolObjectCacheFactory::make()->clear();

            foreach ($namespaces as $namespace) {
                SimpleCacheObjectCacheFactory::make(namespace: $namespace)->clear();
            }
        }

        $this->terminalRaw(string: '<comment>Cache cleared.</comment>');

        // return value is important when using CI
        // to fail the build when the command fails
        // 0 = success, other values = fail
        return self::SUCCESS;
    }
}
