<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Shared\Services\Registry;
use Codefy\Framework\Support\CodefyServiceProvider;
use PDOException;
use Psr\Http\Message\RequestInterface;
use Qubus\Exception\Exception;
use ReflectionException;

use function Codefy\Framework\Helpers\logger;
use function date_default_timezone_set;
use function file_exists;
use function Qubus\Security\Helpers\esc_html;
use function sprintf;

final class SiteServiceProvider extends CodefyServiceProvider
{
    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function register(): void
    {
        /**
         * Set the timezone for the application.
         */
        date_default_timezone_set($this->codefy->configContainer->string(key: 'app.timezone', default: 'UTC'));

        $this->registerSiteKey();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function registerSiteKey(): void
    {
        if(!file_exists($this->codefy->storagePath() . '/install.lock')) {
            return;
        }

        /** @var RequestInterface $request */
        $request = $this->codefy->make(name: RequestInterface::class);

        $pdo = $this->codefy->getDbConnection()->pdo;

        $default = $this->codefy->configContainer->string(key: 'database.default');
        $prefix = $this->codefy->configContainer->string(key: "database.connections.{$default}.prefix");

        try {
            $sql = "SELECT site_key FROM {$prefix}site WHERE site_domain = :domain OR site_mapping = :mapping LIMIT 1";
            $sth = $pdo->prepare($sql);
            $sth->execute(['domain' => $request->getHost(), 'mapping' => $request->getHost()]);

            $currentSiteKey = $sth->fetchColumn();

            if (false === $currentSiteKey) {
                $siteKey = $prefix;
            } else {
                $siteKey = esc_html($currentSiteKey);
            }
            /**
             * Set site key.
             */
            Registry::getInstance()->set('siteKey', $siteKey);
        } catch (PDOException $ex) {
           logger(
                level: 'error',
                message: sprintf('CURRENT_SITEKEY[%s]: %s', $ex->getCode(), $ex->getMessage())
            );
        }
    }
}
