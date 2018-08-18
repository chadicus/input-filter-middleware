<?php

namespace ChadicusTest\Psr\Middleware;

use Chadicus\Psr\Middleware\InputFilterMiddleware;
use Http\Message\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use TraderInteractive\Filterer;
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
final class InputFilterMiddlewareTest extends TestCase
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
        new InputFilterMiddleware(new Filterer(), [], 'headers', $this->getStreamFactoryMock());
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
        $filters = [
            'foo' => [['string']],
            'bar' => [['uint']],
            'boo' => [['bool']],
        ];

        $middleware = new InputFilterMiddleware(new Filterer(), $filters, 'query', $this->getStreamFactoryMock());
        $request = (new ServerRequest())->withQueryParams(['foo' => 'abc', 'bar' => 123, 'boo' => 'true']);
        $response = new Response();

        $test = $this;
        $next = function ($request, $response) use ($test) {
            $test->assertSame(
                ['foo' => 'abc', 'bar' => 123, 'boo' => true ],
                $request->getAttribute('filtered-input')
            );
            return $response;
        };

        $actual = $middleware($request, $response, $next);
        $this->assertSame($actual, $response);
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
        $filters = [
            'foo' => [['string']],
            'bar' => [['uint']],
            'boo' => [['bool']],
        ];

        $body = [
            'foo' => 'abc',
            'bar' => '123',
            'boo' => 'true',
        ];

        $middleware = new InputFilterMiddleware(new Filterer(), $filters, 'body', $this->getStreamFactoryMock());
        $request = (new ServerRequest())->withParsedBody($body)->withMethod('POST');
        $response = new Response();
        $test = $this;
        $next = function ($request, $response) use ($test) {
            $test->assertSame(
                ['foo' => 'abc', 'bar' => 123, 'boo' => true ],
                $request->getAttribute('filtered-input')
            );
            return $response;
        };

        $actual = $middleware($request, $response, $next);
        $this->assertSame($actual, $response);
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
        $filters = [
            'foo' => [['string']],
            'bar' => [['uint']],
            'boo' => [['bool']],
        ];

        $middleware = new InputFilterMiddleware(new Filterer(), $filters, 'query', $this->getStreamFactoryMock());
        $request = (new ServerRequest())->withQueryParams(['foo' => 'abc', 'bar' => '123', 'boo' => 'not boolean']);
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
