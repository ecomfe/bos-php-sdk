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

require_once __BOS_CLIENT_ROOT . "/baidubce/services/bos/BosClient.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/util/Time.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/util/Coder.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/exception/BceServiceException.php";

class BosClientTest extends PHPUnit_Framework_TestCase {
    private $client;

    private $bucket;
    private $key;

    public function __construct() {
        parent::__construct();
        $this->client = new baidubce_services_bos_BosClient(json_decode(__BOS_TEST_CONFIG, true));
        $this->bucket = "my-bucket";
        $this->key = "test.txt";
    }

    public function setUp() {
    }

    public function tearDown() {
    }

    public function testPutObjectFromString() {
        $response = $this->client->putObjectFromString($this->bucket, $this->key, 'Hello World');
        $this->assertEquals(200, $response['status']);
        $this->assertEquals(md5('Hello World'), $response['http_headers']['ETag']);
        $url = $this->client->generatePresignedUrl($this->bucket, $this->key, 0, 30);
        print_r($url);
    }
}





/* vim: set ts=4 sw=4 sts=4 tw=120: */
