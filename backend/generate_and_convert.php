<?php
// Generate a sample DOCX using PhpWord and then convert to PDF using the helper.
if (!defined('WILDLIFE_NO_RUN')) define('WILDLIFE_NO_RUN', true);
require_once __DIR__ . '/users/wildlife/save_wildlife.php';

$vendor = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($vendor)) require_once $vendor;

$docxPath = __DIR__ . '/../sample_generated.docx';
$pdfPath  = __DIR__ . '/../sample_generated.pdf';

try {
    if (!class_exists('PhpOffice\\PhpWord\\PhpWord')) {
        echo "PhpWord not available.\n";
        exit(2);
    }
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $section->addText('Sample DOCX generated for testing conversion');
    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($docxPath);
    echo "DOCX generated at: $docxPath\n";

    $ok = convertDocxToPdfPhpWord($docxPath, $pdfPath);
    if ($ok) {
        echo "Conversion succeeded. PDF at: $pdfPath (" . filesize($pdfPath) . " bytes)\n";
        exit(0);
    } else {
        echo "Conversion failed. Check logs for details.\n";
        exit(3);
    }
} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    exit(4);
}
