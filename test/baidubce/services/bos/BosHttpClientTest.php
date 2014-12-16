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

require_once __BOS_CLIENT_ROOT . "/baidubce/services/bos/BosHttpClient.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/model/stream/BceStringInputStream.php";

class BosHttpClientTest extends PHPUnit_Framework_TestCase {
    private $client;

    public function __construct() {
        $this->client = new baidubce_services_bos_BosHttpClient(json_decode(__BOS_TEST_CONFIG, true));
    }

    public function testSendRequest() {
        $response = $this->client->sendRequest('GET');

        $this->assertTrue(array_key_exists('status', $response));
        $this->assertTrue(array_key_exists('body', $response));
        $this->assertTrue(array_key_exists('http_headers', $response));

        $this->assertEquals(200, $response['status']);

        $http_headers = $response['http_headers'];
        $this->assertEquals('application/json; charset=utf-8', $http_headers['Content-Type']);
        $this->assertTrue(isset($http_headers['x-bce-request-id']));
        $this->assertTrue(isset($http_headers['x-bce-debug-id']));

        $this->assertTrue(isset($response['body']['owner']));
        $this->assertTrue(isset($response['body']['buckets']));
    }

    public function testSendRequestWithInputStream() {
        $grant_list = array();
        $grant_list[] = array(
            'grantee' => array(
                array('id' => 'a0a2fe988a774be08978736ae2a1668b'),
                array('id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
            ),
            'permission' => array('FULL_CONTROL')
        );

        $bucket = 'no-such-bucket-name';
        $key = '';
        $headers = array();
        $body = '';
        $params = array('acl' => null);
        $input_stream = new baidubce_model_stream_BceStringInputStream(json_encode(array('accessControlList' => $grant_list)));

        $this->client->sendRequest('PUT', $bucket);
        $response = $this->client->sendRequest('PUT',
            $bucket, $key, $headers, $body, $params, $input_stream);
        $this->assertEquals(200, $response['status']);
        $this->client->sendRequest('DELETE', $bucket);
    }

    public function testSendRequestWithOutputStream() {
        $output_stream = fopen('php://memory','r+');
        $response = $this->client->sendRequest('GET', '', '', array(), '', array(), null, $output_stream);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals(array(), $response['body']);

        rewind($output_stream);
        $content = stream_get_contents($output_stream);
        $body = json_decode($content, true);
        $this->assertEquals('a0a2fe988a774be08978736ae2a1668b', $body['owner']['id']);
        $this->assertEquals('PASSPORT:105003501', $body['owner']['displayName']);
    }
}



/* vim: set ts=4 sw=4 sts=4 tw=120: */
