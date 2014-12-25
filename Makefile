all:
	php phpunit.phar --debug -c phpunit.xml --exclude-group ignore

lint:
	python eagle.py -f baidubce/Bce.php
	python eagle.py -f baidubce/BceBaseClient.php
	python eagle.py -f baidubce/Exception.php
	python eagle.py -f baidubce/auth/Auth.php
	python eagle.py -f baidubce/auth/BceCredentials.php
	python eagle.py -f baidubce/http/HttpClient.php
	python eagle.py -f baidubce/http/HttpContentTypes.php
	python eagle.py -f baidubce/http/HttpHeaders.php
	python eagle.py -f baidubce/http/HttpMethod.php
	python eagle.py -f baidubce/services/bos/BosClient.php
	python eagle.py -f baidubce/services/bos/CannedAcl.php
	python eagle.py -f baidubce/util/BceTools.php
	python eagle.py -f baidubce/util/Coder.php
	python eagle.py -f baidubce/util/GenerateMimeTypes.php
	python eagle.py -f baidubce/util/Time.php
