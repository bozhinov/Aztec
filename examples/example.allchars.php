<?php

require_once("bootstrap.php");

use Aztec\Aztec;

// Text to be encoded
$text = ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~';

// Encode the data
$aztec = new Aztec();
$aztec->encode($text);

// Create a PNG image
$aztec->toFile('temp/example.allchars.png');
