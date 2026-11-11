<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\Integration;

use Contenir\Resource\Core\Metadata\PageMetadata;
use Contenir\Seo\Head\Article;
use Contenir\Seo\Laminas\Mvc\Factory\SeoFactory;
use Contenir\Seo\Laminas\Mvc\Module;
use Contenir\Seo\Laminas\Mvc\Tests\TestAsset\NoListener;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\SiteUrl;
use Contenir\Seo\Visibility\Visibility;
use Laminas\EventManager\EventManager;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\Navigation\Navigation;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Router\RouteMatch;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Helper\Doctype;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function html_entity_decode;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * The module in a bootstrapped laminas-mvc application: seo() reads the
 * matched route and host from the application's event, and a hidden copy
 * sends X-Robots-Tag on every response.
 */
#[Group('integration')]
final class ApplicationTest extends TestCase
{
    private static function application(string $url, bool $records = false): Application
    {
        $request = new Request();
        $request->setUri($url);

        /** @var array{service_manager: array<string, mixed>} $config */
        $config   = (new Module())->getConfig();
        $services = new ServiceManager($config['service_manager']);
        $services->setService(SeoConfig::class, new SeoConfig(
            site: new SiteUrl('https://www.example.com'),
            visibility: Visibility::Automatic,
            records: $records,
        ));
        $services->setService('Router', new TreeRouteStack());
        foreach ([
            'RouteListener',
            'DispatchListener',
            'HttpMethodListener',
            'ViewManager',
            'SendResponseListener',
            'MiddlewareListener',
        ] as $name) {
            $services->setService($name, new NoListener());
        }

        $application = new Application($services, new EventManager(), $request, new Response());
        $services->setService('Application', $application);
        $services->setService('ViewHelperManager', self::helpers($services));
        $application->bootstrap();
        (new Module())->onBootstrap($application->getMvcEvent());

        return $application;
    }

    /**
     * View helpers with a renderer, as laminas-mvc's view manager sets them up.
     */
    private static function helpers(ServiceManager $services): HelperPluginManager
    {
        $helpers  = new HelperPluginManager($services);
        $renderer = new PhpRenderer();
        $renderer->setHelperPluginManager($helpers);
        $renderer->plugin(Doctype::class)->setDoctype(Doctype::HTML5);

        return $helpers;
    }

    #[Test]
    public function anArticleGivenToHeadWinsOverTheRoutedMetadata(): void
    {
        $application = self::application('https://www.example.com/news/a-story');
        $event       = $application->getMvcEvent();
        $event->setRouteMatch((new RouteMatch([]))->setMatchedRouteName('page-5'));
        $event->setParam(PageMetadata::class, new PageMetadata(
            'https://www.example.com/news/a-story',
            title: 'From metadata',
            type: PageMetadata::ARTICLE,
        ));

        static::assertStringContainsString(
            '"headline":"Given"',
            (new SeoFactory())($application->getServiceManager())()->head(
                article: new Article('https://www.example.com/news/a-story', 'Given'),
            ),
        );
    }

    #[Test]
    public function describesAPageAsAnArticleWhenGivenOne(): void
    {
        $application = self::application('https://www.example.com/news/a-story');
        $application->getMvcEvent()->setRouteMatch((new RouteMatch([]))->setMatchedRouteName('news'));

        $head = (new SeoFactory())($application->getServiceManager())()->head(
            article: new Article('https://www.example.com/news/a-story', 'A story'),
        );

        static::assertStringContainsString('"@type":"Article"', $head);
    }

    #[Test]
    public function describesTheRoutedResourceAsAnArticleFromItsMetadata(): void
    {
        $application = self::application('https://www.example.com/news/a-story');
        $event       = $application->getMvcEvent();
        $event->setRouteMatch((new RouteMatch([]))->setMatchedRouteName('page-5'));
        $event->setParam(PageMetadata::class, new PageMetadata(
            'https://www.example.com/news/a-story',
            title: 'A story',
            type: PageMetadata::ARTICLE,
        ));

        static::assertStringContainsString(
            '"@type":"Article"',
            (new SeoFactory())($application->getServiceManager())()->head(),
        );
    }

