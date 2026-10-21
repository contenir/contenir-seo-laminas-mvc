# Moving from contenir/contenir-sitemap-laminas-mvc

contenir/contenir-seo-laminas-mvc replaces contenir/contenir-sitemap-laminas-mvc (formerly contenir/contenir-mvc-sitemap). The
two can't be installed together.

1. Swap the packages:

   ```bash
   composer remove contenir/contenir-sitemap-laminas-mvc contenir/contenir-mvc-sitemap
   composer require contenir/contenir-seo-laminas-mvc
   ```

2. In `config/modules.config.php`, replace `Contenir\Mvc\Sitemap` with `Contenir\Seo\Laminas\Mvc`.

3. Nothing else is needed for the sitemap and robots.txt. The `sitemap` and `robots` routes keep their names and
   paths, and the sitemap is still built from the `cms` navigation container. What changes:

   | Before | Now |
   |---|---|
   | `Contenir\Mvc\Sitemap\SitemapController` | `Contenir\Seo\Laminas\Mvc\Controller\SitemapController` |
   | laminas-view's sitemap helper, with `changefreq` and `priority` | contenir/contenir-seo's writer: `lastmod` and images, no `changefreq` or `priority` |
   | one file, however large | an index of numbered pages past 50,000 URLs |
   | a `home` route needed for robots.txt | not needed |

4. To use the SEO add-on's settings for the head, add `seo()->head()` to the layout and remove any hard-coded
   " | Site" suffix (`headTitle()->append()`, `setPostfix()` or `setSeparator()` for the site's name). See the
   [README](README.md#the-head).

Until the SEO add-on is enabled in `config/autoload/extensions.global.php`, the module reads only the core settings
(`site.url`, `site.title` and `site.sitemap.enabled`): the sitemap and robots.txt behave as they did, nothing is hidden,
and `seo()->head()` adds only `og:site_name`.
