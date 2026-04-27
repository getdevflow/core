<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Devflow;
use App\Domain\Site\Command\UpdateSiteCommand;
use App\Domain\Site\Model\Site;
use App\Domain\Site\ValueObject\SiteId;
use App\Domain\User\ValueObject\UserId;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\Framework\Http\BaseController;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use Qubus\Http\Session\SessionException;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\cms_unique_site_slug;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_current_site_key;
use function App\Shared\Helpers\get_site_by;
use function App\Shared\Helpers\get_user_timezone;
use function App\Shared\Helpers\update_option;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\view;
use function Qubus\Security\Helpers\esc_html__;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_false__;
use function sprintf;

final class AdminOptionsController extends BaseController
{
    /**
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    public function options(ServerRequest $request): ResponseInterface
    {
        if (!current_user_can(perm: 'manage:options')) {
            Devflow::$PHP->flash->{'error'}(
                message: t__(msgid: 'Access denied', domain: 'devflow'),
            );
            return $this->redirect(admin_url());
        }

        $options = $request->getParsedBody();

        if (!empty(array_filter($options))) {
            foreach ($options as $option => $value) {
                $option = trim($option);

                if (!is_array($value)) {
                    $value = trim($value);
                }

                update_option($option, $value);
            }

            Devflow::$PHP->flash->success(
                Devflow::$PHP->flash->notice(200),
            );
        }
        return $this->redirect($request->getServerParams()['HTTP_REFERER']);
    }

    /**
     * @param ServerRequest $request
     * @return string|ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     * @throws TypeException
     */
    public function generalOptions(ServerRequest $request): string|ResponseInterface
    {
        if (!current_user_can(perm: 'manage:options')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied', domain: 'devflow'),
            );
            return $this->redirect(admin_url());
        }

        try {
            $options = [
            'sitename', 'site_description', 'charset', 'admin_email', 'site_locale',
            'cookieexpire', 'cookiepath', 'site_timezone', 'api_key'
            ];

            foreach ($options as $optionName) {
                if (!isset($request->getParsedBody()[$optionName])) {
                    continue;
                }

                $value = $request->getParsedBody()[$optionName];
                update_option($optionName, $value);
            }

            /** @var Site $currentSite */
            $currentSite = get_site_by('key', get_current_site_key());

            if (!is_false__($currentSite) && $currentSite->name !== $request->getParsedBody()['sitename']) {
                $siteSlug = cms_unique_site_slug(
                    $currentSite->slug,
                    $request->getParsedBody()['sitename'],
                    $currentSite->id
                );

                $command = new UpdateSiteCommand([
                    'id' => SiteId::fromString($currentSite->id),
                    'name' => new StringLiteral($request->getParsedBody()['sitename']),
                    'slug' => new StringLiteral($siteSlug),
                    'domain' => new StringLiteral($currentSite->domain),
                    'mapping' => new StringLiteral($currentSite->mapping ?? ''),
                    'path' => new StringLiteral($currentSite->path),
                    'owner' => UserId::fromString($currentSite->owner),
                    'status' => new StringLiteral($currentSite->status),
                    'modified' => QubusDateTimeImmutable::now(get_user_timezone()),
                    ]);

                command($command);
            }

            Devflow::$PHP->flash->success(
                Devflow::$PHP->flash->notice(200),
            );
        } catch (
            CommandPropertyNotFoundException |
            ContainerExceptionInterface |
            InvalidArgumentException |
            UnresolvableCommandHandlerException |
            ReflectionException |
            TypeException $e
        ) {
            logger(
                'error',
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                [
                    'Route' => '/admin/general/'
                ]
            );

            Devflow::$PHP->flash->error(
                message: esc_html__(
                    string: 'General options exception occurred and was logged.',
                    domain: 'devflow'
                )
            );
        }

        return $this->redirect($request->getServerParams()['HTTP_REFERER']);
    }

    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     * @throws TypeException
     * @throws \Exception
     */
    public function generalView(): ResponseInterface
    {
        if (!current_user_can(perm: 'manage:options')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied', domain: 'devflow'),
            );
            return $this->redirect(admin_url());
        }

        return view(
            template: 'framework::backend/general',
            data: ['title' => t__(msgid: 'General Options', domain: 'devflow')]
        );
    }

    /**
     * @param ServerRequest $request
     * @return string|ResponseInterface
     * @throws CommandPropertyNotFoundException
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     * @throws TypeException
     * @throws UnresolvableQueryHandlerException
     */
    public function readingOptions(ServerRequest $request): string|ResponseInterface
    {
        if (!current_user_can(perm: 'manage:options')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied', domain: 'devflow'),
            );
            return $this->redirect(admin_url());
        }

        try {
            $options = [
                'content_per_page', 'charset', 'date_format', 'time_format'
            ];

            foreach ($options as $optionName) {
                if (!isset($request->getParsedBody()[$optionName])) {
                    continue;
                }

                $value = $request->getParsedBody()[$optionName];
                update_option($optionName, $value);
            }

            Devflow::$PHP->flash->{'success'}(
                Devflow::$PHP->flash->notice(num: 200),
            );
        } catch (
            InvalidArgumentException |
            TypeException |
            Exception |
            ReflectionException |
            NotFoundExceptionInterface |
            ContainerExceptionInterface $e
        ) {
            logger(
                'error',
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                [
                    'Route' => '/admin/general/'
                ]
            );

            Devflow::$PHP->flash->error(
                message: esc_html__(
                    string: 'Reading options exception occurred and was logged.',
                    domain: 'devflow'
                )
            );
        }

        return $this->redirect($request->getServerParams()['HTTP_REFERER']);
    }

    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws SessionException
     * @throws TypeException
     * @throws \Exception
     */
    public function readingView(): ResponseInterface
    {
        if (!current_user_can(perm: 'manage:options')) {
            Devflow::$PHP->flash->error(
                message: t__(msgid: 'Access denied', domain: 'devflow'),
            );
            return $this->redirect(admin_url());
        }

        return view(
            template: 'framework::backend/reading',
            data: ['title' => t__(msgid: 'Reading Options', domain: 'devflow')]
        );
    }
}
