<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function is_callable;
use function ob_get_clean;
use function ob_start;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Support\Helpers\add_trailing_slash;

/**
 * Add an admin parent menu link.
 *
 * Supports:
 * - Single parent menu with route
 * - Parent menu with children
 * - Optional icon
 * - Optional permission
 * - Optional screen parent
 * - Optional child callback/output
 *
 * @param string $location Menu location/hook name.
 * @param string $menuTitle The text to be used for the menu.
 * @param string $screen Unique parent screen name.
 * @param string|null $menuRoute Optional route. Use null or empty string for parent-only dropdown.
 * @param string $icon FontAwesome icon class.
 * @param string|null $permission Permission required for display.
 * @param callable|string|null $children Child menu HTML or callback returning/echoing HTML.
 * @param bool $newTab Whether to open menu link in a new tab/window.
 * @return false|string
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function add_admin_menu(
    string $location,
    string $menuTitle,
    string $screen,
    ?string $menuRoute = null,
    string $icon = 'fa fa-circle',
    ?string $permission = null,
    callable|string|null $children = null,
    bool $newTab = false
): false|string {
    if ($permission !== null && !current_user_can($permission)) {
        return false;
    }

    $hasChildren = $children !== null;

    $active = current_screen('screen_parent', $screen);

    $target = $newTab ? ' target="_blank" rel="noopener noreferrer"' : '';

    $href = '#';

    if (!$hasChildren && $menuRoute !== null && $menuRoute !== '') {
        $href = admin_url(add_trailing_slash($menuRoute));
    }

    ob_start();

    ?>
    <li<?= ae($permission); ?> class="treeview<?= $active; ?>">
        <a href="<?= $href; ?>"<?= $target; ?>>
            <i class="<?= $icon; ?>"></i>
            <span><?= $menuTitle; ?></span>

            <?php if ($hasChildren) : ?>
                <span class="pull-right-container">
                    <i class="fa fa-angle-left pull-right"></i>
                </span>
            <?php endif; ?>
        </a>

        <?php if ($hasChildren) : ?>
            <ul class="treeview-menu">
                <?php
                if (is_callable($children)) {
                    echo $children();
                } else {
                    echo $children;
                }

                Action::getInstance()->doAction("{$location}_submenu");
                ?>
            </ul>
        <?php endif; ?>
    </li>
    <?php

    $menu = ob_get_clean();

    return __observer()->filter->applyFilter("admin.menu.{$location}", $menu);
}

/**
 * Add an admin submenu page link.
 *
 * Uses admin.submenu.$location.{$menuRoute} filter hook.
 *
 * @file core/Shared/Helpers/menu.php
 * @param string $location Submenu location.
 * @param string $menuTitle The text to be used for the menu.
 * @param string $menuRoute The route part of the url.
 * @param string $screen Unique name of menu's screen.
 * @param string|null $permission The permission required for this menu to be displayed to the user.
 * @param bool $newTab Whether to open menu link in a new tab/window.
 * @return mixed
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws InvalidArgumentException
 */
function add_admin_submenu(
    string $location,
    string $menuTitle,
    string $menuRoute,
    string $screen,
    ?string $permission = null,
    bool $newTab = false
): mixed {
    if ($permission !== null && !current_user_can($permission)) {
        return false;
    }

    $menuRoute = add_trailing_slash($menuRoute);

    $target = $newTab ? ' target="_blank" rel="noopener noreferrer"' : '';

    $isActive = '' !== current_screen('screen_child', $screen);

    $icon = $isActive
        ? '<i class="fa-regular fa-circle" style="color:#3498db;font-weight:bold;"></i>'
        : '<i class="fa-regular fa-circle"></i>';

    $title = $isActive
        ? '<strong>' . $menuTitle . '</strong>'
        : $menuTitle;


    $menu = sprintf(
        '<li%s><a href="%s"%s>%s %s</a></li>%s',
        current_screen('screen_child', $screen),
        admin_url($menuRoute),
        $target,
        $icon,
        $title,
        "\n"
    );

    /**
     * Filter's the admin menu.
     *
     * The dynamic parts of this filter are `location` (where menu will appear), and
     * $_menu_route with the removed slash if present.
     *
     * @param string $menu The menu to return.
     */
    return __observer()->filter->applyFilter("admin.submenu.{$location}.{$menuRoute}", $menu);
}

