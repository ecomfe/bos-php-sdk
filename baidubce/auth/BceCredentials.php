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

namespace baidubce\auth;

/**
 * Provides access to the BCE credentials used for accessing BCE services:
 * BCE access key ID and secret access key.
 */
class BceCredentials {
    /**
     * @type string
     */
    public $access_key_id;

    /**
     * @type string
     */
    public $secret_access_key;

    /**
     * @param array $credentials The ak and sk container.
     */
    public function __construct($credentials) {
        $this->access_key_id = $credentials['ak'];
        $this->secret_access_key = $credentials['sk'];
    }
}




/* vim: set ts=4 sw=4 sts=4 tw=120: */
