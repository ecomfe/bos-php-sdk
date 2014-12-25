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

require_once dirname(__DIR__) . '/baidubce/Bce.php';

class BceBaseClient {
    protected $config;
    protected $service_id;
    protected $region_supported;

    /**
     * @param mixed $config the client configuration. The constructor makes a copy of this parameter so
     * that it is safe to change the configuration after then.
     * @param string $service_id The service id, such as bos, ses, sms and so on.
     * @param boolean $region_supported true if this client supports region.
     */
    public function __construct($config, $service_id, $region_supported = true) {
        $this->config = array_merge(array(), Bce::getDefaultConfig(), $config);
        $this->service_id = $service_id;
        $this->region_supported = $region_supported;

        if (!isset($this->config['endpoint'])) {
            $this->config['endpoint'] = $this->_computeEndpoint();
        }
    }

    private function _computeEndpoint() {
        if (isset($this->config['endpoint'])) {
            return $this->config['endpoint'];
        }

        if ($this->region_supported) {
            return sprintf("%s://%s.%s.%s",
                $this->config['protocol'],
                $this->service_id,
                $this->config['region'],
                Bce::DEFAULT_SERVICE_DOMAIN);
        }
        else {
            return sprintf("%s://%s.%s",
                $this->config['protocol'],
                $this->config['region'],
                Bce::DEFAULT_SERVICE_DOMAIN);
        }
    }
}




/* vim: set ts=4 sw=4 sts=4 tw=120: */
