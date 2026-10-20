<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\Integration;

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
    private static function application(string $url): Application
    {
        $request = new Request();
        $request->setUri($url);

        /** @var array{service_manager: array<string, mixed>} $config */
        $config   = (new Module())->getConfig();
        $services = new ServiceManager($config['service_manager']);
        $services->setService(SeoConfig::class, new SeoConfig(
            site: new SiteUrl('https://www.example.com'),
            visibility: Visibility::Automatic,
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
    public function findsTheHomePageByItsRoute(): void
    {
        $application = self::application('https://www.example.com/');
        $application->getMvcEvent()->setRouteMatch((new RouteMatch([]))->setMatchedRouteName('home'));

        $helper = (new SeoFactory())($application->getServiceManager());

        static::assertStringStartsWith('<script type="application/ld+json">', $helper()->head());
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
    public function treatsOtherRoutesAsOtherPages(): void
    {
        $application = self::application('https://www.example.com/about');
        $application->getMvcEvent()->setRouteMatch((new RouteMatch([]))->setMatchedRouteName('about'));

        static::assertSame('', (new SeoFactory())($application->getServiceManager())()->head());
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
}
