<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Factory;

use Contenir\Seo\Laminas\Mvc\EnabledAddOns;
use Contenir\Seo\Laminas\Mvc\ShareImageSize;
use Contenir\Seo\SeoConfig;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function is_array;
use function is_string;

/**
 * Builds the site's SeoConfig from its settings (config['settings'], as the
 * CMS writes them). Without the SEO add-on enabled, only the core settings
 * are read, so the site keeps today's sitemap and robots.txt and nothing is
 * hidden. contenir_seo in the config can change where things are:
 *
 *     'contenir_seo' => [
 *         'extensions_config' => 'config/autoload', // where extensions.global.php and .local.php are
 *         'public_path'       => 'public',          // the document root, to measure the share image
 *     ],
 *
 * @api
 */
final class SeoConfigFactory
{
    public const string HANDLE = 'seo';

    public const string EXTENSIONS_CONFIG = 'config/autoload';

    public const string PUBLIC_PATH = 'public';

    /**
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment The application's config is untyped; each part is checked here.
     */
    public function __invoke(ContainerInterface $container): SeoConfig
    {
        $config   = $container->has('config') ? $container->get('config') : [];
        $config   = is_array($config) ? $config : [];
        $settings = is_array($config['settings'] ?? null) ? $config['settings'] : [];
        $options  = is_array($config['contenir_seo'] ?? null) ? $config['contenir_seo'] : [];

        $directory = $options['extensions_config'] ?? self::EXTENSIONS_CONFIG;
        $enabled   = EnabledAddOns::fromDirectory(is_string($directory) ? $directory : self::EXTENSIONS_CONFIG);
        $public    = $options['public_path'] ?? self::PUBLIC_PATH;

        return (new ShareImageSize(is_string($public) ? $public : self::PUBLIC_PATH))->measure(
            $enabled->has(self::HANDLE) ? SeoConfig::fromSettings($settings) : SeoConfig::fromCoreSettings($settings),
        );
    }
}
