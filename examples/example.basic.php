<?php

require_once("bootstrap.php");

use Aztec\Encoder;
use Aztec\PngRenderer;

list($code, $width) = (new Encoder())->encode('Hello World!');

file_put_contents("temp/example.basic.png", (new PngRenderer())->render($code, $width));

?>