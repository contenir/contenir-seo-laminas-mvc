# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased] - 2026-10-21

The Laminas MVC adapter for [contenir/seo](https://github.com/contenir/contenir-seo). It replaces
contenir/contenir-sitemap-laminas-mvc (SEO-2), keeping its `sitemap` and `robots` routes and the fixes from its
2.2.0 release. See [UPGRADE.md](UPGRADE.md).

### Added

- `/sitemap.xml` from the `cms` navigation container, every page hidden from the menu or not, plus any
  `SitemapSourceInterface` services the site names in `contenir_seo.sitemap_sources`. Pages carry their `lastmod`,
  `images`, `robots` and `workflow` properties. Workflows the site leaves out, noindex pages and pages on other hosts
  are not listed.
- Past 50,000 URLs, `/sitemap.xml` is an index of `/sitemap-1.xml`, `/sitemap-2.xml` and so on (route
  `sitemap-page`).
- `/robots.txt` from the site's settings: the extra rules, and the `Sitemap:` line on the configured site URL.
- The `seo()` view helper. `$this->seo()->head()` builds the title from the site's template, and adds `og:site_name`,
  a robots meta tag, the verification tags on the home page, and the default description and share image to a page
  without its own. It returns the home page's JSON-LD.
- `X-Robots-Tag: noindex, nofollow` on every response of a copy that is hidden from search engines.
- The default share image is measured when it is a file under the public directory, for `og:image:width` and
  `og:image:height`.

### Kept from contenir-sitemap-laminas-mvc 2.2.0

- `settings.site.sitemap.enabled` is honoured: off, `/sitemap.xml` answers 404 and robots.txt names no sitemap.
- Links are built on `settings.site.url`, not the request's `Host` header, and fall back to the request only
  without a site URL.
- Pages hidden from the menu are listed, and `/.well-known/` isn't blocked.
