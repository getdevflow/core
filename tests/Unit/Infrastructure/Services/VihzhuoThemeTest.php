<?php

declare(strict_types=1);

use App\Infrastructure\Services\Options;
use App\Infrastructure\Services\Vihzhuo\DevflowPageBuilder;
use App\Infrastructure\Services\Vihzhuo\PageCacheInvalidator;
use App\Infrastructure\Services\Vihzhuo\VihzhuoTheme;
use Codefy\Framework\Application;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Qubus\Cache\InMemoryCache;
use Qubus\Config\Collection;
use Qubus\Expressive\Connection\Pdo\Sqlite;
use Qubus\Expressive\QueryBuilder;
use Qubus\Injector\Config\InjectorFactory;
use Qubus\Injector\Injector;
use Vihzhuo\Modules\GrapesJS\PageRenderer;
use Vihzhuo\Page;

use function App\Shared\Helpers\activate_theme;
use function App\Shared\Helpers\get_parent_theme;

// Pest does not define PHPUnit's Composer path, which isolated PHPUnit workers need.
if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', dirname(__DIR__, 4) . '/vendor/autoload.php');
}

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class VihzhuoThemeTest extends TestCase
{
    private string $workingDirectory;
    private string $temporaryDirectory;
    private array $config;
    private Sqlite $connection;

    protected function setUp(): void
    {
        $this->workingDirectory = getcwd();
        $this->temporaryDirectory = sys_get_temp_dir() . '/devflow-themes-' . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory . '/bootstrap', 0775, true);
        file_put_contents(
            $this->temporaryDirectory . '/bootstrap/app.php',
            '<?php return $GLOBALS["devflowThemeTestApp"];'
        );

        $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod(Injector::class, '__construct'))->invoke($app, InjectorFactory::create());
        $this->connection = new Sqlite(['dsn' => 'sqlite::memory:']);
        $database = new QueryBuilder($this->connection);
        $database->prefix = 'site_alpha_';
        foreach (['site_alpha_', 'site_beta_'] as $prefix) {
            $this->connection->pdo->exec(
                "CREATE TABLE {$prefix}option (option_id TEXT, option_key TEXT, option_value TEXT)"
            );
            $this->connection->pdo->prepare("INSERT INTO {$prefix}option VALUES (?, ?, ?)")
                ->execute(['theme', 'site_theme', 'Theme\\Other\\OtherTheme']);
        }
        $app->share(new Options($database, new InMemoryCache()));
        $GLOBALS['devflowThemeTestApp'] = $app;
        chdir($this->temporaryDirectory);

        $themes = dirname(__DIR__, 3) . '/Fixtures/Vihzhuo/themes';
        $loader = require dirname(__DIR__, 4) . '/vendor/autoload.php';
        $loader->addPsr4('Theme\\', $themes);
        require_once $themes . '/Child/CustomTheme.php';
        require_once $themes . '/ParentTheme/blocks/php/inherited/model.php';

        $GLOBALS['phpb_db'] = new \Vihzhuo\Core\DB(['dsn' => 'sqlite::memory:']);
        $GLOBALS['phpb_db']->query('CREATE TABLE page_translations (id INTEGER, page_id INTEGER, locale TEXT)');

        $this->config = [
            'theme' => ['folder' => $themes, 'folder_url' => '/themes', 'active_theme' => 'Ancestor'],
            'general' => ['language' => 'en', 'base_url' => 'https://cms.example'],
            'storage' => ['use_database' => false],
            'page' => ['translation' => ['class' => \Vihzhuo\PageTranslation::class]],
            'auth' => ['use_login' => false],
            'website_manager' => ['use_website_manager' => false],
            'language' => ['default' => 'en', 'languages' => ['en' => 'English']],
        ];
        $settings = new Collection([]);
        $settings->setConfigKey('vihzhuo', $this->config);
        $app->share($settings);
        $app->alias('codefy.config', Collection::class);
    }

    protected function tearDown(): void
    {
        chdir($this->workingDirectory);
        unlink($this->temporaryDirectory . '/bootstrap/app.php');
        rmdir($this->temporaryDirectory . '/bootstrap');
        if (is_dir($this->temporaryDirectory . '/cache')) {
            unlink($this->temporaryDirectory . '/cache/unrelated.txt');
            foreach (new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->temporaryDirectory . '/cache', FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            ) as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($this->temporaryDirectory . '/cache');
        }
        rmdir($this->temporaryDirectory);
    }

    private function activateChild(): void
    {
        activate_theme('Theme\\Child\\CustomTheme');
    }

    private function page(): Page
    {
        $page = new Page();
        $page->setData([
            'id' => '1', 'layout' => 'master',
            'data' => ['html' => '[block slug="inherited"]', 'blocks' => []],
        ]);
        return $page;
    }

    public function testActivationResolvesEveryAncestorForTheCurrentSite(): void
    {
        $this->activateChild();
        $builder = new DevflowPageBuilder($this->config);
        $theme = $builder->getTheme();
        self::assertInstanceOf(VihzhuoTheme::class, $theme);
        self::assertSame(['Child', 'ParentTheme', 'Ancestor'], array_keys($theme->getThemeFolders()));
        self::assertSame('Theme\\ParentTheme\\ParentTheme', get_parent_theme('Theme\\Child\\CustomTheme'));
        self::assertNull(get_parent_theme('Theme\\Ancestor\\AncestorTheme'));
        self::assertSame('Theme\\Child\\CustomTheme', $this->connection->pdo
            ->query("SELECT option_value FROM site_alpha_option WHERE option_key = 'site_theme'")->fetchColumn());
        self::assertSame('Theme\\Other\\OtherTheme', $this->connection->pdo
            ->query("SELECT option_value FROM site_beta_option WHERE option_key = 'site_theme'")->fetchColumn());
    }

    public function testPreviewAndSlugResolutionUseTheRequestedTheme(): void
    {
        $this->activateChild();
        $preview = new VihzhuoTheme($this->config['theme'], 'Ancestor', previewTheme: 'Theme\\Other\\OtherTheme');
        self::assertSame(['Other', 'Ancestor'], array_keys($preview->getThemeFolders()));
        $preview = new VihzhuoTheme($this->config['theme'], 'Ancestor', previewTheme: 'Child');
        self::assertSame(['Child', 'ParentTheme', 'Ancestor'], array_keys($preview->getThemeFolders()));
    }

    public function testEditorEnumeratesInheritedResourcesAndReplacesChildDirectories(): void
    {
        $this->activateChild();
        $theme = new DevflowPageBuilder($this->config)->getTheme();
        $blocks = $theme->getThemeBlocks();
        self::assertEqualsCanonicalizing(['inherited', 'shared'], array_keys($blocks));
        self::assertSame('Child resource', $blocks['shared']->get('title'));
        self::assertNull($blocks['shared']->get('parent_setting'));
        self::assertSame('Theme\\ParentTheme\\Blocks\\Php\\Inherited\\Model', $blocks['inherited']->getModelClass());
        self::assertSame(['master'], array_keys($theme->getThemeLayouts()));
        self::assertStringContainsString('/Child/block-thumbs/', $blocks['inherited']->getThumbPath());
    }

    public function testPublicRenderingAndHeaderFooterBodyPreviewUseInheritedPhp(): void
    {
        $this->activateChild();
        $builder = new DevflowPageBuilder($this->config);
        $html = $builder->getPageBuilder()->renderPage($this->page());
        self::assertStringContainsString('<main><p>Inherited model</p>', $html);
        self::assertStringContainsString('https://cms.example/themes/Ancestor/css/ancestor.css', $html);
        $renderer = new PageRenderer($builder->getTheme(), $this->page());
        self::assertStringContainsString('<p>Inherited model</p>', $renderer->renderBody());
        self::assertSame('/themes/Ancestor/css/ancestor.css', $renderer->getThemeAssetPath('css/ancestor.css'));
        self::assertSame('https://cms.example/themes/Ancestor/css/ancestor.css', phpb_theme_asset('css/ancestor.css'));
    }

    public function testTranslationsFallBackThroughAllAncestors(): void
    {
        $this->activateChild();
        $builder = new DevflowPageBuilder($this->config);
        $translations = $builder->loadTranslations('en');
        self::assertSame('From ancestor', $translations['ancestor_message']);
        self::assertSame('From child', $translations['overridden_message']);
    }

    public function testConfigurationDefaultIsUsedWhenNoThemeIsActivated(): void
    {
        $this->connection->pdo->exec('DELETE FROM site_alpha_option');
        $builder = new DevflowPageBuilder($this->config);
        self::assertSame(['Ancestor'], array_keys($builder->getTheme()->getThemeFolders()));
    }

    public function testExplicitCustomAdaptersRemainConfigured(): void
    {
        $this->config['theme']['class'] = CustomVihzhuoTestTheme::class;
        $builder = new DevflowPageBuilder($this->config);
        self::assertInstanceOf(CustomVihzhuoTestTheme::class, $builder->getTheme());
    }

    public function testInvalidSlugsAreRejectedInsteadOfTruncated(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VihzhuoTheme($this->config['theme'], 'Ancestor', previewTheme: '../Child');
    }

    public function testThemeActivationAndCacheFlushInvalidatePagesAndPreserveOtherCaches(): void
    {
        $this->config['cache'] = ['enabled' => true, 'folder' => $this->temporaryDirectory . '/cache'];
        \Codefy\Framework\Helpers\config()->setConfigKey('vihzhuo', $this->config);
        $GLOBALS['phpb_config'] = $this->config;
        $cache = new \Vihzhuo\Cache();
        foreach (['/', '/landing', '/es/landing'] as $url) {
            $cache->storeForUrl($url, 'Old theme', 60);
        }
        file_put_contents($this->temporaryDirectory . '/cache/unrelated.txt', 'Other CMS cache');
        $this->activateChild();
        foreach (['/', '/landing', '/es/landing'] as $url) {
            self::assertNull($cache->getForUrl($url));
        }
        $cache->storeForUrl('/landing', 'Old inherited template', 60);
        PageCacheInvalidator::clear();
        self::assertNull($cache->getForUrl('/landing'));
        self::assertFileExists($this->temporaryDirectory . '/cache/unrelated.txt');
        self::assertSame($this->config, $GLOBALS['phpb_config']);
    }
}

final class CustomVihzhuoTestTheme extends \Vihzhuo\Theme
{
}
