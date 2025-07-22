<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Application\Devflow;
use App\Infrastructure\Errors\HttpRequestError;
use App\Shared\Services\ArgsParser;
use Laminas\Stdlib\DispatchableInterface;
use Laminas\Stdlib\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\EventDispatcher\ActionFilter\Filter;
use Qubus\Exception\Exception;
use Qubus\Http\Factories\JsonResponseFactory;
use ReflectionException;

use function App\Shared\Helpers\get_siteinfo;
use function in_array;
use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_null__;
use function sprintf;

class Client implements DispatchableHttp
{
    protected DispatchableInterface $client;
    protected RequestFactoryInterface $requestFactory;
    protected StreamFactoryInterface $streamFactory;

    public function __construct(
        DispatchableInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * {@inheritDoc}
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws \Exception
     */
    public function request(string $url, array $args = []): ResponseInterface
    {
        $defaults = [
            'method'            => 'GET',
            /**
             * Filters the connection timeout value for an HTTP request.
             *
             * @param int    $timeout Time in seconds until a request times out. Default 10.
             * @param string $url     The request URL.
             */
            'timeout'           => Filter::getInstance()->applyFilter('http.request.timeout', 10, $url),
            /**
             * Filters the maximum number of redirections to follow (0 = none).
             *
             * @param int    $maxredirects Number of redirects allowed. Default 5.
             * @param string $url          The request URL.
             */
            'maxredirects'      => Filter::getInstance()->applyFilter('http.request.maxredirects', 5, $url),
            /**
             * Filters the version of the HTTP protocol used in a request.
             *
             * @param string $httpversion HTTP protocol version used (usually '1.1', '1.0' or '2').
             * @param string $url         The request URL.
             */
            'httpversion'       => Filter::getInstance()->applyFilter('http.request.httpversion', '1.1', $url),
            /**
             * Filters the user agent value sent with an HTTP request.
             *
             * @param string $useragent Devflow user agent string.
             * @param string $url       The request URL.
             */
            'useragent'         => Filter::getInstance()->applyFilter(
                'http.request.useragent',
                sprintf('Devflow/%s; %s', Devflow::inst()->release(), get_siteinfo('siteurl')),
                $url
            ),
            'headers'           => [],
            'body'              => null,
            'strictredirects'   => false,
            'adapter'           => 'Laminas\Http\Client\Adapter\Socket',
            'keepalive'         => false,
            'storeresponse'     => true,
            'encodecookies'     => true,
            'outputstream'      => false,
            'rfc3986strict'     => false,
            'sslcapath'         => null,
            'sslcafile'         => null,

        ];

        // Pre-parse for the HEAD checks.
        $args = ArgsParser::parse($args);
        // By default, HEAD requests do not cause redirections.
        if (isset($args['method']) && 'HEAD' === $args['method']) {
            $defaults['maxredirects'] = 0;
        }

        $parsedArgs = ArgsParser::parse($args, $defaults);
        /**
         * Filters the arguments used in an HTTP request.
         *
         * @param array  $parsedArgs An array of HTTP request arguments.
         * @param string $url        The request URL.
         */
        $parsedArgs = Filter::getInstance()->applyFilter('http.request.args', $parsedArgs, $url);
        /**
         * Filters the preemptive return value of an HTTP request.
         *
         * Returning a non-false value from the filter will short-circuit the HTTP request and return
         * early with that value. A filter should return one of:
         *
         *  - An array containing 'headers', 'body', and 'response' elements
         *  - A HttpRequestError instance
         *  - bool false to avoid short-circuiting the response
         *
         * Returning any other value may result in unexpected behavior.
         *
         * @param false|array|HttpRequestError $response   A preemptive return value of an HTTP request. Default false.
         * @param array                        $parsedArgs HTTP request arguments.
         * @param string                       $url        The request URL.
         */
        $preempt = Filter::getInstance()->applyFilter('http.request.preempt', false, $parsedArgs, $url);
        if (false !== $preempt) {
            return $preempt;
        }

        $parsedUrl = parse_url($url);
        if (empty($parsedUrl) || !isset($parsedUrl['scheme'])) {
            $response = new HttpRequestError(t__('A valid URL was not provided.', 'devflow'), 405);
            Action::getInstance()->doAction(
                'http_api_debug',
                $response,
                'response',
                'Qubus\Http\Request',
                $parsedArgs,
                $url
            );
            return JsonResponseFactory::create($response->getMessage(), (int) $response->getCode());
        }

        if (is_null__($parsedArgs['headers'])) {
            $parsedArgs['headers'] = [];
        }

        $request = $this->requestFactory->createRequest($parsedArgs['method'], $url);
        if (in_array($parsedArgs['method'], ['POST', 'PUT', 'PATCH'])) {
            $stream = $this->streamFactory->createStream($parsedArgs['body']);
            $request = $request->getBody()->write($stream);
        }
        foreach ($parsedArgs['headers'] as $name => $value) {
            $request = $request->withAddedHeader($name, $value);
        }

        $response = $this->client->dispatch($request);

        /**
         * Fires after an HTTP API response is received and before the response is returned.
         *
         * @param Response|mixed $response   HTTP response.
         * @param string         $context    Context under which the hook is fired.
         * @param string         $class      HTTP transport used.
         * @param array          $parsedArgs HTTP request arguments.
         * @param string         $url        The request URL.
         */
        Action::getInstance()->doAction(
            'http_api_debug',
            $response,
            'response',
            'Qubus\Http\Request',
            $parsedArgs,
            $url
        );

        /**
         * Filters a successful HTTP API response immediately before the response is returned.
         *
         * @param Response|mixed $response   HTTP response.
         * @param array          $parsedArgs HTTP request arguments.
         * @param string         $url        The request URL.
         */
        return Filter::getInstance()->applyFilter('http.request.response', $response, $parsedArgs, $url);
    }

    /**
     * {@inheritDoc}
     *
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function get(string $url, array $args = []): ResponseInterface
    {
        $defaults = ['method' => 'GET'];
        $parsedArgs = ArgsParser::parse($args, $defaults);
        return $this->request($url, $parsedArgs);
    }

    /**
     * {@inheritDoc}
     *
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function post(string $url, array $args = []): ResponseInterface
    {
        $defaults = ['method' => 'POST'];
        $parsedArgs = ArgsParser::parse($args, $defaults);
        return $this->request($url, $parsedArgs);
    }
}
