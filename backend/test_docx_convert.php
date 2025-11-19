<?php
// Simple CLI tester for convertDocxToPdfPhpWord in wildlife save handler
// Prevent the save handler from running its HTTP main block when included
if (!defined('WILDLIFE_NO_RUN')) define('WILDLIFE_NO_RUN', true);
require_once __DIR__ . '/users/wildlife/save_wildlife.php';

$in = $argv[1] ?? null;
$out = $argv[2] ?? null;

if (!$in || !$out) {
    echo "Usage: php test_docx_convert.php /path/to/input.docx /path/to/output.pdf\n";
    exit(1);
}

echo "Input: $in\nOutput: $out\n";

$ok = convertDocxToPdfPhpWord($in, $out);
if ($ok) {
    echo "Conversion succeeded. Output size: " . filesize($out) . " bytes\n";
    exit(0);
} else {
    echo "Conversion failed. Check PHP error log for details.\n";
    exit(2);
}
