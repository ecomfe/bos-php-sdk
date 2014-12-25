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

require_once __BOS_CLIENT_ROOT . "/baidubce/Bce.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/Exception.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/auth/Auth.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/auth/BceCredentials.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/util/Coder.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/http/HttpClient.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/http/HttpHeaders.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/http/HttpMethod.php";

use baidubce\Bce;
use baidubce\BceServerError;
use baidubce\auth\Auth;
use baidubce\auth\BceCredentials;
use baidubce\util\Coder;
use baidubce\http\HttpClient;
use baidubce\http\HttpHeaders;
use baidubce\http\HttpMethod;

class HttpClientTest extends PHPUnit_Framework_TestCase {
    public function testConnectionTimeout() {
        $config = array(
            'endpoint' => 'https://bs.baidu.com',
            'connection_timeout_in_mills' => 30,
        );
        $client = new HttpClient($config);
        try {
            $client->sendRequest(HttpMethod::GET, '/');
        }
        catch(BceServerError $ex) {
            $this->assertEquals(CURLE_OPERATION_TIMEOUTED, $ex->code);
        }
    }

    public function testInvalidUrl() {
        $config = array(
            'endpoint' => 'http://no-such-url'
        );
        $client = new HttpClient($config);

        try {
            $client->sendRequest(HttpMethod::GET, '/');
        }
        catch(BceServerError $ex) {
            $this->assertEquals(CURLE_COULDNT_RESOLVE_HOST, $ex->code);
        }
    }

    public function testHttpGet() {
        $config = array(
            'endpoint' => 'https://bs.baidu.com',
            'connection_timeout_in_mills' => 5 * 1000,
        );
        $client = new HttpClient($config);

        $response = $client->sendRequest(HttpMethod::GET, '/adtest/test.json');
        $this->assertEquals('BaiduBS', $response['http_headers']['server']);
        $this->assertEquals('d0b8560f261410878a68bbe070d81853', $response['http_headers']['etag']);
        $this->assertEquals('text/json', $response['http_headers']['content-type']);
        $this->assertEquals(array('hello' => 'world'), $response['body']);
    }

    public function testInvalidHttpStatus() {
        $config = array(
            'endpoint' => 'https://bs.baidu.com',
            'connection_timeout_in_mills' => 5 * 1000,
        );
        $client = new HttpClient($config);

        try {
            $client->sendRequest(HttpMethod::GET, '/');
        }
        catch(BceServerError $ex) {
            $this->assertEquals(403, $ex->status_code);
        }
    }

    public function generateAuthorization($credentials, $http_method, $path, $params, $headers) {
        $auth = new Auth(new BceCredentials($credentials));
        return $auth->generateAuthorization($http_method, $path, $params, $headers);
    }

    public function testSendRequest() {
        $config = json_decode(__BOS_TEST_CONFIG, true);
        $client = new HttpClient($config);
        $response = $client->sendRequest(HttpMethod::GET, Bce::URL_PREFIX,
            null, array(), array(),
            array($this, 'generateAuthorization'),
            null);

        $this->assertTrue(array_key_exists('body', $response));
        $this->assertTrue(array_key_exists('http_headers', $response));
        $http_headers = $response['http_headers'];
        $this->assertEquals('application/json; charset=utf-8', $http_headers['content-type']);
        $this->assertTrue(isset($http_headers['x-bce-request-id']));
        $this->assertTrue(isset($http_headers['x-bce-debug-id']));
        $this->assertTrue(isset($response['body']['owner']));
        $this->assertTrue(isset($response['body']['buckets']));
    }

