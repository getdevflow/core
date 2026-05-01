<?php

declare(strict_types=1);

namespace App\Application\Console\Commands;

use App\Domain\Site\Command\CreateSiteCommand;
use App\Domain\Site\Model\Site;
use App\Domain\Site\ValueObject\SiteId;
use App\Domain\User\Command\CreateUserCommand;
use App\Domain\User\Model\User;
use App\Domain\User\UserError;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use App\Domain\User\ValueObject\UserToken;
use App\Infrastructure\Services\AttributesFactory;
use Codefy\Framework\Support\Password;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Expressive\Database;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\Framework\Application;
use Codefy\Framework\Console\ConsoleCommand;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Error\Error;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\Support\Inflector;
use Qubus\ValueObjects\Identity\Ulid;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use Qubus\ValueObjects\Web\EmailAddress;
use ReflectionException;

use function App\Shared\Helpers\add_user_to_site;
use function App\Shared\Helpers\generate_random_password;
use function App\Shared\Helpers\generate_random_username;
use function App\Shared\Helpers\generate_unique_key;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\storage_path;
use function file_put_contents;
use function Qubus\Error\Helpers\is_error;
use function sprintf;

use const LOCK_EX;

class InstallCmsCommand extends ConsoleCommand
{
    protected string $name = 'cms:install';

    protected string $description = 'Installs the CMS.';

    public function __construct(protected Application $codefy, protected Database $dfdb)
    {
        parent::__construct(codefy: $codefy);
    }

