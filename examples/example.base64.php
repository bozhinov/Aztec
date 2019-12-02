<?php

require_once("bootstrap.php");

use Aztec\Aztec;

// Text to be encoded
$text = 'Hello World!';

// Encode the data
$aztec = new Aztec();
$aztec->encode($text);

// Create a PNG image
file_put_contents("temp/example.base64.txt", $aztec->forWeb('BASE64'));

?>