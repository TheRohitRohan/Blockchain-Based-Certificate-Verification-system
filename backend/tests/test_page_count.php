<?php
require 'vendor/autoload.php';

$parser = new \Smalot\PdfParser\Parser();
$files = glob('storage/certificates/*.pdf');
usort($files, function($a, $b) {
    return filemtime($a) <=> filemtime($b);
});
$latest = end($files);

$pdf = $parser->parseFile($latest);
$pages = count($pdf->getPages());

echo "File: $latest\n";
echo "Total Pages: $pages\n";
