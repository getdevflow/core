<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\User;

use App\Application\Devflow;
use App\Domain\User\Model\User;
use App\Domain\User\UserError;
use App\Domain\User\Validator\StoreUserValidator;
use App\Domain\User\Validator\UpdateUserProfileValidator;
use App\Domain\User\Validator\UpdateUserValidator;
use App\Infrastructure\Services\Queue\NewAccountNotification;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Error\Error;
use Qubus\Exception\Data\TypeException;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\cms_insert_user;
use function App\Shared\Helpers\cms_update_user;
use function App\Shared\Helpers\get_current_site_key;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\get_user_by;
use function App\Shared\Helpers\get_users_by_site_key;
use function App\Shared\Helpers\site_url;
use function App\Shared\Helpers\sort_list;
use function array_merge;
use function Codefy\Framework\Helpers\abort;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\queue;
use function Codefy\Framework\Helpers\trans_html;
use function get_class;
use function Qubus\Error\Helpers\is_error;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Support\Helpers\is_false__;
use function sprintf;

final readonly class UserService
{
    /**
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Qubus\Exception\Exception
     */
    public function find(): array
    {
        try {
            $results = get_users_by_site_key(get_current_site_key());

            return sort_list($results, 'user_registered', 'DESC', true);
        } catch (UnresolvableQueryHandlerException | ReflectionException $e) {
            logger(level: 'error', message: $e->getMessage());

            abort(
                code: 404,
                uri: admin_url(),
                message: trans_html('Query exception occurred and was logged.'),
            );
        }
    }

    /**
     * @param string $id
     * @return User
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     */
    public function findById(string $id): User
    {
        $user = get_user_by('id', $id);
        if (empty($user->id) || is_false__($user)) {
            abort(
                code: 404,
                uri: admin_url('user/'),
                message: trans_html('The user does not exist.'),
            );
        }

        return $user;
    }

    /**
     * @param StoreUserValidator $data
     * @return Error|string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function createUser(StoreUserValidator $data): Error|string
    {
        $validated = $data->validated();

        try {
            /** @var User $user */
            $user = get_user_by(field: 'email', value: $validated['email']);

            if (is_false__($user)) {
                $update = false;
                $userLogin = $validated['login'];
                $extra = ['pass' => $validated['pass']];
            } else {
                $update = true;
                $userLogin = $user->login;
                $extra = ['pass' => $validated['pass'], 'login' => $userLogin];
            }

            if (empty($userLogin)) {
                return new UserError($userLogin->getMessage());
            }

            $arrayMerge = array_merge($extra, $validated);
            if ($update) {
                $userId = cms_update_user($arrayMerge);
            } else {
                $userId = cms_insert_user($arrayMerge);
            }

            if (is_error($userId)) {
                return new UserError($userId->getMessage());
            }

            if ((int) $validated['sendemail'] === 1) {
                queue(
                    new NewAccountNotification([
                        'login' => (string) $validated['login'],
                        'email' => (string) $validated['email'],
                        'pass' => (string) $validated['pass'],
                        'url' => sprintf(
                            site_url('admin/%s/'),
                            Devflow::$PHP->configContainer->string(key: 'auth.login_route')
                        ),
                        'sitename' => (string) get_option(key: 'sitename'),
                    ])
                )
                ->createItem();
            }

            Devflow::$PHP->flash->success(
                message: Devflow::$PHP->flash->notice(num: 201),
            );

            return $userId;
        } catch (
            CommandPropertyNotFoundException |
            ContainerExceptionInterface |
            TypeException |
            Exception $e
        ) {
            logger(
                level: 'error',
                message: $e->getMessage(),
                context: ['code' => $e->getCode(), 'exception' => get_class($e)]
            );

            return new UserError(trans_html('Insertion exception occurred and was logged.'));
        }
    }

    /**
     * @param UpdateUserValidator $data
     * @return Error|string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws \ReflectionException
     * @throws Exception
     */
    public function updateUser(UpdateUserValidator $data): Error|string
    {
        /**
         * Action triggered before user record is updated.
         *
         * @param string $id User id.
         */
        __observer()->action->doAction('pre_update_user', $data->validated()['id']);

        try {
            $userId = cms_update_user($data->validated());

            if (is_error($userId)) {
                return new UserError($userId->getMessage());
            }

            Devflow::$PHP->flash->success(
                message: Devflow::$PHP->flash->notice(num: 200),
            );

            return $userId;
        } catch (
            CommandPropertyNotFoundException |
            ContainerExceptionInterface |
            TypeException |
            Exception $e
        ) {
            logger(level: 'error', message: $e->getMessage());

            return new UserError(trans_html('User change exception occurred and was logged.'));
        }
    }

    /**
     * @param UpdateUserProfileValidator $data
     * @return Error|string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function updateProfile(UpdateUserProfileValidator $data): Error|string
    {
        try {
            $userId = cms_update_user($data->validated());

            if (is_error($userId)) {
                return new UserError($userId->getMessage());
            }

            Devflow::$PHP->flash->success(message: Devflow::$PHP->flash->notice(num: 200));

            return $userId;
        } catch (
            CommandPropertyNotFoundException |
            NotFoundExceptionInterface |
            ContainerExceptionInterface |
            TypeException |
            \Qubus\Exception\Exception |
            ReflectionException $e
        ) {
            logger(level: 'error', message: $e->getMessage());

            return new UserError(trans_html('An update exception occurred and was logged.'));
        }
    }
}
