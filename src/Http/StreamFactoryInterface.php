<?php

namespace Chadicus\Psr\Http;

interface StreamFactoryInterface
{
    /**
     * Create a new StreamInterface with the given string $contents.
     *
     * @param string $contents The string contents for the stream.
     *
     * @return \Psr\Http\Message\StreamInterface
     */
    public function make($contents);
}
