<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Controller;

use Contenir\Seo\Laminas\Mvc\SitemapSourceInterface;
use Contenir\Seo\Robots\RobotsTxt;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\Sitemap\Sitemap;
use Contenir\Seo\Sitemap\SitemapFilter;
use Contenir\Seo\Sitemap\SitemapWriter;
use Contenir\Seo\SiteUrl;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Exception\DomainException;
use Laminas\Mvc\Exception\RuntimeException as MvcRuntimeException;
use Laminas\View\Model\ViewModel;
use Override;

use function is_numeric;
use function sprintf;

/**
 * Serves /sitemap.xml, its numbered pages once it is an index, and
 * /robots.txt, from the site's SEO settings. Links are built on the
 * configured site URL, or on the request's origin when there is none. With
 * the sitemap disabled, its URLs are not found and robots.txt does not name
 * it.
 *
 * @api
 */
final class SitemapController extends AbstractActionController
{
    /**
     * The navigation container service the sitemap is built from.
     */
    public const string CONTAINER = 'cms';

    /**
     * Where each page of a sitemap index is, on the site's origin.
     */
    public const string PAGE_PATH = '/sitemap-%d.xml';

    /**
     * @param list<SitemapSourceInterface> $sources Where the sitemap's pages come from
     * @param positive-int                 $size    The most URLs in one file
     */
    public function __construct(
        private readonly SeoConfig $config,
        private readonly array $sources = [],
        private readonly int $size = Sitemap::MAX_URLS,
    ) {}

    /**
     * @return Response|ViewModel The sitemap or its index, or laminas-mvc's not-found model when it is disabled.
     *
     * @throws DomainException when not dispatched over HTTP.
     *
     * @mago-expect analysis:incompatible-return-type laminas-mvc returns any action result as is; the parent's
     *                                                ViewModel return type is docblock-only.
     */
    #[Override]
    public function indexAction(): Response|ViewModel
    {
        if (! $this->config->sitemap->enabled) {
            return $this->notFoundAction();
        }

        $site    = $this->site();
        $sitemap = $this->sitemap($site);
        $writer  = new SitemapWriter();

        return $this->xml(
            $sitemap->isIndex()
                ? $writer->index($sitemap->references($site->origin . self::PAGE_PATH))
                : $writer->urlset($sitemap->page(1)),
        );
    }

    /**
     * One page of a sitemap index, by the route's "page" parameter.
     *
     * @return Response|ViewModel The page, or laminas-mvc's not-found model when there is no such page.
     *
     * @throws DomainException when not dispatched over HTTP.
     *
     * @mago-expect analysis:mixed-assignment Route parameters are untyped; the value is checked here.
     */
    public function pageAction(): Response|ViewModel
    {
        $number = $this->getEvent()->getRouteMatch()?->getParam('page');
        if (! $this->config->sitemap->enabled || ! is_numeric($number)) {
            return $this->notFoundAction();
        }

        $sitemap = $this->sitemap($this->site());
        $number  = (int) $number;
        if (! $sitemap->isIndex() || $number < 1 || $number > $sitemap->pageCount()) {
            return $this->notFoundAction();
        }

        return $this->xml((new SitemapWriter())->urlset($sitemap->page($number)));
    }

    /**
     * @throws DomainException when not dispatched over HTTP.
     * @throws MvcRuntimeException when the "sitemap" route is missing.
     */
    public function robotsAction(): Response
    {
        $site    = $this->site();
        $sitemap = $this->config->sitemap->enabled ? $site->origin . $this->url()->fromRoute('sitemap') : null;
        $robots  = new RobotsTxt(
            $site->origin,
            $this->config->visibility->hides($this->config->site, $this->host()),
            $sitemap,
            $this->config->robotsRules,
        );

        $response = $this->httpResponse('text/plain; charset=utf-8');
        $response->setContent($robots->render());

        return $response;
    }

    /**
     * @throws DomainException when not dispatched over HTTP.
     */
    private function host(): string
    {
        return (string) $this->httpRequest()->getUri()->getHost();
    }

    /**
     * @throws DomainException when not dispatched over HTTP.
     */
    private function httpRequest(): Request
    {
        $request = $this->getRequest();
        if (! $request instanceof Request) {
            throw new DomainException('The sitemap controller needs an HTTP request');
        }

        return $request;
    }

    /**
     * @throws DomainException when not dispatched over HTTP.
     */
    private function httpResponse(string $contentType): Response
    {
        $response = $this->getResponse();
        if (! $response instanceof Response) {
            throw new DomainException('The sitemap controller needs an HTTP response');
        }

        $response->getHeaders()->addHeaderLine('Content-Type', $contentType);

        return $response;
    }

    /**
     * The configured site URL, or else the request's scheme and host, which
     * a client can spoof: set the site URL on every live site.
     *
     * @throws DomainException when not dispatched over HTTP.
     */
    private function site(): SiteUrl
    {
        if ($this->config->site->isSet()) {
            return $this->config->site;
        }

        $uri = $this->httpRequest()->getUri();

        return new SiteUrl(sprintf('%s://%s', (string) $uri->getScheme(), (string) $uri->getHost()));
    }

    private function sitemap(SiteUrl $site): Sitemap
    {
        $pages = [];
        foreach ($this->sources as $source) {
            foreach ($source->pages() as $page) {
                $pages[] = $page;
            }
        }

        return new Sitemap(
            $pages,
            new SitemapFilter($site, $this->config->sitemap, $this->config->robots),
            $this->size,
        );
    }

    /**
     * @throws DomainException when not dispatched over HTTP.
     */
    private function xml(string $xml): Response
    {
        $response = $this->httpResponse('application/xml; charset=utf-8');
        $response->setContent($xml);

        return $response;
    }
}
