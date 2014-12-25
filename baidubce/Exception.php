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

/**
 * Base Error of BCE.
 */
class BceBaseError extends \Exception {
}

/**
 * Error from BCE client.
 */
class BceClientError extends BceBaseError {
    public function __construct($reason) {
        parent::__construct($response);
    }
}

/**
 * Error threw when connect to server.
 */
class BceServerError extends BceBaseError {
    public $status_code;
    public $code;
    public $request_id;

    public function __construct($message, $status_code = null,
                                $code = null, $request_id = null) {
        parent::__construct($message);
        $this->status_code = $status_code;
        $this->code = $code;
        $this->request_id = $request_id;
    }

    public function __toString() {
        return sprintf("%s, status_code = [%s], code = [%s], request_id = [%s]",
            empty($this->message) ? '(empty)' : $this->message,
            $this->status_code, $this->code, $this->request_id);
    }
}




/* vim: set ts=4 sw=4 sts=4 tw=120: */
