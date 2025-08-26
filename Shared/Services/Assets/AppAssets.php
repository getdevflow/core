<?php

declare(strict_types=1);

namespace App\Shared\Services\Assets;

use Qubus\Support\Assets;

final class AppAssets extends Assets
{
    protected array $collections = [
        'jquery' => '//cdnjs.cloudflare.com/ajax/libs/jquery/2.2.4/jquery.js',
        'jquery-ui' => [
            'jquery',
            '//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js'
        ],
        'colorpicker-js' => [
            'bootstrap-colorpicker/bootstrap-colorpicker.min.js',
            'bootstrap-colorpicker/config.js'
        ],
        'datatables-js' => [
            'datatables/jquery.dataTables.min.js',
            'datatables/dataTables.bootstrap.min.js',
            'pages/datatable.js'
        ],
        'datetimepicker-js' => 'bootstrap-datetimepicker/bootstrap-datetimepicker.min.js',
        'select2-js' => [
            'select2/select2.full.min.js',
            'pages/select2.js'
        ],
        'switchery-js' => 'bootstrap-switchery/switchery.min.js',

        'colorpicker-css' => 'bootstrap-colorpicker/bootstrap-colorpicker.min.css',
        'fontawesome' => '//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css',
        'ionicons' => '//cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css',
        'datatables-css' => 'datatables/dataTables.bootstrap.css',
        'select2-css' => 'select2/select2.min.css',
        'datetimepicker-css' => 'bootstrap-datetimepicker/bootstrap-datetimepicker.min.css',
        'switchery-css' => 'bootstrap-switchery/switchery.min.css'
    ];
}
