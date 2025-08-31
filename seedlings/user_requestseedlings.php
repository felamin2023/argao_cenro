<?php

declare(strict_types=1);

session_start();

// Must be logged in and an Admin
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo (PDO -> Postgres)

// Current user (UUID)
$user_id = (string)$_SESSION['user_id'];

// Guard admin belongs to SEEDLING
try {
    $st = $pdo->prepare("
        SELECT role, department, status
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $user_id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    $isAdmin  = $u && strtolower((string)$u['role']) === 'admin';
    $isSeed   = $u && strtolower((string)$u['department']) === 'seedling';
    if (!$isAdmin || !$isSeed) {
        header('Location: ../superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[SEEDLING-GUARD] ' . $e->getMessage());
    header('Location: ../superlogin.php');
    exit();
}

// Get current page for header active state
$current_page = basename($_SERVER['PHP_SELF']);
$quantities = [
    'total_received' => 1250,
    'plantable_seedlings' => 980,
    'total_released' => 720,
    'total_discarded' => 150,
    'total_balance' => 380,
    'all_records' => 2150
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seedlings Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/user_requestseedlings.css">


</head>

<style>
    /* Add the new styles from the seedling request page */
    .requirements-form {
        margin-top: 20px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        border: 1px solid #ddd;
    }

    .form-header {
        background-color: #2b6625;
        color: white;
        padding: 20px 30px;
        border-bottom: 1px solid #1e4a1a;
    }

    .form-header h2 {
        text-align: center;
        font-size: 1.5rem;
        margin: 0;
    }

    .form-body {
        padding: 30px;
    }

    .requirements-list {
        display: grid;
        gap: 20px;
    }

    .requirement-item {
        display: flex;
        flex-direction: column;
        gap: 15px;
        padding: 20px;
        background: #f5f5f5;
        border-radius: 8px;
        border-left: 4px solid #2b6625;
    }

    .requirement-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .requirement-title {
        font-weight: 600;
        color: #1e4a1a;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .requirement-number {
        background: #2b6625;
        color: white;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }

    .file-upload {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .uploaded-files {
        margin-top: 10px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .file-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: white;
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid #ddd;
    }

    .file-info {
        display: flex;
        align-items: center;
        gap: 8px;
        overflow: hidden;
    }

    .file-icon {
        color: #2b6625;
        flex-shrink: 0;
    }

    .file-actions {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }

    .file-action-btn {
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        transition: all 0.2s ease;
        padding: 5px;
    }

    .file-action-btn:hover {
        color: #2b6625;
    }

    .fee-info {
        margin-top: 20px;
        padding: 15px;
        background: rgba(43, 102, 37, 0.1);
        border-radius: 8px;
        border-left: 4px solid #2b6625;
    }

    .fee-info p {
        margin: 5px 0;
        color: #1e4a1a;
        font-weight: 500;
    }

    .modal-btn {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
    }

    .modal-btn-save {
        background: #2b6625;
        color: white;
    }

    .modal-btn-save:hover {
        background: #1e4a1a;
    }

    .modal-btn-cancel {
        background: #f5f5f5;
        color: #333;
        margin-left: 10px;
    }

    .modal-btn-cancel:hover {
        background: #e0e0e0;
    }

    .wood-table thead th {
        white-space: nowrap;
    }

    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        z-index: 1000;
    }

    .modal .modal-content {
        background: #fff;
        max-width: 900px;
        width: 95%;
        margin: 60px auto;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 12px 24px rgba(0, 0, 0, .25);
    }

    .modal-header {
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #eee;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 20px;
    }

    .modal-body {
        padding: 16px;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
        padding: 16px;
        border-top: 1px solid #eee;
    }

    .close {
        cursor: pointer;
        font-size: 24px;
        line-height: 1;
    }

    .file-preview {
        width: 100%;
        height: 420px;
        border: 1px solid #ddd;
        border-radius: 6px;
    }

    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .pair label {
        font-weight: 600;
        display: block;
        margin-bottom: 4px;
        color: #222;
    }

    .pair div,
    .pair a {
        font-size: 14px;
        word-break: break-word;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 12px;
        text-transform: uppercase;
    }

    .status-pending {
        /* background: #fff3cd; */
        color: #856404;
        border: 1px solid #ffeeba;
    }

    .status-approved {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .status-rejected {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .btn-ghost {
        border: 1px solid #ccc;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
    }

    .btn-primary {
        background: #005117;
        color: #fff;
        border: 0;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
    }

    .btn-danger {
        background: #b91c1c;
        color: #fff;
        border: 0;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
    }

    #detailModal {
        z-index: 1000;
    }

    #docModal {
        z-index: 1100;
    }
</style>
</head>

<body>
    <div id="profile-notification" style="display:none;position:fixed;top:5px;left:50%;transform:translateX(-50%);background:#323232;color:#fff;padding:16px 32px;border-radius:8px;font-size:1.1rem;z-index:9999;box-shadow:0 2px 8px rgba(0,0,0,0.15);text-align:center;min-width:220px;max-width:90vw;"></div>

    <header>
        <div class="logo">
            <a href="seedlingshome.php"><img src="seal.png" alt="Site Logo"></a>
        </div>

        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon active"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="incoming.php" class="dropdown-item">
                        <i class="fas fa-seedling"></i><span class="item-text">Seedlings Received</span>
                        <span class="quantity-badge"><?= (int)$quantities['total_received'] ?></span>
                    </a>
                    <a href="releasedrecords.php" class="dropdown-item">
                        <i class="fas fa-truck"></i><span class="item-text">Seedlings Released</span>
                        <span class="quantity-badge"><?= (int)$quantities['total_released'] ?></span>
                    </a>
                    <a href="discardedrecords.php" class="dropdown-item">
                        <i class="fas fa-trash-alt"></i><span class="item-text">Seedlings Discarded</span>
                        <span class="quantity-badge"><?= (int)$quantities['total_discarded'] ?></span>
                    </a>
                    <a href="balancerecords.php" class="dropdown-item">
                        <i class="fas fa-calculator"></i><span class="item-text">Seedlings Left</span>
                        <span class="quantity-badge"><?= (int)$quantities['total_balance'] ?></span>
                    </a>

                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i><span>Incident Reports</span>
                    </a>

                    <a href="user_requestseedlings.php" class="dropdown-item active-page">
                        <i class="fas fa-paper-plane"></i><span>Seedlings Request</span>
                    </a>
                </div>
            </div>

            <div class="nav-item">
                <div class="nav-icon"><a href="seedlingsmessage.php"><i class="fas fa-envelope" style="color:black;"></i></a></div>
            </div>

            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-bell"></i><span class="badge">1</span></div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3><a href="#" class="mark-all-read">Mark all as read</a>
                    </div>
                    <div class="notification-item unread">
                        <a href="seedlingseach.php?id=1" class="notification-link">
                            <div class="notification-icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="notification-content">
                                <div class="notification-title">Seedlings Disposal Alert</div>
                                <div class="notification-message">Report of seedlings being improperly discarded.</div>
                                <div class="notification-time">15 minutes ago</div>
                            </div>
                        </a>
                    </div>
                    <div class="notification-footer"><a href="seedlingsnotification.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>

            <div class="nav-item dropdown">
                <div class="nav-icon <?= $current_page === 'forestry-profile.php' ? 'active' : '' ?>"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="seedlingsprofile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span class="item-text">Edit Profile</span></a>
                    <a href="../superlogin.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span class="item-text">Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <div class="wood-processing-records">
        <div class="container">
            <div class="header">
                <h1 class="title">SEEDLINGS REQUEST RECORDS</h1>
            </div>

            <div class="controls">
                <div class="search">
                    <input type="text" id="search-input" placeholder="SEARCH HERE" class="search-input">
                    <img src="https://c.animaapp.com/uJwjYGDm/img/google-web-search@2x.png" alt="Search" class="search-icon" id="search-icon">
                </div>
                <div class="export">
                    <button class="export-button" id="export-button">
                        <img src="https://c.animaapp.com/uJwjYGDm/img/vector-1.svg" alt="Export" class="export-icon">
                    </button>
                    <span class="export-label">Export as CSV</span>
                </div>
            </div>

            <div class="table-container">
                <table class="wood-table" id="req-table">
                    <thead>
                        <tr>
                            <th>APPROVAL ID</th>
                            <th>FIRST NAME</th>
                            <th>LAST NAME</th>
                            <th>TYPE</th>
                            <th>DATE</th>
                            <th>STATUS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="req-tbody">
                        <tr>
                            <td colspan="7">Loading…</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="actions">
                <button class="add-record btn-primary" id="refreshBtn">REFRESH</button>
            </div>
        </div>
    </div>

    <!-- Detail/Edit Modal -->
    <div id="detailModal" class="modal" style="z-index:1000;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">SEEDLING REQUEST DETAILS</h2>
                <span class="close" id="closeDetailModal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="grid-2" id="detailGrid"><!-- filled by JS --></div>

                <!-- Only a button here. No iframe to avoid auto-downloads. -->
                <div style="margin-top:14px;">
                    <button class="btn-ghost" id="viewDocBtn" disabled>View application form</button>
                </div>
            </div>
            <div class="modal-actions">
                <!-- <button class="btn-ghost" id="viewOnlyBtn">View</button> -->
                <button class="btn-primary" id="saveStatusBtn">Save Status</button>
                <!-- <button class="btn-danger" id="rejectBtn">Reject</button> -->
            </div>
        </div>
    </div>

    <!-- Stacked Document Viewer Modal (sits above detail modal) -->
    <div id="docModal" class="modal" style="z-index:1100;">
        <div class="modal-content" style="max-width:960px;width:95%;position:relative;">
            <span class="close" id="closeDocModal" style="position:absolute;right:16px;top:10px;font-size:24px;cursor:pointer;">&times;</span>
            <iframe id="docFrame" class="file-preview" src="about:blank"></iframe>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            /* Toast */
            const noteEl = document.getElementById('profile-notification');

            function toast(msg, type = 'info', timeout = 3000) {
                if (!noteEl) return;
                noteEl.style.background = type === 'error' ? '#c0392b' : (type === 'success' ? '#2d8a34' : '#323232');
                noteEl.textContent = msg;
                noteEl.style.display = 'block';
                clearTimeout(noteEl._t);
                noteEl._t = setTimeout(() => noteEl.style.display = 'none', timeout);
            }

            /* Elements */
            const tbody = document.getElementById('req-tbody');
            const refreshBtn = document.getElementById('refreshBtn');
            const searchInput = document.getElementById('search-input');
            const searchIcon = document.getElementById('search-icon');

            const detailModal = document.getElementById('detailModal');
            const closeDetailModal = document.getElementById('closeDetailModal');
            const detailGrid = document.getElementById('detailGrid');
            const saveStatusBtn = document.getElementById('saveStatusBtn');
            const rejectBtn = document.getElementById('rejectBtn');
            const viewDocBtn = document.getElementById('viewDocBtn');

            // Document viewer modal elements
            const docModal = document.getElementById('docModal');
            const closeDocModal = document.getElementById('closeDocModal');
            const docFrame = document.getElementById('docFrame');

            let rowsCache = [];
            let currentApprovalId = null;
            let currentStatusSelect = null;
            let currentDocUrl = '';

            function statusPill(status) {
                const s = (status || '').toLowerCase();
                const cls = s === 'approved' ? 'status-approved' : s === 'rejected' ? 'status-rejected' : 'status-pending';
                return `<span class="status-pill ${cls}">${(status||'').toUpperCase()}</span>`;
            }

            function renderRows(rows) {
                if (!rows || !rows.length) {
                    tbody.innerHTML = `<tr><td colspan="7">No requests found.</td></tr>`;
                    return;
                }
                const html = rows.map(r => {
                    const id = r.approval_id || '';
                    const fn = r.first_name || '';
                    const ln = r.last_name || '';
                    const ty = r.request_type || '';
                    const dt = r.submitted_at ? new Date(r.submitted_at).toISOString().slice(0, 10) : '';
                    const st = r.approval_status || '';
                    return `
            <tr data-id="${id}">
              <td>${id}</td>
              <td>${fn}</td>
              <td>${ln}</td>
              <td>${ty}</td>
              <td>${dt}</td>
              <td>${statusPill(st)}</td>
              <td>
                <!-- View button intentionally commented -->
                <!-- <button class="btn-ghost view-btn">View</button> -->
                <button class="btn-ghost edit-btn">Edit</button>
              </td>
            </tr>
          `;
                }).join('');
                tbody.innerHTML = html;
            }

            async function fetchRows(q = '') {
                try {
                    const url = new URL('../backend/admins/seedlingsRequest/list.php', location.href);
                    if (q) url.searchParams.set('q', q);
                    const res = await fetch(url, {
                        credentials: 'same-origin'
                    });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.error || 'Failed');
                    rowsCache = data.rows || [];
                    renderRows(rowsCache);
                } catch (e) {
                    console.error(e);
                    toast('Failed to load requests', 'error');
                    tbody.innerHTML = `<tr><td colspan="7">Error loading data</td></tr>`;
                }
            }

            function doSearch() {
                const term = (searchInput.value || '').toLowerCase();
                if (!term) {
                    renderRows(rowsCache);
                    return;
                }
                const filtered = rowsCache.filter(r =>
                    String(r.approval_id || '').toLowerCase().includes(term) ||
                    String(r.first_name || '').toLowerCase().includes(term) ||
                    String(r.last_name || '').toLowerCase().includes(term) ||
                    String(r.approval_status || '').toLowerCase().includes(term)
                );
                renderRows(filtered);
            }

            // Build a viewer URL to avoid downloads (Office viewer for Word docs; raw for PDFs)
            function viewerURL(rawUrl) {
                if (!rawUrl) return 'about:blank';
                const clean = String(rawUrl);
                const ext = clean.split('?')[0].split('.').pop()?.toLowerCase() || '';
                if (ext === 'pdf') return clean;
                return 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(clean);
            }

            /* Open modal for edit (does NOT auto-load the document) */
            async function openEdit(approval_id) {
                try {
                    const url = new URL('../backend/admins/seedlingsRequest/get_request.php', location.href);
                    url.searchParams.set('approval_id', approval_id);
                    const res = await fetch(url, {
                        credentials: 'same-origin'
                    });
                    const out = await res.json();
                    if (!out.success) throw new Error(out.error || 'Failed');

                    currentApprovalId = approval_id;

                    const a = out.approval || {};
                    const s = out.seedling || {};
                    currentDocUrl = a.application_form || '';

                    detailGrid.innerHTML = `
            <div class="pair">
              <label>Approval ID</label>
              <div>${a.approval_id || ''}</div>
            </div>
            <div class="pair">
              <label>Client Name</label>
              <div>${(a.first_name || '')} ${(a.last_name || '')}</div>
            </div>
            <div class="pair">
              <label>Request Type</label>
              <div>${a.request_type || ''}</div>
            </div>
            <div class="pair">
              <label>Date Submitted</label>
              <div>${a.submitted_at ? new Date(a.submitted_at).toLocaleString() : ''}</div>
            </div>
            <div class="pair">
              <label>Seedling</label>
              <div>${s.seedling_name ? `${s.seedling_name} (${s.quantity || 0})` : '—'}</div>
            </div>
            <div class="pair">
              <label>Status</label>
              <div>
                <select id="edit-status">
                  <option value="pending"  ${String(a.approval_status||'').toLowerCase()==='pending'  ? 'selected':''}>Pending</option>
                  <option value="approved" ${String(a.approval_status||'').toLowerCase()==='approved' ? 'selected':''}>Approved</option>
                  <option value="rejected" ${String(a.approval_status||'').toLowerCase()==='rejected' ? 'selected':''}>Rejected</option>
                </select>
              </div>
            </div>
            <div class="pair" style="grid-column:1 / -1;">
              <label>Rejection Reason (if rejecting)</label>
              <textarea id="edit-reason" rows="3" style="width:100%;"></textarea>
            </div>
          `;

                    // Enable/disable the view button based on URL presence
                    viewDocBtn.disabled = !currentDocUrl;
                    viewDocBtn.style.opacity = currentDocUrl ? '1' : '.6';

                    // Show edit modal
                    detailModal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } catch (e) {
                    console.error(e);
                    toast('Failed to load details', 'error');
                }
            }

            async function saveStatus() {
                const status = (document.getElementById('edit-status')?.value || 'pending').toLowerCase();
                const reason = (document.getElementById('edit-reason')?.value || '').trim();

                if (status === 'rejected' && !reason) {
                    toast('Please provide a rejection reason.', 'error');
                    return;
                }
                try {
                    const res = await fetch('../backend/admins/seedlingsRequest/update_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            approval_id: currentApprovalId,
                            status: status,
                            rejection_reason: status === 'rejected' ? reason : null
                        })
                    });
                    const out = await res.json();
                    if (!out.success) throw new Error(out.error || 'Update failed');

                    toast('Status updated', 'success');
                    detailModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                    await fetchRows();
                } catch (e) {
                    console.error(e);
                    toast('Failed to update status', 'error');
                }
            }

            function closeEditModal() {
                detailModal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            /* Events */
            refreshBtn?.addEventListener('click', () => fetchRows());
            searchIcon?.addEventListener('click', doSearch);
            searchInput?.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') doSearch();
            });

            closeDetailModal?.addEventListener('click', closeEditModal);
            window.addEventListener('click', (e) => {
                if (e.target === detailModal) closeEditModal();
            });

            saveStatusBtn?.addEventListener('click', saveStatus);
            rejectBtn?.addEventListener('click', () => {
                const sel = document.getElementById('edit-status');
                if (sel) sel.value = 'rejected';
                toast('Set status to Rejected. Provide a reason and click Save Status.', 'info', 4000);
            });

            // Table action: Edit
            document.addEventListener('click', (e) => {
                const row = e.target.closest('tr[data-id]');
                if (!row) return;
                const id = row.getAttribute('data-id');
                if (!id) return;
                if (e.target.classList.contains('edit-btn')) {
                    openEdit(id);
                }
            });

            // Document viewer: open
            viewDocBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                if (!currentDocUrl) return;
                docFrame.src = viewerURL(currentDocUrl);
                docModal.style.display = 'block'; // sits above edit modal
                // keep edit modal open underneath
            });

            // Document viewer: close by X
            closeDocModal?.addEventListener('click', () => {
                docModal.style.display = 'none';
                docFrame.src = 'about:blank';
            });

            // Document viewer: close by clicking overlay (but keep edit modal open)
            docModal?.addEventListener('click', (e) => {
                if (e.target === docModal) {
                    docModal.style.display = 'none';
                    docFrame.src = 'about:blank';
                }
            });

            // Export CSV
            document.getElementById('export-button')?.addEventListener('click', () => {
                let csv = 'APPROVAL ID,FIRST NAME,LAST NAME,TYPE,DATE,STATUS\r\n';
                const rows = tbody.querySelectorAll('tr');
                rows.forEach(tr => {
                    const tds = tr.querySelectorAll('td');
                    if (tds.length < 6) return;
                    const vals = [
                        tds[0]?.textContent || '',
                        tds[1]?.textContent || '',
                        tds[2]?.textContent || '',
                        tds[3]?.textContent || '',
                        tds[4]?.textContent || '',
                        (tds[5]?.textContent || '').trim()
                    ].map(s => `"${(s||'').replace(/"/g,'""')}"`);
                    csv += vals.join(',') + '\r\n';
                });
                const a = document.createElement('a');
                a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
                a.download = 'seedling_requests_' + new Date().toISOString().slice(0, 10) + '.csv';
                a.click();
            });

            // Initial load
            fetchRows();
        });
    </script>
</body>


</html>