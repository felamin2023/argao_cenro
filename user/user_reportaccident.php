<?php

declare(strict_types=1);

/**
 * User-only gate for user_home.php
 * - Requires a logged-in session
 * - Role must be 'User'
 * - Status must be 'Verified'
 * - Verifies against DB on each hit (defense-in-depth)
 */

session_start();

// Optional: extra safety headers (helps on back/forward caching)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Quick session check first
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'user') {
    header('Location: user_login.php');
    exit();
}

// DB check to ensure the session still matches a User, Verified account
require_once __DIR__ . '/../backend/connection.php'; // must expose $pdo (PDO -> Supabase PG)

try {
    $st = $pdo->prepare("
        select role, status
        from public.users
        where user_id = :id
        limit 1
    ");
    $st->execute([':id' => $_SESSION['user_id']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $roleOk   = $row && strtolower((string)$row['role']) === 'user';
    $statusOk = $row && strtolower((string)$row['status']) === 'verified';

    if (!$roleOk || !$statusOk) {
        // Invalidate session if it no longer matches a real verified User
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: user_login.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[USER-HOME GUARD] ' . $e->getMessage());
    header('Location: user_login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../css/user_reportaccident.css">
</head>

<body>
    <div id="loadingScreen" class="loading-overlay" aria-hidden="true">
        <div class="loading-card">
            <img id="loadingLogo" src="../denr.png" alt="Loading Logo" />
            <div class="loading-text">Loading...</div>
        </div>
    </div>
    <header>
        <div class="logo">
            <a href="user_home.php"><img src="seal.png" alt="Site Logo" /></a>
        </div>

        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <!-- Dashboard Dropdown -->
            <div class="nav-item dropdown">
            <div class="nav-icon active"><i class="fas fa-bars"></i></div>
            <div class="dropdown-menu center">
                <a href="user_reportaccident.php" class="dropdown-item"><i class="fas fa-file-invoice"></i><span>Report Incident</span></a>
                <a href="useraddseed.php" class="dropdown-item"><i class="fas fa-seedling"></i><span>Request Seedlings</span></a>
                <a href="useraddwild.php" class="dropdown-item"><i class="fas fa-paw"></i><span>Wildlife Permit</span></a>
                <a href="useraddtreecut.php" class="dropdown-item"><i class="fas fa-tree"></i><span>Tree Cutting Permit</span></a>
                <a href="useraddlumber.php" class="dropdown-item"><i class="fas fa-boxes"></i><span>Lumber Dealers Permit</span></a>
                <a href="useraddwood.php" class="dropdown-item"><i class="fas fa-industry"></i><span>Wood Processing Permit</span></a>
                <a href="useraddchainsaw.php" class="dropdown-item active-page"><i class="fas fa-tools"></i><span>Chainsaw Permit</span></a>
            </div>
            </div>

            <!-- Notifications (from partial) -->
            <?php include dirname(__DIR__) . '/backend/partials/notifications.php'; ?>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
            <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
            <div class="dropdown-menu">
                <a href="user_profile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                <a href="user_login.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </div>
            </div>
        </div>
        </header>


    <div id="confirmationModal" class="hidden-modal">
        <div class="modal-content">
            <h3>Confirm Incident Report</h3>
            <p>Are you sure you want to submit this incident report?</p>
            <div style="margin-top:20px;">
                <button id="confirmSubmit" style="background:#28a745; color:#fff; padding:8px 16px; border:none; border-radius:4px; margin-right:10px; cursor:pointer;">Yes, Submit</button>
                <button id="cancelSubmit" style="background:#dc3545; color:#fff; padding:8px 16px; border:none; border-radius:4px; cursor:pointer;">Cancel</button>
            </div>
        </div>
    </div>




    <!-- Notification Popup -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%);
 background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999;
 box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;">
    </div>

    <div class="content">
        <h2 class="page-title">REPORT AN INCIDENT</h2>

        <div class="profile-form">
            <form id="incidentForm" action="../backend/users/report_incident.php" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="who">WHO</label>
                        <input type="text" id="who" name="who" required>
                    </div>

                    <div class="form-group">
                        <label for="where">WHERE</label>
                        <input type="text" id="where" name="where" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group one-third">
                        <label for="contact">CONTACT NO:</label>
                        <input type="text" id="contact" name="contact" required>
                    </div>
                    <div class="form-group one-third">
                        <label for="when">WHEN</label>
                        <input type="datetime-local" id="when" name="when" required>
                    </div>
                    <div class="form-group one-third">
                        <label for="why">WHY</label>
                        <input type="text" id="why" name="why" required>
                    </div>
                </div>

                <!-- Improved Photo Upload Section -->
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>UPLOAD PHOTOS (Max 5):</label>
                        <button type="button" id="addPhotoBtn" style="margin-bottom:10px;">Add Photo(s)</button>
                        <input type="file" id="photos" name="photos[]" multiple accept="image/*" style="display:none;">
                        <div id="photoPreview" class="photo-preview-container"></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group two-thirds">
                        <label for="categories" style="margin-top: 0;">CATEGORIES:</label>
                        <select id="categories" name="categories" required>
                            <option value="">-- Select Category --</option>
                            <option value="Tree Cutting">Tree Cutting</option>
                            <option value="Marine Resource Monitoring">Marine Resource Monitoring</option>
                            <option value="Seedlings Monitoring">Seedlings Monitoring</option>
                            <option value="WildLife Monitoring">Wildlife Monitoring</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="what">WHAT</label>
                        <textarea type="text" id="what" name="what" style="height: 100px; text-align: start;" required></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="description">MORE DESCRIPTION OF INCIDENT:</label>
                        <textarea id="description" name="description" style="height: 130px;" required></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <div class="button-group">
                            <button type="submit" name="submit" class="save-btn">SUBMIT</button>
                            <button type="button" class="view-records-btn" id="viewRecordsBtn">VIEW RECORDS</button>
                        </div>
                    </div>
                </div>
            </form>

        </div>

        <!-- Records Container -->
        <div class="records-container" id="recordsContainer">
            <h3 class="records-title">INCIDENT REPORTS</h3>

            <table class="records-table">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <!-- <th>Date</th> -->
                        <th>Location</th>
                        <th>Category</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="5" class="no-records">No incident reports found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?= htmlspecialchars($report['id']) ?></td>
                                <!-- <td><?= date('Y-m-d', strtotime($report['date_time'])) ?></td> -->
                                <td><?= htmlspecialchars($report['category']) ?></td>
                                <td class="status-<?= strtolower($report['status']) ?>">
                                    <?= htmlspecialchars($report['status']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            /* ========= Elements ========= */
            const form = document.getElementById("incidentForm");
            const confirmationModal = document.getElementById("confirmationModal");
            const confirmBtn = document.getElementById("confirmSubmit");
            const cancelBtn = document.getElementById("cancelSubmit");
            const notification = document.getElementById("profile-notification");

            const photoInput = document.getElementById('photos');
            const addPhotoBtn = document.getElementById('addPhotoBtn');
            const photoPreview = document.getElementById('photoPreview');
            const maxPhotos = 5;
            let selectedFiles = [];

            /* ========= Loading overlay ========= */
            const loadingEl = document.getElementById('loadingScreen');
            const showLoading = () => loadingEl && (loadingEl.style.display = 'flex');
            const hideLoading = () => loadingEl && (loadingEl.style.display = 'none');

            /* ========= File picker / previews ========= */
            addPhotoBtn.addEventListener('click', () => photoInput.click());

            photoInput.addEventListener('change', function() {
                const newFiles = Array.from(photoInput.files);
                for (let f of newFiles) {
                    if (selectedFiles.length >= maxPhotos) break;
                    // de-dupe: name+size
                    if (!selectedFiles.some(x => x.name === f.name && x.size === f.size)) {
                        selectedFiles.push(f);
                    }
                }
                renderPreviews();
                photoInput.value = '';
            });

            function renderPreviews() {
                photoPreview.innerHTML = '';
                selectedFiles.forEach((file, idx) => {
                    const reader = new FileReader();
                    reader.onload = e => {
                        const wrap = document.createElement('div');
                        wrap.className = 'photo-preview-wrapper';

                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'photo-preview';

                        const x = document.createElement('span');
                        x.className = 'remove-photo';
                        x.textContent = '×';
                        x.onclick = () => {
                            selectedFiles.splice(idx, 1);
                            renderPreviews();
                        };

                        wrap.appendChild(img);
                        wrap.appendChild(x);
                        photoPreview.appendChild(wrap);
                    };
                    reader.readAsDataURL(file);
                });
            }

            /* ========= Confirm modal flow ========= */
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                if (selectedFiles.length === 0) {
                    alert('Please upload at least one photo');
                    return;
                }
                confirmationModal.classList.remove("hidden-modal");
            });

            cancelBtn.addEventListener("click", () => confirmationModal.classList.add("hidden-modal"));

            /* ========= Fetch helper (robust JSON parsing) ========= */
            async function postJSON(url, body) {
                const res = await fetch(url, {
                    method: "POST",
                    body
                });
                const text = await res.text(); // always read raw first
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(`Non-JSON (${res.status}): ${text.slice(0, 500)}`);
                }
            }

            /* ========= Confirm -> show loading until response ========= */
            confirmBtn.addEventListener("click", async function() {
                confirmationModal.classList.add("hidden-modal");

                // build payload
                const fd = new FormData(form);
                fd.delete('photos[]'); // ensure only our selected list gets sent
                selectedFiles.forEach(f => fd.append('photos[]', f));

                // helpful client-side log
                const clientEcho = {
                    who: form.who.value?.trim(),
                    what: form.what.value?.trim(),
                    where: form.where.value?.trim(),
                    when: form.when.value?.trim(),
                    why: form.why.value?.trim(),
                    contact: form.contact.value?.trim(),
                    categories: form.categories.value?.trim(),
                    description: form.description.value?.trim(),
                    files: selectedFiles.map(f => ({
                        name: f.name,
                        size: f.size,
                        type: f.type
                    }))
                };
                console.log('[CLIENT] inputs:', clientEcho);

                // show overlay while waiting
                showLoading();

                try {
                    const data = await postJSON(form.action, fd);

                    // server echo (what PHP received)
                    if (data && data.echo) {
                        console.log('[SERVER] echo:', data.echo);
                        const empties = Object.entries(data.echo)
                            .filter(([k, v]) => ['who', 'what', 'where', 'when_raw', 'why', 'contact_no', 'category', 'description'].includes(k) &&
                                (!v || (typeof v === 'string' && v.trim() === '')))
                            .map(([k]) => k);
                        if (empties.length) console.warn('[SERVER] empty fields:', empties);
                    }

                    // toast
                    notification.textContent = data.message || 'Done.';
                    notification.style.backgroundColor = data.success ? "#28a745" : "#dc3545";
                    notification.style.display = "block";
                    setTimeout(() => {
                        notification.style.display = "none";
                        if (data.success) window.location.reload();
                    }, 2500);

                } catch (err) {
                    console.error('[NETWORK] error:', err);
                    notification.textContent = "Server said: " + err.message;
                    notification.style.backgroundColor = "#dc3545";
                    notification.style.display = "block";
                    setTimeout(() => {
                        notification.style.display = "none";
                    }, 3500);
                } finally {
                    hideLoading();
                }
            });

            /* ========= Mobile menu toggle ========= */
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    const isActive = navContainer.classList.toggle('active');
                    document.body.style.overflow = isActive ? 'hidden' : '';
                });
            }
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.nav-container') && !e.target.closest('.mobile-toggle')) {
                    navContainer.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            /* ========= View Records toggle ========= */
            const viewRecordsBtn = document.getElementById('viewRecordsBtn');
            const recordsContainer = document.getElementById('recordsContainer');
            if (viewRecordsBtn && recordsContainer) {
                viewRecordsBtn.addEventListener('click', function() {
                    if (recordsContainer.style.display === 'none' || recordsContainer.style.display === '') {
                        recordsContainer.style.display = 'block';
                        this.textContent = 'HIDE RECORDS';
                    } else {
                        recordsContainer.style.display = 'none';
                        this.textContent = 'VIEW RECORDS';
                    }
                });
            }
        });
    </script>



</body>

</html>