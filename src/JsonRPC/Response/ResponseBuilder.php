<?php

namespace JsonRPC\Response;

use BadFunctionCallException;
use Exception;
use InvalidArgumentException;
use JsonRPC\Exception\AccessDeniedException;
use JsonRPC\Exception\AuthenticationFailureException;
use JsonRPC\Exception\InvalidJsonFormatException;
use JsonRPC\Exception\InvalidJsonRpcFormatException;
use JsonRPC\Exception\ResponseEncodingFailureException;
use JsonRPC\Exception\ResponseException;
use JsonRPC\Validator\JsonEncodingValidator;

/**
 * Class ResponseBuilder
 *
 * @package JsonRPC
 * @author  Frederic Guillot
 */
class ResponseBuilder
{
    /**
     * Payload ID
     *
     * @var mixed
     */
    protected $id;

    /**
     * Payload ID
     *
     * @var mixed
     */
    protected $result;

    /**
     * Payload error code
     *
     * @var integer
     */
    protected $errorCode;

    /**
     * Payload error message
     *
     * @var string
     */
    protected $errorMessage;

    /**
     * Payload error data
     *
     * @var mixed
     */
    protected $errorData;

    /**
     * HTTP Headers
     *
     * @var array
     */
    protected $headers = [
        'Content-Type' => 'application/json',
    ];

    /**
     * HTTP status
     *
     * @var string
     */
    protected $status;

    /**
     * Exception
     *
     * @var ResponseException
     */
    protected $exception;

    /**
     * Get new object instance
     *
     * @return ResponseBuilder
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Set id
     *
     * @param  mixed  $id
     *
     * @return $this
     */
    public function withId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set result
     *
     * @param  mixed $result
     *
     * @return $this
     */
    public function withResult($result)
    {
        $this->result = $result;
        return $this;
    }

    /**
     * Set error
     *
     * @param  integer $code
     * @param  string  $message
     * @param  string  $data
     *
     * @return $this
     */
    public function withError($code, $message, $data = '')
    {
        $this->errorCode = $code;
        $this->errorMessage = $message;
        $this->errorData = $data;
        return $this;
    }

    /**
     * Set exception
     *
     * @param  Exception $exception
     *
     * @return $this
     */
    public function withException(Exception $exception)
    {
        $this->exception = $exception;
        return $this;
    }

    /**
     * Add HTTP header
     *
     * @param  string $name
     * @param  string $value
     *
     * @return $this
     */
    public function withHeader($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add HTTP Status
     *
     * @param  string $status
     *
     * @return $this
     */
    public function withStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get headers
     *
     * @return string[]
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Build response
     *
     * @return string
     *
     * @throws ResponseEncodingFailureException
     */
    public function build()
    {
        $options = 0;
        if (defined('JSON_UNESCAPED_SLASHES')) {
            $options |= JSON_UNESCAPED_SLASHES;
        }
        if (defined('JSON_UNESCAPED_UNICODE')) {
            $options |= JSON_UNESCAPED_UNICODE;
        }
        $encodedResponse = json_encode($this->buildResponse(), $options);
        JsonEncodingValidator::validate();

        return $encodedResponse;
    }

    /**
     * Send HTTP headers
     *
     * @return $this
     */
    public function sendHeaders()
    {
        if (! empty($this->status)) {
            header($this->status);
        }

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        return $this;
    }

    /**
     * Build response payload
     *
     * @return array
     */
    protected function buildResponse()
    {
        $response = ['jsonrpc' => '2.0'];
        $this->handleExceptions();

        if (! empty($this->errorMessage)) {
            $response['error'] = $this->buildErrorResponse();
        } else {
            $response['result'] = $this->result;
        }

        $response['id'] = $this->id;
        return $response;
    }

    /**
     * Build response error payload
     *
     * @return array
     */
    protected function buildErrorResponse()
    {
        $response = [
            'code' => $this->errorCode,
            'message' => $this->errorMessage,
        ];

        if (! empty($this->errorData)) {
            $response['data'] = $this->errorData;
        }

        return $response;
    }

    /**
     * Transform exceptions to JSON-RPC errors
     */
    protected function handleExceptions()
    {
        try {
            if ($this->exception instanceof Exception) {
                throw $this->exception;
            }
        } catch (InvalidJsonFormatException $e) {
            $this->errorCode = -32700;
            $this->errorMessage = 'Parse error';
            $this->id = null;
        } catch (InvalidJsonRpcFormatException $e) {
            $this->errorCode = -32600;
            $this->errorMessage = 'Invalid Request';
            $this->id = null;
        } catch (BadFunctionCallException $e) {
            $this->errorCode = -32601;
            $this->errorMessage = 'Method not found';
        } catch (InvalidArgumentException $e) {
            $this->errorCode = -32602;
            $this->errorMessage = 'Invalid params';
            $this->errorData = $this->exception->getMessage();
        } catch (ResponseEncodingFailureException $e) {
            $this->errorCode = -32603;
            $this->errorMessage = 'Internal error';
            $this->errorData = $this->exception->getMessage();
        } catch (AuthenticationFailureException $e) {
            $this->errorCode = 401;
            $this->errorMessage = 'Unauthorized';
            $this->status = 'HTTP/1.0 401 Unauthorized';
            $this->withHeader('WWW-Authenticate', 'Basic realm="JsonRPC"');
        } catch (AccessDeniedException $e) {
            $this->errorCode = 403;
            $this->errorMessage = 'Forbidden';
            $this->status = 'HTTP/1.0 403 Forbidden';
        } catch (ResponseException $e) {
            $this->errorCode = $this->exception->getCode();
            $this->errorMessage = $this->exception->getMessage();
            $this->errorData = $this->exception->getData();
        } catch (Exception $e) {
            $this->errorCode = $this->exception->getCode();
            $this->errorMessage = $this->exception->getMessage();
        }
    }
}