    public function testReadRequestBodyFromStream() {
        $grant_list = array();
        $grant_list[] = array(
            'grantee' => array(
                array('id' => 'a0a2fe988a774be08978736ae2a1668b'),
                array('id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
            ),
            'permission' => array('FULL_CONTROL')
        );

        $bucket = 'no-such-bucket-name';

        // Prepare the request body
        $body = fopen('php://memory', 'r+');
        fwrite($body, json_encode(array('accessControlList' => $grant_list)));
        rewind($body);

        $config = json_decode(__BOS_TEST_CONFIG, true);
        $client = new HttpClient($config);

        $path = Coder::appendUri(Bce::URL_PREFIX, $bucket);

        // Create the bucket
        $client->sendRequest(HttpMethod::PUT, $path,
            null, array(), array(),
            array($this, 'generateAuthorization'),
            null);

        // Set bucket acl
        $response = $client->sendRequest(HttpMethod::PUT, $path,
            $body, array(), array('acl' => ''),
            array($this, 'generateAuthorization'),
            null);
        $this->assertArrayHasKey('http_headers', $response);
        $this->assertArrayHasKey('body', $response);
        $this->assertArrayHasKey('x-bce-request-id', $response['http_headers']);
        $this->assertArrayHasKey('x-bce-debug-id', $response['http_headers']);
        $this->assertArrayHasKey('content-length', $response['http_headers']);
        $this->assertArrayHasKey('date', $response['http_headers']);
        $this->assertArrayHasKey('server', $response['http_headers']);
        $this->assertEquals(array(), $response['body']);

        // Delete the bucket
        $client->sendRequest(HttpMethod::DELETE, $path,
            null, array(), array(),
            array($this, 'generateAuthorization'),
            null);
    }

    public function testReadRequestBodyFromString() {
        $grant_list = array();
        $grant_list[] = array(
            'grantee' => array(
                array('id' => 'a0a2fe988a774be08978736ae2a1668b'),
                array('id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
            ),
            'permission' => array('FULL_CONTROL')
        );

        $bucket = 'no-such-bucket-name';

        // Prepare the request body
        $body = json_encode(array('accessControlList' => $grant_list));

        $config = json_decode(__BOS_TEST_CONFIG, true);
        $client = new HttpClient($config);

        $path = Coder::appendUri(Bce::URL_PREFIX, $bucket);

        // Create the bucket
        $client->sendRequest(HttpMethod::PUT, $path,
            null, array(), array(),
            array($this, 'generateAuthorization'),
            null);

        // Set bucket acl
        $response = $client->sendRequest(HttpMethod::PUT, $path,
            $body, array(), array('acl' => ''),
            array($this, 'generateAuthorization'),
            null);
        $this->assertArrayHasKey('http_headers', $response);
        $this->assertArrayHasKey('body', $response);
        $this->assertArrayHasKey('x-bce-request-id', $response['http_headers']);
        $this->assertArrayHasKey('x-bce-debug-id', $response['http_headers']);
        $this->assertArrayHasKey('content-length', $response['http_headers']);
        $this->assertArrayHasKey('date', $response['http_headers']);
        $this->assertArrayHasKey('server', $response['http_headers']);
        $this->assertEquals(array(), $response['body']);

        // Delete the bucket
        $client->sendRequest(HttpMethod::DELETE, $path,
            null, array(), array(),
            array($this, 'generateAuthorization'),
            null);
    }

    public function testSendRequestWithOutputStream() {
        $output_stream = fopen('php://memory','r+');

        $config = json_decode(__BOS_TEST_CONFIG, true);
        $client = new HttpClient($config);

        $response = $client->sendRequest(HttpMethod::GET, Bce::URL_PREFIX,
            null, array(), array(),
            array($this, 'generateAuthorization'),
            $output_stream);
        $this->assertEquals(array(), $response['body']);

        rewind($output_stream);
        $content = stream_get_contents($output_stream);
        $body = json_decode($content, true);
        $this->assertEquals('a0a2fe988a774be08978736ae2a1668b', $body['owner']['id']);
        $this->assertEquals('PASSPORT:105003501', $body['owner']['displayName']);
    }
}



/* vim: set ts=4 sw=4 sts=4 tw=120: */