/**
 * Adds an admin dashboard submenu page link.
 *
 * @file core/Shared/Helpers/menu.php
 * @param string $menuTitle The text to be used for the menu.
 * @param string $menuRoute The route part of the url.
 * @param string $screen Unique name of menu's screen.
 * @param string|null $permission The permission required for this menu to be displayed to the user.
 * @param bool $newTab Whether to open menu link in a new tab/window.
 * @return false|string         Return the new menu or false if permission is not met.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws InvalidArgumentException
 */
function add_dashboard_submenu(
    string $menuTitle,
    string $menuRoute,
    string $screen,
    ?string $permission = null,
    bool $newTab = false
): false|string {
    return add_admin_submenu('dashboard', $menuTitle, $menuRoute, $screen, $permission, $newTab);
}

/**
 * Adds a sites submenu page link.
 *
 * @file core/Shared/Helpers/menu.php
 * @param string $menuTitle The text to be used for the menu.
 * @param string $menuRoute The route part of the url.
 * @param string $screen Unique name of menu's screen.
 * @param string|null $permission The permission required for this menu to be displayed to the user.
 * @param bool $newTab Whether to open menu link in a new tab/window.
 * @return false|string         Return the new menu or false if permission is not met.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws InvalidArgumentException
 */
function add_sites_submenu(
    string $menuTitle,
    string $menuRoute,
    string $screen,
    ?string $permission = null,
    bool $newTab = false
): false|string {
    return add_admin_submenu('sites', $menuTitle, $menuRoute, $screen, $permission, $newTab);
}

/**
 * Adds a plugin submenu page link.
 *
 * @file core/Shared/Helpers/menu.php
 * @param string $menuTitle The text to be used for the menu.
 * @param string $menuRoute The route part of the url.
 * @param string $screen Unique name of menu's screen.
 * @param string|null $permission The permission required for this menu to be displayed to the user.
 * @param bool $newTab Whether to open menu link in a new tab/window.
 * @return false|string         Return the new menu or false if permission is not met.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws InvalidArgumentException
 */
function add_plugins_submenu(
    string $menuTitle,
    string $menuRoute,
    string $screen,
    ?string $permission = null,
    bool $newTab = false
): false|string {
    return add_admin_submenu('plugins', $menuTitle, $menuRoute, $screen, $permission, $newTab);
}

/**
 * Adds a theme submenu page link.
 *
 * @file core/Shared/Helpers/menu.php
 * @param string $menuTitle The text to be used for the menu.
 * @param string $menuRoute The route part of the url.
 * @param string $screen Unique name of menu's screen.
 * @param string|null $permission The permission required for this menu to be displayed to the user.
 * @param bool $newTab Whether to open menu link in a new tab/window.
 * @return false|string         Return the new menu or false if permission is not met.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws InvalidArgumentException
 */
function add_themes_submenu(
    string $menuTitle,
    string $menuRoute,
    string $screen,
    ?string $permission = null,
    bool $newTab = false
): false|string {
    return add_admin_submenu('themes', $menuTitle, $menuRoute, $screen, $permission, $newTab);
}

/**
 * Adds a users submenu page link.
 *
 * @file core/Shared/Helpers/menu.php
 * @param string $menuTitle The text to be used for the menu.
 * @param string $menuRoute The route part of the url.
 * @param string $screen Unique name of menu's screen.
 * @param string|null $permission The permission required for this menu to be displayed to the user.
 * @param bool $newTab Whether to open menu link in a new tab/window.
 * @return false|string         Return the new menu or false if permission is not met.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws InvalidArgumentException
 */
function add_users_submenu(
    string $menuTitle,
    string $menuRoute,
    string $screen,
    ?string $permission = null,
    bool $newTab = false
): false|string {
    return add_admin_submenu('users', $menuTitle, $menuRoute, $screen, $permission, $newTab);
}

/**
 * Adds an options submenu page link.
 *
 * @file core/Shared/Helpers/menu.php
 * @param string $menuTitle The text to be used for the menu.
 * @param string $menuRoute The route part of the url.
 * @param string $screen Unique name of menu's screen.
 * @param string|null $permission The permission required for this menu to be displayed to the user.
 * @param bool $newTab Whether to open menu link in a new tab/window.
 * @return false|string         Return the new menu or false if permission is not met.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws InvalidArgumentException
 */
function add_options_submenu(
    string $menuTitle,
    string $menuRoute,
    string $screen,
    ?string $permission = null,
    bool $newTab = false
): false|string {
    return add_admin_submenu('options', $menuTitle, $menuRoute, $screen, $permission, $newTab);
}
