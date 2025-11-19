<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // $pdo, SUPABASE_URL, SUPABASE_SERVICE_KEY

// Prefer loading Composer autoload early so IDEs and runtime can find PhpWord/Dompdf classes.
$vendorAutoload = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    /** @noinspection PhpIncludeInspection */
    require_once $vendorAutoload;
}

// Import classes for static analysis / readability when available
if (class_exists('\PhpOffice\PhpWord\IOFactory')) {
    // no-op; this helps some IDEs recognize the symbol after autoload
}

/* ---------- storage helpers ---------- */
const DEFAULT_REQUIREMENTS_BUCKET = 'requirements';

function bucket_name(): string
{
    if (defined('REQUIREMENTS_BUCKET') && REQUIREMENTS_BUCKET) return REQUIREMENTS_BUCKET;
    if (defined('SUPABASE_BUCKET') && SUPABASE_BUCKET) return SUPABASE_BUCKET;
    return DEFAULT_REQUIREMENTS_BUCKET;
}
function encode_path_segments(string $path): string
{
    $path = ltrim($path, '/');
    $segs = array_map('rawurlencode', explode('/', $path));
    return implode('/', $segs);
}
function supa_public_url(string $bucket, string $path): string
{
    return rtrim(SUPABASE_URL, '/') . "/storage/v1/object/public/{$bucket}/" . encode_path_segments($path);
}
function supa_upload(string $bucket, string $path, string $tmpPath, string $mime): string
{
    $url = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/{$bucket}/" . encode_path_segments($path);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $mime,
            'x-upsert: false',
        ],
        CURLOPT_POSTFIELDS => file_get_contents($tmpPath),
        CURLOPT_RETURNTRANSFER => true
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false || $code >= 300) {
        $err = $resp ?: curl_error($ch);
        curl_close($ch);
        throw new Exception("Storage upload failed ({$code}): {$err}");
    }
    curl_close($ch);
    return supa_public_url($bucket, $path);
}
function slugify_name(string $s): string
{
    $s = preg_replace('~[^\pL\d._-]+~u', '_', $s);
    $s = trim($s, '_');
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = preg_replace('~[^-\w._]+~', '', $s);
    return strtolower(preg_replace('~_+~', '_', $s) ?: 'file');
}
function pick_ext(array $f, string $fallback): string
{
    $ext = pathinfo($f['name'] ?? '', PATHINFO_EXTENSION);
    return $ext ? '.' . strtolower($ext) : $fallback;
}
function norm(?string $s): string
{
    return strtolower(trim((string)$s));
}

/**
 * Convert MHTML/HTML document to PDF using Dompdf
 * 
 * @param string $htmlPath Path to HTML/MHTML file
 * @param string $outputPath Where to save the PDF
 * @return bool True if conversion succeeded, false otherwise
 */
