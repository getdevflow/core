<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\User\Query\Trait\PopulateUserQueryAware;
use App\Domain\User\Repository\UserQueryRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Expressive\Database;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\get_current_site_id;
use function is_array;
use function Qubus\Support\Helpers\convert_array_to_object;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;

class QueryBusUserRepository implements UserQueryRepository
{
    use PopulateUserQueryAware;

    public function __construct(protected Database $dfdb)
    {
    }

    /**
     * @param string $id
     * @return array|object
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function findById(string $id): array|object
    {
        $sql = "SELECT u.*, m.meta_value AS role FROM {$this->dfdb->basePrefix}user u 
                JOIN {$this->dfdb->prefix}usermeta m 
                ON (m.user_id = u.user_id AND m.meta_key = 'role') 
                WHERE u.user_id = ?";

        $data = $this->dfdb->getRow(
            query: $this->dfdb->prepare(query: $sql, params: [$id]),
            output: Database::ARRAY_A
        );

        if (is_null__(var: $data)) {
            return [];
        }

        $user = $this->populate(data: $data);

        if (is_array(value: $user)) {
            $user = convert_array_to_object(array: $user);
        }

        return $user;
    }

    /**
     * @return array|object|null
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function findUnique(): array|null|object
    {
        $sql = "SELECT DISTINCT u.* FROM {$this->dfdb->basePrefix}user u 
        JOIN {$this->dfdb->prefix}usermeta m 
        ON m.user_id = u.user_id";

        $data = $this->dfdb->getResults(query: $sql, output: Database::ARRAY_A);

        $users = [];

        if (!is_null__($data)) {
            foreach ($data as $user) {
                $users[] = $this->populate($user);
            }
        }

        return $users;
    }

    /**
     * @param string $email
     * @return array|object|null
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function findByEmail(string $email): array|null|object
    {
        $sql = "SELECT u.*, m.meta_value AS role FROM {$this->dfdb->basePrefix}user u 
                JOIN {$this->dfdb->prefix}usermeta m 
                ON (m.user_id = u.user_id AND m.meta_key = 'role') 
                WHERE u.user_email = ?";

        $data = $this->dfdb->getRow(
            query: $this->dfdb->prepare(query: $sql, params: [$email]),
            output: Database::ARRAY_A
        );

        if (is_null__(var: $data)) {
            return [];
        }

        $user = $this->populate(data: $data);

        if (is_array(value: $user)) {
            $user = convert_array_to_object(array: $user);
        }

        return $user;
    }

    /**
     * @param string $login
     * @return array|object|null
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function findByLogin(string $login): array|null|object
    {
        $sql = "SELECT u.*, m.meta_value AS role FROM {$this->dfdb->basePrefix}user u 
                JOIN {$this->dfdb->prefix}usermeta m 
                ON (m.user_id = u.user_id AND m.meta_key = 'role') 
                WHERE u.user_login = ?";

        $data = $this->dfdb->getRow(
            query: $this->dfdb->prepare(query: $sql, params: [$login]),
            output: Database::ARRAY_A
        );

        if (is_null__(var: $data)) {
            return [];
        }

        $user = $this->populate(data: $data);

        if (is_array(value: $user)) {
            $user = convert_array_to_object(array: $user);
        }

        return $user;
    }

    /**
     * @param string $token
     * @return array|object|null
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function findByToken(string $token): array|null|object
    {
        $sql = "SELECT u.*, m.meta_value AS role FROM {$this->dfdb->basePrefix}user u 
                JOIN {$this->dfdb->prefix}usermeta m 
                ON (m.user_id = u.user_id AND m.meta_key = 'role') 
                WHERE u.user_token = ?";

        $data = $this->dfdb->getRow(
            query: $this->dfdb->prepare(query: $sql, params: [$token]),
            output: Database::ARRAY_A
        );

        if (is_null__(var: $data)) {
            return [];
        }

        $user = $this->populate(data: $data);

        if (is_array(value: $user)) {
            $user = convert_array_to_object(array: $user);
        }

        return $user;
    }

    /**
     * @return array
     * @throws Exception
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     */
    public function findAll(): array
    {
        $sql = "SELECT u.* FROM {$this->dfdb->basePrefix}user u 
                JOIN {$this->dfdb->basePrefix}site_user su 
                ON (u.user_id = su.user_id)
                WHERE su.site_id = ?";

        $data = $this->dfdb->getResults(query: $this->dfdb->prepare($sql, [get_current_site_id()]), output: Database::ARRAY_A);

        $users = [];

        if (!is_false__($data)) {
            foreach ($data as $user) {
                $users[] = $this->populate($user);
            }
        }

        return $users;
    }
}
