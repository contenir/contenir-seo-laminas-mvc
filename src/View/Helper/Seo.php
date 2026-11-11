<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\View\Helper;

use Contenir\Resource\Core\Metadata\PageMetadata;
use Contenir\Seo\Head\Article;
use Contenir\Seo\Head\Head;
use Contenir\Seo\Head\HeadBuilder;
use Contenir\Seo\Head\PageContext;
use Contenir\Seo\JsonLd\Graph;
use Contenir\Seo\Laminas\Mvc\PageArticle;
use Contenir\Seo\SeoConfig;
use JsonException;
use Laminas\Http\Request;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Exception\ExceptionInterface as ViewException;
use Laminas\View\Helper\HeadMeta;
use Laminas\View\Helper\HeadTitle;
use Laminas\View\Helper\Placeholder\Container\AbstractContainer;

use function implode;
use function in_array;

/**
 * View helper "seo": $this->seo()->head() in the layout's <head>, after the
 * page's own metadata (resourceMeta) is set and before headTitle() and
 * headMeta() are printed. It
 *
 * - builds the <title> from the site's template, replacing headTitle's;
 * - adds og:site_name, a robots meta tag, the verification tags on the
 *   home page, and the default description and share image to a page
 *   without its own, through headMeta, which escapes them;
 * - returns the page's JSON-LD as one script element, to print where it is
 *   called: the site's owner and the site on the home page, the article when
 *   the page is one, and the breadcrumb trail elsewhere. graph() gives the
 *   same graph, for a template to add its own nodes before it is printed.
 *
 * Open Graph tags use the "property" attribute, which headMeta only accepts
 * with an HTML5 or RDFa doctype.
 *
 * @api
 */
final class Seo
{
    private ?Head $head = null;

    public function __construct(
        private readonly SeoConfig $config,
        private readonly HeadTitle $headTitle,
        private readonly HeadMeta $headMeta,
        private readonly MvcEvent $event,
        private readonly Graph $graph = new Graph(),
    ) {}

    /**
     * The page's structured data, which head() adds its own nodes to.
     */
    public function graph(): Graph
    {
        return $this->graph;
    }

    /**
     * Applies the site's SEO to the head helpers, once per page, and
     * returns the page's JSON-LD script ('' when it has none).
     *
     * @param ?string  $nonce   A CSP nonce for the JSON-LD script
     * @param ?bool    $home    Whether this is the home page; null asks whether the matched route is "home"
     * @param ?Article $article The page as an article, when it is one; null takes it from the page metadata
     *                          contenir-resource-laminas-mvc's ResourceListener set on the event, if any
     *
     * @throws ViewException When the doctype does not allow a tag.
     * @throws JsonException Never in practice; see contenir/contenir-seo's JsonLd.
     */
    public function head(?string $nonce = null, ?bool $home = null, ?Article $article = null): string
    {
        if (null === $this->head) {
            $this->head = $this->apply(
                $home ?? 'home' === $this->event->getRouteMatch()?->getMatchedRouteName(),
                $article ?? $this->routedArticle(),
            );
            $this->graph->append(...$this->head->jsonLd);
        }

        return $this->graph->script($nonce);
    }

    /**
     * @throws ViewException When the doctype does not allow a tag.
     */
    private function apply(bool $home, ?Article $article): Head
    {
        $request = $this->event->getRequest();
        $head    = (new HeadBuilder($this->config))->build(new PageContext(
            $this->title(),
            $home,
            $request instanceof Request ? (string) $request->getUri()->getHost() : '',
            $this->hasMeta('description'),
            $this->hasMeta('og:image'),
            $request instanceof Request ? $request->getUri()->getPath() ?? '' : '',
            $article,
        ));

        if ('' !== $head->title) {
            $this->headTitle->__invoke($head->title, AbstractContainer::SET);
        }

        foreach ($head->tags as $tag) {
            'name' === $tag->attribute
                ? $this->headMeta->setName($tag->key, $tag->content)
                : $this->headMeta->setProperty($tag->key, $tag->content);
        }

        return $head;
    }

    /**
     * Whether headMeta already has a tag named $key, by name or property.
     */
    private function hasMeta(string $key): bool
    {
        foreach ($this->headMeta->getContainer()->getArrayCopy() as $item) {
            if (in_array($key, [$item->name ?? null, $item->property ?? null], strict: true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The routed resource's page as an article, when its metadata gives it a schema type or an article og:type.
     *
     * @mago-expect analysis:mixed-assignment Event parameters are untyped; the type is checked here.
     */
    private function routedArticle(): ?Article
    {
        $page = $this->event->getParam(PageMetadata::class);

        return $page instanceof PageMetadata ? PageArticle::of($page) : null;
    }

    /**
     * The page's own title, as headTitle holds it before the template.
     */
    private function title(): string
    {
        $container = $this->headTitle->getContainer();

        return implode($container->getSeparator(), $container->getArrayCopy());
    }

    public function __invoke(): self
    {
        return $this;
    }
}
