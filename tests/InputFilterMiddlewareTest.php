<?php

namespace ChadicusTest\Psr\Middleware;

use Chadicus\Psr\Middleware\InputFilterMiddleware;
use Http\Message\StreamFactory;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Stream;

/**
 * Unit tests for the \Chadicus\Psr\Middleware\InputFilterMiddleware class.
 *
 * @coversDefaultClass \Chadicus\Psr\Middleware\InputFilterMiddleware
 * @covers ::<private>
 * @covers ::__construct
 */
final class InputFilterMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Verify behavior of __construct() when invalid $queryLocation is given.
     *
     * @test
     * @covers ::__construct
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $inputLocation must be "body" or "query"
     *
     * @return void
     */
    public function constructBadLocation()
    {
        new InputFilterMiddleware(new \ArrayObject, [], 'headers', $this->getStreamFactoryMock());
    }

    /**
     * Verify basic behavior of __invoke()
     *
     * @test
     * @covers ::__invoke
     *
     * @return void
     */
    public function invoke()
    {
        $container = new \ArrayObject();
        $filters = [
            'foo' => [['string']],
            'bar' => [['uint']],
            'boo' => [['bool']],
        ];

        $middleware = new InputFilterMiddleware($container, $filters, 'query', $this->getStreamFactoryMock());
        $request = new ServerRequest(
            [],
            [],
            null,
            'GET',
            'php://input',
            [],
            [],
            ['foo' => 'abc', 'bar' => 123, 'boo' => 'true']
        );
        $response = new Response();

        $next = function ($request, $response) {
            return $response;
        };

        $actual = $middleware($request, $response, $next);

        $this->assertSame($actual, $response);

        $this->assertSame(
            [
                'foo' => 'abc',
                'bar' => 123,
                'boo' => true,
            ],
            $container['input']
        );
    }

    /**
     * Verify behavior of __invoke() with $inputLocation of 'body'
     *
     * @test
     * @covers ::__invoke
     *
     * @return void
     */
    public function invokeInputInBody()
    {
        $container = new \ArrayObject();
        $filters = [
            'foo' => [['string']],
            'bar' => [['uint']],
            'boo' => [['bool']],
        ];

        $body = new \StdClass();
        $body->foo = 'abc';
        $body->bar = '123';
        $body->boo = 'true';

        $middleware = new InputFilterMiddleware($container, $filters, 'body', $this->getStreamFactoryMock());
        $request = new ServerRequest([], [], null, 'POST', 'php://input', [], [], [], $body);
        $response = new Response();
        $next = function ($request, $response) {
            return $response;
        };

        $actual = $middleware($request, $response, $next);
        $this->assertSame($actual, $response);
        $this->assertSame(
            [
                'foo' => 'abc',
                'bar' => 123,
                'boo' => true,
            ],
            $container['input']
        );
    }

    /**
     * Verify behavior of __invoke() when filtering fails.
     *
     * @test
     * @covers ::__invoke
     *
     * @return void
     *
     * @throws \Exception Thrown if middleware test fails.
     */
    public function invokeFilteringFail()
    {
        $container = new \ArrayObject();
        $filters = [
            'foo' => [['string']],
            'bar' => [['uint']],
            'boo' => [['bool']],
        ];

        $middleware = new InputFilterMiddleware($container, $filters, 'query', $this->getStreamFactoryMock());
        $request = new ServerRequest(
            [],
            [],
            null,
            'GET',
            'php://input',
            [],
            [],
            ['foo' => 'abc', 'bar' => '123', 'boo' => 'not boolean']
        );
        $response = new Response();

        $next = function ($request, $response) {
            throw new \Exception('This should not be executed');
        };

        $actual = $middleware($request, $response, $next);

        $this->assertSame(400, $actual->getStatusCode());

        $this->assertContains("Field 'boo' with value 'not boolean' failed filtering", (string)$actual->getBody());
    }

    private function getStreamFactoryMock() : StreamFactory
    {
        $createStream = function ($contents) {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $contents);
            rewind($stream);
            return new Stream($stream);
        };
        $factory = $this->getMockBuilder(StreamFactory::class)->getMock();
        $factory->method('createStream')->will($this->returnCallback($createStream));

        return $factory;
    }
}
