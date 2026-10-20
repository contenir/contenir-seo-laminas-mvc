<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\Integration;

use Contenir\Seo\Laminas\Mvc\Controller\SitemapController;
use Contenir\Seo\Laminas\Mvc\Factory\SitemapControllerFactory;
use Contenir\Seo\Laminas\Mvc\Sitemap\NavigationPages;
use Contenir\Seo\Laminas\Mvc\Tests\TestAsset\ArticleSource;
use Contenir\Seo\Laminas\Mvc\Tests\Trait\SitemapApplicationTrait;
use Contenir\Seo\Robots\RobotsRules;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\Sitemap\SitemapSettings;
use Contenir\Seo\SiteUrl;
use Contenir\Seo\Visibility\Visibility;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Exception\DomainException;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\RouteMatch;
use Laminas\Stdlib\Request as StdlibRequest;
use Laminas\Stdlib\Response as StdlibResponse;
use Laminas\View\Model\ViewModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function get_debug_type;
use function str_contains;

#[Group('integration')]
#[Group('controller')]
final class SitemapControllerTest extends TestCase
{
    use SitemapApplicationTrait;

    private const string SITEMAP = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
          <url>
            <loc>https://www.example.com/</loc>
            <lastmod>2026-10-20T08:00:00+00:00</lastmod>
          </url>
          <url>
            <loc>https://www.example.com/about</loc>
            <image:image>
              <image:loc>https://www.example.com/img/team.jpg</image:loc>
            </image:image>
          </url>
          <url>
            <loc>https://www.example.com/about/team</loc>
          </url>
          <url>
            <loc>https://www.example.com/news/hello</loc>
            <lastmod>2026-10-01T00:00:00+00:00</lastmod>
          </url>
        </urlset>

        XML;

