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

namespace baidubce;

class Bce {
    const SDK_VERSION = '0.8.0';
    const DEFAULT_SERVICE_DOMAIN = 'baidubce.com';
    const URL_PREFIX = '/v1';
    const DEFAULT_ENCODING = 'UTF-8';

    public static function getDefaultConfig() {
        return array(
            'credentials' => null,
            'endpoint' => null,
            'protocol' => 'http',
            'region' => 'bj',
            'connection_timeout_in_mills' => 50 * 1000,
            'send_buf_size' => 1024 * 1024,
            'recv_buf_size' => 10 * 1024 * 1024,
        );
    }
}



/* vim: set ts=4 sw=4 sts=4 tw=120: */
