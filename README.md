Devflow Core
============

This repository contains the core component for Devflow. If you want to build a website or application using Devflow, visit the main [Devflow repository](https://github.com/getdevflow/cmf).

Please report any issues or bugs to the main [Devflow repository](https://github.com/getdevflow/cmf/issues/new).

## Vihzhuo child themes

Core requires Vihzhuo 2.1 or later. `DevflowPageBuilder` defaults to the core `VihzhuoTheme` adapter and retains
explicitly configured custom theme adapters. Existing `theme.class`, `folder`, and `folder_url` settings remain valid.

Devflow declares a child theme by extending the installed parent theme's class:

```php
namespace Theme\MyChild;

use Theme\MyParent\MyParentTheme;

final class MyChildTheme extends MyParentTheme
{
    // Supply the child's own meta() declaration and handle() implementation as needed.
}
```

The CMS `get_parent_theme()` helper resolves that declaration. The Vihzhuo adapter uses it for each ancestor;
there is no separate `parents` configuration map. Parent theme classes must be autoloadable and their theme folders
must remain installed. Activating `Theme\MyChild\MyChildTheme` selects the `MyChild` folder for the current site,
even when Vihzhuo's `active_theme` setting names a different default.

Blocks and layouts fall back to ancestors when the child does not contain their directory. A child directory with
the same resource slug replaces the entire parent resource, including configuration and PHP code. A child layout
containing only `config.php` must also supply `view.php`. Dynamic blocks should declare their PHP `namespace` in
their resource configuration, as with existing Devflow themes.

Assets fall back through the child's and ancestors' `public/` directories (and existing root asset paths), using
`phpb_theme_asset('css/theme.css')`. Translations also fall back through the chain. The editor, public renderer, and
the Header Footer Builder plugin can all use the configured adapter through `phpb_instance('theme', [$themeConfig, $default])`.
The Header Footer Builder plugin body previews should pass this adapter to `PageRenderer`.

To preview a theme independently of the current site's activation:

```php
$theme = new \App\Infrastructure\Services\Vihzhuo\VihzhuoTheme(
    $themeConfig,
    $default,
    previewTheme: 'Theme\\MyChild\\MyChildTheme',
);
$builder->setTheme($theme);
```

Activating or deactivating a theme clears the existing Vihzhuo page cache. After editing an inherited template,
use the CMS's existing cache-flush action to clear cached pages. No additional cache settings are needed.

## 🔐 Security Vulnerabilities

If you discover a vulnerability in the code, please email [joshua@joshuaparker.dev](mailto:joshua@joshuaparker.dev).
