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

namespace baidubce\services\bos;

require_once dirname(dirname(dirname(__DIR__))) . '/baidubce/Bce.php';
require_once dirname(dirname(dirname(__DIR__))) . '/baidubce/Exception.php';
require_once dirname(dirname(dirname(__DIR__))) . '/baidubce/BceBaseClient.php';
require_once dirname(dirname(dirname(__DIR__))) . '/baidubce/auth/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/baidubce/auth/BceCredentials.php';
require_once dirname(dirname(dirname(__DIR__))) . '/baidubce/http/HttpClient.php';
require_once dirname(dirname(dirname(__DIR__))) . '/baidubce/http/HttpHeaders.php';
require_once dirname(dirname(dirname(__DIR__))) . '/baidubce/http/HttpContentTypes.php';
require_once dirname(dirname(dirname(__DIR__))) . '/baidubce/http/HttpMethod.php';
require_once dirname(dirname(dirname(__DIR__))) . '/baidubce/util/Coder.php';

use baidubce\Bce;
use baidubce\BceServerError;
use baidubce\BceBaseClient;
use baidubce\auth\Auth;
use baidubce\auth\BceCredentials;
use baidubce\http\HttpClient;
use baidubce\http\HttpHeaders;
use baidubce\http\HttpContentTypes;
use baidubce\http\HttpMethod;
use baidubce\util\Coder;

class BosClient extends BceBaseClient {
    const MIN_PART_SIZE = 5242880;                // 5M
    const MAX_PUT_OBJECT_LENGTH = 5368709120;     // 5G
    const MAX_USER_METADATA_SIZE = 2048;          // 2 * 1024
    const MIN_PART_NUMBER = 1;
    const MAX_PART_NUMBER = 10000;

    /**
     * @type baidubce\auth\Auth
     */
    private $auth;

    /**
     * The BosClient constructor
     *
     * @param array $config The client configuration
     */
    function __construct(array $config) {
        parent::__construct($config, 'bos');
        $this->auth = new Auth(new BceCredentials($config['credentials']));
    }

    // --- B E G I N ---

    /**
     * Get an authorization url with expire time
     *
     * @param string $bucket_name The bucket name.
     * @param string $object_name The object path.
     * @param number $timestamp
     * @param number $expiration_in_seconds The valid time in seconds.
     * @param mixed $options The extra http request headers or params.
     *
     * @return string
     */
    public function generatePresignedUrl($bucket_name, $key,
                                         $timestamp = 0,
                                         $expiration_in_seconds = 1800,
                                         $headers = array(),
                                         $params = array(),
                                         $headers_to_sign = array(),
                                         $config = array()) {

        $config = array_merge(array(), $this->config, $config);

        $path = $this->_getPath($config, $bucket_name, $key);

        $headers[HttpHeaders::HOST] = preg_replace('/(\w+:\/\/)?([^\/]+)\/?/', '$2',
            $config['endpoint']);

        $authorization = $this->auth->generateAuthorization(
            HttpMethod::GET, $path, $params, $headers, $timestamp, $expiration_in_seconds,
            $headers_to_sign);

        return sprintf("%s%s?authorization=%s", $config['endpoint'],
            $path, Coder::urlEncode($authorization));
    }

    /**
     * List buckets of user.
     *
     * @return mixed All of the available buckets.
     */
    public function listBuckets($config = array()) {
        return $this->_sendRequest(HttpMethod::GET, array(
            'config' => $config,
        ));
    }

    /**
     * Create a new bucket.
     *
     * @param string $bucket_name The bucket name.
     *
     * @return mixed
     */
    public function createBucket($bucket_name, $config = array()) {
        return $this->_sendRequest(HttpMethod::PUT, array(
            'bucket_name' => $bucket_name,
            'config' => $config,
        ));
    }

