<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\Integration;

use Contenir\Seo\JsonLd\JsonLd;
use Contenir\Seo\Laminas\Mvc\Module;
use Contenir\Seo\Organisation\Organisation;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\SettingKeys;
use Laminas\Http\Request;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Helper\Doctype;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver\TemplatePathStack;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_replace_recursive;
use function array_reverse;
use function explode;
use function html_entity_decode;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * Layouts calling seo() through laminas-view, with the module's
 * configuration, after the page set its own title and metadata.
 */
#[Group('integration')]
final class LayoutTest extends TestCase
{
    private static function renderer(SeoConfig $config, string $url): PhpRenderer
    {
        $request = new Request();
        $request->setUri($url);

        $services = new ServiceManager();
        $services->setService(SeoConfig::class, $config);
        $services->setService('Request', $request);

        /** @var array{view_helpers: array<string, mixed>} $module */
        $module  = (new Module())->getConfig();
        $helpers = new HelperPluginManager($services, $module['view_helpers']);
        $services->setService('ViewHelperManager', $helpers);

        $renderer = new PhpRenderer();
        $renderer->setHelperPluginManager($helpers);
        $renderer->setResolver(new TemplatePathStack(['script_paths' => [__DIR__ . '/../TestAsset/view']]));
        $renderer->plugin(Doctype::class)->setDoctype(Doctype::HTML5);

        return $renderer;
    }

    /**
     * @param array<string, mixed> $values By dotted key
     */
    private static function settings(array $values = []): SeoConfig
    {
        $settings = [];
        foreach ([
            SettingKeys::SITE_URL      => 'https://www.example.com',
            SettingKeys::SITE_TITLE    => 'Example',
            SettingKeys::DESCRIPTION   => 'Handmade furniture',
            SettingKeys::SHARE_IMAGE   => '/share.jpg',
            SettingKeys::VERIFY_GOOGLE => 'g-token',
            ...$values,
        ] as $key => $value) {
            foreach (array_reverse(explode('.', $key)) as $segment) {
                $value = [$segment => $value];
            }

            $settings = array_replace_recursive($settings, $value);
        }

        return SeoConfig::fromSettings($settings);
    }

    #[Test]
    public function addsTheDefaultsToAPageAndHidesACopy(): void
    {
        $html = html_entity_decode(
            self::renderer(self::settings(), 'https://staging.example.com/about')->render('about'),
            ENT_QUOTES | ENT_HTML5,
        );

        static::assertSame(
            <<<'HTML'
                <head>
                <title>About us | Example</title>
                <meta name="description" content="Who we are">
                <meta property="og:site_name" content="Example">
                <meta name="robots" content="noindex, nofollow">
                <meta property="og:image" content="https://www.example.com/share.jpg">
                <meta name="twitter:card" content="summary_large_image">
                <meta name="twitter:image" content="https://www.example.com/share.jpg">
                </head>

                HTML,
            $html,
        );
    }

    #[Test]
    public function writesTheHomePageOnceWithItsStructuredData(): void
    {
        $config = self::settings([SettingKeys::VISIBILITY => 'show']);
        $script = (new JsonLd('https://www.example.com', 'Example', new Organisation()))->script('page-nonce');

        $html = html_entity_decode(
            self::renderer($config, 'https://www.example.com/')->render('home'),
            ENT_QUOTES | ENT_HTML5,
        );

        static::assertSame(
            <<<HTML
                <head>
                {$script}{$script}<title>Home | Example</title>
                <meta property="og:image" content="https://www.example.com/home.jpg">
                <meta property="og:site_name" content="Example">
                <meta name="google-site-verification" content="g-token">
                <meta name="description" content="Handmade furniture">
                <meta property="og:description" content="Handmade furniture">
                <meta name="twitter:description" content="Handmade furniture">
                </head>

                HTML,
            $html,
        );
    }
}
