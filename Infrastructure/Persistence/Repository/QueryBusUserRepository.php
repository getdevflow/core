<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\User\Query\Trait\PopulateUserQueryAware;
use App\Domain\User\Repository\UserQueryRepository;
use App\Infrastructure\Persistence\Database;
use Qubus\Exception\Exception;
use ReflectionException;

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
     * @throws ReflectionException
     * @throws Exception
     */
    public function findById(string $userId): array|object
    {
        $sql = "SELECT u.*, m.meta_value AS role FROM {$this->dfdb->basePrefix}user u 
                JOIN {$this->dfdb->basePrefix}usermeta m 
                ON (m.user_id = u.user_id AND m.meta_key = '{$this->dfdb->prefix}role') 
                WHERE u.user_id = ?";

        $data = $this->dfdb->getRow(
            query: $this->dfdb->prepare(query: $sql, params: [$userId]),
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

    public function findUnique(): array|null|object
    {
        $sql = "SELECT DISTINCT * FROM {$this->dfdb->basePrefix}user 
        JOIN {$this->dfdb->basePrefix}usermeta 
        ON {$this->dfdb->basePrefix}usermeta.user_id = {$this->dfdb->basePrefix}user.user_id 
        WHERE meta_key LIKE {$this->dfdb->prefix}%";

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
     * @throws ReflectionException
     * @throws Exception
     */
    public function findByEmail(string $userEmail): array|null|object
    {
        $sql = "SELECT u.*, m.meta_value AS role FROM {$this->dfdb->basePrefix}user u 
                JOIN {$this->dfdb->basePrefix}usermeta m 
                ON (m.user_id = u.user_id AND m.meta_key = '{$this->dfdb->prefix}role') 
                WHERE u.user_email = ?";

        $data = $this->dfdb->getRow(
            query: $this->dfdb->prepare(query: $sql, params: [$userEmail]),
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
     * @throws ReflectionException
     * @throws Exception
     */
    public function findByLogin(string $userLogin): array|null|object
    {
        $sql = "SELECT u.*, m.meta_value AS role FROM {$this->dfdb->basePrefix}user u 
                JOIN {$this->dfdb->basePrefix}usermeta m 
                ON (m.user_id = u.user_id AND m.meta_key = '{$this->dfdb->prefix}role') 
                WHERE u.user_login = ?";

        $data = $this->dfdb->getRow(
            query: $this->dfdb->prepare(query: $sql, params: [$userLogin]),
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
     * @throws ReflectionException
     * @throws Exception
     */
    public function findByToken(string $userToken): array|null|object
    {
        $sql = "SELECT u.*, m.meta_value AS role FROM {$this->dfdb->basePrefix}user u 
                JOIN {$this->dfdb->basePrefix}usermeta m 
                ON (m.user_id = u.user_id AND m.meta_key = '{$this->dfdb->prefix}role') 
                WHERE u.user_token = ?";

        $data = $this->dfdb->getRow(
            query: $this->dfdb->prepare(query: $sql, params: [$userToken]),
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

    public function findAll(): array
    {
        $sql = "SELECT u.*, m.meta_value AS role FROM {$this->dfdb->basePrefix}user u 
                JOIN {$this->dfdb->basePrefix}usermeta m 
                ON (m.user_id = u.user_id AND m.meta_key = '{$this->dfdb->prefix}role')";

        $data = $this->dfdb->getResults(query: $sql, output: Database::ARRAY_A);

        $users = [];

        if (!is_false__($data)) {
            foreach ($data as $user) {
                $users[] = $this->populate($user);
            }
        }

        return $users;
    }
}
