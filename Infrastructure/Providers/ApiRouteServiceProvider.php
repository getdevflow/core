<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Codefy\Framework\Support\CodefyServiceProvider;
use Qubus\Routing\Exceptions\TooLateToAddNewRouteException;
use Qubus\Routing\Router;

final class ApiRouteServiceProvider extends CodefyServiceProvider
{
    /**
     * @return void
     * @throws TooLateToAddNewRouteException
     */
    public function register(): void
    {
        if ($this->codefy->isRunningInConsole()) {
            return;
        }

        /** @var $router Router */
        $router = $this->codefy->make(name: 'router');

        /**
         * Version 1 is legacy and should not be used.
         * @deprecated
         */
        $router->get(uri: '/v1/{table}/', callback: 'ApiController@all')->middleware('rest.api');
        $router->get(uri: '/v1/{table}/{field}/{value}', callback: 'ApiController@column')->middleware('rest.api');

        /**
         * Version 2 API Routes.
         * @since 1.3.0
         */
        $router->group(['middleware' => 'rest.api', 'prefix' => '/v2'], function ($group) {
            $group->get(uri: '/content/', callback: 'ContentRestController@index')
                    ->name('v2.content.index');
            $group->get(uri: '/content/{id}/', callback: 'ContentRestController@show')
                    ->where(['id' => '[0123456789ABCDEFGHJKMNPQRSTVWXYZ{26}$]+'])
                    ->name('v2.content.show');
            $group->post(uri: '/content/store/', callback: 'ContentRestController@store');
            $group->map(verbs: ['PUT', 'PATCH'], uri: '/content/{id}/', callback: 'ContentRestController@update')
                    ->where(['id' => '[0123456789ABCDEFGHJKMNPQRSTVWXYZ{26}$]+'])
                    ->name('v2.content.update');
            $group->delete(uri: '/content/{id}/', callback: 'ContentRestController@destroy')
                    ->where(['id' => '[0123456789ABCDEFGHJKMNPQRSTVWXYZ{26}$]+'])
                    ->name('v2.content.destroy');

            $group->get(uri: '/user/', callback: 'UserRestController@index')
                    ->name('v2.user.index');
            $group->get(uri: '/user/{id}/', callback: 'UserRestController@show')
                    ->where(['id' => '[0123456789ABCDEFGHJKMNPQRSTVWXYZ{26}$]+'])
                    ->name('v2.user.show');
            $group->post(uri: '/user/store/', callback: 'UserRestController@store');
            $group->map(verbs: ['PUT', 'PATCH'], uri: '/user/{id}/', callback: 'UserRestController@update')
                    ->where(['id' => '[0123456789ABCDEFGHJKMNPQRSTVWXYZ{26}$]+'])
                    ->name('v2.user.update');
            $group->delete(uri: '/user/{id}/', callback: 'UserRestController@destroy')
                    ->where(['id' => '[0123456789ABCDEFGHJKMNPQRSTVWXYZ{26}$]+'])
                    ->name('v2.user.destroy');

            $group->get(uri: '/product/', callback: 'ProductRestController@index')
                    ->name('v2.product.index');
            $group->get(uri: '/product/{id}/', callback: 'ProductRestController@show')
                    ->where(['id' => '[0123456789ABCDEFGHJKMNPQRSTVWXYZ{26}$]+'])
                    ->name('v2.product.show');
            $group->post(uri: '/product/store/', callback: 'ProductRestController@store');
            $group->map(verbs: ['PUT', 'PATCH'], uri: '/product/{id}/', callback: 'ProductRestController@update')
                    ->where(['id' => '[0123456789ABCDEFGHJKMNPQRSTVWXYZ{26}$]+'])
                    ->name('v2.product.update');
            $group->delete(uri: '/product/{id}/', callback: 'ProductRestController@destroy')
                    ->where(['id' => '[0123456789ABCDEFGHJKMNPQRSTVWXYZ{26}$]+'])
                    ->name('v2.product.destroy');
        });
    }
}
