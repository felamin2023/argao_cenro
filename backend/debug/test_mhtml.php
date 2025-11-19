<?php
// Quick test to examine MHTML structure

$testFile = $_GET['file'] ?? '';
if (!$testFile || !file_exists($testFile)) {
    die('File not found');
}

$content = file_get_contents($testFile);
echo "<pre>";
echo "File size: " . strlen($content) . " bytes\n";
echo "First 2000 chars:\n";
echo htmlspecialchars(substr($content, 0, 2000)) . "\n\n";

// Find HTML part
$lc = strtolower($content);
$pos = strpos($lc, "content-type: text/html");
if ($pos !== false) {
    echo "Found HTML part at: $pos\n";
    $hdrEnd = strpos($content, "\r\n\r\n", $pos);
    if ($hdrEnd === false) $hdrEnd = strpos($content, "\n\n", $pos);
    echo "Headers end at: $hdrEnd\n";

    $htmlStart = $hdrEnd + 4;
    $htmlChunk = substr($content, $htmlStart, 500);
    echo "HTML part first 500 chars:\n";
    echo htmlspecialchars($htmlChunk) . "\n\n";

    // Look for boundaries
    if (preg_match('/boundary\s*=\s*["\']?([^"\'\r\n;]+)/i', $content, $m)) {
        $boundary = trim($m[1], '"\'');
        echo "Boundary found: " . htmlspecialchars($boundary) . "\n";
        $boundPos = strpos($content, '--' . $boundary, $htmlStart);
        echo "Next boundary at: $boundPos\n";
        if ($boundPos !== false) {
            $htmlLength = $boundPos - $htmlStart;
            echo "HTML part length: $htmlLength bytes\n";
        }
    }
}
