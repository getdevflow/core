<?php

declare(strict_types=1);

namespace App\Domain\User\Query\Trait;

use App\Infrastructure\Services\Trait\CleanAware;
use Qubus\Exception\Exception;

use function Qubus\Security\Helpers\purify_html;

trait PopulateUserQueryAware
{
    use CleanAware;

    /**
     * Populate an array of values from result query.
     *
     * @param array|null $data
     * @return array|null
     * @throws Exception
     */
    private function populate(?array $data = []): ?array
    {
        return [
            'id' => $this->clean($data['user_id']),
            'login' => $this->clean($data['user_login']),
            'token' => $this->clean($data['user_token']),
            'fname' => isset($data['user_fname']) ? purify_html(string: $data['user_fname']) : null,
            'mname' => isset($data['user_mname']) ? purify_html(string: $data['user_mname']) : null,
            'lname' => isset($data['user_lname']) ? purify_html($data['user_lname']) : null,
            'email' => $this->clean($data['user_email']),
            'pass' => $this->clean($data['user_pass']),
            'url' => $this->clean($data['user_url']),
            'bio' => isset($data['user_bio']) ? purify_html(string: $data['user_bio']) : null,
            'timezone' => $this->clean($data['user_timezone']),
            'dateFormat' => $this->clean($data['user_date_format']),
            'timeFormat' => $this->clean($data['user_time_format']),
            'locale' => $this->clean($data['user_locale']),
            'registered' => $this->clean($data['user_registered']),
            'modified' => $this->clean($data['user_modified']),
            'activationKey' => $this->clean($data['user_activation_key']),
        ];
    }
}
