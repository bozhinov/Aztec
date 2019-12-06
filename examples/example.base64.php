<?php

require_once("bootstrap.php");

use Aztec\Aztec;

file_put_contents(
	"temp/example.base64.txt", 
	(new Aztec())->encode('Hello World!')->forWeb('BASE64')
);
