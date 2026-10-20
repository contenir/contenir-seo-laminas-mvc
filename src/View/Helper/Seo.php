<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\View\Helper;

use Contenir\Seo\Head\Head;
use Contenir\Seo\Head\HeadBuilder;
use Contenir\Seo\Head\PageContext;
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
 * - returns the home page's JSON-LD script, to print where it is called.
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
    ) {}

    /**
     * Applies the site's SEO to the head helpers, once per page, and
     * returns the JSON-LD script ('' except on the home page).
     *
     * @param ?string $nonce A CSP nonce for the JSON-LD script
     * @param ?bool   $home  Whether this is the home page; null asks whether the matched route is "home"
     *
     * @throws ViewException When the doctype does not allow a tag.
     * @throws JsonException Never in practice; see contenir/seo's JsonLd.
     */
    public function head(?string $nonce = null, ?bool $home = null): string
    {
        if (null === $this->head) {
            $this->head = $this->apply($home ?? 'home' === $this->event->getRouteMatch()?->getMatchedRouteName());
        }

        return $this->head->jsonLd?->script($nonce) ?? '';
    }

    /**
     * @throws ViewException When the doctype does not allow a tag.
     */
    private function apply(bool $home): Head
    {
        $request = $this->event->getRequest();
        $head    = (new HeadBuilder($this->config))->build(new PageContext(
            $this->title(),
            $home,
            $request instanceof Request ? (string) $request->getUri()->getHost() : '',
            $this->hasMeta('description'),
            $this->hasMeta('og:image'),
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
