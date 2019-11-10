<?php

require_once("bootstrap.php");

use Aztec\Encoder;
use Aztec\PngRenderer;

list($code, $width) = (new Encoder())->encode('Hello World 3 4 5 asasdas22345 . 456!');

file_put_contents("temp/example.pairCode.png", (new PngRenderer())->render($code, $width));

?>