<?php

require_once("bootstrap.php");

use Aztec\Aztec;

$text = 'Hello World 3 4 5 asasdas22345 . 456!';

// Encode the data
$aztec = new Aztec();
$aztec->encode($text)->toFile('temp/example.pairCode.png');
