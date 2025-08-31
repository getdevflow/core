<?php

declare(strict_types=1);

namespace App\Shared\Services\Assets;

use Qubus\Support\Assets;

final class AppAssets extends Assets
{
    protected array $collections = [
        'jquery' => '//cdnjs.cloudflare.com/ajax/libs/jquery/2.2.4/jquery.min.js',

        'jquery-ui' => [
            'jquery',
            '//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/jquery-ui.min.js'
        ],

        'colorpicker-js' => [
            '//cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/2.5.1/js/bootstrap-colorpicker.min.js',
            'bootstrap-colorpicker/config.js'
        ],

        'datatables-js' => [
            '//cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js',
            '//cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap.min.js',
            'pages/datatable.js'
        ],

        'datetimepicker-js' =>
        '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/js/bootstrap-datetimepicker.min.js',

        'select2-js' => [
            '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js',
            'pages/select2.js'
        ],
        'switchery-js' => 'bootstrap-switchery/switchery.min.js',

        'colorpicker-css' =>
        '//cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/2.5.1/css/bootstrap-colorpicker.min.css',

        'fontawesome' => '//cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css',

        'ionicons' => '//cdnjs.cloudflare.com/ajax/libs/ionicons/4.5.6/css/ionicons.min.css',

        'datatables-css' => '//cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap.min.css',

        'select2-css' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',

        'datetimepicker-css' =>
        '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/css/bootstrap-datetimepicker.min.css',

        'switchery-css' => 'bootstrap-switchery/switchery.min.css'
    ];
}
