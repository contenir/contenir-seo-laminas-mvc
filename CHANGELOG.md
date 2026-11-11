# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- With the SEO add-on's records switch on, a page's breadcrumb trail names each step as the site's `cms` navigation
  names it, and leaves out a step that is no page of the site: a `/project` that has no page of its own is no longer
  linked. `NavigationPages::names()` gives the labels by address, and `RoutedPageNames` applies the switch and falls
  back to naming each step from its path where the navigation cannot be read. With the switch off, or no `cms`
  navigation, steps are named from the path as before.

## [0.3.0] - 2026-11-11

### Changed

- `seo()->head()` takes the page as an article from the page metadata contenir-resource-laminas-mvc's
  `ResourceListener` sets on the MvcEvent, where it isn't given one: a page whose metadata has a schema type, such as
  the one its `<seo>` block names, or an article og:type, adds its node to the graph. An `Article` passed to `head()`
  wins.

## [0.2.0] - 2026-11-11

### Changed

- Requires contenir/contenir-seo `^0.9`. `seo()->head()` now writes the page's structured data as one JSON-LD graph:
  the site's owner (from Office details) and the site on the home page, and a breadcrumb trail on every other page,
  named from its path. `head()` takes the page as an `Article` as its third argument, which adds the article to the
  graph, and `seo()->graph()` gives the graph for a template to add its own nodes before `head()` prints it.

### Added

- A record's page defaults from the `<seo>` block its type has in the site's `config/admin.xml`, read from the record's
  own columns, the records it links to and its Section field, with the record's own overrides from its advanced
  column. `Sections\SectionPageDefaults` is offered as `PageDefaultsInterface`, which contenir-resource's
  `PageMetadataBuilder` uses, so `resourceMeta()` writes them. Settings in the `contenir_seo` configuration: `admin_xml`
  (default `config/admin.xml`), `section_map_cache` (default `data/cache/contenir-seo-section-map.cache`, `false` to
  turn it off) and `asset_entity`, the entity a site's assets are mapped with, to let a record's Section images stand in
  for a share image. What the blocks drive beyond the title, description and share image waits for the SEO add-on's
  records switch.
- `PageArticle::of()` gives a page's metadata as the `Article` that `seo()->head()` takes.

### Requires

- contenir/contenir-resource `^2.0.0-RC6` and contenir/contenir-db-model `^2.0.0-rc6`.

## [0.1.0] - 2026-10-21

The Laminas MVC adapter for [contenir/contenir-seo](https://github.com/contenir/contenir-seo). It replaces
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

### Changed

- The package is named `contenir/contenir-seo-laminas-mvc`, after its repository, as Contenir's other libraries are.
  Namespaces are unchanged.
- Requires the library as contenir/contenir-seo `^0.1`, its new name, where it required contenir/seo `0.1.x-dev`. The
  library was renamed and its v0.1.0 recut, so the old name no longer installs. It comes through a `vcs` repository
  entry until it is on Packagist.

### Kept from contenir-sitemap-laminas-mvc 2.2.0

- `settings.site.sitemap.enabled` is honoured: off, `/sitemap.xml` answers 404 and robots.txt names no sitemap.
- Links are built on `settings.site.url`, not the request's `Host` header, and fall back to the request only
  without a site URL.
- Pages hidden from the menu are listed, and `/.well-known/` isn't blocked.
