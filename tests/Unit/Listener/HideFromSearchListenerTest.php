<?php

declare(strict_types=1);

namespace Contenir\Seo\Laminas\Mvc\Tests\Unit\Listener;

use Contenir\Seo\Laminas\Mvc\Listener\HideFromSearchListener;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\SiteUrl;
use Contenir\Seo\Visibility\Visibility;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\Test\EventListenerIntrospectionTrait;
use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\MvcEvent;
use Laminas\Stdlib\Request as StdlibRequest;
use Laminas\Stdlib\Response as StdlibResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function iterator_to_array;

#[Group('unit')]
final class HideFromSearchListenerTest extends TestCase
{
    use EventListenerIntrospectionTrait;

    private static function finish(string $url, ?Request $request = null): Response
    {
        $events = new EventManager();
        (new HideFromSearchListener(new SeoConfig(
            site: new SiteUrl('https://www.example.com'),
            visibility: Visibility::Automatic,
        )))->attach($events);

        $request ??= new Request();
        $request->setUri($url);
        $response = new Response();
        $event    = new MvcEvent(MvcEvent::EVENT_FINISH);
        $event->setRequest($request)->setResponse($response);
        $events->triggerEvent($event);

        return $response;
    }

    #[Test]
    public function hidesACopyThatIsNotAtTheSiteUrl(): void
    {
        $header = self::finish('https://staging.example.com/about')->getHeaders()->get('X-Robots-Tag');

        static::assertSame('noindex, nofollow', $header instanceof HeaderInterface ? $header->getFieldValue() : null);
    }

    #[Test]
    public function hidesWhenCalledDirectly(): void
    {
        $request = new Request();
        $request->setUri('https://staging.example.com/');
        $response = new Response();
        $event    = new MvcEvent(MvcEvent::EVENT_FINISH);
        $event->setRequest($request)->setResponse($response);

        (new HideFromSearchListener(new SeoConfig(visibility: Visibility::Hide)))->onFinish($event);

        static::assertTrue($response->getHeaders()->has('X-Robots-Tag'));
    }

    #[Test]
    public function ignoresARequestThatIsNotHttp(): void
    {
        $events = new EventManager();
        (new HideFromSearchListener(new SeoConfig(visibility: Visibility::Hide)))->attach($events);
        $response = new Response();
        $event    = new MvcEvent(MvcEvent::EVENT_FINISH);
        $event->setRequest(new StdlibRequest())->setResponse($response);
        $events->triggerEvent($event);

        static::assertFalse($response->getHeaders()->has('X-Robots-Tag'));
    }

    #[Test]
    public function ignoresAResponseThatIsNotHttp(): void
    {
        $events = new EventManager();
        (new HideFromSearchListener(new SeoConfig(visibility: Visibility::Hide)))->attach($events);
        $response = new StdlibResponse();
        $event    = new MvcEvent(MvcEvent::EVENT_FINISH);
        $event->setRequest(new Request())->setResponse($response);
        $events->triggerEvent($event);

        static::assertSame([], $response->getMetadata());
    }

    #[Test]
    public function leavesTheLiveSiteAlone(): void
    {
        static::assertFalse(self::finish('https://www.example.com/about')->getHeaders()->has('X-Robots-Tag'));
    }

    #[Test]
    public function listensWhenTheResponseIsFinishedAtTheDefaultPriority(): void
    {
        $events = new EventManager();
        (new HideFromSearchListener(new SeoConfig()))->attach($events);

        static::assertSame(
            [1],
            array_keys(iterator_to_array($this->getListenersForEvent(
                MvcEvent::EVENT_FINISH,
                $events,
                withPriority: true,
            ))),
        );
    }
}
