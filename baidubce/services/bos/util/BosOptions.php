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
namespace baidubce\bos\util;


class BosOptions {
    const ACCESS_KEY_ID = 'AccessKeyId';
    const ACCESS_KEY_SECRET = 'AccessKeySecret';
    const ENDPOINT = 'Endpoint';
    const CHARSET = 'Charset';

    const BUCKET = 'Bucket';
    const OBJECT = 'Object';

    const OBJECT_CONTENT_STRING = "ObjectContentString";
    const OBJECT_CONTENT_STREAM = "ObjectDataStream";

    const OBJECT_COPY_SOURCE = "CopySource";
    const OBJECT_COPY_SOURCE_IF_MATCH_TAG = "IfMatchTag";
    const OBJECT_COPY_METADATA_DIRECTIVE = "MetadataDirective";

    //const BUCKET_LOCATION = "BucketLocation";

    const LIST_DELIMITER = "Delimiter";
    const LIST_MARKER = "Marker";
    const LIST_MAX_KEY_SIZE = "ListMaxKeySize";
    const LIST_PREFIX = "ListPrefix";
    const LIST_MAX_UPLOAD_SIZE =  "ListMaxUploadSize";

    const ACL = "Acl";

    const UPLOAD_ID = "UploadId";
    const PART_NUM = "PartNum";
    const PART_LIST = "PartList";

    const CONTENT_LENGTH = "Content-Length";
    const CONTENT_TYPE = "Content-Type";

    const MAX_PARTS_COUNT = "MaxPartsCount";
    const PART_NUMBER_MARKER = "PArtNumberMarker";
} 