    /**
     * Get Object Information of bucket.
     *
     * @param string $bucket_name The bucket name.
     * @param string $delimiter The default value is null.
     * @param string $marker The default value is null.
     * @param number $max_keys The default value is 1000.
     * @param string $prefix The default value is null.
     *
     * @return mixed
     */
    public function listObjects($bucket_name, $max_keys = 1000,
                                $prefix = null, $marker = null,
                                $delimiter = null, $config = array()) {
        $params = array();
        if (!is_null($max_keys)) { $params['maxKeys'] = $max_keys; }
        if (!is_null($prefix)) { $params['prefix'] = $prefix; }
        if (!is_null($marker)) { $params['marker'] = $marker; }
        if (!is_null($delimiter)) { $params['delimiter'] = $delimiter; }

        return $this->_sendRequest(HttpMethod::GET, array(
            'bucket_name' => $bucket_name,
            'params' => $params,
            'config' => $config,
        ));
    }

    /**
     * Check whether there is some user access to this bucket.
     *
     * @param string $bucket_name The bucket name.
     *
     * @return boolean true means the bucket does exists.
     */
    public function doesBucketExist($bucket_name, $config = array()) {
        try {
            $this->_sendRequest(HttpMethod::HEAD, array(
                'bucket_name' => $bucket_name,
                'config' => $config,
            ));
            return true;
        }
        catch(BceServerError $e) {
            if ($e->status_code === 403) {
                return true;
            }
            if ($e->status_code === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Delete a Bucket(Must Delete all the Object in Bucket before)
     *
     * @param string $bucket_name The bucket name.
     * @return mixed
     */
    public function deleteBucket($bucket_name, $config = array()) {
        return $this->_sendRequest(HttpMethod::DELETE, array(
            'bucket_name' => $bucket_name,
            'config' => $config
        ));
    }

    /**
     * Set Access Control Level of bucket
     *
     * @param string $bucket_name The bucket name.
     * @param string $acl The grant list.
     * @return mixed
     */
    public function setBucketCannedAcl($bucket_name, $canned_acl, $config = array()) {
        return $this->_sendRequest(HttpMethod::PUT, array(
            'bucket_name' => $bucket_name,
            'headers' => array(
                HttpHeaders::BCE_ACL => $canned_acl,
            ),
            'params' => array('acl' => ''),
            'config' => $config,
        ));
    }

    /**
     * Set Access Control Level of bucket
     *
     * @param string $bucket_name The bucket name.
     * @param mixed $acl The grant list.
     * @return mixed
     */
    public function setBucketAcl($bucket_name, $acl, $config = array()) {
        return $this->_sendRequest(HttpMethod::PUT, array(
            'bucket_name' => $bucket_name,
            'body' => json_encode(array('accessControlList' => $acl)),
            'headers' => array(
                HttpHeaders::CONTENT_TYPE => HttpContentTypes::JSON,
            ),
            'params' => array('acl' => ''),
            'config' => $config,
        ));
    }

    /**
     * Get Access Control Level of bucket
     *
     * @param string $bucket_name The bucket name.
     * @return mixed
     */
    public function getBucketAcl($bucket_name, $config = array()) {
        return $this->_sendRequest(HttpMethod::GET, array(
            'bucket_name' => $bucket_name,
            'params' => array('acl' => ''),
            'config' => $config,
        ));
    }

    /**
     * Create object and put content of string to the object
     *
     * @param string $bucket_name The bucket name.
     * @param string $object_name The object path.
     * @param string $input_content The object content.
     * @param mixed $options
     *
     * @return mixed
     */
    public function putObjectFromString($bucket_name, $key, $data,
                                        $headers = array(), $config = array()) {
        $object_headers = array_merge(array(
            HttpHeaders::CONTENT_LENGTH => strlen($data),
            HttpHeaders::CONTENT_MD5 => base64_encode(md5($data, true)),
        ), $headers);

        return $this->putObject($bucket_name, $key, $data, $object_headers, $config);
    }

    /**
     * Put object and copy content of file to the object
     *
     * @param string $bucket_name The bucket name.
     * @param string $object_name The object path.
     * @param string $file_name The absolute file path.
     * @param mixed $options
     *
     * @return mixed
     */
    public function putObjectFromFile($bucket_name, $key, $filename,
                                      $headers = array(), $config = array()) {

        $object_headers = array_merge(array(
            HttpHeaders::CONTENT_LENGTH => filesize($filename),
            HttpHeaders::CONTENT_TYPE => Coder::guessMimeType($filename),
            HttpHeaders::CONTENT_MD5 => base64_encode(md5_file($filename, true)),
        ), $headers);

        $fp = fopen($filename, 'rb');
        try {
            $response = $this->putObject($bucket_name, $key, $fp, $object_headers, $config);
            fclose($fp);
            return $response;
        }
        catch(Exception $ex) {
            fclose($fp);
            throw $ex;
        }
    }

    public function getObject($bucket_name, $key, $range = null, $config = array()) {
        $output_stream = fopen('php://memory', 'r+');
        $response = $this->_sendRequest(HttpMethod::GET, array(
            'bucket_name' => $bucket_name,
            'key' => $key,
            'headers' =>  array(
                HttpHeaders::RANGE => is_null($range) ? '' : sprintf("bytes=%s", $range),
            ),
            'config' => $config,
            // 避免 HttpClient 解析 ResponseBody 的内容
            'output_stream' => $output_stream,
        ));
        rewind($output_stream);
        $response['body'] = stream_get_contents($output_stream);

        return $response;
    }

    public function getObjectAsString($bucket_name, $key, $range = null, $config = array()) {
        $response = $this->getObject($bucket_name, $key, $range, $config);
        return $response['body'];
    }

    /**
     * Get Content of Object and Put Content to File
     *
     * @param string $bucket_name The bucket name.
     * @param string $object_name The object path.
     * @param string $file_name The destination file name.
     * @param string $range The HTTP 'Range' header.
     *
     * @return mixed
     */
    public function getObjectToFile($bucket_name, $key, $filename, $range = null, $config = array()) {
        $output_stream = fopen($filename, 'w+');
        try {
            $response = $this->_sendRequest(HttpMethod::GET, array(
                'bucket_name' => $bucket_name,
                'key' => $key,
                'headers' => array(
                    HttpHeaders::RANGE => is_null($range) ? '' : sprintf("bytes=%s", $range),
                ),
                'config' => $config,
                'output_stream' => $output_stream,
            ));
            fclose($output_stream);
            return $response;
        }
        catch(BceServerError $ex) {
            fclose($output_stream);
            throw $ex;
        }
    }

    /**
     * Delete Object
     *
     * @param string $bucket_name The bucket name.
     * @param string $object_name The object path.
     *
     * @return mixed
     */
    public function deleteObject($bucket_name, $key, $config = array()) {
        return $this->_sendRequest(HttpMethod::DELETE, array(
            'bucket_name' => $bucket_name,
            'key' => $key,
            'config' => $config,
        ));
    }

    public function putObject($bucket_name, $key, $data,
                              $headers = array(), $config = array()) {

        if (empty($key)) {
            throw new \InvalidArgumentException('key should not be empty.');
        }

        list($object_headers, $has_user_metadata) = $this->_prepareObjectHeaders($headers);

        return $this->_sendRequest(HttpMethod::PUT, array(
            'bucket_name' => $bucket_name,
            'key' => $key,
            'body' => $data,
            'headers' => $object_headers,
            'config' => $config,
        ));
    }

    /**
     * Get Object meta information
     *
     * @param string $bucket_name The bucket name.
     * @param string $object_name The object path.
     *
     * @return mixed
     */
    public function getObjectMetadata($bucket_name, $key, $config = array()) {
        return $this->_sendRequest(HttpMethod::HEAD, array(
            'bucket_name' => $bucket_name,
            'key' => $key,
            'config' => $config,
        ));
    }

    /**
     * Copy one object to another.
     *
     * @param string $source_bucket The source bucket name.
     * @param string $source_key The source object path.
     * @param string $target_bucket The target bucket name.
     * @param string $target_key The target object path.
     * @param mixed $options
     *
     * @return mixed
     */
    public function copyObject($source_bucket_name, $source_key,
                               $target_bucket_name, $target_key,
                               $headers = array(), $config = array()) {

        if (empty($source_bucket_name)) { throw new \InvalidArgumentException('source_bucket_name should not be empty or None.'); }
        if (empty($source_key)) { throw new \InvalidArgumentException('source_key should not be empty or None.'); }
        if (empty($target_bucket_name)) { throw new \InvalidArgumentException('target_bucket_name should not be empty or None.'); }
        if (empty($target_key)) { throw new \InvalidArgumentException('target_key should not be empty or None.'); }

        list($object_headers, $has_user_metadata) = $this->_prepareObjectHeaders($headers);

        $object_headers[HttpHeaders::BCE_COPY_SOURCE] = Coder::urlEncodeExceptSlash(
            sprintf("/%s/%s", $source_bucket_name, $source_key));
        if (isset($object_headers[HttpHeaders::ETAG])) {
            $object_headers[HttpHeaders::BCE_COPY_SOURCE_IF_MATCH] =
                $object_headers[HttpHeaders::ETAG];
        }
        $object_headers[HttpHeaders::BCE_COPY_METADATA_DIRECTIVE] =
            $has_user_metadata ? 'replace' : 'copy';

        return $this->_sendRequest(HttpMethod::PUT, array(
            'bucket_name' => $target_bucket_name,
            'key' => $target_key,
            'headers' => $object_headers,
            'config' => $config,
        ));
    }

    /**
     * Initialize multi_upload_file.
     *
     * @param string $bucket_name The bucket name.
     * @param string $object_name The object path.
     * @param string $file_name Init the content-type by file name extension.
     *
     * @return mixed
     */
    public function initiateMultipartUpload($bucket_name, $key, $config = array()) {
        $content_type = Coder::guessMimeType($key);
        $headers = array(
            HttpHeaders::CONTENT_TYPE => $content_type,
        );
        return $this->_sendRequest(HttpMethod::POST, array(
            'bucket_name' => $bucket_name,
            'key' => $key,
            'params' => array('uploads' => ''),
            'headers' => $headers,
            'config' => $config,
        ));
    }

    /**
     * Abort upload a part which is being uploading.
     *
     * @param string $bucket_name The bucket name.
     * @param string $object_name The object path.
     * @param string $upload_id The uploadId returned by initiateMultipartUpload.
     *
     * @return mixed
     */
    public function abortMultipartUpload($bucket_name, $key, $upload_id, $config = array()) {
        return $this->_sendRequest(HttpMethod::DELETE, array(
            'bucket_name' => $bucket_name,
            'key' => $key,
            'params' => array('uploadId' => $upload_id),
        ));
    }

    /**
     * Upload a part from starting with offset.
     *
     * @param string $bucket_name The bucket name.
     * @param string $object_name The object path.
     * @param string $file_name The file which will be uploaded.
     * @param number $offset The file offset.
     * @param number $part_size The uploaded part size.
     * @param string $upload_id The uploadId returned by initiateMultipartUpload.
     * @param number $part_number The part index.
     * @param mixed $options The extra http request headers or params.
     *
     * @return mixed
     */
    public function uploadPart($bucket_name, $key, $upload_id,
                               $part_number, $part_size, $part_fp, $part_md5 = null,
                               $config = array()) {

        if (empty($bucket_name)) { throw new \InvalidArgumentException('bucket_name should not be empty or None.'); }
        if (empty($key)) { throw new \InvalidArgumentException('key should not be empty or None.'); }
        if ($part_number < BosClient::MIN_PART_NUMBER || $part_number > BosClient::MAX_PART_NUMBER) {
            throw new \InvalidArgumentException("Invalid part_number %d. The valid range is from %d to %d.",
                $part_number, BosClient::MIN_PART_NUMBER, BosClient::MAX_PART_NUMBER);
        }

        if (is_null($part_md5)) {
            $part_md5 = base64_encode(Coder::md5FromStream($part_fp, 0, -1, true));
        }

        $headers = array(
            HttpHeaders::CONTENT_LENGTH => $part_size,
            HttpHeaders::CONTENT_TYPE => HttpContentTypes::OCTET_STREAM,
            HttpHeaders::CONTENT_MD5 => $part_md5,
        );

        return $this->_sendRequest(HttpMethod::PUT, array(
            'bucket_name' => $bucket_name,
            'key' => $key,
            'body' => $part_fp,
            'headers' => $headers,
            'params' => array('partNumber' => $part_number, 'uploadId' => $upload_id),
            'config' => $config,
        ));
    }

    public function uploadPartFromFile($bucket_name, $key, $upload_id,
                                       $part_number, $part_size, $filename, $offset,
                                       $part_md5 = null, $config = array()) {
        $fp = fopen($filename, 'r');
        $part_fp = fopen('php://memory', 'r+');
        stream_copy_to_stream($fp, $part_fp, $part_size, $offset);
        rewind($part_fp);
        fclose($fp);

        try {
            $response = $this->uploadPart($bucket_name, $key, $upload_id,
                $part_number, $part_size, $part_fp, $part_md5, $config);
            fclose($part_fp);
            return $response;
        }
        catch(BceServerError $ex) {
            fclose($part_fp);
            throw $ex;
        }
    }

    /**
     * List all the parts that have been upload success.
     *
     * @param string $bucket_name The bucket name.
     * @param string $object_name The object path.
     * @param string $upload_id The uploadId returned by initiateMultipartUpload.
     * @param number $max_keys The maximum size of returned parts, default 1000, maximum value is 1000.
     * @param string $part_number_marker Sort by uploaded partnumber, and returned parts from this given value.
     *
     * @return mixed
     */
    public function listParts($bucket_name, $key, $upload_id,
                              $max_parts = null, $part_number_marker = null,
                              $config = array()) {

        if (empty($upload_id)) {
            throw new \InvalidArgumentException('upload_id should not be None.');
        }

        $params = array(
            'uploadId' => $upload_id,
        );
        if (!is_null($max_parts)) { $params['maxParts'] = $max_parts; }
        if (!is_null($part_number_marker)) { $params['partNumberMarker'] = $part_number_marker; }

        return $this->_sendRequest(HttpMethod::GET, array(
            'bucket_name' => $bucket_name,
            'key' => $key,
            'params' => $params,
            'config' => $config,
        ));
    }

    /**
     * After finish all the task, complete multi_upload_file.
     * bucket, key, upload_id, part_list, options=None
     * @param string $bucket_name The bucket name.
     * @param string $object_name The object path.
     * @param string $upload_id The upload id.
     * @param mixed $part_list (partnumber and etag) list
     * @param mixed $options extra http request header and params.
     *
     * @return mixed
     */
    public function completeMultipartUpload($bucket_name, $key, $upload_id,
                                            $part_list, $headers = array(),
                                            $config = array()) {

        $headers[HttpHeaders::CONTENT_TYPE] = HttpContentTypes::JSON;
        list($object_headers, $has_user_metadata) = $this->_prepareObjectHeaders($headers);

        return $this->_sendRequest(HttpMethod::POST, array(
            'bucket_name' => $bucket_name,
            'key' => $key,
            'body' => json_encode(array('parts' => $part_list)),
            'headers' => $object_headers,
            'params' => array('uploadId' => $upload_id),
            'config' => $config,
        ));
    }

    /**
     * List all Multipart upload task which haven't been ended.
     * call initiateMultipartUpload but not call completeMultipartUpload or abortMultipartUpload
     *
     * @param string $bucket_name The bucket name.
     * @param string $delimiter
     * @param number $max_uploads The default value is 1000.
     * @param string $key_marker
     * @param string $prefix
     * @param string $upload_id_marker
     *
     * @return mixed
     */
    public function listMultipartUploads($bucket_name, $max_uploads = null,
                                         $key_marker = null, $prefix = null,
                                         $delimiter = null, $config = array()) {

        $params = array('uploads' => '');

        if (!is_null($delimiter)) { $params['delimiter'] = $delimiter; }
        if (!is_null($max_uploads)) { $params['maxUploads'] = $max_uploads; }
        if (!is_null($key_marker)) { $params['keyMarker'] = $key_marker; }
        if (!is_null($prefix)) { $params['prefix'] = $prefix; }

        return $this->_sendRequest(HttpMethod::GET, array(
            'bucket_name' => $bucket_name,
            'params' => $params,
            'config' => $config,
        ));
    }


    public function createSignature($credentials, $http_method, $path, $params, $headers) {
        // IGNORE $credentials
        return $this->auth->generateAuthorization($http_method, $path, $params, $headers);
    }

    // --- E N D ---

    private function _prepareObjectHeaders($headers = null) {
        if (is_null($headers)) {
            return array(array(), false);
        }

        $allowed_headers = array(
            HttpHeaders::CONTENT_LENGTH,
            HttpHeaders::CONTENT_ENCODING,
            HttpHeaders::CONTENT_MD5,
            HttpHeaders::CONTENT_TYPE,
            HttpHeaders::CONTENT_DISPOSITION,
            HttpHeaders::ETAG,
        );

        $meta_size = 0;
        $object_headers = array();
        foreach ($headers as $key => $val) {
            if (in_array($key, $allowed_headers)) {
                $object_headers[$key] = $val;
            }
            else if (strpos($key, HttpHeaders::BCE_USER_METADATA_PREFIX) === 0) {
                $object_headers[$key] = $val;
                $meta_size += strlen($val);
            }
        }

        if ($meta_size > BosClient::MAX_USER_METADATA_SIZE) {
            throw new \InvalidArgumentException(sprintf("Metadata size should not be greater than %d.",
                BosClient::MAX_USER_METADATA_SIZE));
        }

        if (isset($object_headers[HttpHeaders::CONTENT_LENGTH])) {
            $content_length = $object_headers[HttpHeaders::CONTENT_LENGTH];
            if ($content_length && $content_length < 0) {
                throw new \InvalidArgumentException('content_length should not be negative.');
            } else if ($content_length > BosClient::MAX_PUT_OBJECT_LENGTH) {
                throw new \InvalidArgumentException(sprintf("Object length should be less than %d. Use multi-part upload instead.",
                    BosClient::MAX_PUT_OBJECT_LENGTH));
            }
        }

        if (isset($object_headers[HttpHeaders::ETAG])) {
            $etag = $object_headers[HttpHeaders::ETAG];
            if (trim($etag, "\"") === $etag) {
                $object_headers[HttpHeaders::ETAG] = sprintf("\"%s\"", $etag);
            }
        }

        if (!isset($object_headers[HttpHeaders::CONTENT_TYPE])) {
            $object_headers[HttpHeaders::CONTENT_TYPE] =
                HttpContentTypes::OCTET_STREAM;
        }

        return array($object_headers, $meta_size > 0);
    }

    private function _sendRequest($http_method, $var_args) {
        $default_args = array(
            'bucket_name' => null,
            'key' => null,
            'body' => null,
            'headers' => array(),
            'params' => array(),
            'config' => array(),
            'output_stream' => null,
        );

        $args = array_merge($default_args, $var_args);
        $config = array_merge(array(), $this->config, $args['config']);
        $path = $this->_getPath($config, $args['bucket_name'], $args['key']);

        $http_client = new HttpClient($config);
        return $http_client->sendRequest(
            $http_method,                           /* http_method */
            $path,                                  /* path */
            $args['body'],                          /* body */
            $args['headers'],                       /* headers */
            $args['params'],                        /* params */
            array($this, 'createSignature'),        /* sign_function */
            $args['output_stream']                  /* output_stream */
        );
    }

    private function _getPath($config, $bucket_name = null, $key = null) {
        return Coder::appendUri(Bce::URL_PREFIX, $bucket_name, $key);
    }
}
