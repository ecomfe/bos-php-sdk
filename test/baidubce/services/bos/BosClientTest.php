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

define('OWNER_ID', '992c67ee10be4e85bf444d18b638f9ba');
define('OWNER_PASSPORT_ID', 'PASSPORT:105015804');

class BosClientTest extends PHPUnit_Framework_TestCase {
    private $client;

    private $bucket;
    private $key;
    private $filename;

    public function __construct() {
        parent::__construct();
        $this->client = new baidubce_services_bos_BosClient(json_decode(__BOS_TEST_CONFIG, true));

        $id = rand();
        $this->bucket = sprintf('test-bucket%d', $id);
        $this->key = sprintf('test_object%d', $id);
        $this->filename = sprintf('temp_file%d', $id);
    }

    public function setUp() {
        // IGNORE
    }

    public function tearDown() {
        // Delete all buckets
        $response = $this->client->listBuckets();
        $buckets = $response['body']['buckets'];
        foreach ($buckets as $idx => $bucket) {
            $response = $this->client->listObjects($bucket['name']);
            $contents = $response['body']['contents'];
            foreach ($contents as $object) {
                $this->client->deleteObject($bucket['name'], $object['key']);
            }
            $this->client->deleteBucket($bucket['name']);
        }

        if (file_exists($this->filename)) {
            unlink($this->filename);
        }
    }

    private function prepareTemporaryFile($size) {
        $fp = fopen($this->filename, 'w');
        fseek($fp, $size - 1, SEEK_SET);
        fwrite($fp, 0);
        fclose($fp);
    }

    private function checkProperties($response) {
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('body', $response);
        $this->assertArrayHasKey('http_headers', $response);

        $this->assertTrue(is_array($response['http_headers']));
        $this->assertTrue(is_array($response['body']));

        $this->assertArrayHasKey('x-bce-request-id', $response['http_headers']);
        $this->assertArrayHasKey('x-bce-debug-id', $response['http_headers']);
    }

    public function testListBuckets() {
        $time1 = baidubce_util_Time::BceTimeNow();
        $this->client->createBucket('aaaaaaxzr1');

        $time2 = baidubce_util_Time::BceTimeNow();
        $this->client->createBucket('aaaaaaxzr2');

        $response = $this->client->listBuckets();

        $owner = array(
            "id" => OWNER_ID,
            "displayName" => OWNER_PASSPORT_ID,
        );
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals($response['body']['owner']['id'], $owner['id']);
        $this->assertEquals($response['body']['owner']['displayName'], $owner['displayName']);
        $this->assertEquals($response['body']['buckets'][0]['name'], 'aaaaaaxzr1');
        $this->assertEquals($response['body']['buckets'][1]['name'], 'aaaaaaxzr2');

        // TODO(leeight) Why the creationDate is same?
        // $this->assertEquals($response['body']['buckets'][0]['creationDate'], $time1);
        // $this->assertEquals($response['body']['buckets'][1]['creationDate'], $time2);

        $this->client->deleteBucket('aaaaaaxzr1');
        $this->client->deleteBucket('aaaaaaxzr2');
    }

    public function testCreateBucket() {
        $response = $this->client->createBucket($this->bucket);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);

