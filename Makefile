all:
	phpunit --configuration phpunit.xml --exclude-group ignore

lint:
	python eagle.py -f baidubce/auth/Auth.php
	python eagle.py -f baidubce/http/HttpClient.php
	python eagle.py -f baidubce/services/bos/BosClient.php
	python eagle.py -f baidubce/services/bos/BosHttpClient.php
	python eagle.py -f baidubce/services/bos/model/stream/BosFileInputStream.php
	python eagle.py -f baidubce/util/BceTools.php
	python eagle.py -f baidubce/util/Coder.php
	python eagle.py -f baidubce/util/GenerateMimeTypes.php
	python eagle.py -f baidubce/util/Time.php
	python eagle.py -f baidubce/exception/BceBaseException.php
	python eagle.py -f baidubce/exception/BceClientException.php
	python eagle.py -f baidubce/exception/BceIllegalArgumentException.php
	python eagle.py -f baidubce/exception/BceRuntimeException.php
	python eagle.py -f baidubce/exception/BceServiceException.php
	python eagle.py -f baidubce/exception/BceStreamException.php

test: all
