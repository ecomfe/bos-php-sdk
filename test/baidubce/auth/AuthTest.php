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

require_once __BOS_CLIENT_ROOT . "/baidubce/auth/Auth.php";
require_once __BOS_CLIENT_ROOT . "/baidubce/auth/BceCredentials.php";

use baidubce\auth\Auth;
use baidubce\auth\BceCredentials;

class AuthTest extends PHPUnit_Framework_TestCase {
    public function testQueryStringCanonicalization() {
        $auth = new Auth(new BceCredentials(array('ak' => 'ak', 'sk' => 'sk')));

        $params = array(
            'A' => 'A',
            'B' => null,
            'C' => ''
        );
        $this->assertEquals($auth->queryStringCanonicalization($params), 'A=A&B=&C=');
    }

    public function testHeadersCanonicalization() {
        $auth = new Auth(new BceCredentials(array('ak' => 'ak', 'sk' => 'sk')));

        $headers = array(
            'Host' =>'localhost',
            'x-bce-a' => 'a/b:c',
            'C' => ''
        );

        list($_, $signed_headers) = $auth->headersCanonicalization($headers);
        $this->assertEquals($signed_headers, array('host', 'x-bce-a'));

        $headers['Content-MD5'] = 'MD5';
        list($canonical_headers, $_) = $auth->headersCanonicalization($headers);
        $this->assertEquals($canonical_headers,
            "content-md5:MD5\nhost:localhost\nx-bce-a:a%2Fb%3Ac");
    }

    public function testGenerateAuthorization() {
        $auth = new Auth(new BceCredentials(array('ak' => 'my_ak', 'sk' => 'my_sk')));

        $method = 'PUT';
        $uri = '/v1/bucket/object1';
        $params = array(
            'A' => null,
            'b' => '',
            'C' => 'd'
        );
        $headers = array(
            'Host' => 'bce.baidu.com',
            'abc' =>'123',
            'x-bce-meta-key1' => 'ABC'
        );

        $signature = $auth->generateAuthorization($method, $uri, $params, $headers, 1402639056);
        $this->assertEquals(
            'bce-auth-v1/my_ak/2014-06-13T05:57:36Z/1800/host;x-bce-meta-key1/' .
            '80c9672aca2ea9af4bb40b9a8ff458d72df94e97d550840727f3a929af271d25',
            $signature
        );

        $signature = $auth->generateAuthorization($method, $uri, $params, $headers, 1402639056, 1800);
        $this->assertEquals(
            'bce-auth-v1/my_ak/2014-06-13T05:57:36Z/1800/host;' .
            'x-bce-meta-key1/80c9672aca2ea9af4bb40b9a8ff458d72' .
            'df94e97d550840727f3a929af271d25',
            $signature
        );

        $method = 'DELETE';
        $uri = '/v1/test-bucket1361199862';
        $params = array();
        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Length' => 0,
            'User-Agent' => 'This is the user-agent'
        );
        $signature = $auth->generateAuthorization($method, $uri, $params, $headers, 1402639056, 1800);
        $this->assertEquals(
            'bce-auth-v1/my_ak/2014-06-13T05:57:36Z/1800/' .
            'content-length;content-type/' .
            'c9386b15d585960ae5e6972f73ed92a9a682dc81025480ba5b41206d3e489822',
            $signature
        );
    }

}




/* vim: set ts=4 sw=4 sts=4 tw=120: */
