<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Http\Message\ResponseInterface;

interface DispatchableHttp
{
    /**
     * Send an HTTP request to a URI.
     *
     * Please note: The only URI that are supported in the HTTP Transport implementation
     * are the HTTP and HTTPS protocols.
     *
     * @param string $url  The request URL.
     * @param array  $args {
     *     Optional. Array of HTTP request arguments.
     *
     *     @type string       $method              Request method. Accepts 'GET', 'POST', 'HEAD', 'PUT', 'DELETE',
     *                                             'TRACE', 'OPTIONS', or 'PATCH'.
     *                                             Default: 'GET'.
     *     @type int          $timeout             Connection timeout (seconds). Default 10.
     *     @type int          $maxredirects        Maximum number of redirections to follow (0 = none).
     *                                             Default: 5.
     *     @type bool         $strictredirects     Whether to strictly follow the RFC when redirecting.
     *                                             Default: false.
     *     @type string       $httpversion         HTTP protocol version (usually '1.1', '1.0' or '2').
     *                                             Default: '1.1'.
     *     @type string       $useragent           User agent identifier string (sent in request headers)
     *                                             Default: 'Devflow/' . Devflow::inst()->release() . '; ' . get_siteinfo('siteurl').
     *     @type mixed        $adapter             Connection adapter class to use.
     *                                             Default: Laminas\Http\Client\Adapter\Socket.
     *     @type bool         $keepalive           Whether to enable keep-alive connections with the server.
     *                                             Useful and might improve performance if several consecutive
     *                                             requests to the same server are performed. Default: false.
     *     @type array        $headers             Array of headers to send with the request.
     *                                             Default: [].
     *     @type bool         $encodecookies       Whether to pass the cookie value through urlencode/urldecode.
     *                                             Enabling this breaks support with some web servers.
     *                                             Disabling this limits the range of values the cookies can contain.
     *                                             Default: true.
     *     @type string       $body                Body to send with the request. Default null.
     *     @type bool         $outputstream        Destination for streaming of received data
     *                                             (options: string (filename), true for temp file,
     *                                             false/null to disable streaming).
     *                                             Default: false.
     *     @type bool         $storeresponse       Whether to store last response for later retrieval with
     *                                             getLastResponse(). If set to FALSE, getLastResponse()
     *                                             will return NULL. Default: true.
     *     @type string       $sslcapath           Path to SSL certificate directory. Default: NULL.
     *     @type string       $sslcafile           Path to Certificate Authority (CA) bundle.
     *                                             Default: NULL.
     *     @type bool         $rfc3986strict       Whether to strictly adhere to RFC 3986 (in practice,
     *                                             this means replacing '+' with '%20'). Default: false.
     *
     * }
     * @return ResponseInterface
     */
    public function request(string $url, array $args = []): ResponseInterface;

    /**
     * Uses the GET HTTP method.
     *
     * Used for sending data that is expected to be in the body.
     *
     * @param string $url  The request URL.
     * @param array  $args Optional. Override the defaults.
     * @return ResponseInterface Response containing 'headers', 'body', etc.
     */
    public function get(string $url, array $args = []): ResponseInterface;

    /**
     * Uses the POST HTTP method.
     *
     * Used for sending data that is expected to be in the body.
     *
     * @param string $url  The request URL.
     * @param array  $args Optional. Override the defaults.
     * @return ResponseInterface Response containing 'headers', 'body', etc.
     */
    public function post(string $url, array $args = []): ResponseInterface;
}
