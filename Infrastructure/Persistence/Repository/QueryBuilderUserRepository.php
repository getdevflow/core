<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\User\Model\User;
use App\Domain\User\Repository\UserCommandRepository;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Services\AttributesFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use Exception as NativeException;
use Qubus\Expressive\QueryBuilderException;

use ReflectionException;

use function App\Shared\Helpers\get_current_site_id;

class QueryBuilderUserRepository implements UserCommandRepository
{
    public function __construct(protected Database $dfdb)
    {
    }

    /**
     * @param User $user
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws NativeException
     */
    public function save(User $user): void
    {
        try {
            $this->dfdb->transactional(callback: function () use ($user) {
                $this->dfdb
                    ->table(tableName: $this->dfdb->basePrefix . 'user')
                    ->set([
                        'user_id' => $user->id,
                        'user_login' => $user->login,
                        'user_fname' => $user->fname,
                        'user_mname' => $user->mname,
                        'user_lname' => $user->lname,
                        'user_email' => $user->email,
                        'user_token' => $user->token,
                        'user_pass' => $user->pass,
                        'user_url' => $user->url,
                        'user_bio' => $user->bio,
                        'user_timezone' => $user->timezone,
                        'user_date_format' => $user->dateFormat,
                        'user_time_format' => $user->timeFormat,
                        'user_locale' => $user->locale,
                        'user_activation_key' => empty($user->activationKey) ? null : $user->activationKey,
                        'user_registered' => $user->registered,
                    ])
                    ->save();
            });

            AttributesFactory::user()->createIfMissing(get_current_site_id(), $user->id);
        } catch (QueryBuilderException $e) {
            throw new NativeException(message: $e->getMessage());
        }
    }

    /**
     * @param User $user
     * @return void
     * @throws NativeException
     */
    public function update(User $user): void
    {
        try {
            $this->dfdb->transactional(callback: function () use ($user) {
                $this->dfdb
                    ->table(tableName: $this->dfdb->basePrefix . 'user')
                    ->set([
                        'user_login' => $user->login,
                        'user_fname' => $user->fname,
                        'user_mname' => $user->mname,
                        'user_lname' => $user->lname,
                        'user_email' => $user->email,
                        'user_token' => $user->token,
                        'user_pass' => $user->pass,
                        'user_url' => $user->url,
                        'user_bio' => $user->bio,
                        'user_timezone' => $user->timezone,
                        'user_date_format' => $user->dateFormat,
                        'user_time_format' => $user->timeFormat,
                        'user_locale' => $user->locale,
                        'user_activation_key' => empty($user->activationKey) ? null : $user->activationKey,
                        'user_modified' => $user->modified,
                    ])
                    ->where('user_id = ?', $user->id)
                    ->update();
            });
        } catch (QueryBuilderException $e) {
            throw new NativeException(message: $e->getMessage());
        }
    }

    /**
     * @param UserId $id
     * @return void
     * @throws NativeException
     */
    public function destroy(UserId $id): void
    {
        try {
            $this->dfdb->transactional(callback: function () use ($id) {
                $this->dfdb
                    ->table(tableName: $this->dfdb->basePrefix . 'user')
                    ->where('user_id = ?', $id->toNative())
                    ->delete();
            });
        } catch (QueryBuilderException $e) {
            throw new NativeException(message: $e->getMessage());
        }
    }

    /**
     * @param User $user
     * @return void
     * @throws NativeException
     */
    public function updatePassword(User $user): void
    {
        try {
            $this->dfdb->transactional(callback: function () use ($user) {
                $this->dfdb
                    ->table(tableName: $this->dfdb->basePrefix . 'user')
                    ->set([
                        'user_pass' => $user->pass,
                        'user_token' => $user->token,
                    ])
                    ->where('user_id = ?', $user->id)
                    ->update();
            });
        } catch (QueryBuilderException $e) {
            throw new NativeException(message: $e->getMessage());
        }
    }
}
