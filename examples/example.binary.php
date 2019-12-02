<?php

require_once("bootstrap.php");

use Aztec\Aztec;

// Text to be encoded
$text = 'Hello World!';

// Encode the data
$aztec = new Aztec(["hint" => "binary"]);
$aztec->encode($text);

// Create a PNG image
$aztec->toFile('temp/example.binary.png');
