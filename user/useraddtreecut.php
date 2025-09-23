<?php
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
