<?php
<<<<<<< HEAD
// useraddtreecut.php — Tree Cutting (single mode) using Chainsaw-style headers + main-container
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'User') {
    header("Location: user_login.php");
    exit();
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Application: Tree Cutting Permit</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.5.3/signature_pad.min.js"></script>

<style>
  :root{
    --primary-color:#2b6625; --primary-dark:#1e4a1a; --white:#fff; --light-gray:#f5f5f5;
    --border-radius:8px; --box-shadow:0 4px 12px rgba(0,0,0,.1); --transition:all .2s ease;
    --medium-gray:#ddd;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background: #f9f9f9 url('images/tree.jpg') center / cover no-repeat fixed;color:#333;line-height:1.6;padding-top:120px}

  /* ===== Headers (same as chainsaw) ===== */
  header{display:flex;justify-content:space-between;align-items:center;background:var(--primary-color);color:var(--white);padding:0 30px;height:58px;position:fixed;left:0;right:0;top:0;z-index:1000;box-shadow:0 2px 10px rgba(0,0,0,.1)}
  header+header{top:58px}
  .logo{height:45px;display:flex;align-items:center}
  .logo a{display:flex;align-items:center;height:90%}
  .logo img{height:98%;width:auto;transition:var(--transition)}
  .logo:hover img{transform:scale(1.05)}
  .nav-container{display:flex;align-items:center;gap:20px}
  .nav-item{position:relative}
  .nav-icon{display:flex;align-items:center;justify-content:center;width:40px;height:40px;background:rgb(233,255,242);border-radius:12px;cursor:pointer;transition:var(--transition);color:black;box-shadow:0 2px 6px rgba(0,0,0,.15)}
  .nav-icon:hover{background:rgba(224,204,204,.3);transform:scale(1.15);box-shadow:0 4px 12px rgba(0,0,0,.25)}
  .nav-icon i{font-size:1.3rem;color:inherit}
  .nav-icon.active::after{content:'';position:absolute;bottom:-6px;left:50%;transform:translateX(-50%);width:40px;height:2px;background:var(--white);border-radius:2px}
  .dropdown-menu{position:absolute;top:calc(100% + 10px);right:0;background:var(--white);min-width:300px;border-radius:var(--border-radius);box-shadow:var(--box-shadow);z-index:1000;opacity:0;visibility:hidden;transform:translateY(10px);transition:var(--transition);padding:0}
  .dropdown:hover .dropdown-menu,.dropdown-menu:hover{opacity:1;visibility:visible;transform:translateY(0)}
  .dropdown-menu:before{content:'';position:absolute;bottom:100%;right:20px;border-width:10px;border-style:solid;border-color:transparent transparent var(--white) transparent}
  .dropdown-item{padding:15px 25px;display:flex;align-items:center;color:black;text-decoration:none;transition:var(--transition);font-size:1.05rem}
  .dropdown-item i{width:30px;font-size:1.4rem;color:var(--primary-color)!important;margin-right:15px}
  .dropdown-item:hover{background:var(--light-gray);padding-left:30px}
  .dropdown-item.active-page{background:#e1ffdc;color:var(--primary-dark);font-weight:600;border-left:4px solid var(--primary-color)}
  .badge{position:absolute;top:2px;right:8px;background:#ff4757;color:#fff;border-radius:50%;width:14px;height:12px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:bold}

  .mobile-toggle{display:none;background:none;border:none;color:#fff;font-size:2rem;cursor:pointer;padding:15px}
  @media(max-width:768px){.mobile-toggle{display:block}}

  /* ===== Main container (same as chainsaw) ===== */
  .main-container{margin-top:-0.5%;padding:30px}
  .action-buttons{display:flex;margin-top:-3%;gap:15px;margin-bottom:30px;flex-wrap:nowrap;justify-content:center;overflow-x:auto;padding-bottom:10px}
  .btn{padding:10px 15px;border-radius:var(--border-radius);font-weight:600;text-decoration:none;transition:var(--transition);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem;white-space:nowrap;min-width:120px}
  .btn-primary{background:var(--primary-color);color:#fff;border:2px solid var(--primary-color)}
  .btn-primary:hover{background:var(--primary-dark);border-color:var(--primary-dark)}

  .requirements-form{margin-top:-1%;background:#fff;border-radius:var(--border-radius);box-shadow:var(--box-shadow);overflow:hidden;border:1px solid var(--medium-gray)}
  .form-header{background:var(--primary-color);color:#fff;padding:20px 30px;border-bottom:1px solid var(--primary-dark)}
  .form-header h2{text-align:center;font-size:1.5rem;margin:0}
  .form-body{padding:30px}
  .form-footer{padding:20px 30px;background:var(--light-gray);border-top:1px solid var(--medium-gray);display:flex;justify-content:flex-end}

  /* Fields */
  .form-section{margin-bottom:25px}
  .form-section h2{background:var(--primary-color);color:#fff;padding:10px 15px;margin-bottom:15px;border-radius:4px;font-size:18px}
  .form-group{margin-bottom:15px}
  .form-group label{display:block;margin-bottom:5px;font-weight:600;color:var(--primary-color)}
  .form-group input,.form-group textarea,.form-group select{width:100%;padding:10px 15px;border:1px solid #ddd;border-radius:4px;font-size:15px;transition:border-color .3s}
  .form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:var(--primary-color);outline:none;box-shadow:0 0 0 2px rgba(43,102,37,.2)}
  .form-row{display:flex;gap:20px;margin-bottom:15px}
  .form-row .form-group{flex:1}
  table{width:100%;border-collapse:collapse;margin-bottom:15px}
  table th,table td{border:1px solid #ddd;padding:10px;text-align:left}
  table th{background-color:#e9f5e8;color:var(--primary-color)}

  /* Declaration & signature (preview only) */
  .declaration{background:#f9f9f9;padding:20px;border-radius:4px;border-left:4px solid var(--primary-color);margin-bottom:25px}
  .signature-pad-container{border:1px solid #ddd;border-radius:4px;margin-bottom:10px;background:#fff}
  #signature-pad{width:100%;height:150px;cursor:crosshair}
  .signature-actions{display:flex;gap:10px;margin-top:10px}
  .signature-btn{padding:8px 15px;border:none;border-radius:4px;cursor:pointer;font-size:14px}
  .clear-signature{background:#ff4757;color:#fff}
  .save-signature{background:var(--primary-color);color:#fff}
  .signature-preview{text-align:center;margin-top:15px}
  #signature-image{max-width:300px;border:1px solid #ddd;border-radius:4px}
  .hidden{display:none}

  /* Toast, loading, confirm */
  #profile-notification{display:none;position:fixed;top:5px;left:50%;transform:translateX(-50%);background:#323232;color:#fff;padding:16px 32px;border-radius:8px;font-size:1.05rem;z-index:9999}
  #loadingIndicator{display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998}
  #loadingIndicator .card{background:#fff;padding:18px 22px;border-radius:10px;box-shadow:var(--box-shadow);color:#333}
  #confirmModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center}
  #confirmModal .dlg{background:#fff;max-width:520px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden}
  #confirmModal .dlg-h{padding:18px 20px;border-bottom:1px solid #eee;font-weight:600}
  #confirmModal .dlg-b{padding:16px 20px;line-height:1.6}
  #confirmModal .dlg-f{display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee}

  @media (max-width:768px){
    .main-container{padding:20px}
    .form-body{padding:20px}
    .form-row{flex-direction:column;gap:10px}
  }
</style>
</head>

<body>
  <!-- ===== Header ===== -->
  <header>
    <div class="logo"><a href="user_home.php"><img src="seal.png" alt="Site Logo"></a></div>
    <button class="mobile-toggle"><i class="fas fa-bars"></i></button>
    <div class="nav-container">
      <div class="nav-item dropdown">
        <div class="nav-icon active"><i class="fas fa-bars"></i></div>
        <div class="dropdown-menu center">
          <a href="user_reportaccident.php" class="dropdown-item"><i class="fas fa-file-invoice"></i><span>Report Incident</span></a>
          <a href="useraddseed.php" class="dropdown-item"><i class="fas fa-seedling"></i><span>Request Seedlings</span></a>
          <a href="useraddwild.php" class="dropdown-item"><i class="fas fa-paw"></i><span>Wildlife Permit</span></a>
          <a href="useraddtreecut.php" class="dropdown-item active-page"><i class="fas fa-tree"></i><span>Tree Cutting Permit</span></a>
          <a href="useraddlumber.php" class="dropdown-item"><i class="fas fa-boxes"></i><span>Lumber Dealers Permit</span></a>
          <a href="useraddwood.php" class="dropdown-item"><i class="fas fa-industry"></i><span>Wood Processing Permit</span></a>
          <a href="useraddchainsaw.php" class="dropdown-item"><i class="fas fa-tools"></i><span>Chainsaw Permit</span></a>
        </div>
      </div>
      <div class="nav-item dropdown">
        <div class="nav-icon"><i class="fas fa-bell"></i><span class="badge">1</span></div>
        <div class="dropdown-menu" style="min-width:350px;max-height:500px;overflow:auto">
          <div class="dropdown-item"><i class="fas fa-exclamation-circle"></i><span>Sample notification…</span></div>
          <a href="user_notification.php" class="dropdown-item"><i class="fas fa-list"></i><span>View All</span></a>
        </div>
      </div>
      <div class="nav-item dropdown">
        <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
        <div class="dropdown-menu">
          <a href="user_profile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
          <a href="user_login.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
      </div>
    </div>
  </header>

  <!-- ===== Main ===== -->
  <div class="main-container">

    <div class="requirements-form">
      <div class="form-header">
        <h2>Application for Tree Cutting Permit</h2>
      </div>

      <div class="form-body">
        <!-- PART I -->
        <div class="form-section">
          <h2>PART I. APPLICANT'S INFORMATION</h2>

          <!-- CHANGED: Split name into First / Middle / Last -->
          <div class="form-row">
            <div class="form-group">
              <label for="first-name" class="required">First Name:</label>
              <input type="text" id="first-name" name="first_name" />
            </div>
            <div class="form-group">
              <label for="middle-name">Middle Name:</label>
              <input type="text" id="middle-name" name="middle_name" />
            </div>
            <div class="form-group">
              <label for="last-name" class="required">Last Name:</label>
              <input type="text" id="last-name" name="last_name" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="street" class="required">Sitio/Street:</label>
              <input type="text" id="street">
            </div>
            <div class="form-group">
              <label for="barangay" class="required">Barangay:</label>
              <input type="text" id="barangay">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="municipality" class="required">Municipality:</label>
              <input type="text" id="municipality">
            </div>
            <div class="form-group">
              <label for="province" class="required">Province:</label>
              <input type="text" id="province">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="contact-number" class="required">Contact No.:</label>
              <input type="text" id="contact-number" placeholder="09XX…">
            </div>
            <div class="form-group">
              <label for="email" class="required">Email Address:</label>
              <input type="email" id="email" placeholder="name@example.com">
            </div>
          </div>

          <div class="form-group">
            <label for="registration-number">If Corporation: SEC/DTI Registration No.</label>
            <input type="text" id="registration-number">
          </div>
        </div>

        <!-- PART II -->
        <div class="form-section">
          <h2>PART II. TREE CUTTING DETAILS</h2>

          <div class="form-group">
            <label for="location" class="required">Location of Area/Trees to be Cut:</label>
            <input type="text" id="location" placeholder="Full location">
          </div>

          <div class="form-group">
            <label>Ownership of Land:</label>
            <div style="display:flex;gap:20px;margin-top:10px;flex-wrap:wrap">
              <label style="display:flex;align-items:center;gap:6px"><input type="radio" name="ownership" value="Private" checked> Private</label>
              <label style="display:flex;align-items:center;gap:6px"><input type="radio" name="ownership" value="Government"> Government</label>
              <label style="display:flex;align-items:center;gap:6px">
                <input type="radio" name="ownership" value="Others"> Others:
                <input type="text" id="other-ownership" style="margin-left:6px;width:160px">
              </label>
            </div>
          </div>

          <div class="form-group">
            <label>Number and Species of Trees Applied for Cutting:</label>
            <table>
              <thead>
                <tr>
                  <th>Species</th>
                  <th>No. of Trees</th>
                  <th>Net Volume (cu.m)</th>
                </tr>
              </thead>
              <tbody id="species-table-body">
                <tr>
                  <td><input type="text" class="species-name"></td>
                  <td><input type="number" class="species-count" min="0"></td>
                  <td><input type="number" class="species-volume" step="0.01" min="0"></td>
                </tr>
                <tr>
                  <td><input type="text" class="species-name"></td>
                  <td><input type="number" class="species-count" min="0"></td>
                  <td><input type="number" class="species-volume" step="0.01" min="0"></td>
                </tr>
                <tr>
                  <td><input type="text" class="species-name"></td>
                  <td><input type="number" class="species-count" min="0"></td>
                  <td><input type="number" class="species-volume" step="0.01" min="0"></td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td><strong>TOTAL</strong></td>
                  <td><input type="number" id="total-count" readonly style="background:#f0f0f0"></td>
                  <td><input type="number" id="total-volume" readonly style="background:#f0f0f0"></td>
                </tr>
              </tfoot>
            </table>
            <button type="button" class="btn btn-primary" id="add-row-btn"><i class="fas fa-plus"></i> Add Row</button>
          </div>

          <div class="form-group">
            <label for="purpose" class="required">Purpose of Application for Tree Cutting Permit:</label>
            <textarea id="purpose" rows="4" placeholder="e.g., land development, safety hazard removal, construction, farming, etc."></textarea>
          </div>
        </div>

        <!-- PART III -->
        <div class="form-section">
          <h2>PART III. DECLARATION OF APPLICANT</h2>
          <div class="declaration">
            <p>I hereby certify that the information provided in this application is true and correct. I understand that the approval of this application is subject to verification and evaluation by DENR, and that I shall comply with all terms and conditions of the Tree Cutting Permit once issued.</p>

            <div class="signature-date" style="margin-top:20px">
              <div class="signature-box" style="width:100%">
                <label>Signature Over Printed Name (preview only):</label>
                <div class="signature-pad-container"><canvas id="signature-pad"></canvas></div>
                <div class="signature-actions">
                  <button type="button" class="signature-btn clear-signature" id="clear-signature"><i class="fa-solid fa-eraser"></i> Clear</button>
                  <button type="button" class="signature-btn save-signature" id="save-signature"><i class="fa-solid fa-floppy-disk"></i> Save Signature</button>
                </div>
                <div class="signature-preview"><img id="signature-image" class="hidden" alt="Signature"></div>
              </div>
            </div>
          </div>
        </div>
      </div><!-- /.form-body -->

      <div class="form-footer">
        <button class="btn btn-primary" id="submitApplication" type="button"><i class="fas fa-paper-plane"></i> Submit Application</button>
      </div>
    </div>
  </div>

  <!-- Toast -->
  <div id="profile-notification"></div>

  <!-- Loading -->
  <div id="loadingIndicator"><div class="card">Working…</div></div>

  <!-- Confirm Modal -->
  <div id="confirmModal">
    <div class="dlg">
      <div class="dlg-h">Submit Application</div>
      <div class="dlg-b">Please confirm you want to submit this Tree Cutting application.</div>
      <div class="dlg-f">
        <button id="btnCancelConfirm" class="btn btn-outline" type="button">Cancel</button>
        <button id="btnOkConfirm" class="btn btn-primary" type="button">Yes, submit</button>
      </div>
    </div>
  </div>

<script>
const CSRF = "<?=htmlspecialchars($CSRF, ENT_QUOTES)?>";
// Point to your backend endpoint:
const SAVE_URL = new URL('../backend/users/treecut/save_treecut.php', window.location.href).toString();


/* ===== Mobile nav toggle ===== */
document.querySelectorAll('.mobile-toggle').forEach(btn=>{
  btn.addEventListener('click', ()=>document.body.classList.toggle('nav-open'));
});

/* ===== Signature Pad (preview only) ===== */
const canvas = document.getElementById('signature-pad');
const signaturePad = new SignaturePad(canvas, { backgroundColor:'#fff', penColor:'#000' });
function resizeCanvas(){
  const ratio = Math.max(window.devicePixelRatio||1,1);
  const rect = canvas.getBoundingClientRect();
  canvas.width = rect.width * ratio; canvas.height = rect.height * ratio;
  canvas.getContext("2d").scale(ratio, ratio);
}
window.addEventListener('resize', resizeCanvas); resizeCanvas();
document.getElementById('clear-signature').addEventListener('click', ()=>{
  signaturePad.clear(); document.getElementById('signature-image').classList.add('hidden');
});
document.getElementById('save-signature').addEventListener('click', ()=>{
  if (signaturePad.isEmpty()) return alert('Please provide a signature first.');
  const img = document.getElementById('signature-image');
  img.src = signaturePad.toDataURL(); img.classList.remove('hidden');
});

/* ===== Species rows & totals ===== */
function addSpeciesRow(){
  const tbody = document.getElementById('species-table-body');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="text" class="species-name"></td>
    <td><input type="number" class="species-count" min="0"></td>
    <td><input type="number" class="species-volume" step="0.01" min="0"></td>`;
  tbody.appendChild(tr);
}
document.getElementById('add-row-btn').addEventListener('click', addSpeciesRow);
const topBtn = document.getElementById('addRowBtn');
if (topBtn) topBtn.addEventListener('click', addSpeciesRow);


function recalcTotals(){
  let totalCount = 0, totalVol = 0;
  document.querySelectorAll('.species-count').forEach(i=> totalCount += Number(i.value)||0);
  document.querySelectorAll('.species-volume').forEach(i=> totalVol += Number(i.value)||0);
  document.getElementById('total-count').value = totalCount;
  document.getElementById('total-volume').value = (Math.round(totalVol*100)/100).toFixed(2);
}
document.addEventListener('input', (e)=>{
  if (e.target.classList.contains('species-count') || e.target.classList.contains('species-volume')) recalcTotals();
});

/* ===== Toast ===== */
function toast(msg){
  const n = document.getElementById("profile-notification");
  n.textContent = msg; n.style.display = "block"; n.style.opacity = "1";
  setTimeout(()=>{ n.style.opacity = "0"; setTimeout(()=>{ n.style.display = "none"; n.style.opacity = "1"; }, 350); }, 2200);
}

/* ===== Helpers & form gather ===== */
function buildFullName(first, middle, last){
  const parts = [first?.trim() || "", middle?.trim() || "", last?.trim() || ""].filter(Boolean);
  return parts.join(" ");
}
function ownershipValue(){
  const sel = document.querySelector('input[name="ownership"]:checked')?.value || '';
  if (sel === 'Others') return `Others: ${document.getElementById('other-ownership').value.trim()}`;
  return sel;
}
function gatherSpecies(){
  const arr = [];
  document.querySelectorAll('#species-table-body tr').forEach(row=>{
    const name = row.querySelector('.species-name')?.value.trim() || '';
    const count = row.querySelector('.species-count')?.value.trim() || '';
    const volume = row.querySelector('.species-volume')?.value.trim() || '';
    if (name || count || volume) arr.push({name, count, volume});
  });
  return arr;
}
function gatherForm(){
  const first = document.getElementById('first-name')?.value.trim() || '';
  const middle = document.getElementById('middle-name')?.value.trim() || '';
  const last = document.getElementById('last-name')?.value.trim() || '';

  return {
    first_name: first,
    middle_name: middle,
    last_name: last,
    applicant_name: buildFullName(first, middle, last),

    street: document.getElementById('street').value.trim(),
    barangay: document.getElementById('barangay').value.trim(),
    municipality: document.getElementById('municipality').value.trim(),
    province: document.getElementById('province').value.trim(),
    contact_number: document.getElementById('contact-number').value.trim(),
    email: document.getElementById('email').value.trim(),
    registration_number: document.getElementById('registration-number').value.trim(),
    location: document.getElementById('location').value.trim(),
    ownership: ownershipValue(),
    purpose: document.getElementById('purpose').value.trim(),
    species: gatherSpecies(),
    total_count: document.getElementById('total-count').value || '0',
    total_volume: document.getElementById('total-volume').value || '0.00'
  };
}

/* ===== Submit flow ===== */
const confirmModal = document.getElementById("confirmModal");
const btnSubmit = document.getElementById("submitApplication");
const btnOk = document.getElementById("btnOkConfirm");
const btnCancel = document.getElementById("btnCancelConfirm");
const loading = document.getElementById("loadingIndicator");

btnSubmit.addEventListener("click", ()=>{ confirmModal.style.display = "flex"; });
btnCancel.addEventListener("click", ()=>{ confirmModal.style.display = "none"; });

btnOk.addEventListener("click", async ()=>{
  confirmModal.style.display = "none";
  loading.style.display = "flex";
  try{
    await doSubmit();
    toast("Application submitted. We’ll notify you once reviewed.");
    resetForm();
  }catch(e){
    console.error(e);
    toast(e?.message || "Submission failed. Please try again.");
  }finally{
    loading.style.display = "none";
  }
});

async function doSubmit(){
  const d = gatherForm();

  // light validations
  if (!d.first_name || !d.last_name) throw new Error("First and Last name are required");
  if (!d.street || !d.barangay || !d.municipality || !d.province) throw new Error("Complete address is required");
  if (!d.contact_number || !d.email) throw new Error("Contact number and email are required");
  if (!d.location) throw new Error("Location is required");
  if (!d.purpose) throw new Error("Purpose is required");

  const fd = new FormData();
  fd.append('csrf', CSRF);
  fd.append('request_type', 'tree_cutting'); // for backend routing

  // names (split + combined)
  fd.append('first_name', d.first_name);
  fd.append('middle_name', d.middle_name);
  fd.append('last_name', d.last_name);
  fd.append('applicant_name', d.applicant_name);

  // address & contacts
  fd.append('street', d.street);
  fd.append('barangay', d.barangay);
  fd.append('municipality', d.municipality);
  fd.append('province', d.province);
  fd.append('contact_number', d.contact_number);
  fd.append('email', d.email);
  fd.append('registration_number', d.registration_number);

  // tree-cut details
  fd.append('location', d.location);
  fd.append('ownership', d.ownership);
  fd.append('purpose', d.purpose);
  fd.append('species_json', JSON.stringify(d.species||[]));
  fd.append('total_count', d.total_count);
  fd.append('total_volume', d.total_volume);

  // NOTE: No uploads (no Word/doc/signature sent)

  const res = await fetch(SAVE_URL, { method:'POST', body: fd, credentials:'include' });
  const json = await res.json().catch(()=>({ok:false,error:'Bad JSON'}));
  if(!res.ok || !json.ok) throw new Error(json.error || `HTTP ${res.status}`);
}

/* ===== Reset ===== */
function resetForm(){
  document.querySelectorAll("input[type='text'], input[type='number'], input[type='email'], textarea").forEach(inp => inp.value = "");
  document.querySelector('input[name="ownership"][value="Private"]').checked = true;

  // reset species table to 3 rows
  const tbody = document.getElementById('species-table-body');
  tbody.innerHTML = '';
  for (let i=0; i<3; i++) addSpeciesRow();
  document.getElementById('total-count').value = 0;
  document.getElementById('total-volume').value = '0.00';

  // signature preview reset
  signaturePad.clear();
  const img = document.getElementById('signature-image'); img.src=""; img.classList.add('hidden');
}
</script>
</body>
</html>
=======
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    header("Location: user_login.php");
    exit();
}
include_once __DIR__ . '/../backend/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $hashed_password, $role);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['role'] = $role;

            header("Location: user_home.php");
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "User not found.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wildlife Registration Application</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2b6625;
            --primary-dark: #1e4a1a;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
            --accent-color: #3a86ff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            padding-top: 100px;
            color: #333;
            line-height: 1.6;
        }

        /* Header Styles */
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Logo */
        .logo {
            height: 45px;
            display: flex;
            margin-top: -1px;
            align-items: center;
            position: relative;
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


        /* Navigation Container */
        .nav-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Navigation Items */
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
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .nav-icon:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .nav-icon i {
            font-size: 1.3rem;
            color: inherit;
            transition: color 0.3s ease;
        }

        .nav-icon.active {
            position: relative;
        }

        .nav-icon.active::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 2px;
            background-color: var(--white);
            border-radius: 2px;
        }


        /* Dropdown Menu */
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

        .dropdown-item.active-page {
            background-color: rgb(225, 255, 220);
            color: var(--primary-dark);
            font-weight: bold;
            border-left: 4px solid var(--primary-color);
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
            font-size: 1.2rem;
        }

        .mark-all-read {
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition), transform 0.2s ease;
        }

        .mark-all-read:hover {
            color: var(--primary-dark);
            transform: scale(1.1);
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
            display: flex;
            align-items: flex-start;
        }

        .notification-item.unread {
            background-color: rgba(43, 102, 37, 0.05);
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-icon {
            margin-right: 15px;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .notification-message {
            color: var(--primary-color);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .notification-time {
            color: #999;
            font-size: 0.8rem;
            margin-top: 5px;
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
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-block;
            padding: 5px 0;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .dropdown-menu.center {
            left: 50%;
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

        /* Dropdown Items */
        .dropdown-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            color: black;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1.1rem;
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

        /* Notification Badge */
        .badge {
            position: absolute;
            top: 2px;
            right: 8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 14px;
            height: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Mobile Menu Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 15px;
        }

        .notification-link {
            display: flex;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
        }

        .notification-link:hover {
            background-color: #f9f9f9;
        }

        /* Content Styles */
        .content {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: -1%;
            padding: 0 20px;
            margin-bottom: 2%;
        }

        .page-title {
            color: #005117;
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #005117;
            padding-bottom: 10px;
            width: 80%;
            max-width: 800px;
        }

        .profile-form {
            background-color: #fff;
            padding: 30px;
            border: 2px solid #005117;
            max-width: 800px;
            width: 90%;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 81, 23, 0.1);
        }

        .form-group input,
        .form-group textarea,
        .form-group input[type="file"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #005117;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
            margin-bottom: 15px;
        }

        .form-group {
            flex: 1 0 200px;
            padding: 0 10px;
            margin-bottom: 25px;
        }

        .form-group.full-width {
            flex: 1 0 100%;
        }

        .form-group.two-thirds {
            flex: 2 0 400px;
        }

        .form-group.one-third {
            flex: 1 0 200px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #000;
            font-size: 14px;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #153415;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2b6625;
            box-shadow: 0 0 0 2px rgba(43, 102, 37, 0.2);
        }

        .form-group textarea {
            height: 180px;
            resize: vertical;
        }

        .form-group input[type="file"] {
            width: 100%;
            height: 40px;
            padding: 5px;
            border: 1px solid #153415;
            border-radius: 4px;
            font-size: 14px;
            background-color: #ffffff;
            box-sizing: border-box;
        }

        /* Make input[type="date"] same height as file input */
        .form-group input[type="date"] {
            height: 40px;
            box-sizing: border-box;
        }

        /* Make all inputs, textarea, select height 40px to match user_requestseedlings.php */
        .form-group input,
        .form-group textarea,
        .form-group select {
            height: 40px;
        }

        .button-group {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .save-btn,
        .view-records-btn {
            background-color: #005117;
            color: #fff;
            border: none;
            padding: 12px 40px;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s;
            text-align: center;
        }

        .view-records-btn {
            background-color: #005117;
        }

        .save-btn:hover {
            background-color: #006622;
            transform: translateY(-2px);
        }

        .view-records-btn:hover {
            background-color: #006622;
            transform: translateY(-2px);
        }

        /* Records Table Styles */
        .records-container {
            background-color: #fff;
            border: 2px solid #005117;
            border-radius: 12px;
            padding: 30px;
            margin-top: 30px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 6px 20px rgba(0, 81, 23, 0.1);
            display: none;
        }

        .records-title {
            color: #005117;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 2px solid #005117;
            padding-bottom: 10px;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .records-table th,
        .records-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        /* Center first column header and data cells text to match user_requestseedlings.php */
        .records-table th:first-child,
        .records-table td:first-child {
            text-align: center;
        }

        .records-table th {
            background-color: #005117;
            color: white;
            font-weight: 600;
        }

        .records-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .records-table tr:hover {
            background-color: #f1f1f1;
        }

        .status-pending {
            color: #4caf50;
            font-weight: 600;
        }

        .status-approved {
            color: #4caf50;
            font-weight: 600;
        }

        .status-rejected {
            color: #4caf50;
            font-weight: 600;
        }

        .no-records {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .mobile-toggle {
                display: block;
            }

            /* Header Styles */
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
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
        }

        /* Logo */
        .logo {
            height: 45px;
            display: flex;
            margin-top: -1px;
            align-items: center;
            position: relative;
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

        /* Navigation Container */
        .nav-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Navigation Items - Larger Icons */
        .nav-item {
            position: relative;
        }

        .nav-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            /* smaller width */
            height: 40px;
            /* smaller height */
            background: rgb(233, 255, 242);
            /* slightly brighter background */
            border-radius: 12px;
            /* softer corners */
            cursor: pointer;
            transition: var(--transition);
            color: black;
            /* changed icon color to black */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            /* subtle shadow for depth */
        }

        .nav-icon:hover {
            background: rgba(224, 204, 204, 0.3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .nav-icon i {
            font-size: 1.3rem;
            /* smaller icon size */
            color: inherit;
            transition: color 0.3s ease;
        }

        /* Dropdown Menu */
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

        /* Notification-specific dropdown styles */
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
            font-size: 1.2rem;
        }

        .mark-all-read {
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition), transform 0.2s ease;
        }

        .mark-all-read:hover {
            color: var(--primary-dark);
            /* Slightly darker color on hover */
            transform: scale(1.1);
            /* Slightly bigger on hover */
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
            display: flex;
            align-items: flex-start;
        }

        .notification-item.unread {
            background-color: rgba(43, 102, 37, 0.05);
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-icon {
            margin-right: 15px;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .notification-message {
            color: var(--primary-color);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .notification-time {
            color: #999;
            font-size: 0.8rem;
            margin-top: 5px;
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
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-block;
            padding: 5px 0;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .dropdown-menu.center {
            left: 50%;
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

        /* Larger Dropdown Items */
        .dropdown-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            color: black;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1.1rem;
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

        /* Notification Badge - Larger */
        .badge {
            position: absolute;
            top: 2px;
            right: 8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 14px;
            height: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Mobile Menu Toggle - Larger */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 15px;
        }

        .notification-link {
            display: flex;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
        }

        .notification-link:hover {
            background-color: #f9f9f9;
        }


        /* Main Content */
        .main-container {
            margin-top: -0.5%;
            padding: 30px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            margin-top: -3%;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: nowrap;
            justify-content: center;
            overflow-x: auto;
            padding-bottom: 10px;
            -webkit-overflow-scrolling: touch;
        }

        .btn {
            padding: 10px 15px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            white-space: nowrap;
            min-width: 120px;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-outline {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--light-gray);
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
            border: 2px solid var(--primary-color);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        /* Requirements Form */
        .requirements-form {
            margin-top: -1%;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            border: 1px solid var(--medium-gray);
        }

        .form-header {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 20px 30px;
            border-bottom: 1px solid var(--primary-dark);
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
            background: var(--light-gray);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }

        .requirement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .requirement-title {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .requirement-number {
            background: var(--primary-color);
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            margin-right: 10px;
            flex-shrink: 0;
            /* Add this to prevent shrinking */
            line-height: 25px;
            /* Add this to ensure vertical centering */
            text-align: center;
            /* Add this for horizontal centering */
        }

        .new-number {
            display: inline;
        }

        .renewal-number {
            display: none;
        }

        .file-upload {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .file-input-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .file-input-label {
            padding: 8px 15px;
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .file-input-label:hover {
            background: var(--primary-dark);
        }

        .file-input {
            display: none;
        }

        .file-name {
            font-size: 0.9rem;
            color: var(--dark-gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
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
            border-radius: var(--border-radius);
            border: 1px solid var(--medium-gray);
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow: hidden;
        }

        .file-icon {
            color: var(--primary-color);
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
            color: var(--dark-gray);
            cursor: pointer;
            transition: var(--transition);
            padding: 5px;
        }

        .file-action-btn:hover {
            color: var(--primary-color);
        }

        .form-footer {
            padding: 20px 30px;
            background: var(--light-gray);
            border-top: 1px solid var(--medium-gray);
            display: flex;
            justify-content: flex-end;
        }

        /* Fee Information */
        .fee-info {
            margin-top: 20px;
            padding: 15px;
            background: rgba(43, 102, 37, 0.1);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }

        .fee-info p {
            margin: 5px 0;
            color: var(--primary-dark);
            font-weight: 500;
        }

        /* File Preview Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            overflow: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            border-radius: var(--border-radius);
            position: relative;
        }

        .close-modal {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-modal:hover {
            color: black;
        }

        .file-preview {
            width: 100%;
            height: 70vh;
            border: none;
            margin-top: 20px;
        }

        /* Download button styles */
        .download-btn {
            display: inline-flex;
            align-items: center;
            background-color: #2b6625;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 10px;
            transition: all 0.3s;
        }

        .download-btn:hover {
            background-color: #1e4a1a;
        }

        .download-btn i {
            margin-right: 8px;
        }

        /* Permit Type Selector */
        .permit-type-selector {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 20px;
        }

        .permit-type-btn {
            padding: 12px 25px;
            margin: 0 10px 0 0;
            border: 2px solid #2b6625;
            background-color: white;
            color: #2b6625;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .permit-type-btn.active {
            background-color: #2b6625;
            color: white;
        }

        .permit-type-btn:hover {
            background-color: #2b6625;
            color: white;
        }

        /* Add new styles for name fields */
        .name-fields {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .name-field {
            flex: 1;
            min-width: 200px;
        }

        .name-field input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #153415;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
            height: 40px;
            box-sizing: border-box;
        }

        .name-field input:focus {
            outline: none;
            border-color: #2b6625;
            box-shadow: 0 0 0 2px rgba(43, 102, 37, 0.2);
        }

        .name-field input::placeholder {
            color: #999;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .notifications-dropdown {
                width: 320px;
            }

            .main-container {
                padding: 20px;
            }

            .form-body {
                padding: 20px;
            }

            .requirement-item {
                padding: 15px;
            }

            .requirement-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .file-input-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }

            .permit-type-selector {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 10px;
                -webkit-overflow-scrolling: touch;
            }

            .permit-type-btn {
                flex: 0 0 auto;
                margin: 0 5px 0 0;
                padding: 10px 15px;
            }
        }

        @media (max-width: 576px) {
            header {
                padding: 0 15px;
            }

            .nav-container {
                gap: 15px;
            }

            .notifications-dropdown {
                width: 280px;
                right: -50px;
            }

            .notifications-dropdown:before {
                right: 65px;
            }

            .action-buttons {
                margin-top: -6%;
                gap: 8px;
                padding-bottom: 5px;
            }

            .btn {
                padding: 10px 10px;
                font-size: 0.85rem;
                min-width: 80px;
            }

            .btn i {
                font-size: 0.85rem;
                margin-right: 5px;
            }

            .form-header {
                padding: 15px 20px;
            }

            .form-header h2 {
                font-size: 1.3rem;
            }

            .permit-type-btn {
                font-size: 0.9rem;
                padding: 8px 12px;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="logo">
            <a href="user_home.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>

        <!-- Mobile menu toggle -->
        <button class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation on the right -->
        <div class="nav-container">
            <!-- Dashboard Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon active">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">

                    <a href="user_reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Report Incident</span>
                    </a>

                    <a href="useraddseed.php" class="dropdown-item">
                        <i class="fas fa-seedling"></i>
                        <span>Request Seedlings</span>
                    </a>

                    <a href="useraddwild.php" class="dropdown-item">
                        <i class="fas fa-paw"></i>
                        <span>Wildlife Permit</span>
                    </a>

                    <a href="useraddtreecut.php" class="dropdown-item active-page">
                        <i class="fas fa-tree"></i>
                        <span>Tree Cutting Permit</span>
                    </a>
                    <a href="useraddlumber.php" class="dropdown-item">
                        <i class="fas fa-boxes"></i>
                        <span>Lumber Dealers Permit</span>
                    </a>
                    <a href="useraddwood.php" class="dropdown-item">
                        <i class="fas fa-industry"></i>
                        <span>Wood Processing Permit</span>
                    </a>
                    <a href="useraddchainsaw.php" class="dropdown-item">
                        <i class="fas fa-tools"></i>
                        <span>Chainsaw Permit</span>
                    </a>


                </div>
            </div>


            <!-- Notifications -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bell"></i>
                    <span class="badge">1</span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <a href="#" class="mark-all-read">Mark all as read</a>
                    </div>

                    <div class="notification-item unread">
                        <a href="user_each.php?id=1" class="notification-link">
                            <div class="notification-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">Chainsaw Renewal Status</div>
                                <div class="notification-message">Chainsaw Renewal has been approved.</div>
                                <div class="notification-time">10 minutes ago</div>
                            </div>
                        </a>
                    </div>

                    <div class="notification-footer">
                        <a href="user_notification.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="user_login.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>


    <div class="main-container">
        <div class="action-buttons">
            <button class="btn btn-primary" id="addFilesBtn">
                <i class="fas fa-plus-circle"></i> Add
            </button>
            <a href="useredittreecut.php" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="userviewtreecut.php" class="btn btn-outline">
                <i class="fas fa-eye"></i> View
            </a>
        </div>

        <div class="requirements-form">
            <div class="form-header">
                <h2>Tree Cutting Permit - Requirements</h2>
            </div>

            <div class="form-body">
                <div class="name-fields">
                    <div class="name-field">
                        <input type="text" placeholder="First Name" required>
                    </div>
                    <div class="name-field">
                        <input type="text" placeholder="Middle Name">
                    </div>
                    <div class="name-field">
                        <input type="text" placeholder="Last Name" required>
                    </div>
                </div>

                <div class="requirements-list">
                    <!-- Requirement 1 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                Certificate of Verification (COV)- 2 copies for CENRO signature or OIC
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-1" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-1" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-1"></div>
                        </div>
                    </div>

                    <!-- Requirement 2 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">2</span>
                                Order of Payment and Official Receipt
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-2" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-2"></div>
                        </div>
                    </div>

                    <!-- Requirement 3 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">3</span>
                                Memorandom Report (2 copies signed by inspecting officer subscribed by register forester)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-3" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-3"></div>
                        </div>
                    </div>

                    <!-- Requirement 4 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">4</span>
                                Tally sheets (inventory sheet of forest product)- 2 copies signed by inspecting officer subscribed by registered forester
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-4" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-4" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-4"></div>
                        </div>
                    </div>

                    <!-- Requirement 5 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">5</span>
                                Geo-tagged photos of forest products (2 copies signed by inspecting officer subscribed by registered forester)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-5" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-5" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-5"></div>
                        </div>
                    </div>

                    <!-- Requirement 6 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">6</span>
                                Sworn Statement (2 copies signed by inspecting officer subscribed by registered forester)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-6" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-6" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-6"></div>
                        </div>
                    </div>

                    <!-- Requirement 7 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">7</span>
                                Certificate of Transport Agreement duly notarized (2 copies)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Photocopy of OR/CR of conveyance</p>
                                <div class="file-input-container">
                                    <label for="file-7a" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-7a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-7a"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Photocopy of Drivers License</p>
                                <div class="file-input-container">
                                    <label for="file-7b" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-7b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-7b"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Requirement 8 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">8</span>
                                Purchase Order(Signed by the Consignee - 2 copies)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-8" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-8" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-8"></div>
                        </div>
                    </div>

                    <!-- Requirement 9 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">9</span>
                                Letter request with SPA (2 copies)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-9" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-9" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-9"></div>
                        </div>
                    </div>

                    <!-- Requirement 10 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">10</span>
                                Photocopy of approved TCP/ SPTLP/ PLTP/ STCP (2 copies)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Tally sheets and stand/ stock table</p>
                                <div class="file-input-container">
                                    <label for="file-10a" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-10a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-10a"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Tree Charting</p>
                                <div class="file-input-container">
                                    <label for="file-10b" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-10b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-10b"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </div>
        </div>
    </div>

    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <!-- File Preview Modal -->
    <div id="filePreviewModal" class="modal">
        <div class="modal-content">
            <span id="closeFilePreviewModal" class="close-modal">&times;</span>
            <h3 id="modal-title">File Preview</h3>
            <iframe id="filePreviewFrame" class="file-preview" src="about:blank"></iframe>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content" style="max-width:400px;text-align:center;">
            <span id="closeConfirmModal" class="close-modal">&times;</span>
            <h3>Confirm Submission</h3>
            <p>Are you sure you want to submit this tree cutting permit request?</p>
            <button id="confirmSubmitBtn" class="btn btn-primary" style="margin:10px 10px 0 0;">Yes, Submit</button>
            <button id="cancelSubmitBtn" class="btn btn-outline">Cancel</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    const isActive = navContainer.classList.toggle('active');
                    document.body.style.overflow = isActive ? 'hidden' : '';
                });
            }

            // File input handling
            const fileInputs = [{
                    id: 'file-1',
                    uploaded: 'uploaded-files-1'
                },
                {
                    id: 'file-2',
                    uploaded: 'uploaded-files-2'
                },
                {
                    id: 'file-3',
                    uploaded: 'uploaded-files-3'
                },
                {
                    id: 'file-4',
                    uploaded: 'uploaded-files-4'
                },
                {
                    id: 'file-5',
                    uploaded: 'uploaded-files-5'
                },
                {
                    id: 'file-6',
                    uploaded: 'uploaded-files-6'
                },
                {
                    id: 'file-7a',
                    uploaded: 'uploaded-files-7a'
                },
                {
                    id: 'file-7b',
                    uploaded: 'uploaded-files-7b'
                },
                {
                    id: 'file-8',
                    uploaded: 'uploaded-files-8'
                },
                {
                    id: 'file-9',
                    uploaded: 'uploaded-files-9'
                },
                {
                    id: 'file-10a',
                    uploaded: 'uploaded-files-10a'
                },
                {
                    id: 'file-10b',
                    uploaded: 'uploaded-files-10b'
                }
            ];

            let selectedFiles = {};

            fileInputs.forEach(input => {
                const fileInput = document.getElementById(input.id);
                const uploadedFilesContainer = document.getElementById(input.uploaded);
                if (fileInput) {
                    fileInput.addEventListener('change', function() {
                        uploadedFilesContainer.innerHTML = '';
                        const file = this.files[0];
                        this.parentElement.querySelector('.file-name').textContent = file ? file.name : 'No file chosen';
                        if (file) {
                            selectedFiles[input.id] = file;
                            addUploadedFileMulti(file, uploadedFilesContainer, fileInput, input.id);
                        } else {
                            selectedFiles[input.id] = null;
                        }
                    });
                }
            });

            function addUploadedFileMulti(file, uploadedFilesContainer, fileInput, inputId) {
                uploadedFilesContainer.innerHTML = '';
            }

            // File preview functionality
            const modal = document.getElementById('filePreviewModal');
            const modalFrame = document.getElementById('filePreviewFrame');
            const closeFilePreviewModal = document.getElementById('closeFilePreviewModal');

            function previewFile(file) {
                const modalFrame = document.getElementById('filePreviewFrame');
                if (!modalFrame) return;
                modalFrame.removeAttribute('src');
                modalFrame.removeAttribute('srcdoc');
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (file.type.startsWith('image/')) {
                        modalFrame.srcdoc = `<img src='${e.target.result}' style='max-width:100%;max-height:80vh;'>`;
                    } else if (file.type === 'application/pdf') {
                        modalFrame.src = e.target.result;
                    } else if (
                        file.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
                        file.type === 'application/msword'
                    ) {
                        const url = URL.createObjectURL(file);
                        modalFrame.srcdoc = `<div style='padding:20px;text-align:center;'>Cannot preview this file type.<br><a href='${url}' download='${file.name}' style='color:#2b6625;font-weight:bold;'>Download ${file.name}</a></div>`;
                    } else {
                        modalFrame.srcdoc = `<div style='padding:20px;'>Cannot preview this file type.</div>`;
                    }
                    modal.style.display = 'block';
                };
                if (file.type.startsWith('image/')) {
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    reader.readAsDataURL(file);
                } else if (
                    file.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
                    file.type === 'application/msword'
                ) {
                    reader.onload();
                } else {
                    reader.onload();
                }
            }

            if (closeFilePreviewModal) {
                closeFilePreviewModal.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            }
            window.addEventListener('click', function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });

            // Confirmation modal logic
            const confirmModal = document.getElementById('confirmModal');
            const closeConfirmModal = document.getElementById('closeConfirmModal');
            const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
            const cancelSubmitBtn = document.getElementById('cancelSubmitBtn');

            const submitApplicationBtn = document.getElementById('submitApplication');
            if (submitApplicationBtn) {
                submitApplicationBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Validate fields
                    const firstName = document.querySelector('.name-fields input[placeholder="First Name"]').value.trim();
                    const lastName = document.querySelector('.name-fields input[placeholder="Last Name"]').value.trim();
                    if (!firstName || !lastName) {
                        alert('First name and last name are required.');
                        return;
                    }
                    if (!selectedFiles["file-1"]) {
                        alert('Please upload your Certificate of Verification.');
                        return;
                    }
                    if (confirmModal) confirmModal.style.display = 'block';
                });
            }

            if (closeConfirmModal) {
                closeConfirmModal.addEventListener('click', function() {
                    if (confirmModal) confirmModal.style.display = 'none';
                });
            }
            if (cancelSubmitBtn) {
                cancelSubmitBtn.addEventListener('click', function() {
                    if (confirmModal) confirmModal.style.display = 'none';
                });
            }

            if (confirmSubmitBtn) {
                confirmSubmitBtn.addEventListener('click', function() {
                    if (confirmModal) confirmModal.style.display = 'none';
                    // Prepare form data
                    const firstName = document.querySelector('.name-fields input[placeholder="First Name"]').value.trim();
                    const middleName = document.querySelector('.name-fields input[placeholder="Middle Name"]').value.trim();
                    const lastName = document.querySelector('.name-fields input[placeholder="Last Name"]').value.trim();

                    const formData = new FormData();
                    formData.append('first_name', firstName);
                    formData.append('middle_name', middleName);
                    formData.append('last_name', lastName);

                    // Append all files
                    for (let i = 1; i <= 10; i++) {
                        if (i === 7) {
                            if (selectedFiles[`file-${i}a`]) formData.append(`file_${i}a`, selectedFiles[`file-${i}a`]);
                            if (selectedFiles[`file-${i}b`]) formData.append(`file_${i}b`, selectedFiles[`file-${i}b`]);
                        } else if (i === 10) {
                            if (selectedFiles[`file-${i}a`]) formData.append(`file_${i}a`, selectedFiles[`file-${i}a`]);
                            if (selectedFiles[`file-${i}b`]) formData.append(`file_${i}b`, selectedFiles[`file-${i}b`]);
                        } else {
                            if (selectedFiles[`file-${i}`]) formData.append(`file_${i}`, selectedFiles[`file-${i}`]);
                        }
                    }

                    fetch('../backend/users/addtreecut.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // Clear all inputs
                                document.querySelector('.name-fields input[placeholder="First Name"]').value = '';
                                document.querySelector('.name-fields input[placeholder="Middle Name"]').value = '';
                                document.querySelector('.name-fields input[placeholder="Last Name"]').value = '';
                                fileInputs.forEach(input => {
                                    const fileInput = document.getElementById(input.id);
                                    const uploadedFilesContainer = document.getElementById(input.uploaded);
                                    if (fileInput) {
                                        fileInput.value = '';
                                        fileInput.parentElement.querySelector('.file-name').textContent = 'No file chosen';
                                    }
                                    if (uploadedFilesContainer) uploadedFilesContainer.innerHTML = '';
                                });
                                selectedFiles = {};
                                // Show notification
                                showProfileNotification('Tree cutting permit application submitted successfully!');
                            } else {
                                alert(data.errors ? data.errors.join('\n') : 'Failed to submit request.');
                            }
                        })
                        .catch(() => {
                            alert('Network error.');
                        });
                });
            }

            function showProfileNotification(message) {
                const notif = document.getElementById('profile-notification');
                if (!notif) return;
                notif.textContent = message;
                notif.style.display = 'block';
                notif.style.opacity = '1';
                setTimeout(() => {
                    notif.style.opacity = '0';
                    setTimeout(() => {
                        notif.style.display = 'none';
                        notif.style.opacity = '1';
                    }, 400);
                }, 2200);
            }

            // Add files button (demo functionality)
            const addFilesBtn = document.getElementById('addFilesBtn');
            if (addFilesBtn) {
                addFilesBtn.addEventListener('click', function() {
                    alert('In a real application, this would open a dialog to add multiple files at once.');
                });
            }
        });
    </script>
</body>

</html>
>>>>>>> origin/main
