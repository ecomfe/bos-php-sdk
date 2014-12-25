<?php
/*
 * Copyright (c) 2014 Baidu.com, Inc. All Rights Reserved
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 */

namespace baidubce\http;

require_once dirname(dirname(__DIR__)) . "/baidubce/Bce.php";
require_once dirname(dirname(__DIR__)) . "/baidubce/Exception.php";
require_once dirname(dirname(__DIR__)) . "/baidubce/util/Time.php";
require_once dirname(dirname(__DIR__)) . "/baidubce/util/Coder.php";
require_once dirname(dirname(__DIR__)) . "/baidubce/util/BceTools.php";
require_once dirname(dirname(__DIR__)) . "/baidubce/http/HttpHeaders.php";
require_once dirname(dirname(__DIR__)) . "/baidubce/http/HttpContentTypes.php";
require_once dirname(dirname(__DIR__)) . "/baidubce/http/HttpMethod.php";

use baidubce\Bce;
use baidubce\BceServerError;
use baidubce\BceClientError;
use baidubce\util\Time;
use baidubce\util\Coder;
use baidubce\util\BceTools;

/**
 * Standard http request of BCE.
 */
class HttpClient {
    /**
     * @type array
     */
    private $config;

    /**
     * HttpClient's constructor
     * @param array $config The http client configuration.
     */
    public function __construct($config = array()) {
        $this->config = $config;
    }

    /**
     * The curl_setopt only accept string list, so we change the header map
     * to header list format.
     *
     * @param mixed $headers The http headers which will be sent out.
     * @return mixed
     */
    private function generateRequestHeaders($headers) {
        $request_headers = array();
        foreach ($headers as $key => $val) {
            if ($key !== HttpHeaders::HOST) {
                array_push($request_headers, sprintf('%s: %s', $key, $val));
            }
        }

        return $request_headers;
    }

    /**
     * @param mixed $body The request body.
     * @return number
     */
    private function guessContentLength($body) {
        if (is_null($body)) {
            return 0;
        } else if (is_string($body)) {
            return strlen($body);
        } else if (is_resource($body)) {
            return fstat($body)['size'];
        } else if (is_object($body) && method_exists($body, 'getSize')) {
            return $body->getSize();
        }
        throw new \InvalidArgumentException(sprintf("No %s is specified.",
            HttpHeaders::CONTENT_LENGTH));
    }

    /**
     * @param string $path The bucket and object path.
     * @param array @param The query strings
     *
     * @return string The complete request url path with query string.
     */
    private function getRequestUrl($path, $params) {
        $uri = Coder::urlEncodeExceptSlash($path);

        $query_string = implode("&", array_map(
            function($k, $v) {
                return $k . "=" . Coder::urlEncode($v);
            },
            array_keys($params), $params));

        if (!is_null($query_string) && $query_string != "") {
            $uri = $uri . "?" . $query_string;
        }

        $parsed_url = parse_url($this->config['endpoint']);

        $port = '';
        $protocol = isset($parsed_url['scheme']) ? $parsed_url['scheme'] : 'http';
        if ($protocol !== 'http' && $protocol !== 'https') {
            throw new \InvalidArgumentException(sprintf(
                "Invalid protocol: %s, either HTTP or HTTPS is expected.", $protocol));
        }

        return sprintf("%s%s", $this->config['endpoint'], $uri);
    }

    /**
     * Send request to BCE.
     *
     * @param string $http_method The http request method, uppercase.
     * @param string $path The resource path.
     * @param string $body The http request body.
     * @param mixed $headers The extra http request headers.
     * @param mixed $params The extra http url query strings.
     * @param mixed $sign_function This function will genenrate authorization header.
     * @param mixed $output_stream Write the http response to this stream.
     *
     * @return mixed body and http_headers
     */
    public function sendRequest($http_method, $path, $body = null, $headers = array(),
                                $params = array(), $sign_function = null, $output_stream = null) {

        $curl_handle = \curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $this->getRequestUrl($path, $params));
        curl_setopt($curl_handle, CURLOPT_NOPROGRESS, true);
        curl_setopt($curl_handle, CURLINFO_HEADER_OUT, true);