        try {
            $response = $this->client->createBucket($this->bucket);
        }
        catch(baidubce_exception_BceServiceException $ex) {
            $this->assertEquals('BucketAlreadyExists', $ex->getServiceErrorCode());
            $this->assertEquals(409, $ex->getStatusCode());
        }
    }

    public function testDeleteBucket() {
        try {
            $this->client->deleteBucket($this->bucket);
        }
        catch(baidubce_exception_BceServiceException $ex) {
            $this->assertEquals(404, $ex->getStatusCode());
            $this->assertEquals('NoSuchBucket', $ex->getServiceErrorCode());
        }
    }

    public function testDoesBucketExist() {
        $response = $this->client->doesBucketExist($this->bucket);
        $this->assertFalse($response);

        $this->client->createBucket($this->bucket);

        $response = $this->client->doesBucketExist($this->bucket);
        $this->assertTrue($response);
    }

    public function testSetBucketAcl() {
        $this->client->createBucket($this->bucket);

        $grant_list = array();
        $grant_list[] = array(
            'grantee' => array(
                array('id' => OWNER_ID),
                array('id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
            ),
            'permission' => array('FULL_CONTROL')
        );

        $response = $this->client->setBucketAcl($this->bucket, $grant_list);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
    }

    public function testSetBucketCannedAcl() {
        $this->client->createBucket($this->bucket);
        $response = $this->client->setBucketCannedAcl($this->bucket, 'public-read-write');
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
    }

    public function testGetBucketAcl() {
        $this->client->createBucket($this->bucket);
        $this->client->setBucketCannedAcl($this->bucket, 'public-read-write');

        $response = $this->client->getBucketAcl($this->bucket);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);

        $this->assertTrue(array_key_exists('owner', $response['body']));
        $this->assertTrue(array_key_exists('version', $response['body']));
        $this->assertTrue(array_key_exists('accessControlList', $response['body']));
        $this->assertEquals($response['body']['owner']['id'], OWNER_ID);
        $this->assertEquals($response['body']['version'], 1);
        $this->assertEquals($response['body']['accessControlList'][0], array(
            'grantee' => array(
                array('id' => '*')
            ),
            'permission' => array('READ', 'WRITE')
        ));
    }

    public function testListObjectsWithDelimiter() {
        $this->client->createBucket($this->bucket);

        $this->client->putObjectFromFile($this->bucket, 'dir0/a.php', __FILE__);
        $this->client->putObjectFromFile($this->bucket, 'dir0/b.php', __FILE__);
        $this->client->putObjectFromFile($this->bucket, 'dir0/c.php', __FILE__);
        $this->client->putObjectFromFile($this->bucket, 'dir0/d.php', __FILE__);
        $this->client->putObjectFromFile($this->bucket, 'dir0/dir1/a.php', __FILE__);
        $this->client->putObjectFromFile($this->bucket, 'dir0/dir2/a.php', __FILE__);
        $this->client->putObjectFromFile($this->bucket, 'dir0/dir3/a.php', __FILE__);
        $this->client->putObjectFromFile($this->bucket, 'dir1/dir2/a.php', __FILE__);
        $this->client->putObjectFromFile($this->bucket, 'dir2/dir1/a.php', __FILE__);

        $response = $this->client->listObjects($this->bucket, '/', 'dir0/b.php', 1, 'dir0/');
        $this->assertEquals($this->bucket, $response['body']['name']);
        $this->assertEquals('/', $response['body']['delimiter']);
        $this->assertEquals('dir0/b.php', $response['body']['marker']);
        $this->assertEquals('dir0/c.php', $response['body']['nextMarker']);
        $this->assertEquals(1, $response['body']['maxKeys']);
        $this->assertEquals('true', $response['body']['isTruncated']);
        $this->assertEquals(1, count($response['body']['contents']));
        $this->assertEquals('dir0/c.php', $response['body']['contents'][0]['key']);
        $this->assertEquals(array(), $response['body']['commonPrefixes']);


        $response = $this->client->listObjects($this->bucket, '/', null, 1000, 'dir0/');
        $this->assertEquals($this->bucket, $response['body']['name']);
        $this->assertEquals('/', $response['body']['delimiter']);
        $this->assertEquals('', $response['body']['marker']);
        $this->assertEquals(1000, $response['body']['maxKeys']);
        $this->assertEquals(false, $response['body']['isTruncated']);
        $this->assertEquals(4, count($response['body']['contents']));
        $this->assertEquals('dir0/a.php', $response['body']['contents'][0]['key']);
        $this->assertEquals(array(
            array('prefix' => 'dir0/dir1/'),
            array('prefix' => 'dir0/dir2/'),
            array('prefix' => 'dir0/dir3/'),
        ), $response['body']['commonPrefixes']);
    }

    public function testListObjectsWithMaxKeys() {
        $this->client->createBucket($this->bucket);
        for ($i = 0; $i < 9; $i ++) {
            $this->client->putObjectFromString($this->bucket,
                sprintf("test_object_%d", rand()), "This is a string.");
            $response = $this->client->listObjects($this->bucket);
            $this->checkProperties($response);
            $this->assertEquals(200, $response['status']);

            $all_list = array();
            $tmp_list = array();
            foreach ($response['body']['contents'] as $item) {
                $all_list[] = $item['key'];
            }

            $response = $this->client->listObjects($this->bucket, null, null, 4);
            $this->checkProperties($response);
            $this->assertEquals(200, $response['status']);
            foreach ($response['body']['contents'] as $item) {
                $tmp_list[] = $item['key'];
            }

            $marker = $tmp_list[count($tmp_list) - 1];
            $response = $this->client->listObjects($this->bucket, null, $marker, 5);
            $this->checkProperties($response);
            $this->assertEquals(200, $response['status']);
            foreach ($response['body']['contents'] as $item) {
                $tmp_list[] = $item['key'];
            }

            $this->assertEquals($all_list, $tmp_list);
        }
    }

    public function testListObjects() {
        $this->client->createBucket($this->bucket);

        for ($i = 0; $i < 10; $i ++) {
            $this->client->putObjectFromString($this->bucket,
                sprintf("test_object_%s", $i), "This is a string.");
        }

        $response = $this->client->listObjects($this->bucket);

        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals(false, $response['body']['isTruncated']);
        $this->assertEquals(1000, $response['body']['maxKeys']);
        $this->assertEquals($this->bucket, $response['body']['name']);
        $this->assertEquals("", $response['body']['prefix']);

        $contents = $response['body']['contents'];
        for ($i = 0; $i < 10; $i ++) {
            $this->assertEquals(sprintf("test_object_%s", $i), $contents[$i]['key']);
            $this->assertEquals(strlen('This is a string.'), $contents[$i]['size']);
        }
    }

    public function testPutObjectFromFileWithExtraHttpHeaders() {
        $this->client->createBucket($this->bucket);

        $options = array(
            // Not supported headers
            'Cache-Control' => 'private',
            'X-XSS-Protection' => '1; mode=block',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Location' => 'http://www.google.com/',

            // Support metadata headers
            'x-bce-meta-foo1' => 'bar1',
        );
        $response = $this->client->putObjectFromFile($this->bucket, $this->key, __FILE__, $options);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);

        $response = $this->client->getObjectMetadata($this->bucket, $this->key);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertFalse(isset($response['http_headers']['Cache-Control']));
        $this->assertFalse(isset($response['http_headers']['X-XSS-Protection']));
        $this->assertFalse(isset($response['http_headers']['X-Frame-Options']));
        $this->assertFalse(isset($response['http_headers']['Location']));
        $this->assertEquals('bar1', $response['http_headers']['x-bce-meta-foo1']);
    }

    public function testPutObjectFromFile() {
        $this->client->createBucket($this->bucket);

        $response = $this->client->putObjectFromFile($this->bucket, $this->key, __FILE__);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);

        $response = $this->client->getObjectMetadata($this->bucket, $this->key);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals('application/octet-stream', $response['http_headers']['Content-Type']);
        $this->assertEquals(md5(file_get_contents(__FILE__)), $response['http_headers']['ETag']);

        $url = $this->client->generatePresignedUrl($this->bucket, $this->key, 0, 1800, array('x-bce-range' => '9-19'));
        $this->assertEquals(md5(substr(file_get_contents(__FILE__), 9, 11)), md5(file_get_contents($url)));

        $url = $this->client->generatePresignedUrl($this->bucket, $this->key);
        $this->assertEquals(md5(file_get_contents(__FILE__)), md5(file_get_contents($url)));

        $response = $this->client->getObjectToFile($this->bucket, $this->key, $this->filename, '9-19');
        $this->checkProperties($response);
        $this->assertEquals(206, $response['status']);
        $this->assertEquals(file_get_contents($this->filename), substr(file_get_contents(__FILE__), 9, 11));
        $this->assertEquals(sprintf("bytes 9-19/%d", filesize(__FILE__)), $response['http_headers']['Content-Range']);
        $this->assertEquals(
            base64_encode(md5(file_get_contents(__FILE__), true)),
            $response['http_headers']['Content-MD5']
        );
    }

    public function testPutObjectFromString() {
        $this->client->createBucket($this->bucket);

        $response = $this->client->putObjectFromString($this->bucket, $this->key, 'Hello World');
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals(md5('Hello World'), $response['http_headers']['ETag']);

        $response = $this->client->putObjectFromString($this->bucket,
            'this/is/a/path', 'sdfdsfd');
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);

        $response = $this->client->putObjectFromString($this->bucket,
            '我/爱/北/京/天/安/门', '我/爱/北/京/天/安/门');
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);

        $response = $this->client->getObjectAsString($this->bucket,
            '我/爱/北/京/天/安/门');
        $this->assertEquals('我/爱/北/京/天/安/门', $response);

        $response = $this->client->listObjects($this->bucket);
        $contents = $response['body']['contents'];
        $this->assertEquals($this->key, $contents[0]['key']);
        $this->assertEquals('this/is/a/path', $contents[1]['key']);
        $this->assertEquals('我/爱/北/京/天/安/门', $contents[2]['key']);
    }

    public function testGetObjectMetadata() {
        $this->client->createBucket($this->bucket);

        $this->client->putObjectFromString($this->bucket, $this->key, 'Hello World', array(
            'x-bce-meta-foo1' => 'bar1',
            'foo1' => 'bar1',
        ));

        $response = $this->client->getObjectMetadata($this->bucket, $this->key);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('x-bce-meta-foo1', $response['http_headers']);
        $this->assertFalse(isset($response['http_headers']['foo1']));
        $this->assertEquals(strlen('Hello World'), $response['http_headers']['Content-Length']);
        $this->assertEquals(md5('Hello World'), $response['http_headers']['ETag']);
        $this->assertEquals(array(), $response['body']);

        // TODO(leeight) 默认的Content-Type设置的是有问题的
    }

    public function testCopyObject() {
        $this->client->createBucket($this->bucket);
        $this->client->putObjectFromString($this->bucket, $this->key, 'Hello World', array(
            'x-bce-meta-foo1' => 'bar1',
            'x-bce-meta-foo2' => 'bar2',
            'x-bce-meta-foo3' => 'bar3',
            'x-bce-meta-foo4' => 'bar4'
        ));

        $target_bucket = 'this-is-a-test-bucket';
        $this->client->createBucket($target_bucket);

        // x-bce-copy-source-if-match
        $response = $this->client->copyObject($this->bucket, $this->key, $target_bucket, $this->key, array(
            // ETag match
            'x-bce-copy-source-if-match' => "\"" . md5('Hello World') . "\""
        ));
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);

        $response = $this->client->getObjectMetadata($target_bucket, $this->key);
        $this->assertArrayHasKey('x-bce-meta-foo1', $response['http_headers']);
        $this->assertArrayHasKey('x-bce-meta-foo2', $response['http_headers']);
        $this->assertArrayHasKey('x-bce-meta-foo3', $response['http_headers']);
        $this->assertArrayHasKey('x-bce-meta-foo4', $response['http_headers']);

        // x-bce-metadata-directive
        $response = $this->client->copyObject($this->bucket, $this->key, $target_bucket, $this->key, array(
            // ETag match
            'x-bce-copy-source-if-match' => "\"" . md5('Hello World') . "\"",
            'x-bce-metadata-directive' => 'replace',
            'x-bce-meta-bar1' => 'foo1',
            'x-bce-meta-bar2' => 'foo2',
            'x-bce-meta-bar3' => 'foo3',
        ));
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);

        $response = $this->client->getObjectMetadata($target_bucket, $this->key);
        $this->assertFalse(isset($response['http_headers']['x-bce-meta-foo1']));
        $this->assertFalse(isset($response['http_headers']['x-bce-meta-foo2']));
        $this->assertFalse(isset($response['http_headers']['x-bce-meta-foo3']));
        $this->assertFalse(isset($response['http_headers']['x-bce-meta-foo4']));
        $this->assertArrayHasKey('x-bce-meta-bar1', $response['http_headers']);
        $this->assertArrayHasKey('x-bce-meta-bar2', $response['http_headers']);
        $this->assertArrayHasKey('x-bce-meta-bar3', $response['http_headers']);

        // listObjects check.
        $response = $this->client->listObjects($target_bucket);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);

        $contents = $response['body']['contents'];
        $this->assertEquals($this->key, $contents[0]['key']);

        // x-bce-copy-source-if-match
        try {
            $this->client->copyObject($this->bucket, $this->key, $target_bucket, $this->key, array(
                // ETag mismatch
                'x-bce-copy-source-if-match' => "\"" . md5('hello world') . "\""
            ));
        }
        catch(baidubce_exception_BceServiceException $ex) {
            $this->assertEquals(412, $ex->getStatusCode());
            $this->assertEquals('PreconditionFailed', $ex->getServiceErrorCode());
        }
    }

    public function testInitiateMultipartUpload() {
        $this->client->createBucket($this->bucket);

        $response = $this->client->initiateMultipartUpload($this->bucket, $this->key);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals($this->bucket, $response['body']['bucket']);
        $this->assertEquals($this->key, $response['body']['key']);
        $this->assertArrayHasKey('uploadId', $response['body']);

        $upload_id = $response['body']['uploadId'];
        $response = $this->client->abortMultipartUpload($this->bucket, $this->key, $upload_id);
        $this->checkProperties($response);
        $this->assertEquals(204, $response['status']);
    }

    public function testMultipartUploadSmallSuperfileWithMultiParts() {
        // superfile size is less than 1M and should upload with only one part.
        $file_size = 1 * 1024 * 1024 - 1;
        $this->client->createBucket($this->bucket);
        $this->prepareTemporaryFile($file_size);

        $response = $this->client->initiateMultipartUpload($this->bucket, $this->key);
        $upload_id = $response['body']['uploadId'];

        $left_size = filesize($this->filename);
        $offset = 0;
        $part_number = 1;
        $part_list = array();
        $etags = '';

        while ($left_size > 0) {
            $part_size = min(128 * 1024, $left_size);

            $response = $this->client->uploadPart($this->bucket, $this->key, $this->filename,
                $offset, $part_size, $upload_id, $part_number);
            $this->checkProperties($response);
            $this->assertEquals(200, $response['status']);
            $this->assertEquals(0, $response['http_headers']['Content-Length']);

            $part_list[] = array(
                'partNumber' => $part_number,
                'eTag' => $response['http_headers']['ETag'],
            );
            $etags .= $response['http_headers']['ETag'];
            $left_size -= $part_size;
            $offset += $part_size;
            $part_number += 1;
        }

        try {
            $this->client->completeMultipartUpload($this->bucket, $this->key, $upload_id, $part_list);
        }
        catch(baidubce_exception_BceServiceException $ex) {
            $this->assertEquals(400, $ex->getStatusCode());
            $this->assertEquals('EntityTooSmall', $ex->getServiceErrorCode());
        }
    }

    public function testMultipartUploadSmallSuperfile() {
        // superfile size is less than 1M
        $file_size = 1 * 1024 * 1024 - 1;
        $this->client->createBucket($this->bucket);
        $this->prepareTemporaryFile($file_size);

        $response = $this->client->initiateMultipartUpload($this->bucket, $this->key, null, array(
            'Content-Type' => 'foo/bar'
        ));
        $upload_id = $response['body']['uploadId'];

        $left_size = filesize($this->filename);
        $offset = 0;
        $part_number = 1;
        $part_list = array();
        $etags = '';

        while ($left_size > 0) {
            $part_size = min(1 * 1024 * 1024, $left_size);

            $response = $this->client->uploadPart($this->bucket, $this->key, $this->filename,
                $offset, $part_size, $upload_id, $part_number);
            $this->checkProperties($response);
            $this->assertEquals(200, $response['status']);
            $this->assertEquals(0, $response['http_headers']['Content-Length']);

            $part_list[] = array(
                'partNumber' => $part_number,
                'eTag' => $response['http_headers']['ETag'],
            );
            $etags .= $response['http_headers']['ETag'];
            $left_size -= $part_size;
            $offset += $part_size;
            $part_number += 1;
        }

        $response = $this->client->completeMultipartUpload($this->bucket, $this->key,
            $upload_id, $part_list);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('location', $response['body']);
        $this->assertEquals($this->bucket, $response['body']['bucket']);
        $this->assertEquals($this->key, $response['body']['key']);
        $this->assertEquals(md5_file($this->filename), $response['body']['eTag']);

        $response = $this->client->getObjectMetadata($this->bucket, $this->key);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals($file_size, $response['http_headers']['Content-Length']);
        $this->assertEquals('foo/bar', $response['http_headers']['Content-Type']);
        $this->assertEquals(md5_file($this->filename), $response['http_headers']['ETag']);
    }

    private function make_part_list_on_partnumber_order($a, $b) {
        return ($a['partNumber'] < $b['partNumber']) ? -1 : 1;
    }

    public function testMultipartUploadWithRandomPartNumberOrder() {
        // multipartUpload support random partNumber order
        $file_size = 20 * 1024 * 1024 + 317;
        $this->client->createBucket($this->bucket);
        $this->prepareTemporaryFile($file_size);

        $response = $this->client->initiateMultipartUpload($this->bucket, $this->key);
        $upload_id = $response['body']['uploadId'];

        $left_size = filesize($this->filename);
        $offset = 0;
        $part_number = 1;
        $part_list = array();

        $etags = array();
        $parts = array();

        while ($left_size > 0) {
            $part_size = min(5 * 1024 * 1024, $left_size);
            $parts[] = array($offset, $part_size, $part_number);
            $left_size -= $part_size;
            $offset += $part_size;
            $part_number += 1;
        }
        $last_element = array_pop($parts);
        shuffle($parts);
        $parts[] = $last_element;

        foreach ($parts as $part) {
            list($offset, $part_size, $part_number) = $part;
            $response = $this->client->uploadPart($this->bucket, $this->key, $this->filename,
                $offset, $part_size, $upload_id, $part_number);
            // printf("\n%s\n", json_encode($response));
            $this->checkProperties($response);
            $this->assertEquals(200, $response['status']);
            $this->assertEquals(0, $response['http_headers']['Content-Length']);

            $part_list[] = array(
                'partNumber' => $part_number,
                'eTag' => $response['http_headers']['ETag'],
            );
            $etags[$part_number - 1] = $response['http_headers']['ETag'];
        }
        usort($part_list, array($this, 'make_part_list_on_partnumber_order'));

        $response = $this->client->completeMultipartUpload($this->bucket, $this->key,
            $upload_id, $part_list);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('location', $response['body']);
        $this->assertEquals($this->bucket, $response['body']['bucket']);
        $this->assertEquals($this->key, $response['body']['key']);
        $this->assertEquals('-' . md5(implode('', $etags)), $response['body']['eTag']);

        $response = $this->client->getObjectMetadata($this->bucket, $this->key);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals($file_size, $response['http_headers']['Content-Length']);
        $this->assertEquals('-' . md5(implode('', $etags)), $response['http_headers']['ETag']);
    }

    public function testMultipartUploadFromString() {
        // superfile size is over 1M
        $file_size = 20 * 1024 * 1024 + 317;
        $this->client->createBucket($this->bucket);
        $this->prepareTemporaryFile($file_size);

        $response = $this->client->initiateMultipartUpload($this->bucket, $this->key);
        $upload_id = $response['body']['uploadId'];

        $left_size = filesize($this->filename);
        $offset = 0;
        $part_number = 1;
        $part_list = array();
        $etags = '';

        $fp = fopen($this->filename, 'r');
        while ($left_size > 0) {
            $part_size = min(5 * 1024 * 1024, $left_size);

            fseek($fp, $offset);
            $string = fread($fp, $part_size);
            $response = $this->client->uploadPartFromString($this->bucket, $this->key, $string, $upload_id, $part_number);

            $this->checkProperties($response);
            $this->assertEquals(200, $response['status']);
            $this->assertEquals(0, $response['http_headers']['Content-Length']);

            $part_list[] = array(
                'partNumber' => $part_number,
                'eTag' => $response['http_headers']['ETag'],
            );
            $etags .= $response['http_headers']['ETag'];
            $left_size -= $part_size;
            $offset += $part_size;
            $part_number += 1;
        }
        fclose($fp);

        $response = $this->client->completeMultipartUpload($this->bucket, $this->key,
            $upload_id, $part_list);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('location', $response['body']);
        $this->assertEquals($this->bucket, $response['body']['bucket']);
        $this->assertEquals($this->key, $response['body']['key']);
        $this->assertEquals('-' . md5($etags), $response['body']['eTag']);

        $response = $this->client->getObjectMetadata($this->bucket, $this->key);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals($file_size, $response['http_headers']['Content-Length']);
        $this->assertEquals(baidubce_util_Coder::guessMimeType($this->key), $response['http_headers']['Content-Type']);
        $this->assertEquals('-' . md5($etags), $response['http_headers']['ETag']);
    }

    public function testMultipartUpload() {
        // superfile size is over 1M
        $file_size = 20 * 1024 * 1024 + 317;
        $this->client->createBucket($this->bucket);
        $this->prepareTemporaryFile($file_size);

        $response = $this->client->initiateMultipartUpload($this->bucket, $this->key);
        $upload_id = $response['body']['uploadId'];

        $left_size = filesize($this->filename);
        $offset = 0;
        $part_number = 1;
        $part_list = array();
        $etags = '';

        while ($left_size > 0) {
            $part_size = min(5 * 1024 * 1024, $left_size);

            $response = $this->client->uploadPart($this->bucket, $this->key, $this->filename,
                $offset, $part_size, $upload_id, $part_number);
            $this->checkProperties($response);
            $this->assertEquals(200, $response['status']);
            $this->assertEquals(0, $response['http_headers']['Content-Length']);

            $part_list[] = array(
                'partNumber' => $part_number,
                'eTag' => $response['http_headers']['ETag'],
            );
            $etags .= $response['http_headers']['ETag'];
            $left_size -= $part_size;
            $offset += $part_size;
            $part_number += 1;
        }

        $response = $this->client->completeMultipartUpload($this->bucket, $this->key,
            $upload_id, $part_list);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('location', $response['body']);
        $this->assertEquals($this->bucket, $response['body']['bucket']);
        $this->assertEquals($this->key, $response['body']['key']);
        $this->assertEquals('-' . md5($etags), $response['body']['eTag']);

        $response = $this->client->getObjectMetadata($this->bucket, $this->key);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals($file_size, $response['http_headers']['Content-Length']);
        $this->assertEquals(baidubce_util_Coder::guessMimeType($this->key), $response['http_headers']['Content-Type']);
        $this->assertEquals('-' . md5($etags), $response['http_headers']['ETag']);
    }

    public function testListParts() {
        $this->client->createBucket($this->bucket);
        $this->prepareTemporaryFile(5 * 1024 * 1024);

        $time1 = baidubce_util_Time::BceTimeNow();
        $response = $this->client->initiateMultipartUpload($this->bucket, $this->key);
        $upload_id = $response['body']['uploadId'];

        $time2 = baidubce_util_Time::BceTimeNow();
        $offset = 0;
        $size = 100;
        $part_number = 1;
        $response = $this->client->uploadPart($this->bucket, $this->key, $this->filename,
            $offset, $size, $upload_id, $part_number);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals(0, $response['http_headers']['Content-Length']);
        $this->assertEquals('6d0bb00954ceb7fbee436bb55a8397a9', $response['http_headers']['ETag']);

        $response = $this->client->listParts($this->bucket, $this->key, $upload_id);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals($this->bucket, $response['body']['bucket']);
        $this->assertEquals(false, $response['body']['isTruncated']);
        $this->assertEquals($time1, $response['body']['initiated']);
        $this->assertEquals($upload_id, $response['body']['uploadId']);
        $this->assertEquals(1, $response['body']['nextPartNumberMarker']);
        $this->assertEquals(0, $response['body']['partNumberMarker']);
        $this->assertEquals(OWNER_ID, $response['body']['owner']['id']);
        $this->assertEquals(OWNER_PASSPORT_ID, $response['body']['owner']['displayName']);

        foreach ($response['body']['parts'] as $item) {
            $this->assertEquals('6d0bb00954ceb7fbee436bb55a8397a9', $item['eTag']);
            $this->assertEquals(100, $item['size']);
            $this->assertEquals(1, $item['partNumber']);
            $this->assertEquals($time2, $item['lastModified']);
        }
    }

    public function testListMultipartUploads() {
        $this->client->createBucket($this->bucket);

        $time1 = baidubce_util_Time::BceTimeNow();
        $response = $this->client->initiateMultipartUpload($this->bucket, 'aaa');
        $upload_id1 = $response['body']['uploadId'];

        $time2 = baidubce_util_Time::BceTimeNow();
        $response = $this->client->initiateMultipartUpload($this->bucket, 'bbb');
        $upload_id2 = $response['body']['uploadId'];

        $response = $this->client->listMultipartUploads($this->bucket);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals(1000, $response['body']['maxUploads']);
        $this->assertEquals('', $response['body']['prefix']);
        $this->assertEquals('', $response['body']['keyMarker']);
        $this->assertEquals(false, $response['body']['isTruncated']);
        $this->assertEquals(2, count($response['body']['uploads']));
        $this->assertEquals($upload_id1, $response['body']['uploads'][0]['uploadId']);
        $this->assertEquals($time1, $response['body']['uploads'][0]['initiated']);
        $this->assertEquals($upload_id2, $response['body']['uploads'][1]['uploadId']);
        $this->assertEquals($time2, $response['body']['uploads'][1]['initiated']);

        $response = $this->client->listMultipartUploads($this->bucket, '', 1);
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertEquals(1, $response['body']['maxUploads']);
        $this->assertEquals('', $response['body']['prefix']);
        $this->assertEquals('', $response['body']['keyMarker']);
        $this->assertEquals('true', $response['body']['isTruncated']);
        $this->assertEquals(1, count($response['body']['uploads']));
        $this->assertEquals($upload_id1, $response['body']['uploads'][0]['uploadId']);
        $this->assertEquals($time1, $response['body']['uploads'][0]['initiated']);
    }

    /**
     * @group ignore
     */
    public function testDumpObjectMeta() {
        $bucket = getenv('BUCKET');
        $object = getenv('OBJECT');
        $response = $this->client->getObjectMetadata($bucket, $object);
        print_r($response);
    }

    /**
     * Run this test case manually
     * env LARGE_FILE_PATH=large_file_path php phpunit.phar --filter=testMultipartUploadByManual
     * @group ignore
     */
    public function testMultipartUploadByManual() {
        $this->client->createBucket($this->bucket);
        $large_file = getenv('LARGE_FILE_PATH');
        if ($large_file === false) {
            $this->fail('Please set LARGE_FILE_PATH environment variable.');
            return;
        }

        if (!is_file($large_file) || !file_exists($large_file)) {
            $this->fail('No such file or not a regular file');
            return;
        }

        $object_name = basename($large_file);

        $response = $this->client->initiateMultipartUpload($this->bucket, $object_name, $large_file);
        $upload_id = $response['body']['uploadId'];

        $left_size = filesize($large_file);
        $offset = 0;
        $part_number = 1;
        $part_count = intval(ceil($left_size * 1.0 / (5 * 1024 * 1024)));
        $part_list = array();
        $etags = '';

        while ($left_size > 0) {
            $part_size = min(5 * 1024 * 1024, $left_size);

            $response = $this->client->uploadPart($this->bucket, $object_name, $large_file,
                $offset, $part_size, $upload_id, $part_number);
            $this->checkProperties($response);
            $this->assertEquals(200, $response['status']);
            $this->assertEquals(0, $response['http_headers']['Content-Length']);
            printf("%d/%d\n", $part_number, $part_count);
            flush();
            ob_flush();

            $part_list[] = array(
                'partNumber' => $part_number,
                'eTag' => $response['http_headers']['ETag'],
            );
            $etags .= $response['http_headers']['ETag'];
            $left_size -= $part_size;
            $offset += $part_size;
            $part_number += 1;
        }

        $response = $this->client->completeMultipartUpload($this->bucket, $object_name,
            $upload_id, $part_list, array('Content-Type' => baidubce_util_Coder::guessMimeType($large_file)));
        $this->checkProperties($response);
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('location', $response['body']);
        $this->assertEquals($this->bucket, $response['body']['bucket']);
        $this->assertEquals($object_name, $response['body']['key']);
        $this->assertEquals('-' . md5($etags), $response['body']['eTag']);

        $url = $this->client->generatePresignedUrl($this->bucket, $object_name);
        printf("\nDownload url = %s\n", $url);
    }
}





/* vim: set ts=4 sw=4 sts=4 tw=120: */
