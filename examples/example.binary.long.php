<?php

require_once("bootstrap.php");

use Aztec\Aztec;

// Text to be encoded
#$text = str_repeat("a", 32);
$text = "Rock-a-bye, baby On the treetop When the wind blows The cradle will rock If the bough breaks The cradle will fall But mama will catch you Cradle and all";

// Encode the data
$aztec = new Aztec(["hint" => "binary"]);
$aztec->encode($text);

// Create a PNG image
$aztec->toFile('temp/example.binary.long.png');
