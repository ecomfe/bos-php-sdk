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

require_once __BOS_CLIENT_ROOT . "/baidubce/util/Coder.php";

use baidubce\util\Coder;

class CoderTest extends PHPUnit_Framework_TestCase {
    public function testMd5FromStream() {
        $fp = fopen(__FILE__, 'r');

        $this->assertEquals(
            md5(file_get_contents(__FILE__)),
            Coder::Md5FromStream($fp, 0)
        );

        $this->assertEquals(
            md5(substr(file_get_contents(__FILE__), 10, 10)),
            Coder::Md5FromStream($fp, 10, 10)
        );

        $this->assertEquals(
            md5(substr(file_get_contents(__FILE__), 10)),
            Coder::Md5FromStream($fp, 10)
        );

        fclose($fp);
    }

    public function testGuessMimeType() {
        $this->assertEquals('application/javascript', Coder::GuessMimeType('a.js'));
        $this->assertEquals('application/javascript', Coder::GuessMimeType('a.js'));
        $this->assertEquals('application/octet-stream', Coder::GuessMimeType('a.js1'));
        $this->assertEquals('application/octet-stream', Coder::GuessMimeType(''));
        $this->assertEquals('application/vnd.ms-excel.addin.macroenabled.12', Coder::GuessMimeType('a.xlam'));
    }

    public function testAppendUri() {
        $this->assertEquals('a/b/c', Coder::appendUri('a', 'b', 'c'));
        $this->assertEquals('a/b/c/', Coder::appendUri('a', '/b/', '/c/'));
        $this->assertEquals('a/b/c/', Coder::appendUri('a/', '/b/', '/c/'));
        $this->assertEquals('a/b/foo/bar/c/', Coder::appendUri('a/', '/b/foo//bar//', '/c/'));
        $this->assertEquals('a/c/', Coder::appendUri('a/', '', '/c/'));
        $this->assertEquals('a/c/', Coder::appendUri('a/', null, '/c/'));
        $this->assertEquals('a/', Coder::appendUri('a/', null, null));
        $this->assertEquals('/v1', Coder::appendUri('/v1', null, null));
        $this->assertEquals('/v1', Coder::appendUri('/v1', ''));
    }
}




/* vim: set ts=4 sw=4 sts=4 tw=120: */
