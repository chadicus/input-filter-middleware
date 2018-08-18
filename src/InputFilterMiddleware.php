<?php

namespace Chadicus\Psr\Middleware;

use ArrayAccess;
use Http\Message\StreamFactory as StreamFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use TraderInteractive\Filterer;

/**
 * Interface for all middleware.
 */
final class InputFilterMiddleware implements MiddlewareInterface
{
    /**
     * @var Filterer
     */
    private $inputFilterer;

    /**
     * The specification to apply to the input.
     *
     * @var array
     */
    private $filters;

    /**
     * Factory for creating message streams.
     *
     * @var StreamFactoryInterface;
     */
    private $streamFactory;

    /**
     * Create a new instance of the middleware.
     *
     * @param Filterer               $inputFilterer The filterer to use for input filtering.
     * @param array                  $filters       The specification to apply to the input.
     * @param StreamFactoryInterface $streamFactory Factory to create message stream upon filter error.
     */
    public function __construct(
        Filterer $inputFilterer,
        array $filters,
        StreamFactoryInterface $streamFactory
    ) {
        $this->inputFilterer = $inputFilterer;
        $this->filters = $filters;
        $this->streamFactory = $streamFactory;
    }

    /**
     * Execute this middleware.
     *
     * @param  ServerRequestInterface $request  The PSR7 request.
     * @param  ResponseInterface      $response The PSR7 response.
     * @param  callable               $next     The Next middleware.
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $input = $request->getQueryParams() + (array)$request->getParsedBody();
        list($success, $filteredInput, $error) = $this->inputFilterer->filter($this->filters, $input);
        if (!$success) {
            return $response->withStatus(400, 'Bad Request')->withBody($this->createStream($error));
        }

        return $next($request->withAttribute('filtered-input', $filteredInput), $response);
    }

    private function createStream(string $error) : StreamInterface
    {
        $json = json_encode(['error' => ['message' => $error]]);
        return $this->streamFactory->createStream($json);
    }
}
