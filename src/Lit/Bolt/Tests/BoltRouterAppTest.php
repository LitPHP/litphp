<?php

namespace Lit\Bolt\Tests;

use FastRoute\RouteCollector;
use Lit\Air\Configurator as C;
use Lit\Air\Factory;
use Lit\Bolt\BoltApp;
use Lit\Bolt\Router\StubResolveException;
use Lit\Nimo\Handlers\CallableHandler;
use Lit\Router\FastRoute\ArgumentHandler\RouteArgumentBag;
use Lit\Router\FastRoute\FastRouteConfiguration;
use Lit\Router\FastRoute\FastRouteRouter;
use Lit\Voltage\Interfaces\RouterInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Request\ArraySerializer;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;

class BoltRouterAppTest extends BoltTestCase
{

    public function testSmoke()
    {
        $response = new Response();

        /** @var ServerRequest $request */
        $request = (new ServerRequest())
            ->withUri(new Uri('http://localhost/book/42/author'));

        $handler = CallableHandler::wrap(function (ServerRequestInterface $actualRequest) use ($response, $request) {
            self::assertEquals(
                ArraySerializer::toArray($request),
                ArraySerializer::toArray($actualRequest)
            );
            self::assertSame('42', RouteArgumentBag::fromRequest($actualRequest)->get('id'));

            return $response;
        });

        $response404 = new Response();
        /** @var ServerRequest $request404 */
        $request404 = (new ServerRequest())
            ->withUri(new Uri('http://localhost/404'));

        $fooRequest = (new ServerRequest())
            ->withMethod('PUT')
            ->withUri(new Uri('http://localhost/foo'));

        $config = [
            C::join(FastRouteRouter::class, 'notFound') => $response404,
        ];
        $config += FastRouteConfiguration::default(function (RouteCollector $routeCollector) use ($handler) {
            $routeCollector->get('/book/{id:\d+}/author', $handler);
            $routeCollector->put('/foo', [VoidHandler::class]);
        });
        C::config($this->container, $config);

        $factory = Factory::of($this->container);
        /**
         * @var BoltApp $app
         */
        $app = $factory->produce(BoltApp::class);

        $result = $app->handle($request);
        self::assertSame($result, $response);


        $result404 = $app->handle($request404);
        self::assertSame($result404, $response404);

        $router = $this->container->get(C::join(BoltApp::class, 'handler', 'router'));
        self::assertInstanceOf(VoidHandler::class, $router->route($fooRequest));
    }

    public function testNotFound()
    {
        C::config($this->container, FastRouteConfiguration::default(function () {
        }));
        $factory = Factory::of($this->container);
        /** @var ServerRequest $request404 */
        $request404 = (new ServerRequest())
            ->withUri(new Uri('http://localhost/404'));

        /** @var FastRouteRouter $router */
        $router = $factory->produce(FastRouteRouter::class);
        try {
            $router->route($request404);
            self::fail('shoulld throw');
        } catch (StubResolveException $exception) {
            self::assertSame(404, $exception->getResponse()->getStatusCode());
            self::assertEquals(null, $exception->getStub());
        }
    }
}