    #[Test]
    public function findsTheHomePageByItsRoute(): void
    {
        $application = self::application('https://www.example.com/');
        $application->getMvcEvent()->setRouteMatch((new RouteMatch([]))->setMatchedRouteName('home'));

        $helper = (new SeoFactory())($application->getServiceManager());

        static::assertStringStartsWith('<script type="application/ld+json">', $helper()->head());
    }

    #[Test]
    public function givesTheGraphTheHeadWritesTo(): void
    {
        $application = self::application('https://www.example.com/');
        $helper      = (new SeoFactory())($application->getServiceManager())();
        $helper->graph()->append(['@type' => 'Thing']);

        static::assertStringContainsString('"@type":"Thing"', $helper->head(home: true));
    }

    #[Test]
    public function hidesACopyOnEveryResponse(): void
    {
        $application = self::application('https://staging.example.com/about');
        $event       = $application->getMvcEvent();
        $event->setName(MvcEvent::EVENT_FINISH);
        $application->getEventManager()->triggerEvent($event);

        $response = $application->getResponse();

        static::assertSame(
            'noindex, nofollow',
            $response instanceof Response ? $response->getHeaders()->get('X-Robots-Tag')?->getFieldValue() : null,
        );
    }

    #[Test]
    public function ignoresAnEventParameterThatIsNotPageMetadata(): void
    {
        $application = self::application('https://www.example.com/news/a-story');
        $event       = $application->getMvcEvent();
        $event->setRouteMatch((new RouteMatch([]))->setMatchedRouteName('page-5'));
        $event->setParam(PageMetadata::class, 'not metadata');

        static::assertStringNotContainsString(
            '"@type":"Article"',
            (new SeoFactory())($application->getServiceManager())()->head(),
        );
    }

    #[Test]
    public function namesBreadcrumbStepsFromTheSitesNavigation(): void
    {
        $application = self::application('https://www.example.com/stay/packages', records: true);
        $services    = $application->getServiceManager();
        $services->setService('cms', new Navigation([
            ['label' => 'Stay', 'uri' => '/stay', 'pages' => [['label' => 'Packages', 'uri' => '/stay/packages']]],
        ]));
        $application->getMvcEvent()->setRouteMatch((new RouteMatch([]))->setMatchedRouteName('page-7'));

        static::assertStringContainsString(
            '"name":"Stay","item":"https://www.example.com/stay"',
            (new SeoFactory())($services)()->head(),
        );
    }

    #[Test]
    public function usesTheRequestServiceWithoutAnApplication(): void
    {
        $request = new Request();
        $request->setUri('https://staging.example.com/');
        $services = new ServiceManager();
        $services->setService(SeoConfig::class, new SeoConfig(
            site: new SiteUrl('https://www.example.com'),
            visibility: Visibility::Automatic,
        ));
        $services->setService('Request', $request);
        $helpers = self::helpers($services);
        $services->setService('ViewHelperManager', $helpers);

        (new SeoFactory())($services)()->head(home: false);

        static::assertSame(
            '<meta name="robots" content="noindex, nofollow">',
            html_entity_decode($helpers->get('headMeta')->toString(), ENT_QUOTES | ENT_HTML5),
        );
    }

    #[Test]
    public function worksWithNeitherApplicationNorRequest(): void
    {
        $services = new ServiceManager();
        $services->setService(SeoConfig::class, new SeoConfig(
            site: new SiteUrl('https://www.example.com'),
            visibility: Visibility::Automatic,
        ));
        $services->setService('Application', 'not an application');
        $services->setService('Request', 'not a request');
        $helpers = self::helpers($services);
        $services->setService('ViewHelperManager', $helpers);

        (new SeoFactory())($services)()->head();

        static::assertSame('', $helpers->get('headMeta')->toString());
    }

    #[Test]
    public function writesABreadcrumbTrailForOtherRoutes(): void
    {
        $application = self::application('https://www.example.com/about');
        $application->getMvcEvent()->setRouteMatch((new RouteMatch([]))->setMatchedRouteName('about'));

        static::assertStringContainsString(
            '"@type":"BreadcrumbList"',
            (new SeoFactory())($application->getServiceManager())()->head(),
        );
    }
}
