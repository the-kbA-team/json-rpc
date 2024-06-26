<?php

namespace JsonRPC\Request;

/**
 * Class BatchRequestParser
 *
 * @package JsonRPC\Request
 * @author  Frederic Guillot
 */
class BatchRequestParser extends RequestParser
{
    /**
     * Parse incoming request
     *
     * @return string
     *
     * @throws \Exception
     */
    public function parse()
    {
        $responses = [];

        foreach ($this->payload as $payload) {
            $responses[] = RequestParser::create()
                ->withPayload($payload)
                ->withProcedureHandler($this->procedureHandler)
                ->withMiddlewareHandler($this->middlewareHandler)
                ->withLocalException($this->localExceptions)
                ->parse();
        }

        $responses = array_filter($responses);
        return empty($responses) ? '' : '[' . implode(',', $responses) . ']';
    }

    /**
     * Return true if we have a batch request
     *
     * ex : [
     *   0 => '...',
     *   1 => '...',
     *   2 => '...',
     *   3 => '...',
     * ]
     *
     * @param  array $payload
     *
     * @return bool
     */
    public static function isBatchRequest(array $payload)
    {
        return array_keys($payload) === range(0, count($payload) - 1);
    }
}
