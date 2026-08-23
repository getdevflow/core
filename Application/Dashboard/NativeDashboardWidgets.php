<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Devflow;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;
use Throwable;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\current_user_can;
use function App\Shared\Helpers\get_all_content_types;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\number_content_by_type;
use function App\Shared\Helpers\site_url;
use function Codefy\Framework\Helpers\trans_html;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\esc_url;
use function sprintf;

use const PHP_VERSION;

final class NativeDashboardWidgets
{
    /**
     * @throws Exception
     */
    public static function register(DashboardWidgetRegistry $widgets): void
    {
        $widgets->register('devflow.welcome', trans_html('Welcome to Devflow'), [self::class, 'welcome'])
            ->description(trans_html('Helpful links for getting started with this site.'))
            ->icon('fa fa-hand-wave')
            ->column(DashboardWidget::COLUMN_LEFT)
            ->priority(10);

        $widgets->register(
            'devflow.content-overview',
            trans_html('Content at a Glance'),
            [self::class, 'contentOverview'],
        )
            ->description(trans_html('A summary of the content currently managed by this site.'))
            ->icon('fa fa-chart-column')
            ->column(DashboardWidget::COLUMN_LEFT)
            ->priority(20);

        $widgets->register('devflow.quick-actions', trans_html('Quick Actions'), [self::class, 'quickActions'])
            ->description(trans_html('Shortcuts to common content and site tasks.'))
            ->icon('fa fa-bolt')
            ->column(DashboardWidget::COLUMN_RIGHT)
            ->priority(10);

        $widgets->register('devflow.system-info', trans_html('System Information'), [self::class, 'systemInformation'])
            ->description(trans_html('The Devflow and PHP versions running this site.'))
            ->icon('fa fa-circle-info')
            ->column(DashboardWidget::COLUMN_RIGHT)
            ->priority(20);
    }

    /**
     * @throws Exception
     */
    public static function welcome(): string
    {
        return sprintf(
            '<p>%s</p><p><a class="btn btn-primary" href="%s" target="_blank" rel="noopener noreferrer">'
            . '<i class="fa fa-book"></i> %s</a> '
            . '<a class="btn btn-default" href="%s" target="_blank" rel="noopener noreferrer">'
            . '<i class="fa fa-external-link"></i> %s</a></p>',
            trans_html(
                'Shape this dashboard around your work. Add, remove, and reorder widgets '
                . 'whenever your priorities change.'
            ),
            esc_url('https://docs.getdevflow.com/'),
            trans_html('Read the documentation'),
            esc_url(site_url()),
            trans_html('View site'),
        );
    }

    /**
     * @throws Exception
     */
    public static function contentOverview(): string
    {
        try {
            $contentTypes = get_all_content_types();
        } catch (Throwable) {
            return '<p class="text-muted">' . trans_html('Content totals are temporarily unavailable.') . '</p>';
        }

        if ($contentTypes === []) {
            return '<p class="text-muted">' . trans_html('No content types have been created yet.') . '</p>';
        }

        $html = '<ul class="list-group dashboard-widget-list">';

        foreach ($contentTypes as $contentType) {
            $slug = (string) ($contentType['slug'] ?? '');
            $title = (string) ($contentType['title'] ?? $slug);

            if ($slug === '') {
                continue;
            }

            try {
                $count = number_content_by_type($slug);
            } catch (Throwable) {
                $count = 0;
            }

            $html .= sprintf(
                '<li class="list-group-item"><span class="badge">%d</span>'
                . '<a href="%s">%s</a></li>',
                $count,
                esc_url(admin_url('content-type/' . $slug . '/')),
                esc_html($title),
            );
        }

        return $html . '</ul>';
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public static function quickActions(): string
    {
        $actions = [];

        if (current_user_can('create:content')) {
            try {
                foreach (get_all_content_types() as $contentType) {
                    $slug = (string) ($contentType['slug'] ?? '');
                    $title = (string) ($contentType['title'] ?? $slug);

                    if ($slug !== '') {
                        $actions[] = sprintf(
                            '<a class="btn btn-app" href="%s"><i class="fa fa-plus"></i>%s</a>',
                            esc_url(admin_url('content-type/' . $slug . '/create/')),
                            sprintf(trans_html('Add %s'), esc_html($title)),
                        );
                    }
                }
            } catch (Throwable) {
            }
        }

        if (current_user_can('manage:media')) {
            $actions[] = sprintf(
                '<a class="btn btn-app" href="%s"><i class="fa fa-photo-film"></i>%s</a>',
                esc_url(admin_url('media/')),
                trans_html('Media'),
            );
        }

        $actions[] = sprintf(
            '<a class="btn btn-app" href="%s"><i class="fa fa-user"></i>%s</a>',
            esc_url(admin_url('user/profile/')),
            trans_html('Profile'),
        );

        $actions[] = sprintf(
            '<a class="btn btn-app" href="%s" target="_blank" rel="noopener noreferrer">'
            . '<i class="fa fa-arrow-up-right-from-square"></i>%s</a>',
            esc_url(site_url()),
            trans_html('View Site'),
        );

        return '<div class="dashboard-quick-actions">' . implode('', $actions) . '</div>';
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     */
    public static function systemInformation(): string
    {
        $siteName = (string) get_option(key: 'sitename');

        return sprintf(
            '<dl class="dl-horizontal dashboard-system-info">'
            . '<dt>%s</dt><dd>%s</dd>'
            . '<dt>%s</dt><dd>%s</dd>'
            . '<dt>%s</dt><dd>%s</dd>'
            . '<dt>%s</dt><dd>%s</dd>'
            . '</dl>',
            trans_html('Site'),
            esc_html($siteName),
            trans_html('Devflow'),
            esc_html(Devflow::release()),
            trans_html('PHP'),
            esc_html(PHP_VERSION),
            trans_html('Last Cron Run'),
            get_option('cron_last_run', 'Unknown')
        );
    }
}
