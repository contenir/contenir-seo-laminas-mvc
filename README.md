# contenir/seo-laminas-mvc

[![Continuous Integration](https://github.com/contenir/contenir-seo-laminas-mvc/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-seo-laminas-mvc/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-seo-laminas-mvc/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-seo-laminas-mvc)

The Laminas MVC adapter for [contenir/seo](https://github.com/contenir/contenir-seo). It serves a site's
`/sitemap.xml` and `/robots.txt`, adds an `seo()` view helper for the head, and hides staging and local copies from
search engines.

The settings are set in the CMS, by the [SEO add-on](https://github.com/contenir-extension/seo). They are read from
the site's `config['settings']`, as the CMS writes them, so there is nothing to configure on the site.

It replaces contenir/contenir-sitemap-laminas-mvc and keeps its routes. See [UPGRADE.md](UPGRADE.md).

## Requirements

- PHP 8.3, 8.4 or 8.5
- laminas-mvc 3.8+, laminas-router 3.13+, laminas-view 2.35+ and laminas-navigation 2.19+

## Install

```bash
composer require contenir/seo-laminas-mvc
```

Add `Contenir\Seo\Laminas\Mvc` to the application's modules (laminas-component-installer offers to).

## Routes

| Route | Path | Response |
|---|---|---|
| `sitemap` | `/sitemap.xml` | the sitemap, or past 50,000 URLs an index of its pages |
| `sitemap-page` | `/sitemap-1.xml`, `/sitemap-2.xml`… | one page of an index |
| `robots` | `/robots.txt` | `text/plain`, built from the settings |

With the sitemap switched off in the CMS (`site.sitemap.enabled`), the sitemap's URLs answer 404 through laminas-mvc's
`notFoundAction()` and robots.txt names no sitemap.

Links are built on the CMS's site URL (`site.url`). Only without one do they fall back to the request's scheme and
`Host` header, which a client can spoof: set the site URL on every live site.

## The sitemap

The sitemap lists every page of the `cms` navigation container, including pages hidden from the menu, and then the
pages of each `SitemapSourceInterface` service named in `contenir_seo.sitemap_sources`:

```php
return [
    'contenir_seo' => ['sitemap_sources' => [App\Sitemap\NewsArticles::class]],
];
```

```php
final class NewsArticles implements Contenir\Seo\Laminas\Mvc\SitemapSourceInterface
{
    public function pages(): iterable
    {
        foreach ($this->articles->published() as $article) {
            yield new Contenir\Seo\Sitemap\Page($article->url(), $article->updated, [$article->imageUrl()]);
        }
    }
}
```

A navigation page can carry these properties:

| Property | Holds |
|---|---|
| `lastmod` | When its record last changed: a date string, as contenir-workflow-laminas-mvc sets it, or a `DateTimeInterface` |
| `images` | Its images, as URLs or paths on the site: a list, or one string |
| `robots` | Its robots value; `noindex` leaves it out |
| `workflow` | Its workflow (`page`, `news`, `redirect`…); the SEO add-on can leave chosen workflows out |

Pages on another host (a menu link to a shop elsewhere) and fragments are left out, and each location is listed once.
Which pages are in the navigation container at all is up to whatever builds it: inactive, unpublished or expired
pages must be left out there.

## robots.txt

```text
# robots.txt for https://www.example.com/

User-agent: *
Allow: /

Disallow: /search

Sitemap: https://www.example.com/sitemap.xml
```

The middle block is the extra rules from the SEO add-on. A copy of the site that is hidden from search engines gets
`Disallow: /` instead, and no sitemap line.

## The head

In the layout, call `seo()->head()` inside `<head>`, after the page has set its own title and metadata (for example
with contenir-resource's `resourceMeta()`) and before `headTitle()` and `headMeta()` are printed:

```php
<head>
    <meta charset="utf-8">
    <?= $this->seo()->head($nonce) ?>
    <?= $this->headTitle() ?>
    <?= $this->headMeta() ?>
```

`head()`:

- builds the title from the site's template ("About us | Example"), replacing headTitle's. Remove any hard-coded
  " | Site" suffix from the layout;
- adds `og:site_name`, a robots meta tag when the page shouldn't be indexed, and on the home page the verification
  tags for Google, Bing, Pinterest and Meta;
- adds the default description, and the default share image with its alt text and size, to a page without its own;
- returns the home page's JSON-LD (Organization and WebSite) as a script element, with `$nonce` when given.

The home page is the page whose matched route is `home`; pass `home: true` or `false` to say otherwise. Calling
`head()` again on the same page changes nothing more. Open Graph tags use the `property` attribute, which `headMeta`
only accepts with an HTML5 or RDFa doctype: set `view_manager.doctype` to `HTML5`.

## Hiding a copy of the site

The SEO add-on's "Search engines" choice for this environment is kept in its local settings. Automatic, the default,
hides every request whose host isn't the site URL's. A hidden copy sends `X-Robots-Tag: noindex, nofollow` on every
response, writes a `noindex, nofollow` robots meta tag, and its robots.txt disallows everything.

## Without the SEO add-on

The settings are read only when the SEO add-on is enabled in `config/autoload/extensions.global.php` or
`extensions.local.php` (a list in the local file replaces the global one, as the CMS reads it). Until then, only
`site.url`, `site.title` and `site.sitemap.enabled` are read, so a site keeps today's sitemap and robots.txt and
nothing is hidden.

## Configuration

Nothing is needed. To change the defaults:

```php
return [
    'contenir_seo' => [
        'extensions_config' => 'config/autoload', // where extensions.global.php and .local.php are
        'public_path'       => 'public',          // the document root, to measure the share image
        'sitemap_sources'   => [],                // services adding pages to the sitemap
    ],
];
```

With laminas-mvc's configuration cache on (`config_cache_enabled`), clear it after the CMS saves settings.

## Development

The QA toolchain is [contenir/contenir-qa-tools](https://github.com/contenir/contenir-qa-tools).

```bash
composer check             # everything below
composer cs-check          # mago format --check && mago lint
composer static-analysis   # mago analyze
composer test              # unit suite
composer test-integration  # integration suite: real router, view helpers, application and files
composer mutation-test     # Infection
```

## Licence

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