        if (isset($this->config['connection_timeout_in_mills'])) {
            curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT_MS,
                $this->config['connection_timeout_in_mills']);
        }

        if (!isset($headers[HttpHeaders::CONTENT_LENGTH])) {
            $content_length = $this->guessContentLength($body);
            $headers[HttpHeaders::CONTENT_LENGTH] = $content_length;
        }

        $parsed_url = parse_url($this->config['endpoint']);
        $default_headers = array(
            HttpHeaders::USER_AGENT => sprintf("bce-sdk-php/%s/%s/%s", Bce::SDK_VERSION, PHP_OS, phpversion()),
            HttpHeaders::BCE_DATE => Time::bceTimeNow(),
            HttpHeaders::BCE_REQUEST_ID => BceTools::genUUid(),
            HttpHeaders::EXPECT => '',
            HttpHeaders::TRANSFER_ENCODING => '',
            HttpHeaders::CONTENT_TYPE => HttpContentTypes::JSON,
            HttpHeaders::HOST => preg_replace('/(\w+:\/\/)?([^\/]+)\/?/', '$2', $this->config['endpoint']),
        );
        $headers = array_merge($default_headers, $headers);
        if (!is_null($sign_function)) {
            $headers[HttpHeaders::AUTHORIZATION] = call_user_func($sign_function,
                $this->config['credentials'], $http_method, $path, $params, $headers);
        }

        // Handle Http Request Headers
        $request_headers = $this->generateRequestHeaders($headers);
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, $http_method);

        if ($http_method === HttpMethod::HEAD) {
            curl_setopt($curl_handle, CURLOPT_NOBODY, true);
        }

        // Handle Http Request Body
        // Read everything from stream interface, if $body's type is string, wrapp is as a stream.
        if (!is_null($body)) {
            $input_stream = is_string($body) ? fopen('php://memory', 'r+') : $body;
            if (is_string($body)) {
                fwrite($input_stream, $body);
                rewind($input_stream);
            }

            curl_setopt($curl_handle, CURLOPT_POST, true);
            $read_callback = function($_1, $_2, $size) use ($input_stream) {
                if (is_resource($input_stream)) {
                    return fread($input_stream, $size);
                }
                else if (method_exists($input_stream, 'read')) {
                    return $input_stream->read($size);
                }

                // EOF
                return '';
            };
            curl_setopt($curl_handle, CURLOPT_READFUNCTION, $read_callback);
        }

        // Handle Http Response Headers
        $http_response_headers = array();
        $read_response_headers_callback = function($_1, $str) use (&$http_response_headers) {
            array_push($http_response_headers, $str);
            return strlen($str);
        };
        curl_setopt($curl_handle, CURLOPT_HEADERFUNCTION, $read_response_headers_callback);
        curl_setopt($curl_handle, CURLOPT_HEADER, false);

        // Handle Http Response Body
        if (!is_null($output_stream)) {
            $write_callback = function($_1, $str) use ($output_stream) {
                if (is_resource($output_stream)) {
                    return fwrite($output_stream, $str);
                }
                else if (method_exists($output_stream, 'write')) {
                    return $output_stream->write($str);
                }

                // EOF
                return false;
            };
            curl_setopt($curl_handle, CURLOPT_WRITEFUNCTION, $write_callback);
        }
        else {
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        }

        // Send request
        $http_response = curl_exec($curl_handle);
        $error = curl_error($curl_handle);
        $errno = curl_errno($curl_handle);
        $status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($curl_handle, CURLINFO_CONTENT_TYPE);
        // print_r(curl_getinfo($curl_handle, CURLINFO_HEADER_OUT));
        curl_close($curl_handle);

        $response_headers = $this->parseResponseHeaders($http_response_headers);
        $response_body = $this->parseHttpResponseBody($http_response, $content_type);
        $request_id = isset($response_headers[HttpHeaders::BCE_REQUEST_ID])
                      ? $response_headers[HttpHeaders::BCE_REQUEST_ID]
                      : null;

        // Error Check
        if ($error !== '') {
            // printf("Url = %s\n", $this->getRequestUrl($path, $params));
            throw new BceServerError($error, $status, $errno, $request_id);
        }

        if ($status >= 100 && $status < 200) {
            throw new BceClientError('Can not handle 1xx http status code');
        }
        else if ($status < 200 || $status >= 300) {
            if (isset($response_body['message'])) {
                throw new BceServerError($response_body['message'], $status,
                    $response_body['code'], $response_body['requestId']);
            }
            throw new BceServerError($http_response, $status);
        }

        // $status >= 200 && $status < 300 means HTTP OK
        return array(
            'http_headers' => $response_headers,
            'body' => $response_body,
        );
    }

    /**
     * @param mixed $http_response The http response body.
     * @param string $content_type The http response content type.
     *
     * @return mixed json decoded or the raw response body.
     */
    private function parseHttpResponseBody($http_response, $content_type) {
        if ($http_response === '' || $http_response === true) {
            // $http_response === true means the response body was handled by $output_stream.
            // $http_response === '' means there is no response body.
            return array();
        } else if (!is_null($content_type) && (strpos($content_type, 'application/json') === 0
                                               || strpos($content_type, 'text/json') === 0)) {
            $data = json_decode($http_response, true);
            if (is_null($data)) {
                throw new BceClientError(sprintf("MalformedJSON (%s)", $http_response));
            }
            return $data;
        }

        return $http_response;
    }

    /**
     * @param array $raw_headers The http response headers.
     *
     * @return array The normalized http response headers, empty value header
     *   was omited.
     */
    private function parseResponseHeaders($raw_headers) {
        $headers = array();
        foreach ($raw_headers as $i => $h) {
            if ($i > 0) {
                $h = explode(':', $h, 2);
                if (isset($h[1])) {
                    $h[0] = strtolower($h[0]);
                    if ($h[0] === strtolower(HttpHeaders::ETAG)) {
                        $h[1] = str_replace("\"", "", $h[1]);
                    }
                    $headers[$h[0]] = trim($h[1]);
                }
            }
        }

        return $headers;
    }
}
