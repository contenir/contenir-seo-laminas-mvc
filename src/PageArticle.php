<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc;

use Contenir\Resource\Core\Metadata\PageMetadata;
use Contenir\Seo\Head\Article;

/**
 * A page's main JSON-LD node from its metadata: of the schema type the site's
 * section map gives it, or an Article when its og:type is article, and none
 * when it has neither or no title. Pass it to seo()->head() as the article.
 *
 * @api
 */
final class PageArticle
{
    public static function of(PageMetadata $page): ?Article
    {
        $type = $page->schema ?? (PageMetadata::ARTICLE === $page->type ? 'Article' : null);

        if (null === $type || null === $page->title) {
            return null;
        }

        return new Article(
            $page->url,
            $page->title,
            $page->description,
            $page->image,
            $page->published,
            $page->updated,
            $type,
        );
    }
}
