<?php

require_once("bootstrap.php");

use Aztec\Aztec;

// Text to be encoded
$text = 'Hello World!';

# consider ini_get('default_charset') != ('UTF-8' || 'ISO-8859-1')
$text = iconv('UTF-8', 'ISO-8859-1//IGNORE', $text);

// Encode the data
$aztec = new Aztec(["hint" => "binary"]);
$aztec->encode($text)->toFile('temp/example.string.png');
