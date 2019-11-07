<?php

require_once("bootstrap.php");

use Aztec\Encoder;
use Aztec\PngRenderer;

// ... some awesome code here ...

$code = Encoder::encode('Hello World!');
$renderer = new PngRenderer();

file_put_contents("temp/example.basic.png", $renderer->render($code));

?>