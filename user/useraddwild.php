<?php
<<<<<<< HEAD
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
    <title>Request Seedlings</title>
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

        /* Sample Letter Button */
        .sample-letter-btn {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 20px;
        }

        .download-sample {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .download-sample:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }


        .name-fields {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            MARGIN-TOP: -1%;
            margin-bottom: 10px;
            padding: 15px;
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
            transition: all 0.3s ease;
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
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Report Incident</span>
                    </a>
                    <a href="useraddseed.php" class="dropdown-item ">
                        <i class="fas fa-seedling"></i>
                        <span>Request Seedlings</span>
                    </a>
                    <a href="useraddwild.php" class="dropdown-item active-page">
                        <i class="fas fa-paw"></i>
                        <span>Wildlife Permit</span>
                    </a>
                    <a href="useraddtreecut.php" class="dropdown-item">
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
                                <div class="notification-title">Seedling Request Status</div>
                                <div class="notification-message">Your seedling request has been approved.</div>
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
            <a href="usereditwild.php" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="uservieww.php" class="btn btn-outline">
                <i class="fas fa-eye"></i> View
            </a>
        </div>

        <div class="requirements-form">
            <div class="form-header">
                <h2>Wildlife Registration Permit - Requirements</h2>
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

                <div class="sample-letter-btn">
                    <button class="download-sample" id="downloadSample">
                        <a href="http://localhost/denr/superadmin/user/form_wild.docx" style="color: white; text-decoration: none; font-weight: 100;" class="download-btn" id="downloadApplicationForm" download="Wildlife_Registration_Application_Form.docx">
                            <i class="fas fa-file-word"></i> Download Application Form (DOCX)
                        </a>
                    </button>
                </div>

                <div class="requirements-list">
                    <!-- Requirement 1 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                Application Form filed-up with 2 copies of photo of the applicant/s
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-1" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Filled Form
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
                                SEC/CDA Registration (Security and Exchange Commission/Cooperative Development Authority) DTI, if for commercial purposes
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-2" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload SEC/CDA/DTI Registration
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
                                Proof of Scientific Expertise (Veterinary Certificate)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-3" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Veterinary Certificate
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
                                Financial Plan for Breeding (Financial/Bank Statement)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-4" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Financial/Bank Statement
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
                                Proposed Facility Design (Photo of Facility)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-5" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Photo of Facility
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
                                Prior Clearance of affected communities (Municipal or Barangay Clearance)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-6" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Municipal/Barangay Clearance
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
                                Vicinity Map of the area/site (Ex. Google map Sketch map)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-7" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Vicinity Map
                                </label>
                                <input type="file" id="file-7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-7"></div>
                        </div>
                    </div>
                    <!-- Requirement 8 (two files) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">8</span>
                                Legal Acquisition of Wildlife:
                                <ul style="margin-left:20px;">
                                    <li>Proof of Purchase (Official Receipt/Deed of Sale or Captive Bred Certificate)</li>
                                    <li>Deed of Donation with Notary</li>
                                </ul>
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-8a" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Proof of Purchase
                                </label>
                                <input type="file" id="file-8a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-8a"></div>
                            <div class="file-input-container">
                                <label for="file-8b" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Deed of Donation
                                </label>
                                <input type="file" id="file-8b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-8b"></div>
                        </div>
                    </div>
                    <!-- Requirement 9 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">9</span>
                                Inspection Report conducted by concerned CENRO
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-9" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Inspection Report
                                </label>
                                <input type="file" id="file-9" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-9"></div>
                        </div>
                    </div>

                    <!-- Information about the letter -->
                    <div class="fee-info">
                        <p><strong>Application and Processing Fee:</strong> ₱500.00</p>
                        <p><strong>Permit Fee:</strong> ₱2,500.00</p>
                        <p><strong>Total Fee:</strong> ₱3,000.00</p>
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
            <p>Are you sure you want to submit this seedling request?</p>
            <button id="confirmSubmitBtn" class="btn btn-primary" style="margin:10px 10px 0 0;">Yes, Submit</button>
            <button id="cancelSubmitBtn" class="btn btn-outline">Cancel</button>
        </div>
    </div>

    <!-- Sample Letter Content (hidden) -->
    <div id="sampleLetterContent" style="display: none;">
        <p style="text-align: right;">[Your Address]<br>[City, Province]<br>[Date]</p>

        <p style="text-align: left; margin-top: 30px;">
            <strong>CENRO Argao<br>

        </p>

        <p style="margin-top: 30px;"><strong>Subject: Request for Seedlings</strong></p>

        <p style="margin-top: 20px; text-align: justify;">
            Dear Sir/Madam,
        </p>

        <p style="text-align: justify; text-indent: 50px;">
            I am writing to formally request [number] seedlings of [seedling name/species] for [purpose - e.g., reforestation project, backyard planting, etc.]. The seedlings will be planted at [location/address where seedlings will be planted].
        </p>

        <p style="text-align: justify; text-indent: 50px;">
            The purpose of this request is [explain purpose in more detail]. This initiative is part of [explain any project or personal initiative if applicable].
        </p>

        <p style="text-align: justify; text-indent: 50px;">
            I would be grateful if you could approve this request at your earliest convenience. Please let me know if you require any additional information or documentation to process this request.
        </p>

        <p style="margin-top: 30px;">
            Thank you for your time and consideration.
        </p>

        <p style="margin-top: 50px;">
            Sincerely,<br><br>
            _________________________<br>
            [Your Full Name]<br>
            [Your Contact Information]<br>
            [Your Organization, if applicable]
        </p>
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

            // New file input and preview logic for multiple files
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
                    id: 'file-7',
                    uploaded: 'uploaded-files-7'
                },
                {
                    id: 'file-8a',
                    uploaded: 'uploaded-files-8a'
                },
                {
                    id: 'file-8b',
                    uploaded: 'uploaded-files-8b'
                },
                {
                    id: 'file-9',
                    uploaded: 'uploaded-files-9'
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
                // Do not show the selected file at all
            }

            // function addUploadedFile(file) {
            //     uploadedFilesContainer.innerHTML = '';
            //     let fileIcon;
            //     if (file.type.includes('pdf')) {
            //         fileIcon = '<i class="fas fa-file-pdf file-icon"></i>';
            //     } else if (file.type.includes('image')) {
            //         fileIcon = '<i class="fas fa-file-image file-icon"></i>';
            //     } else if (file.type.includes('word') || file.type.includes('document')) {
            //         fileIcon = '<i class="fas fa-file-word file-icon"></i>';
            //     } else {
            //         fileIcon = '<i class="fas fa-file file-icon"></i>';
            //     }
            //     const fileItem = document.createElement('div');
            //     fileItem.className = 'file-item';
            //     fileItem.innerHTML = `
            //         <div class="file-info">
            //             ${fileIcon}
            //             <span>${file.name}</span>
            //         </div>
            //         <div class="file-actions">
            //             <button class="file-action-btn view-file" title="View"><i class="fas fa-eye"></i></button>
            //             <button class="file-action-btn delete-file" title="Delete"><i class="fas fa-trash"></i></button>
            //         </div>
            //     `;
            //     uploadedFilesContainer.appendChild(fileItem);

            //     // View file
            //     fileItem.querySelector('.view-file').addEventListener('click', function() {
            //         previewFile(file);
            //     });
            //     // Delete file
            //     fileItem.querySelector('.delete-file').addEventListener('click', function() {
            //         uploadedFilesContainer.innerHTML = '';
            //         fileInput.value = '';
            //         fileInput.parentElement.querySelector('.file-name').textContent = 'No file chosen';
            //         selectedFile = null;
            //     });
            // }

            // File preview functionality
            const modal = document.getElementById('filePreviewModal');
            const modalFrame = document.getElementById('filePreviewFrame');
            const closeFilePreviewModal = document.getElementById('closeFilePreviewModal');

            function previewFile(file) {
                const modalFrame = document.getElementById('filePreviewFrame');
                if (!modalFrame) return;
                // Always clear both src and srcdoc before setting
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
                        // For doc/docx, show a download link
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
                    // For doc/docx, just show download link
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
            const successModal = document.getElementById('successModal');
            const closeSuccessModal = document.getElementById('closeSuccessModal');
            const okSuccessBtn = document.getElementById('okSuccessBtn');

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
                        alert('Please upload your application form.');
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
                    for (let i = 1; i <= 7; i++) {
                        if (selectedFiles[`file-${i}`]) {
                            formData.append(`file_${i}`, selectedFiles[`file-${i}`]);
                        }
                    }
                    if (selectedFiles['file-8a']) {
                        formData.append('file_8a', selectedFiles['file-8a']);
                    }
                    if (selectedFiles['file-8b']) {
                        formData.append('file_8b', selectedFiles['file-8b']);
                    }
                    if (selectedFiles['file-9']) {
                        formData.append('file_9', selectedFiles['file-9']);
                    }

                    fetch('../backend/users/addwildlifepermit.php', {
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
                                // Show notification using the provided bar
                                showProfileNotification('Wildlife permit application submitted successfully!');
                            } else {
                                alert(data.errors ? data.errors.join('\n') : 'Failed to submit request.');
                            }
                        })
                        .catch(() => {
                            alert('Network error.');
                        });
                });
            }


            // Success notification logic using #profile-notification
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

            // Download sample letter button
            // const downloadSampleBtn = document.getElementById('downloadSample');
            // if (downloadSampleBtn) {
            //     downloadSampleBtn.addEventListener('click', function() {
            //         // Create a Blob with the sample letter content
            //         const sampleLetterContent = document.getElementById('sampleLetterContent').innerHTML;
            //         const blob = new Blob([`
            //             <!DOCTYPE html>
            //             <html>
            //             <head>
            //                 <meta charset="UTF-8">
            //                 <title>Seedling Request Letter</title>
            //                 <style>
            //                     body {
            //                         font-family: Arial, sans-serif;
            //                         line-height: 1.6;
            //                         margin: 50px;
            //                     }
            //                 </style>
            //             </head>
            //             <body>
            //                 ${sampleLetterContent}
            //             </body>
            //             </html>
            //         `], {
            //             type: 'text/html'
            //         });

            //         // Create a download link
            //         const url = URL.createObjectURL(blob);
            //         const a = document.createElement('a');
            //         a.href = url;
            //         a.download = 'ApplicationForm.doc';
            //         document.body.appendChild(a);
            //         a.click();

            //         // Clean up
            //         setTimeout(() => {
            //             document.body.removeChild(a);
            //             window.URL.revokeObjectURL(url);
            //         }, 100);
            //     });
            // }

            // Add files button (demo functionality)
            const addFilesBtn = document.getElementById('addFilesBtn');
            if (addFilesBtn) {
                addFilesBtn.addEventListener('click', function() {
                    // This would be more sophisticated in a real app
                    alert('In a real application, this would open a dialog to add multiple files at once.');
                });
            }

            // Initialize existing file items with event listeners
            document.querySelectorAll('.file-item .view-file').forEach(btn => {
                btn.addEventListener('click', function() {
                    const fileName = this.getAttribute('data-file');
                    // For demo purposes, we'll just show the file name
                    document.getElementById('modal-title').textContent = `Preview: ${fileName}`;
                    modalFrame.srcdoc = `
                        <html>
                            <head>
                                <style>
                                    body { 
                                        font-family: Arial, sans-serif; 
                                        display: flex; 
                                        justify-content: center; 
                                        align-items: center; 
                                        height: 100vh; 
                                        margin: 0; 
                                        background-color: #f5f5f5;
                                    }
                                    .preview-content {
                                        text-align: center;
                                        padding: 20px;
                                    }
                                    .file-icon {
                                        font-size: 48px;
                                        color: #2b6625;
                                        margin-bottom: 20px;
                                    }
                                </style>
                            </head>
                            <body>
                                <div class="preview-content">
                                    <div class="file-icon">
                                        <i class="fas fa-file-word"></i>
                                    </div>
                                    <h2>${fileName}</h2>
                                    <p>This is a preview of the uploaded file.</p>
                                    <p>In a real application, the actual file content would be displayed here.</p>
                                </div>
                            </body>
                        </html>
                    `;
                    if (modal) modal.style.display = "block";
                });
            });

            // Initialize existing file items with delete functionality
            document.querySelectorAll('.file-item .fa-trash').forEach(btn => {
                btn.addEventListener('click', function() {
                    const fileItem = this.closest('.file-item');
                    fileItem.remove();
                });
            });
        });
    </script>
</body>

</html>
>>>>>>> origin/main
