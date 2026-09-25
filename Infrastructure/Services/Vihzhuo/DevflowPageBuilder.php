<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Vihzhuo;

use Vihzhuo\Vihzhuo;

final class DevflowPageBuilder extends Vihzhuo
{
    public function __construct(?array $config = [])
    {
        if (!empty($config)) {
            foreach (
                [
                'auth' => [\Vihzhuo\Modules\Auth\Auth::class, VihzhuoAuth::class],
                'website_manager' => [\Vihzhuo\Modules\WebsiteManager\WebsiteManager::class, WebsiteManager::class],
                'pagebuilder' => [\Vihzhuo\Modules\GrapesJS\PageBuilder::class, PageEditor::class],
                ] as $section => [$upstream, $adapter]
            ) {
                if (!isset($config[$section]['class']) || $config[$section]['class'] === $upstream) {
                    $config[$section]['class'] = $adapter;
                }
            }
        }
        parent::__construct($config);
    }
}
