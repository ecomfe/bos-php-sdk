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

define('__BOS_CLIENT_ROOT', dirname(dirname(__FILE__)));

// Only scalar and null values are allowed
define('__BOS_TEST_CONFIG', json_encode(array(
    'AccessKeyId' => '225b574233f9447792ff218a4abb4e35',
    'AccessKeySecret' => '61cce7190bb044f781cb55bc895a5b91',
    'TimeOut' => 5000,    // 5 seconds
    'Host' => '10.105.97.15',
    // 'Host' => 'localhost:8828',
    'User-Agent' => 'This is the user-agent'
)));


/* vim: set ts=4 sw=4 sts=4 tw=120: */