    /**
     * @return array<string, array{bool, string}>
     */
    public static function indexPagesNotServed(): array
    {
        return [
            'page 0'             => [true, '0'],
            'a disabled sitemap' => [false, '1'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingPages(): array
    {
        return [
            'one file, page 1'   => ['https://www.example.com/sitemap-1.xml'],
            'past the last page' => ['https://www.example.com/sitemap-9.xml'],
        ];
    }

    private static function config(
        bool $enabled = true,
        Visibility $visibility = Visibility::Automatic,
        string $siteUrl = 'https://www.example.com',
    ): SeoConfig {
        return new SeoConfig(
            site: new SiteUrl($siteUrl),
            visibility: $visibility,
            sitemap: new SitemapSettings($enabled, ['redirect']),
            robotsRules: RobotsRules::parse('Disallow: /search'),
        );
    }

    private static function content(mixed $result): mixed
    {
        return $result instanceof Response ? $result->getContent() : $result;
    }

    private static function response(MvcEvent $event): Response
    {
        $response = $event->getResponse();
        self::assertInstanceOf(Response::class, $response);

        return $response;
    }

    #[DataProvider('indexPagesNotServed')]
    #[Test]
    public function answersNotFoundForAnIndexPageItDoesNotServe(bool $enabled, string $number): void
    {
        $event = new MvcEvent();
        $event->setRouteMatch(new RouteMatch(['action' => 'page', 'page' => $number]));
        $request = new Request();
        $request->setUri('https://www.example.com/sitemap-x.xml');
        $controller = new SitemapController(
            self::config(enabled: $enabled),
            [new NavigationPages(self::navigation())],
            size: 2,
        );
        $controller->setEvent($event);
        $controller->dispatch($request, new Response());

        static::assertSame(404, self::response($event)->getStatusCode());
    }

    #[Test]
    public function answersNotFoundForAPageWithoutANumber(): void
    {
        $event = new MvcEvent();
        $event->setRouteMatch(new RouteMatch(['action' => 'page']));
        $controller = new SitemapController(self::config());
        $controller->setEvent($event);
        $controller->dispatch(new Request(), new Response());

        static::assertSame([404, ViewModel::class], [
            self::response($event)->getStatusCode(),
            get_debug_type($event->getResult()),
        ]);
    }

    #[DataProvider('missingPages')]
    #[Test]
    public function answersNotFoundForPagesTheSitemapDoesNotHave(string $url): void
    {
        static::assertSame(404, self::response($this->dispatch($url, self::config()))->getStatusCode());
    }

    #[Test]
    public function answersNotFoundForTheSitemapWhenItIsDisabled(): void
    {
        $sitemap = $this->dispatch('https://www.example.com/sitemap.xml', self::config(enabled: false));
        $page    = $this->dispatch('https://www.example.com/sitemap-1.xml', self::config(enabled: false));

        static::assertSame(
            [404, '', ViewModel::class, 404],
            [
                self::response($sitemap)->getStatusCode(),
                self::response($sitemap)->getContent(),
                get_debug_type($sitemap->getResult()),
                self::response($page)->getStatusCode(),
            ],
        );
    }

    #[Test]
    public function buildsLinksOnTheRequestWithoutASiteUrl(): void
    {
        $response = self::response($this->dispatch('http://shop.example.org/robots.txt', self::config(siteUrl: '')));

        static::assertSame(
            "# robots.txt for http://shop.example.org/\n\nUser-agent: *\nAllow: /\n\nDisallow: /search\n\n"
                . "Sitemap: http://shop.example.org/sitemap.xml\n",
            $response->getContent(),
        );
    }

    #[Test]
    public function hidesACopyInItsRobotsTxt(): void
    {
        $response = self::response($this->dispatch('https://staging.example.com/robots.txt', self::config()));

        static::assertSame(
            "# robots.txt for https://www.example.com/\n\n# This copy of the site is hidden from search engines.\n"
                . "User-agent: *\nDisallow: /\n",
            $response->getContent(),
        );
    }

    #[Test]
    public function leavesTheSitemapOutOfRobotsTxtWhenItIsDisabled(): void
    {
        $response = self::response($this->dispatch(
            'https://www.example.com/robots.txt',
            self::config(
                enabled: false,
                visibility: Visibility::Show,
            ),
        ));

        static::assertSame(
            "# robots.txt for https://www.example.com/\n\nUser-agent: *\nAllow: /\n\nDisallow: /search\n",
            $response->getContent(),
        );
    }

    #[Test]
    public function listsThePagesOfANavigationAndTheSitesSources(): void
    {
        $response = self::response($this->dispatch('https://www.example.com/sitemap.xml', self::config()));

        static::assertSame(
            [200, 'application/xml; charset=utf-8', self::SITEMAP],
            [
                $response->getStatusCode(),
                $response->getHeaders()->get('Content-Type')?->getFieldValue(),
                $response->getContent(),
            ],
        );
    }

    #[Test]
    public function refusesARequestThatIsNotHttp(): void
    {
        $event = new MvcEvent();
        $event->setRouteMatch(new RouteMatch(['action' => 'robots']));
        $controller = new SitemapController(self::config(siteUrl: ''));
        $controller->setEvent($event);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The sitemap controller needs an HTTP request');

        $controller->dispatch(new StdlibRequest(), new Response());
    }

    #[Test]
    public function refusesAResponseThatIsNotHttp(): void
    {
        $event = new MvcEvent();
        $event->setRouteMatch(new RouteMatch(['action' => 'index']));
        $controller = new SitemapController(self::config());
        $controller->setEvent($event);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The sitemap controller needs an HTTP response');

        $controller->dispatch(new Request(), new StdlibResponse());
    }

    #[Test]
    public function servesRobotsTxtWithTheSitemapOnTheSiteUrl(): void
    {
        $response = self::response($this->dispatch('https://www.example.com/robots.txt', self::config()));

        static::assertSame(
            [
                'text/plain; charset=utf-8',
                "# robots.txt for https://www.example.com/\n\nUser-agent: *\nAllow: /\n\nDisallow: /search\n\n"
                    . "Sitemap: https://www.example.com/sitemap.xml\n",
            ],
            [$response->getHeaders()->get('Content-Type')?->getFieldValue(), $response->getContent()],
        );
    }

    #[Test]
    public function servesTheFirstPageOfAnIndex(): void
    {
        $controller = new SitemapController(self::config(), [new NavigationPages(self::navigation())], size: 2);

        $response = self::response($this->dispatchTo($controller, 'https://www.example.com/sitemap-1.xml'));

        static::assertSame(
            [true, $response->getContent()],
            [
                str_contains((string) $response->getContent(), '<loc>https://www.example.com/about</loc>'),
                self::content($controller->pageAction()),
            ],
        );
    }

    #[Test]
    public function splitsALargeSitemapIntoNumberedPages(): void
    {
        $config = self::config();
        $pages  = (new SitemapControllerFactory())(self::services($config));
        $sized  = static fn(): SitemapController => new SitemapController(
            $config,
            [new NavigationPages(self::navigation()), new ArticleSource([])],
            size: 2,
        );

        $index = self::response($this->dispatchTo($sized(), 'https://www.example.com/sitemap.xml'));
        $page  = self::response($this->dispatchTo($sized(), 'https://www.example.com/sitemap-2.xml'));

        static::assertSame(
            [
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                      <sitemap>
                        <loc>https://www.example.com/sitemap-1.xml</loc>
                        <lastmod>2026-10-20T08:00:00+00:00</lastmod>
                      </sitemap>
                      <sitemap>
                        <loc>https://www.example.com/sitemap-2.xml</loc>
                      </sitemap>
                    </sitemapindex>

                    XML,
                <<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
                      <url>
                        <loc>https://www.example.com/about/team</loc>
                      </url>
                    </urlset>

                    XML,
                SitemapController::class,
            ],
            [$index->getContent(), $page->getContent(), $pages::class],
        );
    }

    #[Test]
    public function writesRobotsTxtWhenCalledDirectly(): void
    {
        $request = new Request();
        $request->setUri('https://www.example.com/robots.txt');
        $event = new MvcEvent();
        $event->setRouteMatch(new RouteMatch(['action' => 'robots']));
        $controller = new SitemapController(self::config(enabled: false));
        $controller->setEvent($event);
        $controller->dispatch($request, new Response());

        static::assertSame(
            "# robots.txt for https://www.example.com/\n\nUser-agent: *\nAllow: /\n\nDisallow: /search\n",
            $controller->robotsAction()->getContent(),
        );
    }
}
