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

use baidubce\exception\BceIllegalArgumentException;

require_once dirname(dirname(dirname(__DIR__))). "/exception/BceIllegalArgumentException.php";

class BosConstraint {
    public static function checkBucketName($bucket_name) {
        $bucket_name_length = strlen($bucket_name);
        if ($bucket_name_length < 3 || $bucket_name_length > 63) {
            throw new BceIllegalArgumentException("bucket name Illegal");
        }

        $bucket_name_pattern = "/^[a-z0-9][-0-9a-z]*[a-z0-9]$/";
        if (!preg_match($bucket_name_pattern, $bucket_name)) {
            throw new BceIllegalArgumentException("bucket name Illegal");
        }
    }

    public static function checkObjectName($object_name) {
        $object_name_length = strlen($object_name);
        if ($object_name_length > 1024 || $object_name_length < 1) {
            throw new BceIllegalArgumentException("object name name Illegal");
        }
    }

} 