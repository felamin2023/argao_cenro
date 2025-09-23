<?php
// useraddwild.php — Wildlife Registration (New/Renewal), unified
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
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Wildlife Registration Application</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.5.3/signature_pad.min.js"></script>
  <style>
    :root{
      --primary-color:#2b6625; --primary-dark:#1e4a1a; --white:#fff; --light-gray:#f5f5f5;
      --border-radius:8px; --box-shadow:0 4px 12px rgba(0,0,0,.1); --transition:all .2s ease;
      --medium-gray:#ddd;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f9f9f9 url('images/wildlife.jpg') center/cover no-repeat fixed;color:#333;line-height:1.6;padding-top:120px}

    /* headers */
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

    /* main container */
    .main-container{margin-top:-0.5%;padding:30px}
    .inner{max-width:1280px;margin:0 auto}
    .action-buttons{display:flex;margin-top:-3%;gap:15px;margin-bottom:30px;flex-wrap:nowrap;justify-content:center;overflow-x:auto;padding-bottom:10px}
    .btn{padding:10px 15px;border-radius:var(--border-radius);font-weight:600;text-decoration:none;transition:var(--transition);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem;white-space:nowrap;min-width:120px}
    .btn i{margin-right:8px}
    .btn-outline{border:2px solid var(--primary-color);color:var(--primary-color);background:white}
    .btn-outline:hover{background:var(--light-gray)}
    .btn-primary{background:var(--primary-color);color:#fff;border:2px solid var(--primary-color)}
    .btn-primary:hover{background:var(--primary-dark);border-color:var(--primary-dark)}

    .requirements-form{margin-top:-1%;background:#fff;border-radius:var(--border-radius);box-shadow:var(--box-shadow);overflow:hidden;border:1px solid var(--medium-gray)}
    .form-header{background:var(--primary-color);color:#fff;padding:20px 30px;border-bottom:1px solid var(--primary-dark)}
    .form-header h2{text-align:center;font-size:1.5rem;margin:0}
    .form-body{padding:30px}
    .form-footer{padding:20px 30px;background:var(--light-gray);border-top:1px solid var(--medium-gray);display:flex;justify-content:center;gap:12px;flex-wrap:wrap}

    .form-section{margin-bottom:25px}
    .form-section h2{background:var(--primary-color);color:#fff;padding:10px 15px;margin-bottom:15px;border-radius:4px;font-size:18px}
    .form-group{margin-bottom:15px}
    .form-group label{display:block;margin-bottom:5px;font-weight:600;color:var(--primary-color)}
    .form-group input,.form-group textarea,.form-group select{width:100%;padding:10px 15px;border:1px solid #ddd;border-radius:4px;font-size:15px;transition:border-color .3s}
    .form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:var(--primary-color);outline:none;box-shadow:0 0 0 2px rgba(43,102,37,.2)}
    .required::after{content:" *";color:#ff4757}
    .form-row{display:flex;gap:20px;margin-bottom:15px}
    .form-row .form-group{flex:1}

    .checkbox-group{display:flex;gap:20px;margin-bottom:15px;flex-wrap:wrap}
    .checkbox-item{display:flex;align-items:center;gap:8px}
    .checkbox-item input{width:auto}

    table{width:100%;border-collapse:collapse;margin:15px 0}
    table th,table td{border:1px solid #ddd;padding:10px;text-align:left}
    table th{background:#f2f2f2;font-weight:600}
    .table-input{width:100%;padding:8px;border:1px solid #ddd;border-radius:4px}
    .add-row-btn{background:var(--primary-color);color:#fff;border:none;padding:8px 15px;border-radius:4px;cursor:pointer;margin-bottom:15px;font-size:14px}
    .remove-row-btn{background:#ff4757;color:#fff;border:none;padding:5px 10px;border-radius:4px;cursor:pointer}

    .declaration{background:#f9f9f9;padding:20px;border-radius:4px;border-left:4px solid var(--primary-color);margin-bottom:25px}
    .signature-date{display:flex;justify-content:space-between;margin-top:30px;flex-wrap:wrap}
    .signature-box{width:100%;margin-top:20px}
    .signature-pad-container{border:1px solid #ddd;border-radius:4px;margin-bottom:10px;background:#fff}
    #signature-pad{width:100%;height:150px;cursor:crosshair}
    .signature-actions{display:flex;gap:10px;margin-top:10px}
    .signature-btn{padding:8px 15px;border:none;border-radius:4px;cursor:pointer;font-size:14px}
    .clear-signature{background:#ff4757;color:#fff}
    .save-signature{background:var(--primary-color);color:#fff}
    .signature-preview{text-align:center;margin-top:15px}
    #signature-image{max-width:300px;border:1px solid #ddd;border-radius:4px}
    .hidden{display:none}

    #profile-notification{display:none;position:fixed;top:5px;left:50%;transform:translateX(-50%);background:#323232;color:#fff;padding:16px 32px;border-radius:8px;font-size:1.05rem;z-index:9999}
    #loadingIndicator{display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998}
    #loadingIndicator .card{background:#fff;padding:18px 22px;border-radius:10px;box-shadow:var(--box-shadow);color:#333}
    #confirmModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center}
    #confirmModal .dlg{background:#fff;max-width:520px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden}
    #confirmModal .dlg-h{padding:18px 20px;border-bottom:1px solid #eee;font-weight:600}
    #confirmModal .dlg-b{padding:16px 20px;line-height:1.6}
    #confirmModal .dlg-f{display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee}

    /* toggle band */
    .toggle-group{display:flex;gap:10px;justify-content:flex-start;align-items:center;margin:10px 0 20px;flex-wrap:wrap}
    .toggle-btn{border:2px solid var(--primary-color);background:#fff;color:var(--primary-color);padding:10px 16px;border-radius:10px;cursor:pointer;font-weight:700;min-width:220px;text-align:left;transition:.2s}
    .toggle-btn.active{background:var(--primary-color);color:#fff}
    .toggle-btn:hover{filter:brightness(.96)}

    @media (max-width:768px){
      .form-row{flex-direction:column;gap:0}
      .toggle-btn{min-width:unset}
    }
    @media print{
      .add-row-btn,.remove-row-btn,.signature-actions,.signature-pad-container,.action-buttons,.toggle-group{display:none!important}
      body{background:#fff;padding:0}
      .requirements-form{box-shadow:none;border:none}
    }
  </style>
</head>
<body>

  <!-- Header -->
  <header>
    <div class="logo"><a href="user_home.php"><img src="seal.png" alt="Site Logo"></a></div>
    <button class="mobile-toggle"><i class="fas fa-bars"></i></button>
    <div class="nav-container">
      <div class="nav-item dropdown">
        <div class="nav-icon active"><i class="fas fa-bars"></i></div>
        <div class="dropdown-menu center">
          <a href="user_reportaccident.php" class="dropdown-item"><i class="fas fa-file-invoice"></i><span>Report Incident</span></a>
          <a href="useraddseed.php" class="dropdown-item"><i class="fas fa-seedling"></i><span>Request Seedlings</span></a>
          <a href="useraddwild.php" class="dropdown-item active-page"><i class="fas fa-paw"></i><span>Wildlife Permit</span></a>
          <a href="useraddtreecut.php" class="dropdown-item"><i class="fas fa-tree"></i><span>Tree Cutting Permit</span></a>
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

  <!-- Main -->
  <div class="main-container">
    <div class="inner">
      <div class="action-buttons">
        <button class="btn btn-primary" id="addAnimalTop"><i class="fas fa-plus-circle"></i> Add Animal</button>
        <a href="usereditwild.php" class="btn btn-outline"><i class="fas fa-edit"></i> Edit</a>
        <a href="userviewwild.php" class="btn btn-outline"><i class="fas fa-eye"></i> View</a>
      </div>

      <div class="requirements-form">
        <div class="form-header">
          <h2 id="formTitle">Application for Certificate of Wildlife Registration</h2>
        </div>

        <div class="form-body">
          <!-- Toggle -->
          <div class="toggle-group" id="permitToggle">
            <button type="button" class="toggle-btn active" data-type="new"><i class="fa-solid fa-file-circle-plus"></i> New Wildlife Permit</button>
            <button type="button" class="toggle-btn" data-type="renewal"><i class="fa-solid fa-arrows-rotate"></i> Wildlife Renewal</button>
          </div>

          <!-- Categories -->
          <div class="checkbox-group">
            <div class="checkbox-item"><input type="checkbox" id="zoo"><label for="zoo">Zoo</label></div>
            <div class="checkbox-item"><input type="checkbox" id="botanical-garden"><label for="botanical-garden">Botanical Garden</label></div>
            <div class="checkbox-item"><input type="checkbox" id="private-collection"><label for="private-collection">Private Collection</label></div>
          </div>

          <!-- Applicant Info -->
          <div class="form-section">
            <h2>APPLICANT INFORMATION</h2>

            <!-- New: ask first/middle/last -->
            <div id="nameRowNew">
              <div class="form-row">
                <div class="form-group">
                  <label for="first-name" class="required">First Name:</label>
                  <input type="text" id="first-name" name="first_name">
                </div>
                <div class="form-group">
                  <label for="middle-name">Middle Name:</label>
                  <input type="text" id="middle-name" name="middle_name">
                </div>
                <div class="form-group">
                  <label for="last-name" class="required">Last Name:</label>
                  <input type="text" id="last-name" name="last_name">
                </div>
              </div>
            </div>

            <!-- Renewal: first/middle/last -->
            <div id="nameRowRenewal" class="hidden">
              <div class="form-row">
                <div class="form-group">
                  <label for="renewal-first-name" class="required">First Name:</label>
                  <input type="text" id="renewal-first-name" name="renewal_first_name">
                </div>
                <div class="form-group">
                  <label for="renewal-middle-name">Middle Name:</label>
                  <input type="text" id="renewal-middle-name" name="renewal_middle_name">
                </div>
                <div class="form-group">
                  <label for="renewal-last-name" class="required">Last Name:</label>
                  <input type="text" id="renewal-last-name" name="renewal_last_name">
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="residence-address" class="required">Residence Address:</label>
              <input type="text" id="residence-address" placeholder="House/Street, Barangay, Municipality/City, Province">
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="telephone-number">Contact Number:</label>
                <input type="text" id="telephone-number" placeholder="09XX…">
              </div>
              <div class="form-group">
                <label for="establishment-name" class="required">Name of Establishment:</label>
                <input type="text" id="establishment-name">
              </div>
            </div>

            <div class="form-group">
              <label for="establishment-address" class="required">Address of Establishment:</label>
              <input type="text" id="establishment-address" placeholder="Street, Barangay, Municipality/City, Province">
            </div>

            <div class="form-group">
              <label for="establishment-telephone">Establishment Telephone Number:</label>
              <input type="text" id="establishment-telephone">
            </div>

            <!-- Renewal-only fields -->
            <div id="renewalOnly" class="hidden">
              <div class="form-row">
                <div class="form-group">
                  <label for="wfp-number" class="required">Original WFP No.:</label>
                  <input type="text" id="wfp-number">
                </div>
                <div class="form-group">
                  <label for="issue-date" class="required">Issued on:</label>
                  <input type="date" id="issue-date">
                </div>
              </div>
            </div>
          </div>

          <!-- Animals -->
          <div class="form-section">
            <h2>ANIMALS/STOCKS INFORMATION</h2>

            <table id="animals-table">
              <thead>
                <tr id="animals-head-row"></tr>
              </thead>
              <tbody id="animals-tbody"></tbody>
            </table>

            <button type="button" class="add-row-btn" id="add-row-btn"><i class="fas fa-plus"></i> Add Animal</button>
          </div>

          <!-- Declaration -->
          <div class="form-section">
            <h2>DECLARATION</h2>
            <div class="declaration">
              <p id="declarationText">
                I understand that the filing of this application conveys no right to possess any wild animals until a Certificate of Registration is issued to me by the Regional Director of the DENR Region 7.
              </p>

              <div class="signature-date">
                <div class="signature-box">
                  <label>Signature of Applicant (preview only):</label>
                  <div class="signature-pad-container"><canvas id="signature-pad"></canvas></div>
                  <div class="signature-actions">
                    <button type="button" class="signature-btn clear-signature" id="clear-signature">Clear</button>
                    <button type="button" class="signature-btn save-signature" id="save-signature">Save Signature</button>
                  </div>
                  <div class="signature-preview">
                    <img id="signature-image" class="hidden" alt="Signature">
                  </div>
                </div>
              </div>

              <div class="form-group" style="margin-top:14px">
                <label for="postal-address">Postal Address:</label>
                <input type="text" id="postal-address">
              </div>
            </div>
          </div>
        </div>

        <div class="form-footer">
          <button type="button" class="btn btn-primary" id="submitBtn"><i class="fa-solid fa-paper-plane"></i> Submit Application</button>
          <button type="button" class="btn btn-outline" id="downloadBtn"><i class="fas fa-download"></i> Download as Word</button>
        </div>
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
      <div class="dlg-b">Please confirm you want to submit this Wildlife Registration application.</div>
      <div class="dlg-f">
        <button id="btnCancelConfirm" class="btn btn-outline" type="button">Cancel</button>
        <button id="btnOkConfirm" class="btn btn-primary" type="button">Yes, submit</button>
      </div>
    </div>
  </div>

<script>
const CSRF = "<?=htmlspecialchars($CSRF, ENT_QUOTES)?>";
const SAVE_URL = new URL('../backend/users/wildlife/save_wildlife.php', window.location.href).toString();

/* ===== Mobile nav toggle ===== */
document.querySelectorAll('.mobile-toggle').forEach(btn=>{
  btn.addEventListener('click', ()=>document.body.classList.toggle('nav-open'));
});

/* ===== Toggle state & UI swap ===== */
let PERMIT_TYPE = 'new';

const nameRowNew = document.getElementById('nameRowNew');
const nameRowRenewal = document.getElementById('nameRowRenewal');
const renewalOnly = document.getElementById('renewalOnly');
const formTitle = document.getElementById('formTitle');
const declarationText = document.getElementById('declarationText');

document.querySelectorAll('#permitToggle .toggle-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.querySelectorAll('#permitToggle .toggle-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    PERMIT_TYPE = btn.dataset.type;
    applyMode(PERMIT_TYPE);
  });
});

function applyMode(mode){
  const isRenewal = mode === 'renewal';
  // Titles + text
  formTitle.textContent = isRenewal
    ? 'Application for Renewal: Certificate of Wildlife Registration'
    : 'Application for Certificate of Wildlife Registration';
  declarationText.textContent = isRenewal
    ? 'I understand that this application for renewal does not by itself grant the right to continue possession of any wild animals until the corresponding Renewal Certificate of Registration is issued.'
    : 'I understand that the filing of this application conveys no right to possess any wild animals until a Certificate of Registration is issued to me by the Regional Director of the DENR Region 7.';

  // Name fields
  nameRowNew.classList.toggle('hidden', isRenewal);
  nameRowRenewal.classList.toggle('hidden', !isRenewal);
  renewalOnly.classList.toggle('hidden', !isRenewal);

  // Animals table
  renderAnimalsTable(isRenewal);
}

// ===== Signature Pad (preview only) =====
const canvas = document.getElementById('signature-pad');
const signaturePad = new SignaturePad(canvas, { backgroundColor:'#fff', penColor:'#000' });
function resizeCanvas(){
  const ratio = Math.max(window.devicePixelRatio||1,1);
  canvas.width = canvas.offsetWidth * ratio;
  canvas.height = canvas.offsetHeight * ratio;
  canvas.getContext("2d").scale(ratio, ratio);
  signaturePad.clear();
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

/* ===== Animals table (dynamic columns) ===== */
const headRow = document.getElementById('animals-head-row');
const tbody = document.getElementById('animals-tbody');

function renderAnimalsTable(isRenewal){
  // header
  headRow.innerHTML = `
    <th>Common Name</th>
    <th>Scientific Name</th>
    <th>Quantity</th>
    ${isRenewal ? '<th>Remarks (Alive/Deceased)</th>' : ''}
    <th>Action</th>
  `;
  // body (preserve existing values where possible)
  const oldRows = Array.from(tbody.querySelectorAll('tr')).map(row=>{
    const cells = row.querySelectorAll('input, select');
    return {
      common: cells[0]?.value || '',
      scientific: cells[1]?.value || '',
      qty: cells[2]?.value || '',
      remarks: cells[3]?.value || 'Alive'
    };
  });

  tbody.innerHTML = '';
  if (oldRows.length === 0) oldRows.push({common:'',scientific:'',qty:'',remarks:'Alive'});
  oldRows.forEach(r=>addAnimalRow(isRenewal, r));
}

function addAnimalRow(isRenewal = (PERMIT_TYPE==='renewal'), preset = {common:'',scientific:'',qty:'',remarks:'Alive'}){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="text" class="table-input" value="${preset.common}"></td>
    <td><input type="text" class="table-input" value="${preset.scientific}"></td>
    <td><input type="number" class="table-input" min="1" value="${preset.qty}"></td>
    ${isRenewal ? `
      <td>
        <select class="table-input">
          <option ${preset.remarks==='Alive'?'selected':''}>Alive</option>
          <option ${preset.remarks==='Deceased'?'selected':''}>Deceased</option>
        </select>
      </td>` : ''
    }
    <td><button type="button" class="remove-row-btn">Remove</button></td>
  `;
  tbody.appendChild(tr);
  tr.querySelector('.remove-row-btn').addEventListener('click', ()=>{
    if (tbody.children.length > 1) tbody.removeChild(tr);
    else alert('You must have at least one animal entry.');
  });
}
// top buttons
document.getElementById('add-row-btn').addEventListener('click', ()=>addAnimalRow());
document.getElementById('addAnimalTop').addEventListener('click', ()=>addAnimalRow());
// initial table render
applyMode('new'); // default new mode, with one row

/* ===== Toast ===== */
function toast(msg){
  const n = document.getElementById("profile-notification");
  n.textContent = msg; n.style.display = "block"; n.style.opacity = "1";
  setTimeout(()=>{ n.style.opacity = "0"; setTimeout(()=>{ n.style.display = "none"; n.style.opacity = "1"; }, 350); }, 2200);
}

/* ===== Gather form ===== */
function buildFullName(first, middle, last){
  return [first?.trim()||'', middle?.trim()||'', last?.trim()||''].filter(Boolean).join(' ');
}
function gatherAnimals(){
  const rows = Array.from(document.querySelectorAll('#animals-tbody tr'));
  return rows.map(r=>{
    const inputs = r.querySelectorAll('input, select');
    const isRenewal = PERMIT_TYPE==='renewal';
    return {
      commonName: inputs[0].value.trim(),
      scientificName: inputs[1].value.trim(),
      quantity: inputs[2].value.trim(),
      ...(isRenewal ? { remarks: inputs[3].value } : {})
    };
  }).filter(x=>x.commonName || x.scientificName || x.quantity);
}

function gatherForm(){
  const isRenewal = PERMIT_TYPE === 'renewal';

  // Read names from the correct set of inputs depending on mode
  let first, middle, last;
  if (isRenewal) {
    first  = document.getElementById('renewal-first-name')?.value.trim() || '';
    middle = document.getElementById('renewal-middle-name')?.value.trim() || '';
    last   = document.getElementById('renewal-last-name')?.value.trim() || '';
  } else {
    first  = document.getElementById('first-name')?.value.trim() || '';
    middle = document.getElementById('middle-name')?.value.trim() || '';
    last   = document.getElementById('last-name')?.value.trim() || '';
  }

  // compute for Word export only
  const applicant_name = buildFullName(first, middle, last);

  return {
    permit_type: PERMIT_TYPE,
    first_name: first,
    middle_name: middle,
    last_name: last,
    applicant_name,

    residence_address: document.getElementById('residence-address')?.value.trim() || '',
    telephone_number: document.getElementById('telephone-number')?.value.trim() || '',
    establishment_name: document.getElementById('establishment-name')?.value.trim() || '',
    establishment_address: document.getElementById('establishment-address')?.value.trim() || '',
    establishment_telephone: document.getElementById('establishment-telephone')?.value.trim() || '',

    categories: {
      zoo: !!document.getElementById('zoo')?.checked,
      botanical_garden: !!document.getElementById('botanical-garden')?.checked,
      private_collection: !!document.getElementById('private-collection')?.checked
    },

    // renewal-only
    wfp_number: isRenewal ? (document.getElementById('wfp-number')?.value.trim() || '') : '',
    issue_date: isRenewal ? (document.getElementById('issue-date')?.value || '') : '',

    animals: gatherAnimals(),
    postal_address: document.getElementById('postal-address')?.value.trim() || '',
  };
}

/* ===== Validations ===== */
function validateForm(d){
  if (d.permit_type === 'new'){
    if (!d.first_name || !d.last_name) throw new Error('First and Last name are required (New).');
  } else {
    if (!d.first_name || !d.last_name) throw new Error('First and Last name are required (Renewal).');
    if (!d.wfp_number) throw new Error('Original WFP No. is required (Renewal).');
    if (!d.issue_date) throw new Error('Issued on (date) is required (Renewal).');
  }
  if (!d.residence_address) throw new Error('Residence Address is required');
  if (!d.establishment_name) throw new Error('Name of Establishment is required');
  if (!d.establishment_address) throw new Error('Address of Establishment is required');
  if (!d.animals.length) throw new Error('Please add at least one animal entry');
}

/* ===== Submit flow ===== */
const confirmModal = document.getElementById("confirmModal");
const btnSubmit = document.getElementById("submitBtn");
const btnOk = document.getElementById("btnOkConfirm");
const btnCancel = document.getElementById("btnCancelConfirm");
const loading = document.getElementById("loadingIndicator");

btnSubmit.addEventListener("click", ()=>{ confirmModal.style.display = "flex"; });
btnCancel.addEventListener("click", ()=>{ confirmModal.style.display = "none"; });

btnOk.addEventListener("click", async ()=>{
  confirmModal.style.display = "none";
  loading.style.display = "flex";
  try{
    const d = gatherForm();
    validateForm(d);

    const fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('permit_type', d.permit_type);
    // names (ONLY these three are posted)
    fd.append('first_name', d.first_name);
    fd.append('middle_name', d.middle_name);
    fd.append('last_name', d.last_name);
    // common fields
    fd.append('residence_address', d.residence_address);
    fd.append('telephone_number', d.telephone_number);
    fd.append('establishment_name', d.establishment_name);
    fd.append('establishment_address', d.establishment_address);
    fd.append('establishment_telephone', d.establishment_telephone);
    fd.append('zoo', d.categories.zoo ? '1' : '0');
    fd.append('botanical_garden', d.categories.botanical_garden ? '1' : '0');
    fd.append('private_collection', d.categories.private_collection ? '1' : '0');
    fd.append('animals_json', JSON.stringify(d.animals||[]));
    fd.append('postal_address', d.postal_address);
    // renewal-only
    if (d.permit_type==='renewal'){
      fd.append('wfp_number', d.wfp_number);
      fd.append('issue_date', d.issue_date);
    }

    // === SIGNATURE: include dataURL ===
    if (!signaturePad.isEmpty()) {
      fd.append('signature_data', signaturePad.toDataURL('image/png'));
    } else {
      const img = document.getElementById('signature-image');
      if (img && img.src && img.src.startsWith('data:image/')) {
        fd.append('signature_data', img.src);
      }
    }
    // === end signature block ===

    const res = await fetch(SAVE_URL, { method:'POST', body: fd, credentials:'include' });
    const json = await res.json().catch(()=>({ok:false,error:'Bad JSON'}));
    if(!res.ok || !json.ok) throw new Error(json.error || `HTTP ${res.status}`);

    toast("Application submitted. We’ll notify you once reviewed.");
    resetForm();
  }catch(e){
    console.error(e);
    toast(e?.message || "Submission failed. Please try again.");
  }finally{
    loading.style.display = "none";
  }
});

/* ===== Reset ===== */
function resetForm(){
  document.querySelectorAll("input[type='text'], input[type='number'], input[type='date'], textarea").forEach(inp=> inp.value='');
  ['zoo','botanical-garden','private-collection'].forEach(id=> document.getElementById(id).checked=false);
  tbody.innerHTML = '';
  addAnimalRow(PERMIT_TYPE==='renewal');
  signaturePad.clear(); const img = document.getElementById('signature-image'); img.src=""; img.classList.add('hidden');

  // back to NEW
  document.querySelectorAll('#permitToggle .toggle-btn').forEach(b=>b.classList.remove('active'));
  document.querySelector('#permitToggle .toggle-btn[data-type="new"]').classList.add('active');
  PERMIT_TYPE = 'new';
  applyMode('new');
}

/* ===== Word Download (New vs Renewal) ===== */
document.getElementById('downloadBtn').addEventListener('click', function(){
  document.getElementById('loadingIndicator').style.display='flex';
  const d = gatherForm();

  // date format helper
  const formattedIssueDate = (()=>{
    if (!d.issue_date) return '';
    const dateObj = new Date(d.issue_date);
    return dateObj.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
  })();

  const animalsRowsNew = (d.animals||[]).map(a=>`
    <tr><td>${a.commonName||''}</td><td>${a.scientificName||''}</td><td>${a.quantity||''}</td></tr>
  `).join('') || `<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>`;

  const animalsRowsRenewal = (d.animals||[]).map(a=>`
    <tr><td>${a.commonName||''}</td><td>${a.scientificName||''}</td><td>${a.quantity||''}</td><td>${a.remarks||''}</td></tr>
  `).join('') || `
    <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
    <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
  `;

  const categoriesLine = `
    <span class="checkbox">${d.categories.zoo ? '☒' : '☐'}</span> Zoo
    <span class="checkbox">${d.categories.botanical_garden ? '☒' : '☐'}</span> Botanical Garden
    <span class="checkbox">${d.categories.private_collection ? '☒' : '☐'}</span> Private Collection
  `;

  const commonHead = `
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title>Wildlife Registration Application</title>
<style>
  body,div,p{line-height:1.6;font-family:Arial;font-size:11pt;margin:0;padding:0}
  .header{text-align:center;margin-bottom:20px}
  .underline{display:inline-block;border-bottom:1px solid #000;min-width:300px;padding:0 5px;margin:0 5px}
  .underline-small{display:inline-block;border-bottom:1px solid #000;min-width:150px;padding:0 5px;margin:0 5px}
  table{width:100%;border-collapse:collapse;margin:15pt 0}
  table,th,td{border:1px solid #000}
  th,td{padding:8px;text-align:left}
  .signature-line{margin-top:40px;border-top:1px solid #000;width:50%;padding-top:3pt}
  .bold{font-weight:bold}
  .checkbox{font-family:"Wingdings 2";font-size:14pt;vertical-align:middle}
  .indent{margin-left:40px}
  .info-line{margin:12pt 0}
</style>
<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
</head>
<body>
  <div class="header">
    <p class="bold">Republic of the Philippines</p>
    <p class="bold">Department of Environment and Natural Resources</p>
    <p class="bold">REGION 7</p>
    <p>______</p>
    <p>Date</p>
  </div>
`;

  const htmlNew = `
  ${commonHead}
  <p style="text-align:center;margin-bottom:20px" class="bold">APPLICATION FOR: CERTIFICATE OF WILDLIFE REGISTRATION (NEW)</p>
  <p style="margin-bottom:15px;">${categoriesLine}</p>
  <p class="info-line">Sir/Madam:</p>
  <p class="info-line">
    I <span class="underline">${d.applicant_name||''}</span> with address at <span class="underline">${d.residence_address||''}</span>
  </p>
  <p class="info-line indent">
    and Tel. no. <span class="underline-small">${d.telephone_number||''}</span> have the honor to apply for the registration of <span class="underline">${d.establishment_name||''}</span>
  </p>
  <p class="info-line indent">
    located at <span class="underline">${d.establishment_address||''}</span> with Tel. no. <span class="underline-small">${d.establishment_telephone||''}</span> and registration of animals/stocks maintained
  </p>
  <p class="info-line">there at which are as follows:</p>
  <table>
    <tr><th>Common Name</th><th>Scientific Name</th><th>Quantity</th></tr>
    ${animalsRowsNew}
  </table>
  <div style="margin-top:40px;">
    <div class="signature-line"></div>
    <p>Signature of Applicant</p>
  </div>
  <p class="info-line">Postal Address: <span class="underline">${d.postal_address||''}</span></p>
</body></html>`;

  const htmlRenewal = `
  ${commonHead}
  <p style="text-align:center;margin-bottom:20px" class="bold">APPLICATION FOR: RENEWAL CERTIFICATE OF WILDLIFE REGISTRATION</p>
  <p style="margin-bottom:15px;">${categoriesLine}</p>
  <p class="info-line">The Regional Executive Director</p>
  <p class="info-line">DENR Region 7</p>
  <p class="info-line">National Government Center,</p>
  <p class="info-line">Sudlon, Lahug, Cebu City</p>
  <p class="info-line">Sir/Madam:</p>
  <p class="info-line">
    I, <span class="underline">${d.applicant_name||''}</span> with address at <span class="underline">${d.residence_address||''}</span>
  </p>
  <p class="info-line indent">
    and Tel. no. <span class="underline-small">${d.telephone_number||''}</span>, have the honor to request for the
  </p>
  <p class="info-line indent">
    renewal of my Certificate of Wildlife Registration of <span class="underline">${d.establishment_name||''}</span>
  </p>
  <p class="info-line indent">
    located at <span class="underline">${d.establishment_address||''}</span> with Tel. no. <span class="underline-small">${d.establishment_telephone||''}</span>
  </p>
  <p class="info-line indent">
    and Original WFP No. <span class="underline-small">${d.wfp_number||''}</span> issued on <span class="underline-small">${formattedIssueDate||''}</span>, and
  </p>
  <p class="info-line">registration of animals/stocks maintained which are as follows:</p>
  <table>
    <tr><th>Common Name</th><th>Scientific Name</th><th>Quantity</th><th>Remarks (Alive/Deceased)</th></tr>
    ${animalsRowsRenewal}
  </table>
  <p class="info-line">
    I understand that this application for renewal does not by itself grant the right to continue possession of any wild animals until the corresponding Renewal Certificate of Registration is issued.
  </p>
  <div style="margin-top:40px;">
    <div class="signature-line"></div>
    <p>Signature of Applicant</p>
  </div>
  <p class="info-line">Postal Address: <span class="underline">${d.postal_address||''}</span></p>
</body></html>`;

  const htmlContent = d.permit_type === 'renewal' ? htmlRenewal : htmlNew;

  const blob = new Blob([htmlContent], { type:'application/msword' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = d.permit_type === 'renewal'
    ? 'Wildlife_Renewal_Application.doc'
    : 'Wildlife_Registration_Application.doc';
  document.body.appendChild(link); link.click(); document.body.removeChild(link);

  setTimeout(()=>{ document.getElementById('loadingIndicator').style.display='none'; }, 800);
});
</script>
</body>
</html>
