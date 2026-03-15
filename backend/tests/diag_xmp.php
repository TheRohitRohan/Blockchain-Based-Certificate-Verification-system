<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;

// Test with PDFA mode enabled (forces writeMetadata to run)
$mpdf = new Mpdf([
    'format' => 'A4',
    'PDFA' => true,
    'PDFAauto' => true,
]);
$mpdf->WriteHTML('<p>Test PDF</p>');

$xmpFragment = '<rdf:Description rdf:about="" xmlns:cert="http://certificate.system/metadata/">'
             . '<cert:metadata><![CDATA[{"test":"hello"}]]></cert:metadata>'
             . '</rdf:Description>';

$mpdf->SetAdditionalXmpRdf($xmpFragment);

$tmpPath = sys_get_temp_dir() . '/xmp_test_pdfa_' . time() . '.pdf';
$mpdf->Output($tmpPath, \Mpdf\Output\Destination::FILE);

$binary = file_get_contents($tmpPath);

echo "PDF size: " . strlen($binary) . " bytes\n";
echo "Contains 'cert:metadata': " . (strpos($binary, 'cert:metadata') !== false ? 'YES ✓' : 'NO ✗') . "\n";
echo "Contains 'certificate.system': " . (strpos($binary, 'certificate.system') !== false ? 'YES ✓' : 'NO ✗') . "\n";

$xmpStart = strpos($binary, '<?xpacket');
echo "XMP packet present: " . ($xmpStart !== false ? 'YES ✓' : 'NO ✗') . "\n";

if ($xmpStart !== false) {
    $xmpEnd = strpos($binary, '<?xpacket end', $xmpStart);
    $xmpChunk = substr($binary, $xmpStart, min(1000, $xmpEnd - $xmpStart + 30));
    echo "\n--- XMP Snippet ---\n" . $xmpChunk . "\n---\n";
}

unlink($tmpPath);
echo "\nDone.\n";
