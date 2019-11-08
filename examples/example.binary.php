<?php

require_once("bootstrap.php");

use Aztec\Encoder\BinaryDataEncoder;
use Aztec\Encoder;
use Aztec\PngRenderer;

$encoder = new BinaryDataEncoder();

list($code, $width) = (new Encoder())->encode('Hello World!', 33, $encoder);

file_put_contents("temp/example.binary.png", (new PngRenderer())->render($code, $width));

?>