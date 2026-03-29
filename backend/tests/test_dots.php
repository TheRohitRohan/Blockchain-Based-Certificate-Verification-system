<?php
$test = '...';
$decoded = base64_decode($test);
echo "Base64 decode of '...': ";
var_dump($decoded);
echo "Length: " . strlen($decoded) . "\n";
