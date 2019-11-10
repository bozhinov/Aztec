<?php

require_once("bootstrap.php");

use Aztec\Encoder;
use Aztec\PngRenderer;

list($code, $width) = (new Encoder())->encode('Hello World!', 33, "binary");

file_put_contents("temp/example.binary.png", (new PngRenderer())->render($code, $width));

?>