function convertHtmlToPdfDompdf(string $htmlPath, string $outputPath): bool
{
    try {
        if (!file_exists($htmlPath) || !is_readable($htmlPath)) {
            error_log("[WILDLIFE-PDF-DOMPDF] Source file not found or not readable: $htmlPath");
            return false;
        }

        // Load the HTML content
        $htmlContent = file_get_contents($htmlPath);
        if ($htmlContent === false) {
            error_log("[WILDLIFE-PDF-DOMPDF] Failed to read HTML file: $htmlPath");
            return false;
        }

        // Create Dompdf instance
        $dompdf = new \Dompdf\Dompdf();

        // Load HTML content into Dompdf
        $dompdf->loadHtml($htmlContent);

        // Set paper size and orientation
        $dompdf->setPaper('A4', 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        // Get the PDF output and save to file
        $pdfContent = $dompdf->output();
        if ($pdfContent === false) {
            error_log("[WILDLIFE-PDF-DOMPDF] Failed to render PDF");
            return false;
        }

        // Write to output file
        $written = file_put_contents($outputPath, $pdfContent);
        if ($written === false) {
            error_log("[WILDLIFE-PDF-DOMPDF] Failed to write PDF to: $outputPath");
            return false;
        }

        error_log("[WILDLIFE-PDF-DOMPDF] PDF created successfully: $outputPath (" . filesize($outputPath) . " bytes)");
        return true;
    } catch (Throwable $e) {
        error_log("[WILDLIFE-PDF-DOMPDF] Exception during conversion: " . $e->getMessage());
        return false;
    }
}

/**
 * Try to extract the HTML part from an MHTML / multipart/related blob.
 * Returns path to extracted HTML file on success, or empty string on failure.
 */
function extractHtmlFromMhtml(string $mhtmlPath, string $outHtmlPath): bool
{
    try {
        $content = file_get_contents($mhtmlPath);
        if ($content === false) return false;

        // Find the start of the HTML part by locating Content-Type: text/html
        $lc = strtolower($content);
        $pos = strpos($lc, "content-type: text/html");
        if ($pos === false) {
            // Some MHTML files may include "Content-Type: text/html; charset=..."
            $pos = strpos($lc, "content-type:text/html");
            if ($pos === false) return false;
        }

        // Move to the end of the headers of that part (double CRLF)
        $hdrEnd = strpos($content, "\r\n\r\n", $pos);
        if ($hdrEnd === false) $hdrEnd = strpos($content, "\n\n", $pos);
        if ($hdrEnd === false) return false;

        $htmlPart = substr($content, $hdrEnd + 4);

        // If it's base64 encoded, try to detect and decode; otherwise use raw
        // Heuristic: if preceding headers contains "base64" then decode
        $partHeaders = substr($content, $pos, $hdrEnd - $pos);
        if (stripos($partHeaders, 'base64') !== false) {
            // Remove any whitespace/newlines and decode
            $b = preg_replace('/\s+/', '', $htmlPart);
            $decoded = base64_decode($b, true);
            if ($decoded !== false) $htmlPart = $decoded;
        } else {
            // If quoted-printable
            if (stripos($partHeaders, 'quoted-printable') !== false) {
                $htmlPart = quoted_printable_decode($htmlPart);
            }
        }

        // Strip Office/Word XML namespaces that break Dompdf rendering
        $htmlPart = preg_replace('/\s+xmlns:[a-z]+="[^"]*"/i', '', $htmlPart);
        $htmlPart = preg_replace('/<\?xml[^?]*\?>/i', '', $htmlPart);

        // Remove Office-specific style definitions that can cause rendering issues
        if (preg_match('/<style[^>]*>(.*?)<\/style>/is', $htmlPart, $m)) {
            $styles = $m[1];
            // Keep only basic styles, remove Office-specific mso- styles
            $styles = preg_replace('/\.mso[a-z-]*\s*{[^}]*}/i', '', $styles);
            $styles = preg_replace('/mso-[a-z-]*:[^;]*;?/i', '', $styles);
            $htmlPart = preg_replace('/<style[^>]*>(.*?)<\/style>/is', '<style>' . $styles . '</style>', $htmlPart);
        }

        // Strip Office/Word XML namespaces that break Dompdf rendering
        $htmlPart = preg_replace('/\s+xmlns:[a-z]+="[^"]*"/i', '', $htmlPart);
        $htmlPart = preg_replace('/<\?xml[^?]*\?>/i', '', $htmlPart);

        // Remove Office-specific style definitions that can cause rendering issues
        if (preg_match('/<style[^>]*>(.*?)<\/style>/is', $htmlPart, $m)) {
            $styles = $m[1];
            // Keep only basic styles, remove Office-specific mso- styles
            $styles = preg_replace('/\.mso[a-z-]*\s*{[^}]*}/i', '', $styles);
            $styles = preg_replace('/mso-[a-z-]*:[^;]*;?/i', '', $styles);
            $htmlPart = preg_replace('/<style[^>]*>(.*?)<\/style>/is', '<style>' . $styles . '</style>', $htmlPart);
        }

        // Strip comments, <o:p>, <w:...> and xml blocks
        $htmlPart = preg_replace('/<!--.*?-->/s', '', $htmlPart);
        $htmlPart = preg_replace('/<o:p>.*?<\/o:p>/is', '', $htmlPart);
        $htmlPart = preg_replace('/<w:[^>]+>.*?<\/w:[^>]+>/is', '', $htmlPart);
        $htmlPart = preg_replace('/<xml[^>]*>.*?<\/xml>/is', '', $htmlPart);

        // Try to extract the body content and rebuild a minimal, clean HTML document
        $bodyContent = null;
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $htmlPart, $bm)) {
            $bodyContent = $bm[1];
        } else {
            // Fallback: try to find first <p>.. or use the whole thing
            if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $htmlPart, $pm)) {
                $bodyContent = $pm[0];
            } else {
                $bodyContent = $htmlPart;
            }
        }

        // Clean up leftover xml namespaces or tags in the body
        $bodyContent = preg_replace('/<[^>]+:/', '<', $bodyContent);
        $bodyContent = preg_replace('/<\/?(meta|link|script|style)[^>]*>/is', '', $bodyContent);
        $bodyContent = trim($bodyContent);

        $cleanHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:Arial, Helvetica, sans-serif; font-size:12px;}</style></head><body>' . $bodyContent . '</body></html>';

        // Save extracted HTML
        $written = file_put_contents($outHtmlPath, $cleanHtml);
        return $written !== false;
    } catch (Throwable $e) {
        error_log('[WILDLIFE-PDF] extractHtmlFromMhtml error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Convert a DOCX file to PDF using PhpWord writer (requires phpoffice/phpword and dompdf)
 * Returns true on success (PDF written to $outputPath), false otherwise.
 */
function convertDocxToPdfPhpWord(string $docxPath, string $outputPath): bool
{
    try {
        // Ensure autoload available
        $autoload = __DIR__ . '/../../../vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;
        if (!class_exists('\PhpOffice\\PhpWord\\IOFactory')) {
            error_log('[WILDLIFE-PDF] PhpWord IOFactory not available');
            return false;
        }

        // If Settings is present, try to set Dompdf as the PDF renderer and point to vendor path
        if (class_exists('PhpOffice\\PhpWord\\Settings')) {
            try {
                $dompdfBase = __DIR__ . '/../../../vendor/dompdf/dompdf';
                $ok1 = \PhpOffice\PhpWord\Settings::setPdfRendererName(\PhpOffice\PhpWord\Settings::PDF_RENDERER_DOMPDF);
                $ok2 = \PhpOffice\PhpWord\Settings::setPdfRendererPath($dompdfBase);
                error_log(sprintf('[WILDLIFE-PDF] PhpWord Settings setPdfRendererName=%s setPdfRendererPath=%s (path=%s)', $ok1 ? 'true' : 'false', $ok2 ? 'true' : 'false', $dompdfBase));
            } catch (Throwable $e) {
                error_log('[WILDLIFE-PDF] Failed to set PhpWord PDF renderer: ' . $e->getMessage());
            }
        }

        // Load the document using late static call to avoid static analysis warnings
        $ioFactory = '\\PhpOffice\\PhpWord\\IOFactory';
        $phpWord = call_user_func([$ioFactory, 'load'], $docxPath, 'Word2007');

        // Try direct PDF writer first
        try {
            $pdfWriter = call_user_func([$ioFactory, 'createWriter'], $phpWord, 'PDF');
            $pdfWriter->save($outputPath);
        } catch (Throwable $e) {
            error_log('[WILDLIFE-PDF] PhpWord PDF writer failed: ' . $e->getMessage());
            // Fallback: export to HTML then use Dompdf
            try {
                $htmlTmp = $outputPath . '.html';
                $htmlWriter = call_user_func([$ioFactory, 'createWriter'], $phpWord, 'HTML');
                $htmlWriter->save($htmlTmp);
                error_log('[WILDLIFE-PDF] PhpWord exported HTML to ' . $htmlTmp);
                if (convertHtmlToPdfDompdf($htmlTmp, $outputPath)) {
                    @unlink($htmlTmp);
                    return file_exists($outputPath) && filesize($outputPath) > 0;
                }
                @unlink($htmlTmp);
            } catch (Throwable $e2) {
                error_log('[WILDLIFE-PDF] Fallback DOCX->HTML->PDF failed: ' . $e2->getMessage());
            }
            // rethrow to outer catch
            throw $e;
        }
        return file_exists($outputPath) && filesize($outputPath) > 0;
    } catch (Throwable $e) {
        error_log('[WILDLIFE-PDF] convertDocxToPdfPhpWord error: ' . $e->getMessage());
        return false;
    }
}

/* --------------- main --------------- */
// When this file is included by CLI test helpers, define WILDLIFE_NO_RUN to skip the HTTP request handler.
if (!defined('WILDLIFE_NO_RUN')) {
    try {
        if (!isset($_SESSION['user_id'])) throw new Exception('Not authenticated');
        if (!$pdo) throw new Exception('DB not available');

        // Resolve user UUID
        $idOrUuid = (string)$_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT user_id FROM public.users WHERE id::text=:v OR user_id::text=:v LIMIT 1");
        $stmt->execute([':v' => $idOrUuid]);
        $urow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$urow) throw new Exception('User record not found');
        $user_uuid = $urow['user_id'];

        // Inputs
        $permit_type = isset($_POST['permit_type']) && in_array(strtolower($_POST['permit_type']), ['new', 'renewal'], true)
            ? strtolower($_POST['permit_type']) : 'new';

        $first_name  = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name   = trim($_POST['last_name'] ?? '');

        // NEW common fields
        $residence_address       = trim($_POST['residence_address'] ?? '');
        $telephone_number        = trim($_POST['telephone_number'] ?? '');
        $establishment_name      = trim($_POST['establishment_name'] ?? '');
        $establishment_address   = trim($_POST['establishment_address'] ?? '');
        $establishment_telephone = trim($_POST['establishment_telephone'] ?? '');
        $postal_address          = trim($_POST['postal_address'] ?? '');

        // checkboxes
        $zoo      = trim($_POST['zoo'] ?? '0') === '1';
        $bot_gdn  = trim($_POST['botanical_garden'] ?? '0') === '1';
        $priv_col = trim($_POST['private_collection'] ?? '0') === '1';

        // animals tables
        $animals_json         = trim($_POST['animals_json'] ?? '[]');         // NEW
        $renewal_animals_json = trim($_POST['renewal_animals_json'] ?? '[]'); // RENEWAL

        // RENEWAL only
        $wfp_number     = trim($_POST['wfp_number'] ?? '');
        $issue_date     = trim($_POST['issue_date'] ?? '');
        $renewal_postal = trim($_POST['renewal_postal_address'] ?? $postal_address);

        $complete_name = trim(preg_replace('/\s+/', ' ', "$first_name $middle_name $last_name"));

        // Normalize for client lookup
        $nf = norm($first_name);
        $nm = norm($middle_name);
        $nl = norm($last_name);

        // Force-create-new toggle
        $force_new_client = false;
        if (isset($_POST['force_new_client'])) {
            $val = strtolower(trim((string)$_POST['force_new_client']));
            $force_new_client = in_array($val, ['1', 'true', 'yes', 'on'], true);
        }

        $pdo->beginTransaction();

        // --- Extra safeguard: do not allow "Submit as new" to bypass existing blocks
        if ($force_new_client) {
            // exact-name existing client?
            $dup = $pdo->prepare("
            SELECT client_id FROM public.client
            WHERE lower(trim(coalesce(first_name,'')))=:f
              AND lower(trim(coalesce(middle_name,'')))=:m
              AND lower(trim(coalesce(last_name,'')))=:l
            LIMIT 1
        ");
            $dup->execute([':f' => $nf, ':m' => $nm, ':l' => $nl]);
            $dupId = $dup->fetchColumn();

            if ($dupId) {
                $dFlags = $pdo->prepare("
                SELECT
                    bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'new')     AS has_pending_new,
                    bool_or(approval_status ILIKE 'released'  AND permit_type ILIKE 'new')     AS has_released_new,
                    bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'renewal') AS has_pending_renewal,
                    bool_or(approval_status ILIKE 'for payment')                               AS has_for_payment
                FROM public.approval
                WHERE client_id=:cid AND request_type ILIKE 'wildlife'
            ");
                $dFlags->execute([':cid' => $dupId]);
                $df = $dFlags->fetch(PDO::FETCH_ASSOC) ?: [];
                $dupHasPendingNew     = (bool)($df['has_pending_new'] ?? false);
                $dupHasPendingRenewal = (bool)($df['has_pending_renewal'] ?? false);
                $dupHasForPayment     = (bool)($df['has_for_payment'] ?? false);

                $dUnexp = $pdo->prepare("
                SELECT 1
                FROM public.approval a
                JOIN public.approved_docs d ON d.approval_id = a.approval_id
                WHERE a.client_id = :cid
                  AND a.request_type ILIKE 'wildlife'
                  AND a.approval_status ILIKE 'released'
                  AND d.expiry_date IS NOT NULL
                  AND d.expiry_date::date >= (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date
                LIMIT 1
            ");
                $dUnexp->execute([':cid' => $dupId]);
                $dupHasUnexpired = (bool)$dUnexp->fetchColumn();

                if ($dupHasForPayment) {
                    throw new Exception('An existing client with this name still has an unpaid wildlife permit (for payment). Please settle it first.');
                }
                if ($permit_type === 'renewal') {
                    if ($dupHasPendingRenewal || $dupHasPendingNew) {
                        throw new Exception('An existing client with this name already has a pending wildlife application.');
                    }
                    if ($dupHasUnexpired) {
                        throw new Exception('An existing client with this name still has an unexpired wildlife permit. Renewal is not allowed yet.');
                    }
                } else { // new
                    if ($dupHasPendingNew || $dupHasPendingRenewal) {
                        throw new Exception('An existing client with this name already has a pending wildlife application.');
                    }
                    if ($dupHasUnexpired) {
                        throw new Exception('An existing client with this name still has an unexpired wildlife permit. A new application is not allowed.');
                    }
                }
            }
        }

        /* ========= Client resolution ========= */
        $client_id = null;
        $client_resolution = null;

        if (!$force_new_client) {
            $override_client_id = trim((string)$_POST['use_existing_client_id'] ?? '');
            if ($override_client_id !== '') {
                if (!preg_match('/^[0-9a-fA-F-]{36}$/', $override_client_id)) {
                    throw new Exception('Invalid client id format.');
                }
                $chk = $pdo->prepare("SELECT client_id FROM public.client WHERE client_id::text = :cid LIMIT 1");
                $chk->execute([':cid' => $override_client_id]);
                $client_id = $chk->fetchColumn() ?: null;
                if (!$client_id) throw new Exception('Selected client not found.');
                $client_resolution = 'existing_override';
            }

            if (!$client_id) {
                $stmt = $pdo->prepare("
                SELECT client_id FROM public.client
                WHERE lower(trim(coalesce(first_name,'')))=:f
                  AND lower(trim(coalesce(middle_name,'')))=:m
                  AND lower(trim(coalesce(last_name,'')))=:l
                LIMIT 1
            ");
                $stmt->execute([':f' => $nf, ':m' => $nm, ':l' => $nl]);
                $client_id = $stmt->fetchColumn() ?: null;
                if ($client_id) $client_resolution = 'exact_match_reuse';
            }
        }

        if (!$client_id) {
            $stmt = $pdo->prepare("
            INSERT INTO public.client (user_id, first_name, middle_name, last_name, contact_number)
            VALUES (:uid, :first, :middle, :last, :contact)
            RETURNING client_id
        ");
            $stmt->execute([
                ':uid'     => $user_uuid,
                ':first'   => $first_name,
                ':middle'  => $middle_name,
                ':last'    => $last_name,
                ':contact' => $telephone_number
            ]);
            $client_id = $stmt->fetchColumn();
            if (!$client_id) throw new Exception('Failed to create client record');
            $client_resolution = $force_new_client ? 'forced_new' : 'created_new';
        }

        // -------- Status flags for validations --------
        $stmt = $pdo->prepare("
        SELECT
            bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'new')     AS has_pending_new,
            bool_or(approval_status ILIKE 'released'  AND permit_type ILIKE 'new')     AS has_released_new,
            bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'renewal') AS has_pending_renewal,
            bool_or(approval_status ILIKE 'for payment')                               AS has_for_payment
        FROM public.approval
        WHERE client_id=:cid AND request_type ILIKE 'wildlife'
    ");
        $stmt->execute([':cid' => $client_id]);
        $flags = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $hasPendingNew     = (bool)($flags['has_pending_new'] ?? false);
        $hasReleasedNew    = (bool)($flags['has_released_new'] ?? false);
        $hasPendingRenewal = (bool)($flags['has_pending_renewal'] ?? false);
        $hasForPayment     = (bool)($flags['has_for_payment'] ?? false);

        $q = $pdo->prepare("
        SELECT 1
        FROM public.approval a
        JOIN public.approved_docs d ON d.approval_id = a.approval_id
        WHERE a.client_id = :cid
          AND a.request_type ILIKE 'wildlife'
          AND a.approval_status ILIKE 'released'
          AND d.expiry_date IS NOT NULL
          AND d.expiry_date::date >= (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date
        LIMIT 1
    ");
        $q->execute([':cid' => $client_id]);
        $hasUnexpired = (bool)$q->fetchColumn();

        // -------- Validations --------
        if ($hasForPayment) {
            throw new Exception('You still have an unpaid wildlife permit (status: for payment). Please pay personally at the office before filing another request.');
        }

        if ($permit_type === 'renewal') {
            if ($hasPendingRenewal) {
                throw new Exception('You already have a pending RENEWAL wildlife application.');
            }
            if ($hasPendingNew) {
                throw new Exception('You already have a pending NEW wildlife application.');
            }
            if ($hasUnexpired) {
                throw new Exception('You still have an unexpired wildlife permit. Please wait until it expires before requesting a renewal.');
            }
        } else { // NEW
            if ($hasPendingNew) {
                throw new Exception('You already have a pending NEW wildlife application.');
            }
            if ($hasPendingRenewal) {
                throw new Exception('You have a pending RENEWAL; please wait for the update first.');
            }
            if ($hasUnexpired) {
                throw new Exception('You still have an unexpired wildlife permit. You cannot file a new application.');
            }
        }

        // -------- Create requirements row --------
        $ridStmt = $pdo->query("INSERT INTO public.requirements DEFAULT VALUES RETURNING requirement_id");
        $requirement_id = $ridStmt->fetchColumn();
        if (!$requirement_id) throw new Exception('Failed to create requirements record');

        $bucket = bucket_name();

        $run = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $folder = ($permit_type === 'renewal') ? 'renewal permit' : 'new permit';
        $prefix = "wildlife/{$folder}/{$client_id}/{$run}/";

        $uploaded = ['application_form' => null, 'signature' => null];

        if (!empty($_FILES['application_doc']) && is_uploaded_file($_FILES['application_doc']['tmp_name'])) {
            $f = $_FILES['application_doc'];
            $safe = slugify_name('application_doc__' . ($f['name'] ?: 'wildlife.doc'));
            if (!pathinfo($safe, PATHINFO_EXTENSION)) $safe .= '.doc';

            $convertedPdfUrl = null;
            $tmpDir = sys_get_temp_dir();

            // Prepare temp paths
            $origTmp = tempnam($tmpDir, 'wild_orig_');
            $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
            $origPath = $origTmp . ($ext ? '.' . $ext : '.bin');
            @copy($f['tmp_name'], $origPath);

            $pdfTmp = tempnam($tmpDir, 'wild_pdf_');
            $pdfCandidate = $pdfTmp . '.pdf';

            $conversionDone = false;

            // 1) If DOCX, try PhpWord -> PDF
            if ($ext === 'docx') {
                if (convertDocxToPdfPhpWord($origPath, $pdfCandidate)) {
                    $conversionDone = true;
                } else {
                    error_log('[WILDLIFE-PDF] DOCX -> PDF via PhpWord failed');
                }
            }

            // 2) If not done yet, attempt to detect MHTML/HTML and convert with Dompdf
            if (!$conversionDone) {
                // Read first chunk to detect MHTML markers
                $head = @file_get_contents($origPath, false, null, 0, 2048) ?: '';
                $isMhtml = stripos($head, 'mime-version:') !== false || stripos($head, 'multipart/related') !== false || in_array($ext, ['mht', 'mhtml', 'doc']);

                if ($isMhtml) {
                    $extractedHtml = $origTmp . '_extracted.html';
                    if (extractHtmlFromMhtml($origPath, $extractedHtml)) {
                        if (convertHtmlToPdfDompdf($extractedHtml, $pdfCandidate)) {
                            $conversionDone = true;
                        } else {
                            error_log('[WILDLIFE-PDF] Dompdf conversion of extracted MHTML failed');
                        }
                        @unlink($extractedHtml);
                    } else {
                        error_log('[WILDLIFE-PDF] Failed to extract HTML from MHTML; trying to treat file as plain HTML');
                        // Some browsers generate a .doc file that is actually plain HTML (no MHTML headers).
                        $sample = @file_get_contents($origPath, false, null, 0, 512) ?: '';
                        if (stripos($sample, '<html') !== false || stripos($sample, '<!doctype html') !== false) {
                            if (convertHtmlToPdfDompdf($origPath, $pdfCandidate)) {
                                $conversionDone = true;
                            } else {
                                error_log('[WILDLIFE-PDF] Dompdf conversion treating .doc as HTML failed');
                            }
                        } else {
                            error_log('[WILDLIFE-PDF] Uploaded .doc does not appear to be HTML/MHTML');
                        }
                    }
                } else {
                    // If plain HTML
                    if (in_array($ext, ['html', 'htm'])) {
                        if (convertHtmlToPdfDompdf($origPath, $pdfCandidate)) {
                            $conversionDone = true;
                        } else {
                            error_log('[WILDLIFE-PDF] Dompdf conversion of HTML failed');
                        }
                    }
                }
            }

            // 3) If conversion produced a PDF, upload it
            if ($conversionDone && file_exists($pdfCandidate) && filesize($pdfCandidate) > 0) {
                $pdfSafe = preg_replace('/\.[^.]+$/', '.pdf', $safe);
                try {
                    $convertedPdfUrl = supa_upload($bucket, $prefix . $pdfSafe, $pdfCandidate, 'application/pdf');
                    error_log("[WILDLIFE-PDF] PDF uploaded successfully: $convertedPdfUrl");
                    $uploaded['application_form'] = $convertedPdfUrl;
                } catch (Exception $e) {
                    error_log('[WILDLIFE-PDF] PDF upload failed: ' . $e->getMessage());
                    $convertedPdfUrl = null;
                }
            }

            // 4) Fallback to original file upload when conversion didn't occur or failed
            if (empty($uploaded['application_form'])) {
                error_log('[WILDLIFE-PDF] Falling back to original Word file upload');
                $uploaded['application_form'] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'application/msword');
            }
            // If conversion did not occur, save debug copies for inspection
            if (!$conversionDone) {
                try {
                    $dbgBase = __DIR__ . '/../../debug/wildlife/' . $run . '/';
                    if (!is_dir($dbgBase)) @mkdir($dbgBase, 0777, true);
                    $origName = basename($f['name'] ?: 'application_doc');
                    $dbgOrig = $dbgBase . 'original_' . $origName;
                    @copy($f['tmp_name'], $dbgOrig);
                    if (file_exists($origPath)) {
                        $dbgOrigPath = $dbgBase . 'tmpcopy' . (pathinfo($origPath, PATHINFO_EXTENSION) ? '.' . pathinfo($origPath, PATHINFO_EXTENSION) : '.bin');
                        @copy($origPath, $dbgOrigPath);
                    }
                    // attempt extraction again into debug folder
                    $dbgExtract = $dbgBase . 'extracted.html';
                    if (file_exists($origPath) && extractHtmlFromMhtml($origPath, $dbgExtract)) {
                        error_log('[WILDLIFE-PDF] extracted HTML saved: ' . $dbgExtract);
                    } else {
                        error_log('[WILDLIFE-PDF] failed to extract HTML to debug');
                    }
                    if (file_exists($pdfCandidate)) {
                        @copy($pdfCandidate, $dbgBase . 'candidate.pdf');
                    }
                    error_log('[WILDLIFE-PDF] Debug files saved to: ' . $dbgBase);
                } catch (Throwable $e) {
                    error_log('[WILDLIFE-PDF] Failed to save debug files: ' . $e->getMessage());
                }
            }

            // cleanup temp files
            @unlink($origPath);
            @unlink($pdfCandidate);
            @unlink($pdfTmp);
        }
        if (!empty($_FILES['signature_file']) && is_uploaded_file($_FILES['signature_file']['tmp_name'])) {
            $f = $_FILES['signature_file'];
            $ext = pick_ext($f, '.png');
            $safe = slugify_name("signature__signature{$ext}");
            $uploaded['signature'] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'image/png');
        }

        // ========= Map page uploads =========
        $newMap = [
            'file_2'  => 'wild_sec_cda_registration',
            'file_3'  => 'wild_scientific_expertise',
            'file_4'  => 'wild_financial_plan',
            'file_5'  => 'wild_facility_design',
            'file_6'  => 'wild_prior_clearance',
            'file_7'  => 'wild_vicinity_map',
            'file_8a' => 'wild_proof_of_purchase',
            'file_8b' => 'wild_deed_of_donation',
            'file_9'  => 'wild_inspection_report',
        ];

        $renMap = [
            'renewal_file_2'  => 'wild_previous_wfp_copy',
            'renewal_file_3'  => 'wild_breeding_report_quarterly',
            'renewal_file_4a' => 'wild_cites_import_permit',
            'renewal_file_4b' => 'wild_proof_of_purchase',
            'renewal_file_4c' => 'wild_deed_of_donation',
            'renewal_file_4d' => 'wild_local_transport_permit',
            'renewal_file_5a' => 'wild_barangay_mayor_clearance',
            'renewal_file_5b' => 'wild_facility_design',
            'renewal_file_5c' => 'wild_vicinity_map',
            'renewal_file_6'  => 'wild_inspection_report',
        ];

        $map = ($permit_type === 'renewal') ? $renMap : $newMap;
        $reqSet = [];
        $reqParams = [':rid' => $requirement_id];

        if ($uploaded['application_form']) {
            $reqSet[] = "application_form = :application_form";
            $reqParams[':application_form'] = $uploaded['application_form'];
        }

        $setColumns = [];
        foreach ($map as $postField => $col) {
            if (!empty($_FILES[$postField]) && is_uploaded_file($_FILES[$postField]['tmp_name'])) {
                $f = $_FILES[$postField];
                $ext = pick_ext($f, '.bin');
                $safe = slugify_name("{$postField}__" . ($f['name'] ?: "file{$ext}"));
                if (!pathinfo($safe, PATHINFO_EXTENSION)) $safe .= $ext;
                $url = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'application/octet-stream');
                $reqSet[] = "{$col} = :{$col}";
                $reqParams[":{$col}"] = $url;
                $setColumns[$col] = true;
            }
        }

        // ---- Accept existing file URLs (from detected data) when no new upload was sent ----
        $existingFileUrls = [];
        if (!empty($_POST['existing_file_urls'])) {
            $decoded = json_decode((string)$_POST['existing_file_urls'], true);
            if (is_array($decoded)) {
                // Normalize keys: hyphens -> underscores, lower-case
                $normalized = [];
                foreach ($decoded as $k => $v) {
                    $normalized[str_replace('-', '_', strtolower((string)$k))] = trim((string)$v);
                }
                $existingFileUrls = $normalized;
            }
        }
        foreach ($existingFileUrls as $postField => $url) {
            $col = $map[$postField] ?? null; // $postField MUST match e.g. renewal_file_2
            $url = trim((string)$url);
            if ($col && $url !== '' && empty($setColumns[$col])) {
                $reqSet[] = "{$col} = :{$col}";
                $reqParams[":{$col}"] = $url;
                $setColumns[$col] = true;
            }
        }

        if ($reqSet) {
            $sql = "UPDATE public.requirements SET " . implode(', ', $reqSet) . " WHERE requirement_id = :rid";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($reqParams);
        }

        // Build additional_information JSON snapshot
        $info = [
            'submission_key'             => $run,
            'permit_type'                => $permit_type,
            'categories'                 => ['zoo' => $zoo, 'botanical_garden' => $bot_gdn, 'private_collection' => $priv_col],
            'residence_address'          => $residence_address,
            'telephone_number'           => $telephone_number,
            'establishment_name'         => $establishment_name,
            'establishment_address'      => $establishment_address,
            'establishment_telephone'    => $establishment_telephone,
            'postal_address'             => $permit_type === 'renewal' ? $renewal_postal : $postal_address,
            'animals'                    => json_decode($permit_type === 'renewal' ? $renewal_animals_json : $animals_json, true) ?: [],
            'wfp_number'                 => $wfp_number ?: null,
            'issue_date'                 => $issue_date ?: null,
            'generated_application_doc'  => $uploaded['application_form'] ?: null,
            'client_resolution'          => $client_resolution,
        ];
        $additional_information = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Insert application_form
        $stmt = $pdo->prepare("
        INSERT INTO public.application_form
            (client_id, contact_number, application_for, type_of_permit, complete_name, present_address,
             telephone_number, signature_of_applicant,
             renewal_of_my_certificate_of_wildlife_registration_of, permit_number,
             additional_information, date_today)
        VALUES
            (:client_id, :contact_number, 'wildlife', :type_of_permit, :complete_name, :present_address,
             :telephone_number, :signature_url,
             :renewal_of, :permit_number,
             :additional_information, to_char(now(),'YYYY-MM-DD'))
        RETURNING application_id
    ");
        $stmt->execute([
            ':client_id'              => $client_id,
            ':contact_number'         => $telephone_number,
            ':type_of_permit'         => $permit_type,
            ':complete_name'          => $complete_name,
            ':present_address'        => $residence_address,
            ':telephone_number'       => $telephone_number,
            ':signature_url'          => $uploaded['signature'] ?? null,
            ':renewal_of'             => ($permit_type === 'renewal') ? $establishment_name : null,
            ':permit_number'          => ($permit_type === 'renewal') ? ($wfp_number ?: null) : null,
            ':additional_information' => $additional_information,
        ]);
        $application_id = $stmt->fetchColumn();
        if (!$application_id) throw new Exception('Failed to create application form');

        // Create approval row
        $stmt = $pdo->prepare("
        INSERT INTO public.approval
          (client_id, requirement_id, request_type, approval_status, seedl_req_id, permit_type, application_id, submitted_at)
        VALUES
          (:client_id, :requirement_id, 'wildlife', 'pending', NULL, :permit_type, :application_id, now())
        RETURNING approval_id
    ");
        $stmt->execute([
            ':client_id'      => $client_id,
            ':requirement_id' => $requirement_id,
            ':permit_type'    => $permit_type,
            ':application_id' => $application_id,
        ]);
        $approval_id = $stmt->fetchColumn();
        if (!$approval_id) throw new Exception('Failed to create approval record');

        // Notification
        $msg = sprintf('%s requested a wildlife %s permit.', $first_name ?: $complete_name, $permit_type);
        $stmt = $pdo->prepare("
        INSERT INTO public.notifications (approval_id, incident_id, message, is_read, \"from\", \"to\")
        VALUES (:approval_id, NULL, :message, false, :from_user, :to_dept)
        RETURNING notif_id
    ");
        $stmt->execute([
            ':approval_id' => $approval_id,
            ':message'     => $msg,
            ':from_user'   => $user_uuid,
            ':to_dept'     => 'Wildlife',
        ]);
        $notif_id = $stmt->fetchColumn();

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'client_id'       => $client_id,
            'requirement_id'  => $requirement_id,
            'application_id'  => $application_id,
            'approval_id'     => $approval_id,
            'notification_id' => $notif_id,
            'bucket'          => $bucket,
            'storage_prefix'  => $prefix,
            'submission_key'  => $run,
        ]);
    } catch (\Throwable $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}
