<?php

declare(strict_types=1);

/**
 * application_status.php
 * - Shows ALL approvals for the logged-in user
 * - Two-pane modal (left: form, right: files) with preview drawer
 * - Shows rejection reason + “Request again” (prefill via sessionStorage)
 * - Filters: Status, Request Type, Permit Type, Search by Client
 * - Header/nav copied from user_home.php (including Notifications UI)
 * - Notifications are now LIVE: public.notifications."to" = current user_id
 * - UPDATED: View modal opens instantly with skeleton (bone) UI while details load
 * - NEW: Download button for approved rows (requirements.application_form vs approved_docs.approved_document)
 */

session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'user') {
    header('Location: user_login.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo

$FILE_BASE = '';

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function notempty($v): bool
{
    return $v !== null && trim((string)$v) !== '' && $v !== 'null';
}
function normalize_url(string $v, string $base): string
{
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('~^https?://~i', $v)) return $v;
    if ($base !== '') {
        $base = rtrim($base, '/');
        $v = ltrim($v, '/');
        return $base . '/' . $v;
    }
    return $v; // allow site-relative paths like /storage/... to pass through
}
/** Strip any rejection reason text from a notification message */
function strip_reason_from_message(?string $msg): string
{
    $t = trim((string)$msg);

    // Remove trailing "Reason: ..." or "Rejection Reason - ..." pieces
    $t = preg_replace('/\s*\(?\b(rejection\s*reason|reason)\b\s*[:\-–]\s*.*$/i', '', $t);
    // Remove trailing explanatory clauses like "because ..." or "due to ..."
    $t = preg_replace('/\s*\b(because|due\s+to)\b\s*.*$/i', '', $t);
    // Clean up extra spaces
    $t = trim(preg_replace('/\s{2,}/', ' ', $t));

    return $t;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'details') {
    header('Content-Type: application/json');
    $approvalId = $_GET['approval_id'] ?? '';
    if (!$approvalId) {
        echo json_encode(['ok' => false, 'error' => 'missing approval_id']);
        exit;
    }

    try {
        // 1) Query
        $st = $pdo->prepare("
          select
            a.approval_id,
            lower(coalesce(nullif(btrim(a.request_type), ''), ''))      as request_type,
            lower(coalesce(nullif(btrim(a.permit_type), ''), 'none'))   as permit_type,
            lower(coalesce(nullif(btrim(a.approval_status), ''), 'pending')) as approval_status,
            a.rejection_reason,
            a.submitted_at,
            a.application_id,
            a.requirement_id,
            c.first_name, c.last_name,
            ad.approved_document
          from public.approval a
          left join public.client c on c.client_id = a.client_id
          left join lateral (
              select d.approved_document
              from public.approved_docs d
              where d.approval_id = a.approval_id
              order by d.id desc
              limit 1
          ) ad on true
          where a.approval_id = :aid
            and c.user_id = :uid
          limit 1
        ");
        $st->execute([':aid' => $approvalId, ':uid' => $_SESSION['user_id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'not found']);
            exit;
        }

        // 2) Build application fields
        $appFields = [];
        $signatureField = null;
        if (notempty($row['application_id'])) {
            $st2 = $pdo->prepare("select * from public.application_form where application_id = :app limit 1");
            $st2->execute([':app' => $row['application_id']]);
            $app = $st2->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($app as $k => $v) {
                if (in_array($k, ['id', 'client_id'], true) || !notempty($v)) continue;
                $label = ucwords(str_replace('_', ' ', $k));
                $norm  = strtolower($label);
                if ($norm === 'additional information' || $norm === 'additional info') continue;
                if (strpos($norm, 'signature') !== false) {
                    $signatureField = ['label' => $label, 'value' => (string)$v];
                    continue;
                }
                $appFields[] = ['label' => $label, 'value' => (string)$v];
            }
        }
        if ($signatureField) array_unshift($appFields, $signatureField);

        // 3) Files
        $files = [];
        if (notempty($row['requirement_id'])) {
            $st3 = $pdo->prepare("select * from public.requirements where requirement_id = :rid limit 1");
            $st3->execute([':rid' => $row['requirement_id']]);
            $req = $st3->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($req as $k => $v) {
                if (in_array($k, ['id', 'requirement_id'], true) || !notempty($v)) continue;
                $url = normalize_url((string)$v, $FILE_BASE);
                if ($url === '') continue;
                $label = ucwords(str_replace('_', ' ', $k));
                $path  = parse_url($url, PHP_URL_PATH) ?? '';
                $ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $files[] = ['name' => $label, 'url' => $url, 'ext' => $ext];
            }
        }

        // 4) Download link when status is RELEASED
        $status      = strtolower((string)($row['approval_status'] ?? ''));
        $downloadUrl = ($status === 'released')
            ? normalize_url((string)($row['approved_document'] ?? ''), $FILE_BASE)
            : '';

        // 5) Echo once
        echo json_encode([
            'ok'   => true,
            'meta' => [
                'client'       => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'first_name'   => ($row['first_name'] ?? ''),
                'last_name'    => ($row['last_name'] ?? ''),
                'request_type' => $row['request_type'] ?? '',
                'permit_type'  => $row['permit_type'] ?? 'none',
                'status'       => $status ?: 'pending',
                'reason'       => $row['rejection_reason'] ?? '',
                'submitted_at' => $row['submitted_at'] ?? null,
                'approval_id'  => $row['approval_id'] ?? null,
                'download_url' => $downloadUrl,
            ],
            'application' => $appFields,
            'files'       => $files
        ]);
    } catch (Throwable $e) {
        error_log('[APP-STATUS AJAX] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit;
}


/* ---------- AJAX: notifications mark read / all read ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_all_read') {
    header('Content-Type: application/json');
    try {
        $st = $pdo->prepare('update public.notifications set is_read = true where "to" = :uid and is_read = false');
        $st->execute([':uid' => $_SESSION['user_id']]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        error_log('[APP-STATUS mark_all_read] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit;
}
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_read') {
    header('Content-Type: application/json');
    $notifId = $_GET['notif_id'] ?? '';
    if (!$notifId) {
        echo json_encode(['ok' => false, 'error' => 'missing notif_id']);
        exit;
    }
    try {
        $st = $pdo->prepare('update public.notifications set is_read = true where notif_id = :nid and "to" = :uid');
        $st->execute([':nid' => $notifId, ':uid' => $_SESSION['user_id']]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        error_log('[APP-STATUS mark_read] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit;
}

/* ---------- PAGE DATA: approvals (all for this user) ---------- */
$rows = [];
try {
    // Join requirements for application_form; LATERAL join to get latest approved_docs for each approval
    $st = $pdo->prepare("
        select
          a.approval_id,
          lower(coalesce(nullif(btrim(a.request_type), ''), ''))      as request_type,
          lower(coalesce(nullif(btrim(a.permit_type), ''), 'none'))   as permit_type,
          lower(coalesce(nullif(btrim(a.approval_status), ''), 'pending')) as approval_status,
          a.submitted_at,
          a.application_id,
          a.requirement_id,
          c.first_name,
          r.application_form as req_application_form,
          ad.approved_document
        from public.approval a
        left join public.client c on c.client_id = a.client_id
        left join public.requirements r on r.requirement_id = a.requirement_id
        left join lateral (
            select d.approved_document
            from public.approved_docs d
            where d.approval_id = a.approval_id
            order by d.id desc
            limit 1
        ) ad on true
        where c.user_id = :uid
        order by a.submitted_at desc nulls last, a.approval_id desc
        limit 200
    ");
    $st->execute([':uid' => $_SESSION['user_id']]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[APP-STATUS FETCH] ' . $e->getMessage());
}
$hasRows = count($rows) > 0;

/* ---------- PAGE DATA: notifications (to = current user_id) ---------- */
$notifs = [];
$unreadCount = 0;
try {
    $ns = $pdo->prepare('
        select notif_id, approval_id, incident_id, message, is_read, created_at
        from public.notifications
        where "to" = :uid
        order by created_at desc
        limit 30
    ');
    $ns->execute([':uid' => $_SESSION['user_id']]);
    $notifs = $ns->fetchAll(PDO::FETCH_ASSOC);
    foreach ($notifs as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }
} catch (Throwable $e) {
    error_log('[APP-STATUS NOTIFS] ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1" />
    <title>Application Status</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary-color: #2b6625;
            --primary-dark: #1e4a1a;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            --transition: all .2s ease;
        }

        /* === Header (copied/styled like user_home.php) === */
        body {
            background: #f9f9f9;
            color: #111827;
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial;
            padding-top: 100px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary-color);
            color: var(--white);
            padding: 0 30px;
            height: 58px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .1);
        }

        .logo {
            height: 45px;
            display: flex;
            align-items: center;
        }

        .logo a {
            display: flex;
            align-items: center;
            height: 90%;
        }

        .logo img {
            height: 98%;
            width: auto;
            transition: var(--transition);
        }

        .logo:hover img {
            transform: scale(1.05);
        }

        .nav-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-item {
            position: relative;
        }

        .nav-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgb(233, 255, 242);
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            color: black;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
        }

        .nav-icon i {
            font-size: 1.3rem;
            color: inherit;
        }

        .nav-icon:hover {
            background: rgba(255, 255, 255, .3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .25);
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--white);
            min-width: 300px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
            padding: 0;
        }

        .dropdown-menu.center {
            left: 50%;
            right: auto;
            transform: translateX(-50%) translateY(10px);
        }

        .dropdown:hover .dropdown-menu,
        .dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu.center:hover,
        .dropdown:hover .dropdown-menu.center {
            transform: translateX(-50%) translateY(0);
        }

        .dropdown-menu:before {
            content: '';
            position: absolute;
            bottom: 100%;
            right: 20px;
            border-width: 10px;
            border-style: solid;
            border-color: transparent transparent var(--white) transparent;
        }

        .dropdown-menu.center:before {
            left: 50%;
            right: auto;
            transform: translateX(-50%);
        }

        .dropdown-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            color: black;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1.05rem;
        }

        .dropdown-item i {
            width: 30px;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
            margin-right: 15px;
        }

        .dropdown-item:hover {
            background: var(--light-gray);
            padding-left: 30px;
        }

        .notifications-dropdown {
            min-width: 350px;
            max-height: 500px;
            overflow-y: auto;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .mark-all-read {
            color: var(--primary-color);
            cursor: pointer;
            font-size: .9rem;
            text-decoration: none;
            transition: var(--transition);
        }

        .mark-all-read:hover {
            color: var(--primary-dark);
            transform: scale(1.05);
        }

        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
            display: flex;
            align-items: flex-start;
            background: #fff;
        }

        .notification-item.unread {
            background-color: rgba(43, 102, 37, .05);
        }

        .notification-link {
            display: flex;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
            width: 100%;
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-icon {
            margin-right: 12px;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--primary-color);
        }

        .notification-message {
            color: #2b6625;
            font-size: .92rem;
            line-height: 1.35;
        }

        .notification-time {
            color: #999;
            font-size: .8rem;
            margin-top: 4px;
        }

        .notification-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #eee;
        }

        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: .9rem;
            transition: var(--transition);
            display: inline-block;
            padding: 5px 0;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .badge {
            position: absolute;
            top: 2px;
            right: 8px;
            background: #ff4757;
            color: #fff;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }

        /* === Page content styles (table, modal) — unchanged aside from spacing === */
        .main-content {
            padding: 0px 16px 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin: 1rem 0;

        }

        .title-wrap h1 {
            margin: 0;
            font-size: 30px;
            color: #2b6625;
        }

        .filters {
            display: flex;
            gap: .75rem;
            align-items: flex-end;
        }

        .filter-row {
            display: flex;
            flex-direction: column;
            gap: 6px
        }

        .input {
            padding: 10px 12px;
            min-width: 12rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            color: #111827
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #111827;
            color: #fff;
            cursor: pointer;
            text-decoration: none
        }

        .btn.small {
            padding: 7px 10px;
            font-size: .92rem
        }

        .btn.primary {
            background: #2b6625;
            border-color: #2b6625
        }

        .btn.ghost {
            background: #fff;
            color: #111827
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden
        }

        .card-header,
        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .75rem 1rem;
            border-bottom: 1px solid #f3f4f6
        }

        .table {
            width: 100%;
            border-collapse: collapse
        }

        .table th,
        .table td {
            padding: .75rem;
            border-bottom: 1px solid #f3f4f6;
            text-align: left;
            background: #fff
        }

        .table thead th {
            font-weight: 600;
            color: #374151;
            background: #fafafa
        }

        .pill {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: .85rem
        }

        .pill.neutral {
            background: #f3f4f6;
            color: #374151
        }

        .badge.status {
            position: static;
            width: auto;
            height: auto;
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 7px;
            font-size: .82rem;
            background: #e5e7eb;
            color: #111827
        }

        .badge.approved {
            background: #ecfdf5;
            color: #065f46
        }

        .badge.pending {
            background: #fff7ed;
            color: #9a3412
        }

        .badge.rejected {
            background: #fef2f2;
            color: #991b1b
        }

        .empty-row {
            text-align: center;
            color: #6b7280;
        }

        /* Modal + preview drawer */
        .modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1050
        }

        .modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .55)
        }

        .modal-panel {
            position: relative;
            z-index: 1;
            background: #fff;
            width: min(1280px, 98vw);
            max-height: 92vh;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .2)
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6
        }

        .icon-btn {
            background: #fff;
            border: 1px solid #e5e7eb;
            padding: 6px;
            border-radius: 8px;
            cursor: pointer
        }

        .meta-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            padding: 10px 16px;
            border-bottom: 1px solid #f3f4f6;
            background: #fafafa
        }

        .meta .label {
            display: block;
            font-size: .75rem;
            color: #6b7280
        }

        .meta .value {
            font-weight: 600
        }

        .alert {
            margin: 10px 16px 0;
            padding: 12px 14px;
            background: #fff1f1;
            color: #9b1c1c;
            border: 1px solid #ffdede;
            border-radius: 10px
        }

        .modal-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            flex: 1;
            min-height: 0
        }

        .pane {
            display: flex;
            flex-direction: column;
            min-height: 0
        }

        .pane.left {
            border-right: 1px solid #f3f4f6
        }

        .pane-title {
            margin: 0;
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6
        }

        .scroll-area {
            padding: 12px 16px 28px;
            overflow: auto;
            flex: 1;
            min-height: 0
        }

        .deflist {
            margin: 0
        }

        .defrow {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed #f3f4f6
        }

        .defrow dt {
            color: #6b7280
        }

        .defrow dd {
            margin: 0;
            word-break: break-word
        }

        .field-image {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 6px;
            background: #fff
        }

        .field-image img {
            display: block;
            max-width: 100%;
            height: auto
        }

        .file-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer
        }

        .file-item:hover {
            background: #f9fafb
        }

        .file-item .name {
            font-weight: 500
        }

        .file-item .hint {
            margin-left: auto;
            color: #6b7280;
            font-size: .85rem
        }

        .preview-drawer {
            position: fixed;
            top: 5%;
            right: 1%;
            width: min(640px, 96vw);
            height: 90vh;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            z-index: 1100;
            display: flex;
            flex-direction: column;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .2)
        }

        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid #f3f4f6
        }

        .truncate {
            max-width: 75%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .preview-body {
            position: relative;
            flex: 1;
            min-height: 0;
            overflow: hidden;
            scrollbar-gutter: stable both-edges
        }

        #previewImageWrap,
        #previewPdfWrap,
        #previewFrameWrap {
            position: absolute;
            inset: 0;
            overflow-y: auto;
            overflow-x: hidden
        }

        .preview-body iframe,
        .preview-body object,
        .preview-body embed,
        #previewImage {
            display: block;
            width: 100%;
            height: 100%;
            border: 0
        }

        #previewImage {
            height: 100%
        }

        .hidden {
            display: none !important
        }

        @media (max-width:980px) {
            .modal-content {
                grid-template-columns: 1fr
            }

            .pane.left {
                border-right: 0;
                border-bottom: 1px solid #f3f4f6
            }

            .meta-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .defrow {
                grid-template-columns: 1fr
            }
        }

        /* Active highlight for "Application Status" in the app menu (this page only) */
        .dropdown-menu a[href="application_status.php"] {
            background: var(--light-gray);
            color: var(--primary-color);
            font-weight: 700;
            padding-left: 30px;
            /* match hover offset, avoid jump */
            border-left: 4px solid var(--primary-color);
        }

        .dropdown-menu a[href="application_status.php"] i {
            color: var(--primary-dark) !important;
        }

        .dropdown-menu a[href="application_status.php"]:hover {
            background: var(--light-gray);
            /* keep stable on hover */
            padding-left: 30px;
        }

        /* ================= Skeleton (bone) UI ================= */
        .s-wrap.hidden {
            display: none !important
        }

        .s-wrap {
            padding: 12px 16px 16px
        }

        .s-meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 8px
        }

        .s-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 420px
        }

        .s-pane {
            border-top: 1px solid #f3f4f6
        }

        .s-pane+.s-pane {
            border-left: 1px solid #f3f4f6
        }

        .s-title {
            height: 18px;
            width: 40%;
            margin: 12px 0 6px
        }

        .s-list {
            padding: 12px 16px;
            display: grid;
            gap: 10px;
            max-height: calc(90vh - 210px);
            overflow: auto
        }

        .sk {
            position: relative;
            overflow: hidden;
            border-radius: 6px;
            background: #e5e7eb
        }

        .sk::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, .6), rgba(255, 255, 255, 0));
            animation: shimmer 1.1s infinite
        }

        @keyframes shimmer {
            100% {
                transform: translateX(100%)
            }
        }

        .sk.sm {
            height: 12px
        }

        .sk.md {
            height: 14px
        }

        .sk.row {
            height: 14px;
            width: 100%
        }

        .sk.w25 {
            width: 25%
        }

        .sk.w35 {
            width: 35%
        }

        .sk.w45 {
            width: 45%
        }

        .sk.w60 {
            width: 60%
        }

        .sk.w80 {
            width: 80%
        }

        .sk.w100 {
            width: 100%
        }

        .s-defrow {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 12px
        }

        @media (max-width:980px) {
            .s-meta {
                grid-template-columns: repeat(2, 1fr)
            }

            .s-body {
                grid-template-columns: 1fr
            }

            .s-pane+.s-pane {
                border-left: 0;
                border-top: 1px solid #f3f4f6
            }

            .s-defrow {
                grid-template-columns: 1fr
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="logo"><a href="user_home.php"><img src="seal.png" alt="Site Logo"></a></div>

        <!-- Mobile menu toggle (optional) -->
        <button class="mobile-toggle" style="display:none"><i class="fas fa-bars"></i></button>

        <!-- Navigation (copied from home) -->
        <div class="nav-container">
            <!-- Dashboard Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="user_reportaccident.php" class="dropdown-item"><i class="fas fa-file-invoice"></i><span>Report Incident</span></a>
                    <a href="useraddseed.php" class="dropdown-item"><i class="fas fa-seedling"></i><span>Request Seedlings</span></a>
                    <a href="useraddwild.php" class="dropdown-item"><i class="fas fa-paw"></i><span>Wildlife Permit</span></a>
                    <a href="useraddtreecut.php" class="dropdown-item"><i class="fas fa-tree"></i><span>Tree Cutting Permit</span></a>
                    <a href="useraddlumber.php" class="dropdown-item"><i class="fas fa-boxes"></i><span>Lumber Dealers Permit</span></a>
                    <a href="useraddwood.php" class="dropdown-item"><i class="fas fa-industry"></i><span>Wood Processing Permit</span></a>
                    <a href="useraddchainsaw.php" class="dropdown-item active-page"><i class="fas fa-tools"></i><span>Chainsaw Permit</span></a>
                    <a href="applicationstatus.php" class="dropdown-item"><i class="fas fa-clipboard-check"></i><span>Application Status</span></a>
                </div>
            </div>

            <!-- Notifications (LIVE) -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge" id="notifBadge"><?= h((string)$unreadCount) ?></span>
                    <?php endif; ?>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>

                    <?php if (!$notifs): ?>
                        <div class="notification-item">
                            <div class="notification-content">
                                <div class="notification-title">No record found</div>
                                <div class="notification-message">There are no notifications.</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifs as $n): ?>
                            <?php
                            $unread = empty($n['is_read']);
                            $ts = $n['created_at'] ? (new DateTime((string)$n['created_at']))->getTimestamp() : time();
                            $title = $n['approval_id'] ? 'Permit Update' : ($n['incident_id'] ? 'Incident Update' : 'Notification');

                            // Strip any rejection reason from the message for notifications UI
                            $msg = strip_reason_from_message($n['message'] ?? '');
                            if ($msg === '') {
                                $msg = 'There’s an update.';
                            }
                            ?>
                            <div class="notification-item <?= $unread ? 'unread' : '' ?>">
                                <a href="#" class="notification-link"
                                    data-notif-id="<?= h((string)$n['notif_id']) ?>"
                                    <?= $n['approval_id'] ? 'data-approval-id="' . h((string)$n['approval_id']) . '"' : '' ?>
                                    <?= $n['incident_id'] ? 'data-incident-id="' . h((string)$n['incident_id']) . '"' : '' ?>>
                                    <div class="notification-icon"><i class="fas fa-exclamation-circle"></i></div>
                                    <div class="notification-content">
                                        <div class="notification-title"><?= h($title) ?></div>
                                        <div class="notification-message"><?= h($msg) ?></div>
                                        <div class="notification-time" data-ts="<?= h((string)$ts) ?>">just now</div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="notification-footer">
                        <a href="user_notification.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content">
        <section class="page-header">
            <div class="title-wrap">
                <h1>Application Status</h1>
                <p class="subtitle">Your permit requests</p>
            </div>

            <div class="filters">
                <div class="filter-row">
                    <label for="filterStatus">Status</label>
                    <select id="filterStatus" class="input">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div class="filter-row">
                    <label for="filterReqType">Request Type</label>
                    <select id="filterReqType" class="input">
                        <option value="">All</option>
                    </select>
                </div>

                <div class="filter-row">
                    <label for="filterPermitType">Permit Type</label>
                    <select id="filterPermitType" class="input">
                        <option value="">All</option>
                    </select>
                </div>

                <div class="filter-row">
                    <label for="searchName">Search Client</label>
                    <input id="searchName" class="input" type="text" placeholder="First name…">
                </div>

                <button id="btnClearFilters" class="btn ghost" type="button"><i class="fas fa-eraser"></i> Clear</button>
            </div>
        </section>

        <main class="card">
            <div class="card-header">
                <h2>Requests</h2>
                <div class="right-actions"><span id="rowsCount" class="muted"><?= count($rows) ?> results</span></div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client First Name</th>
                            <th>Request Type</th>
                            <th>Permit Type</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="statusTableBody">
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $st = strtolower((string)($r['approval_status'] ?? 'pending'));
                            $cls = $st === 'approved' ? 'approved' : ($st === 'rejected' ? 'rejected' : 'pending');
                            $rt  = strtolower((string)($r['request_type'] ?? ''));
                            $pt  = strtolower((string)($r['permit_type'] ?? 'none'));

                            // Compute download URL per your rule:
                            // - permit_type 'none'  -> requirements.application_form
                            // - new / renewal       -> approved_docs.approved_document
                            $downloadUrl = '';
                            if ($st === 'approved') {
                                if ($pt === 'none') {
                                    $downloadUrl = normalize_url((string)($r['req_application_form'] ?? ''), $FILE_BASE);
                                } else {
                                    $downloadUrl = normalize_url((string)($r['approved_document'] ?? ''), $FILE_BASE);
                                }
                            }
                            ?>
                            <tr
                                data-approval-id="<?= h($r['approval_id']) ?>"
                                data-status="<?= h($st) ?>"
                                data-request-type="<?= h($rt) ?>"
                                data-permit-type="<?= h($pt) ?>"
                                data-client="<?= h($r['first_name'] ?? '') ?>"
                                data-download-url="<?= h($downloadUrl) ?>">
                                <td><?= h($r['first_name'] ?? '—') ?></td>
                                <td><span class="pill"><?= h($rt ?: '—') ?></span></td>
                                <td><span class="pill neutral"><?= h($pt) ?></span></td>
                                <td><span class="badge status <?= $cls ?>"><?= ucfirst($st) ?></span></td>
                                <td><?= h($r['submitted_at'] ? date('Y-m-d H:i', strtotime((string)$r['submitted_at'])) : '—') ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                                        <button class="btn small" data-action="view"><i class="fas fa-eye"></i> View</button>
                                        <?php if ($st === 'approved'): ?>
                                            <button class="btn small" data-action="download" <?= $downloadUrl ? '' : 'disabled title="No file available yet"' ?>>
                                                <i class="fas fa-download"></i> Download
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Always render a "No record found" row so filtering can show it too -->
                        <tr id="noRows" class="empty-row" style="<?= $hasRows ? 'display:none' : '' ?>">
                            <td colspan="6" style="padding:1rem;">No record found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- ===== TWO-PANE MODAL ===== -->
    <div id="viewModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-panel" role="document">
            <div class="modal-header">
                <h3 id="modalTitle">Request Details</h3>
                <div style="display:flex;gap:8px;align-items:center">
                    <button class="btn small primary hidden" id="btnDownloadIssued" type="button" aria-hidden="true">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <button class="btn primary hidden" id="btnRequestAgain" type="button" aria-hidden="true">
                        <i class="fas fa-rotate-right"></i> Request again
                    </button>
                    <button class="icon-btn" type="button" aria-label="Close" data-close-modal>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>


            <!-- SKELETON while loading -->
            <div id="modalSkeleton" class="s-wrap hidden" aria-hidden="true">
                <div class="s-meta">
                    <div class="sk sm w60"></div>
                    <div class="sk sm w45"></div>
                    <div class="sk sm w35"></div>
                    <div class="sk sm w25"></div>
                </div>
                <div class="s-body">
                    <div class="s-pane">
                        <div class="s-title sk md"></div>
                        <div class="s-list">
                            <div class="s-defrow">
                                <div class="sk sm w60"></div>
                                <div class="sk sm w80"></div>
                            </div>
                            <div class="s-defrow">
                                <div class="sk sm w35"></div>
                                <div class="sk sm w100"></div>
                            </div>
                            <div class="s-defrow">
                                <div class="sk sm w45"></div>
                                <div class="sk sm w80"></div>
                            </div>
                            <div class="s-defrow">
                                <div class="sk sm w25"></div>
                                <div class="sk sm w60"></div>
                            </div>
                        </div>
                    </div>
                    <div class="s-pane">
                        <div class="s-title sk md"></div>
                        <div class="s-list">
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END SKELETON -->

            <div class="meta-strip">
                <div class="meta"><span class="label">Client</span><span id="metaClientName" class="value">—</span></div>
                <div class="meta"><span class="label">Request Type</span><span id="metaRequestType" class="pill">—</span></div>
                <div class="meta"><span class="label">Permit Type</span><span id="metaPermitType" class="pill neutral">—</span></div>
                <div class="meta"><span class="label">Status</span><span id="metaStatus" class="badge status">—</span></div>
            </div>

            <div id="rejectBanner" class="alert hidden"></div>

            <div class="modal-content">
                <section class="pane left">
                    <h4 class="pane-title"><i class="fas fa-list"></i> Application Form</h4>
                    <div id="formScroll" class="scroll-area">
                        <dl id="applicationFields" class="deflist"></dl>
                        <div id="formEmpty" class="hidden" style="text-align:center;color:#6b7280;">No form data.</div>
                    </div>
                </section>

                <section class="pane right">
                    <h4 class="pane-title"><i class="fas fa-paperclip"></i> Documents</h4>
                    <div id="filesScroll" class="scroll-area">
                        <ul id="filesList" class="file-list"></ul>
                        <div id="filesEmpty" class="hidden" style="text-align:center;color:#6b7280;">No documents uploaded.</div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Drawer -->
        <div id="filePreviewDrawer" class="preview-drawer hidden" aria-live="polite" aria-hidden="true">
            <div class="preview-header">
                <span id="previewTitle" class="truncate">Document</span>
                <button class="icon-btn" type="button" aria-label="Close preview" data-close-preview><i class="fas fa-times"></i></button>
            </div>
            <div class="preview-body">
                <div id="previewImageWrap" class="hidden"><img id="previewImage" alt="Preview"></div>
                <div id="previewPdfWrap" class="hidden">
                    <object id="previewPdf" type="application/pdf" data="" aria-label="PDF preview">
                        <iframe id="previewPdfFallback" src="" title="PDF preview" loading="lazy"></iframe>
                    </object>
                </div>
                <div id="previewFrameWrap" class="hidden"><iframe id="previewFrame" title="Document preview" loading="lazy"></iframe></div>
                <div id="previewLinkWrap" class="hidden" style="padding:16px;text-align:center">
                    <p class="muted">Preview not available. Open or download the file instead.</p>
                    <a id="previewDownload" class="btn" href="#" target="_blank" rel="noopener"><i class="fas fa-download"></i> Open / Download</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            /* --- Dropdown hover like home page --- */
            document.querySelectorAll('.dropdown').forEach(dd => {
                const menu = dd.querySelector('.dropdown-menu');
                dd.addEventListener('mouseenter', () => {
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(0)' : 'translateY(0)';
                });
                dd.addEventListener('mouseleave', (e) => {
                    if (!dd.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    }
                });
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    });
                }
            });

            /* --- Build filter options from table --- */
            const reqTypeSet = new Set(),
                permitTypeSet = new Set();
            document.querySelectorAll('#statusTableBody tr').forEach(tr => {
                if (tr.id === 'noRows') return;
                const rt = (tr.dataset.requestType || '').trim();
                const pt = (tr.dataset.permitType || '').trim();
                if (rt) reqTypeSet.add(rt);
                if (pt) permitTypeSet.add(pt);
            });
            const reqTypeSelect = document.getElementById('filterReqType');
            const permitTypeSelect = document.getElementById('filterPermitType');
            [...reqTypeSet].sort().forEach(v => reqTypeSelect.insertAdjacentHTML('beforeend', `<option value="${v}">${v}</option>`));
            [...permitTypeSet].sort().forEach(v => permitTypeSelect.insertAdjacentHTML('beforeend', `<option value="${v}">${v}</option>`));

            /* --- Modal utilities + state --- */
            const btnRequestAgain = document.getElementById('btnRequestAgain');
            const btnDownloadIssued = document.getElementById('btnDownloadIssued');
            const modalEl = document.getElementById('viewModal');
            const modalSkeleton = document.getElementById('modalSkeleton');
            const metaStripEl = document.querySelector('.meta-strip');
            const modalContent = document.querySelector('.modal-content');

            function showModalSkeleton() {
                modalSkeleton.classList.remove('hidden');
                metaStripEl.classList.add('hidden');
                modalContent.classList.add('hidden');
                btnRequestAgain.classList.add('hidden');
                if (btnDownloadIssued) btnDownloadIssued.classList.add('hidden'); // ← new
            }

            function hideModalSkeleton() {
                modalSkeleton.classList.add('hidden');
                metaStripEl.classList.remove('hidden');
                modalContent.classList.remove('hidden');
            }

            let cachedDetails = null;
            async function forceDownload(url) {
                if (!url) return;
                try {
                    if (url.startsWith('data:')) {
                        const a = document.createElement('a');
                        a.href = url;
                        a.setAttribute('download', 'document');
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        return;
                    }
                    const res = await fetch(url, {
                        credentials: 'omit'
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    const dispo = res.headers.get('Content-Disposition') || '';
                    let filename = 'document';
                    const m = dispo.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
                    if (m && m[1]) {
                        filename = decodeURIComponent(m[1].replace(/["']/g, ''));
                    } else {
                        const u = new URL(url, window.location.origin);
                        filename = (u.pathname.split('/').pop() || 'document').split('?')[0];
                    }

                    const blob = await res.blob();
                    const blobUrl = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.setAttribute('download', filename);
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    setTimeout(() => URL.revokeObjectURL(blobUrl), 2000);
                } catch {
                    const iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = url;
                    document.body.appendChild(iframe);
                    setTimeout(() => iframe.remove(), 10000);
                }
            }


            async function openApproval(approvalId) {
                if (!approvalId) return;

                // Reset UI placeholders
                document.getElementById('metaClientName').textContent = '—';
                document.getElementById('metaRequestType').textContent = '—';
                document.getElementById('metaPermitType').textContent = '—';
                const ms = document.getElementById('metaStatus');
                ms.textContent = '—';
                ms.className = 'badge status';

                const banner = document.getElementById('rejectBanner');
                banner.classList.add('hidden');
                banner.textContent = '';

                const list = document.getElementById('applicationFields');
                const filesList = document.getElementById('filesList');
                const formEmpty = document.getElementById('formEmpty');
                const filesEmpty = document.getElementById('filesEmpty');

                list.innerHTML = '';
                filesList.innerHTML = '';
                formEmpty.classList.add('hidden');
                filesEmpty.classList.add('hidden');

                btnRequestAgain.classList.add('hidden');
                btnRequestAgain.disabled = true;
                btnRequestAgain.setAttribute('aria-hidden', 'true');

                // OPEN MODAL immediately with skeleton
                showModalSkeleton();
                modalEl.classList.remove('hidden');

                // Fetch details
                const res = await fetch(`<?php echo basename(__FILE__); ?>?ajax=details&approval_id=${encodeURIComponent(approvalId)}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                }).then(r => r.json()).catch(() => ({
                    ok: false
                }));

                if (!res.ok) {
                    hideModalSkeleton();
                    ms.textContent = 'Error';
                    formEmpty.classList.remove('hidden');
                    filesEmpty.classList.remove('hidden');
                    alert('Failed to load details');
                    return;
                }

                // Populate
                cachedDetails = res;
                const meta = res.meta || {};
                document.getElementById('metaClientName').textContent = meta.client || '—';
                document.getElementById('metaRequestType').textContent = meta.request_type || '—';
                document.getElementById('metaPermitType').textContent = meta.permit_type || '—';

                const st = (meta.status || '').toLowerCase();
                ms.textContent = st ? st[0].toUpperCase() + st.slice(1) : '—';
                // 'released' uses the green badge like approved
                ms.className = 'badge status ' + ((st === 'released' || st === 'approved') ? 'approved' : (st === 'rejected' ? 'rejected' : 'pending'));

                // Toggle modal Download button only for released
                if (btnDownloadIssued) {
                    btnDownloadIssued.classList.add('hidden');
                    btnDownloadIssued.disabled = true;
                    btnDownloadIssued.setAttribute('aria-hidden', 'true');
                    btnDownloadIssued.onclick = null;

                    const dlUrl = (meta.download_url || '').trim();
                    if (st === 'released' && dlUrl) {
                        btnDownloadIssued.classList.remove('hidden');
                        btnDownloadIssued.disabled = false;
                        btnDownloadIssued.setAttribute('aria-hidden', 'false');
                        btnDownloadIssued.onclick = () => forceDownload(dlUrl);
                    }
                }


                if (st === 'rejected' && (meta.reason || '').trim() !== '') {
                    banner.textContent = 'Reason for rejection: ' + meta.reason;
                    banner.classList.remove('hidden');
                    btnRequestAgain.classList.remove('hidden');
                    btnRequestAgain.disabled = false;
                    btnRequestAgain.setAttribute('aria-hidden', 'false');
                }

                // Application fields
                list.innerHTML = '';
                if (Array.isArray(res.application) && res.application.length) {
                    formEmpty.classList.add('hidden');
                    res.application.forEach(({
                        label,
                        value
                    }) => {
                        const norm = (label || '').trim().toLowerCase();
                        if (norm === 'additional information' || norm === 'additional info') return;
                        const row = document.createElement('div');
                        row.className = 'defrow';
                        const dt = document.createElement('dt');
                        dt.textContent = label;
                        const dd = document.createElement('dd');
                        const isImageLike = norm.includes('signature') || /^https?:\/\//i.test(value || '') || (value || '').startsWith('data:image/');
                        if (isImageLike) {
                            const img = document.createElement('img');
                            img.src = value;
                            img.alt = label;
                            img.loading = 'lazy';
                            const wrap = document.createElement('div');
                            wrap.className = 'field-image';
                            wrap.appendChild(img);
                            dd.appendChild(wrap);
                        } else {
                            dd.textContent = value;
                        }
                        row.appendChild(dt);
                        row.appendChild(dd);
                        list.appendChild(row);
                    });
                } else {
                    formEmpty.classList.remove('hidden');
                }

                // Files
                filesList.innerHTML = '';
                if (Array.isArray(res.files) && res.files.length) {
                    filesEmpty.classList.add('hidden');
                    res.files.forEach(f => {
                        const li = document.createElement('li');
                        li.className = 'file-item';
                        li.dataset.fileUrl = f.url;
                        li.dataset.fileName = f.name;
                        li.dataset.fileExt = (f.ext || '').toLowerCase();
                        li.innerHTML = `<i class="far fa-file"></i><span class="name">${f.name}</span><span class="hint">${(f.ext||'').toUpperCase()}</span>`;
                        filesList.appendChild(li);
                    });
                } else {
                    filesEmpty.classList.remove('hidden');
                }

                // Reveal content
                hideModalSkeleton();
            }

            // table "View"
            document.getElementById('statusTableBody')?.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action="view"]');
                if (!btn) return;
                const tr = btn.closest('tr');
                const approvalId = tr?.dataset.approvalId;
                openApproval(approvalId);
            });

            // table "Download" (force download without opening a page or tab)
            document.getElementById('statusTableBody')?.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-action="download"]');
                if (!btn) return;

                const tr = btn.closest('tr');
                const url = (tr?.dataset.downloadUrl || '').trim();
                if (!url) {
                    alert('Download link is not available yet.');
                    return;
                }

                try {
                    // Fast path for data URLs
                    if (url.startsWith('data:')) {
                        const a = document.createElement('a');
                        a.href = url;
                        a.setAttribute('download', 'document');
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        return;
                    }

                    const res = await fetch(url, {
                        credentials: 'omit'
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    // Try filename from headers; else derive from URL path
                    const dispo = res.headers.get('Content-Disposition') || '';
                    let filename = 'document';
                    const m = dispo.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
                    if (m && m[1]) {
                        filename = decodeURIComponent(m[1].replace(/["']/g, ''));
                    } else {
                        const u = new URL(url, window.location.origin);
                        filename = (u.pathname.split('/').pop() || 'document').split('?')[0];
                    }

                    const blob = await res.blob();
                    const blobUrl = URL.createObjectURL(blob);

                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.setAttribute('download', filename);
                    document.body.appendChild(a);
                    a.click();
                    a.remove();

                    setTimeout(() => URL.revokeObjectURL(blobUrl), 2000);
                } catch (err) {
                    // Fallback: hidden iframe download (no navigation)
                    const iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = url;
                    document.body.appendChild(iframe);
                    setTimeout(() => iframe.remove(), 10000);
                }
            });

            // close modal
            document.querySelectorAll('[data-close-modal]').forEach(btn => btn.addEventListener('click', () => {
                document.getElementById('viewModal').classList.add('hidden');
                closePreview();
            }));
            document.querySelector('.modal-backdrop')?.addEventListener('click', () => {
                document.getElementById('viewModal').classList.add('hidden');
                closePreview();
            });

            // file preview
            document.getElementById('filesList')?.addEventListener('click', (e) => {
                const li = e.target.closest('.file-item');
                if (!li) return;
                showPreview(li.dataset.fileName || 'Document', li.dataset.fileUrl || '#', (li.dataset.fileExt || '').toLowerCase());
            });
            document.querySelector('[data-close-preview]')?.addEventListener('click', closePreview);

            function closePreview() {
                document.getElementById('filePreviewDrawer').classList.add('hidden');
                document.getElementById('previewImage').src = '';
                document.getElementById('previewFrame').src = '';
                document.getElementById('previewPdf').data = '';
                document.getElementById('previewPdfFallback').src = '';
            }

            function showPreview(name, url, ext) {
                const drawer = document.getElementById('filePreviewDrawer');
                document.getElementById('previewTitle').textContent = name;
                const imgWrap = document.getElementById('previewImageWrap');
                const pdfWrap = document.getElementById('previewPdfWrap');
                const frameWrap = document.getElementById('previewFrameWrap');
                const linkWrap = document.getElementById('previewLinkWrap');
                imgWrap.classList.add('hidden');
                pdfWrap.classList.add('hidden');
                frameWrap.classList.add('hidden');
                linkWrap.classList.add('hidden');

                const imgExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                const offExt = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
                const txtExt = ['txt', 'csv', 'json', 'md', 'log'];

                if (imgExt.includes(ext) || url.startsWith('data:image/')) {
                    document.getElementById('previewImage').src = url;
                    imgWrap.classList.remove('hidden');
                } else if (ext === 'pdf') {
                    const pdfUrl = url + (url.includes('#') ? '' : '#') + 'zoom=page-width';
                    document.getElementById('previewPdf').data = pdfUrl;
                    document.getElementById('previewPdfFallback').src = 'https://docs.google.com/gview?embedded=1&url=' + encodeURIComponent(url);
                    pdfWrap.classList.remove('hidden');
                } else if (offExt.includes(ext)) {
                    document.getElementById('previewFrame').src = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(url);
                    frameWrap.classList.remove('hidden');
                } else if (txtExt.includes(ext)) {
                    document.getElementById('previewFrame').src = 'https://docs.google.com/gview?embedded=1&url=' + encodeURIComponent(url);
                    frameWrap.classList.remove('hidden');
                } else {
                    document.getElementById('previewDownload').href = url;
                    linkWrap.classList.remove('hidden');
                }
                drawer.classList.remove('hidden');
            }

            // filters
            const filterStatus = document.getElementById('filterStatus');
            const filterReqType = document.getElementById('filterReqType');
            const filterPermit = document.getElementById('filterPermitType');
            const searchName = document.getElementById('searchName');
            const rowsCount = document.getElementById('rowsCount');
            const noRowsTr = document.getElementById('noRows');

            document.getElementById('btnClearFilters')?.addEventListener('click', () => {
                filterStatus.value = '';
                filterReqType.value = '';
                filterPermit.value = '';
                searchName.value = '';
                applyFilters();
            });
            [filterStatus, filterReqType, filterPermit, searchName].forEach(el => el.addEventListener('input', applyFilters));

            function applyFilters() {
                const st = (filterStatus.value || '').toLowerCase();
                const rt = (filterReqType.value || '').toLowerCase();
                const pt = (filterPermit.value || '').toLowerCase();
                const q = (searchName.value || '').trim().toLowerCase();
                let shown = 0;
                document.querySelectorAll('#statusTableBody tr').forEach(tr => {
                    if (tr.id === 'noRows') return;
                    const name = (tr.dataset.client || '').toLowerCase();
                    const stat = (tr.dataset.status || '').toLowerCase();
                    const reqT = (tr.dataset.requestType || '').toLowerCase();
                    const permT = (tr.dataset.permitType || '').toLowerCase();
                    let ok = true;
                    if (st && stat !== st) ok = false;
                    if (rt && reqT !== rt) ok = false;
                    if (pt && permT !== pt) ok = false;
                    if (q && !name.includes(q)) ok = false;
                    tr.style.display = ok ? '' : 'none';
                    if (ok) shown++;
                });
                if (noRowsTr) noRowsTr.style.display = shown === 0 ? 'table-row' : 'none';
                rowsCount.textContent = `${shown} result${shown===1?'':'s'}`;
            }

            // Run once to ensure the noRows state is correct when page loads
            applyFilters?.();

            // Request again (guard to rejected only)
            btnRequestAgain?.addEventListener('click', () => {
                if (!cachedDetails || !cachedDetails.meta) return;
                const statusNow = (cachedDetails.meta.status || '').toLowerCase();
                if (statusNow !== 'rejected') return;
                const meta = cachedDetails.meta;
                const reqType = (meta.request_type || '').toLowerCase();
                const permit = (meta.permit_type || '').toLowerCase();
                const routeMap = {
                    wildlife: 'useraddwild.php',
                    chainsaw: 'useraddchainsaw.php',
                    lumber: 'useraddlumber.php',
                    wood: 'useraddwood.php',
                    seedling: 'useraddseed.php',
                    treecut: 'useraddtreecut.php'
                };
                const target = routeMap[reqType] || 'user_home.php';
                const prefill = {
                    first_name: meta.first_name || '',
                    last_name: meta.last_name || '',
                    client_full_name: (meta.client || '').trim()
                };
                try {
                    const payload = {
                        approval_id: meta.approval_id,
                        request_type: reqType,
                        permit_type: permit,
                        prefill,
                        application: (cachedDetails.application || [])
                    };
                    sessionStorage.setItem('reapply_payload', JSON.stringify(payload));
                } catch {}
                let qs = '';
                if (reqType === 'wildlife' && permit === 'new') qs = `?mode=new&from=${encodeURIComponent(meta.approval_id||'')}`;
                else if (permit) qs = `?mode=${encodeURIComponent(permit)}&from=${encodeURIComponent(meta.approval_id||'')}`;
                window.location.href = target + qs;
            });

            /* ===== Notifications: timeago, open, mark read ===== */
            // relative times
            document.querySelectorAll('.notification-time[data-ts]').forEach(el => {
                const ts = Number(el.dataset.ts || '0') * 1000;
                if (!ts) return;
                el.textContent = timeAgo(ts);
                el.title = new Date(ts).toLocaleString();
            });

            function timeAgo(ms) {
                const s = Math.floor((Date.now() - ms) / 1000);
                if (s < 60) return 'just now';
                const m = Math.floor(s / 60);
                if (m < 60) return `${m} minute${m>1?'s':''} ago`;
                const h = Math.floor(m / 60);
                if (h < 24) return `${h} hour${h>1?'s':''} ago`;
                const d = Math.floor(h / 24);
                if (d < 7) return `${d} day${d>1?'s':''} ago`;
                const w = Math.floor(d / 7);
                if (w < 5) return `${w} week${w>1?'s':''} ago`;
                const mo = Math.floor(d / 30);
                if (mo < 12) return `${mo} month${mo>1?'s':''} ago`;
                const y = Math.floor(d / 365);
                return `${y} year${y>1?'s':''} ago`;
            }

            // mark all read
            const badge = document.getElementById('notifBadge');
            document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
                e.preventDefault();
                const r = await fetch(`<?php echo basename(__FILE__); ?>?ajax=mark_all_read`, {
                        method: 'POST'
                    })
                    .then(r => r.json()).catch(() => ({
                        ok: false
                    }));
                if (!r.ok) return;
                document.querySelectorAll('.notification-item.unread').forEach(item => item.classList.remove('unread'));
                if (badge) badge.style.display = 'none';
            });

            // click a single notification
            document.querySelector('.notifications-dropdown')?.addEventListener('click', async (e) => {
                const link = e.target.closest('.notification-link');
                if (!link) return;
                e.preventDefault();
                const notifId = link.dataset.notifId;
                const approvalId = link.dataset.approvalId || '';
                const incidentId = link.dataset.incidentId || '';

                // mark this one read (optimistic)
                link.closest('.notification-item')?.classList.remove('unread');
                const left = document.querySelectorAll('.notification-item.unread').length;
                if (badge) {
                    if (left > 0) {
                        badge.textContent = String(left);
                        badge.style.display = 'flex';
                    } else badge.style.display = 'none';
                }
                fetch(`<?php echo basename(__FILE__); ?>?ajax=mark_read&notif_id=${encodeURIComponent(notifId)}`, {
                    method: 'POST'
                }).catch(() => {});

                // open target
                if (approvalId) openApproval(approvalId);
                else if (incidentId) window.location.href = `user_reportaccident.php?view=${encodeURIComponent(incidentId)}`;
            });
        });
    </script>

</body>

</html>