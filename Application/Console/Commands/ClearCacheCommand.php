<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use App\Shared\Services\ItemPoolObjectCacheFactory;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Codefy\Framework\Application;
use Codefy\Framework\Console\ConsoleCommand;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

use function App\Shared\Helpers\get_all_sites;
use function App\Shared\Helpers\restore_current_site;
use function App\Shared\Helpers\switch_to_site;
use function preg_filter;

class ClearCacheCommand extends ConsoleCommand
{
    protected string $name = 'cache:clear';

    protected string $description = 'Clears cache except for user cookies.';

    public function __construct(protected Application $codefy, protected Database $dfdb)
    {
        parent::__construct(codefy: $codefy);
    }

    /**
     * @return int
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     * @throws Exception
     */
    public function handle(): int
    {
        $globalNamespaces = ['auto_updater','useremail','userlogin','users','usertoken','sites','sitekey','siteslug'];
        $siteNamespaces = preg_filter(
            pattern: '/^/',
            replacement: $this->dfdb->prefix,
            subject: [
                'content','contentauthor','contentslug','contenttype','content_attribute','products','productauthor',
                'productslug','productsku','product_attribute','options'
            ]
        );

        foreach ($globalNamespaces as $namespace) {
            SimpleCacheObjectCacheFactory::make(namespace: $namespace)->clear();
        }

        foreach (get_all_sites() as $site) {
            switch_to_site($site['key']);
            if (true === SimpleCacheObjectCacheFactory::make(namespace: $this->dfdb->prefix . 'user_attribute')->clear()) {
                ItemPoolObjectCacheFactory::make()->clear();

                foreach ($siteNamespaces as $namespace) {
                    SimpleCacheObjectCacheFactory::make(namespace: $namespace)->clear();
                }
            }
            restore_current_site();
        }

        $this->terminalRaw(string: '<comment>Cache cleared.</comment>');

        // return value is important when using CI
        // to fail the build when the command fails
        // 0 = success, other values = fail
        return self::SUCCESS;
    }
}
