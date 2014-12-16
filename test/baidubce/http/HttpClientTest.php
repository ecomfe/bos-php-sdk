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

require_once __BOS_CLIENT_ROOT . "/baidubce/http/HttpClient.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/exception/BceRuntimeException.php";

use baidubce\http\HttpClient;

class HttpClientTest extends PHPUnit_Framework_TestCase {
    public function testInvalidUrl() {
        $config = array();
        $client = new HttpClient($config);
        try {
            $client->sendRequest('GET', 'http://no-such-url/');
        }
        catch(\baidubce\exception\BceRuntimeException $ex) {
            $this->assertEquals(0, strpos('errno = 6', $ex->getMessage()));
        }
    }

    public function testTimeout() {
        $config = array(
            'TimeOut' => 10,    // 10ms
        );
        $client = new HttpClient($config);

        try {
            $client->sendRequest('GET', 'https://bs.baidu.com');
        }
        catch(\baidubce\exception\BceRuntimeException $ex) {
            $this->assertEquals(0, strpos('errno = 28', $ex->getMessage()));
        }
    }

    public function testHttpGet() {
        $config = array();
        $client = new HttpClient($config);
        $response = $client->sendRequest('GET', 'https://bs.baidu.com/', null, array());

        $this->assertTrue(array_key_exists('status', $response));
        $this->assertTrue(array_key_exists('body', $response));
        $this->assertTrue(array_key_exists('http_headers', $response));

        $this->assertTrue(is_array($response['body']));
        $this->assertTrue(is_array($response['http_headers']));

        $http_headers = $response['http_headers'];
        $this->assertEquals($http_headers['Content-Type'], 'application/json');
        $this->assertTrue(array_key_exists('x-bs-request-id', $http_headers));
        $this->assertTrue(array_key_exists('x-bs-client-ip', $http_headers));
    }
}



/* vim: set ts=4 sw=4 sts=4 tw=120: */