    /**
     * @return int
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function handle(): int
    {
        if ($this->usersExist()) {
            $this->terminalRaw(string: '<error>Error: system already installed.</error>');
            return self::FAILURE;
        }

        $this->terminalRaw(string: '<info>Installer started....</info>');

        $user = $this->createSuperAdmin();

        $this->terminalRaw(string: '<info>Creating the super administrator...</info>');

        if (is_error($user)) {
            $this->terminalRaw(string: '<error>Error: not installed.</error>');
            return self::FAILURE;
        }

        $this->terminalRaw(string: '<info>Creating the main site...</info>');

        $siteId = $this->createMainSite($user['id']);

        $this->terminalRaw(string: '<info>Adding default site options...</info>');

        $this->createSiteOptions();

        file_put_contents(storage_path(path: 'install.lock'), LOCK_EX);

        $this->terminalRaw(string: '<info>New account details: </info>');
        $this->terminalRaw(string: '<info>=====================</info>');

        $this->terminalRaw(string: sprintf(
            'Username: <comment>%s</comment>',
            $user['login']
        ));

        $this->terminalRaw(string: sprintf(
            'Password: <comment>%s</comment>',
            $user['pass']
        ));

        $this->terminalRaw(string: sprintf(
            'Main Site Id: <comment>%s</comment>',
            $siteId
        ));

        // return value is important when using CI
        // to fail the build when the command fails
        // 0 = success, other values = fail
        return self::SUCCESS;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function createSuperAdmin(): array|Error
    {
        $login = generate_random_username();
        $password = generate_random_password(26);

        $user = new User($this->dfdb);
        $user->id = UserId::generateAsString();
        $user->fname = 'Super';
        $user->mname = '';
        $user->lname = 'Admin';
        $user->email = 'admin@devflow.com';
        $user->login = $login;
        $user->token = UserToken::generateAsString();
        $user->pass = $password;
        $user->url = '';
        $user->bio = '';
        $user->timezone = $this->codefy->configContainer->string(key: 'app.timezone');
        $user->dateFormat = 'd F Y';
        $user->timeFormat = 'h:i A';
        $user->locale = $this->codefy->configContainer->string(key: 'app.locale');
        $user->registered = QubusDateTimeImmutable::now(
            $this->codefy->configContainer->string(key: 'app.timezone')
        )->format('Y-m-d H:i:s');

        try {
            $command = new CreateUserCommand([
                'id' => UserId::fromString($user->id),
                'login'   => new Username($user->login),
                'fname' => new StringLiteral($user->fname),
                'mname' => new StringLiteral(''),
                'lname'  => new StringLiteral($user->lname),
                'email'  => new EmailAddress($user->email),
                'pass'   => new StringLiteral(Password::hash(password: $user->pass)),
                'token' => UserToken::fromString($user->token),
                'url' => new StringLiteral($user->url),
                'bio' => new StringLiteral($user->bio),
                'timezone' => new StringLiteral($user->timezone),
                'dateFormat' => new StringLiteral($user->dateFormat),
                'timeFormat' => new StringLiteral($user->timeFormat),
                'locale' => new StringLiteral($user->locale),
                'activationKey' => new StringLiteral(''),
                'registered' => $user->registered
            ]);
            command($command);

            return [
                'id' => $user->id,
                'login' => $user->login,
                'pass' => $user->pass,
            ];
        } catch (
            UnresolvableCommandHandlerException |
            CommandPropertyNotFoundException |
            ReflectionException |
            TypeException |
            Exception $e
        ) {
            logger(level: 'error', message: $e->getMessage());

            return new UserError(message: 'Super admin user could not be created.');
        }
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    protected function createSiteOptions(): void
    {
        $options = [
            'sitename' => $this->codefy->configContainer->string(key: 'app.name'),
            'site_description' => 'Just another Devflow site.',
            'charset' => 'UTF-8',
            'admin_email' => 'admin@example.com',
            'site_locale' => $this->codefy->configContainer->string(key: 'app.locale'),
            'cookieexpire' => 604800,
            'cookiepath' => '/',
            'site_timezone' => $this->codefy->configContainer->string(key: 'app.timezone'),
            'admin_skin' => 'skin-red',
            'date_format' => 'd F Y',
            'time_format' => 'h:i A',
            'content_per_page' => 6,
            'api_key' => generate_unique_key(length: 32),
        ];

        $this->dfdb->transactional(function () use ($options) {
            foreach ($options as $optionName => $optionValue) {
                $this->dfdb->table(tableName: $this->dfdb->basePrefix . 'option')
                    ->set([
                        'option_id' => Ulid::generateAsString(),
                        'option_key' => $optionName,
                        'option_value' => $optionValue,
                    ])
                    ->save();
            }
        });
    }

    /**
     * @param string $ownerId
     * @return string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    protected function createMainSite(string $ownerId): string
    {
        $dbConnection = $this->codefy->configContainer->string(key: 'database.default');

        $siteId = new SiteId();

        try {
            $site = new Site($this->dfdb);
            $site->key = $this->codefy->configContainer->string(
                key: "database.connections.{$dbConnection}.prefix"
            );
            $site->name = $this->codefy->configContainer->string(key: 'app.name');
            $site->slug = Inflector::slugify($this->codefy->configContainer->string(key: 'app.name'));
            $site->domain = $this->codefy->configContainer->string(key: 'cms.main_site_url');
            $site->mapping = '';
            $site->path = $this->codefy->configContainer->string(key: 'cms.main_site_path');
            $site->owner = $ownerId;
            $site->status = 'public';

            $command = new CreateSiteCommand([
                'id' => $siteId,
                'key' => new StringLiteral($site->key),
                'name' => new StringLiteral($site->name),
                'slug' => new StringLiteral($site->slug),
                'domain' => new StringLiteral($site->domain),
                'mapping' => new StringLiteral($site->mapping),
                'path' => new StringLiteral($site->path),
                'owner' => UserId::fromString($site->owner),
                'status' => new StringLiteral($site->status),
                'registered' => QubusDateTimeImmutable::now(
                    $this->codefy->configContainer->string(key: 'app.timezone')
                ),
            ]);

            command($command);
        } catch (
            UnresolvableCommandHandlerException |
            ReflectionException |
            CommandPropertyNotFoundException |
            TypeException $e
        ) {
            logger(level: 'error', message: $e->getMessage());
        }

        AttributesFactory::user()->createIfMissing($siteId->toNative(), $ownerId);
        add_user_to_site(user: $ownerId, site: $siteId->toNative(), role: 'super');

        return $siteId->toNative();
    }

    protected function usersExist(): bool
    {
        $prefix = $this->dfdb->basePrefix;
        $sql = "SELECT * FROM {$prefix}user";
        $users = $this->dfdb->getResults($sql);

        return count($users) > 0 && file_exists(storage_path('install.lock'));
    }
}
