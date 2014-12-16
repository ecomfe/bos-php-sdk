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

require_once __BOS_CLIENT_ROOT . "/baidubce/services/bos/model/stream/BosFileInputStream.php";

use baidubce\services\bos\model\stream\BosFileInputStream;

class BosFileInputStreamTest extends PHPUnit_Framework_TestCase {
    public function testReadFullContent() {
        $fp = fopen(__FILE__, 'rb');
        $file_size = filesize(__FILE__);

        $input_stream = new BosFileInputStream($fp, $file_size);
        $content = '';
        while (($part = $input_stream->read(10)) !== '') {
            $content = $content . $part;
        }

        $this->assertEquals(
            md5(file_get_contents(__FILE__)),
            md5($content)
        );
    }

    public function testReadPartContent() {
        $fp = fopen(__FILE__, 'rb');
        $file_size = filesize(__FILE__);

        $input_stream = new BosFileInputStream($fp, $file_size, 123, 99);
        $content = '';
        while (($part = $input_stream->read(10)) !== '') {
            $content = $content . $part;
        }
        $this->assertEquals(99, strlen($content));

        $this->assertEquals(
            md5(substr(file_get_contents(__FILE__), 123, 99)),
            md5($content)
        );
    }
}




/* vim: set ts=4 sw=4 sts=4 tw=120: */
