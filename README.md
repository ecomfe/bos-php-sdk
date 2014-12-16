## bos-php-sdk

RESTful api 文档：<http://gollum.baidu.com/BOS_API>

### 环境依赖

```
git clone http://gitlab.baidu.com/inf/bos-php-sdk.git
```

### php < 5.3

如果是在开发机上面，并且通过`jumbo install odp-php odp-php-pear-PHPUnit`安装的php，那么默认的版本可能是`php 5.2.17`，此时应该使用`git checkout php-5.2`这个分支的代码。

### php >= 5.3

默认的`master`分支代码应该工作在`php >= 5.3.0`的环境下面，如果你的环境符合这个需求，可以直接使用`master`分支的代码。

### 测试用例

检出代码之后，执行`make`即可，必须保证在内网的环境下才可以正常的运行测试用例。

## 如何使用

### php >= 5.3

```
define('__BOS_CLIENT_ROOT', 'the bos-php-sdk dir');
require_once __BOS_CLIENT_ROOT . "/baidubce/services/bos/BosClient.php";

use baidubce\services\bos\BosClient;

$config = array(
    'AccessKeyId' => 'your ak',
    'AccessKeySecret' => 'your sk',
    'TimeOut' => 5000, // 5s
    'Host' => 'bos.bj.baidubce.com'
);
$client = new BosClient($config);
$client->createBucket('my-bucket');
$client->putObjectFromString('my-bucket', 'this/is/my/file.txt', 'hello world, this is file content');

$url = $client->generatePresignedUrl('my-bucket', 'this/is/my/file.txt');
print_r(file_get_contents($url));
```

### php < 5.3

```
define('__BOS_CLIENT_ROOT', 'the bos-php-sdk dir');
require_once __BOS_CLIENT_ROOT . "/baidubce/services/bos/BosClient.php";

$config = array(
    'AccessKeyId' => 'your ak',
    'AccessKeySecret' => 'your sk',
    'TimeOut' => 5000, // 5s
    'Host' => 'bos.bj.baidubce.com'
);
$client = new baidubce_services_bos_BosClient($config);
$client->createBucket('my-bucket');
$client->putObjectFromString('my-bucket', 'this/is/my/file.txt', 'hello world, this is file content');

$url = $client->generatePresignedUrl('my-bucket', 'this/is/my/file.txt');
print_r(file_get_contents($url));
```
