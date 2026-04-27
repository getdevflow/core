<?php

declare(strict_types=1);

namespace App\Domain\User\Query\Trait;

use Qubus\Exception\Exception;

use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\purify_html;

trait PopulateUserQueryAware
{
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
            'id' => isset($data['user_id']) ? esc_html(string: $data['user_id']) : null,
            'login' => isset($data['user_login']) ? esc_html(string: $data['user_login']) : null,
            'token' => isset($data['user_token']) ? esc_html(string: $data['user_token']) : null,
            'fname' => isset($data['user_fname']) ? purify_html(string: $data['user_fname']) : null,
            'mname' => isset($data['user_mname']) ? purify_html(string: $data['user_mname']) : null,
            'lname' => isset($data['user_lname']) ? purify_html($data['user_lname']) : null,
            'email' => isset($data['user_email']) ? esc_html(string: $data['user_email']) : null,
            'pass' => isset($data['user_pass']) ? esc_html(string: $data['user_pass']) : null,
            'url' => isset($data['user_url']) ? esc_html(string: $data['user_url']) : null,
            'timezone' => isset($data['user_timezone']) ? esc_html(string: $data['user_timezone']) : null,
            'dateFormat' => isset($data['user_date_format']) ? esc_html(string: $data['user_date_format']) : null,
            'timeFormat' => isset($data['user_time_format']) ? esc_html(string: $data['user_time_format']) : null,
            'locale' => isset($data['user_locale']) ? esc_html(string: $data['user_locale']) : null,
            'registered' => isset($data['user_registered']) ? esc_html(string: $data['user_registered']) : null,
            'modified' => isset($data['user_modified']) ? esc_html(string: $data['user_modified']) : null,
            'activationKey' => isset($data['user_activation_key'])
                    ? esc_html(string: $data['user_activation_key']) :
                    null,
        ];
    }
}
