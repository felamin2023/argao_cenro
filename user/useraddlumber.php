<?php
<<<<<<< HEAD
// useraddlumber.php — New & Renewal in one UI (headers + main-container preserved)
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
<title>Lumber Dealer Permit</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.5.3/signature_pad.min.js"></script>

<style>
  :root {
    --primary-color:#2b6625; --primary-dark:#1e4a1a; --white:#fff; --light-gray:#f5f5f5;
    --border-radius:8px; --box-shadow:0 4px 12px rgba(0,0,0,.1); --transition:all .2s ease;
    --medium-gray:#ddd;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background: #f9f9f9 url('images/Lumber.jpg')center / cover no-repeat fixed;color:#333;line-height:1.6;padding-top:120px}

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

  /* Permit type selector */
  .permit-type-selector{display:flex;gap:10px;margin:20px 30px 0}
  .permit-type-btn{padding:12px 18px;border:2px solid var(--primary-color);background:#fff;color:var(--primary-color);border-radius:6px;font-weight:700;cursor:pointer;transition:var(--transition)}
  .permit-type-btn.active,.permit-type-btn:hover{background:var(--primary-color);color:#fff}

  /* Fields */
  .form-section{margin-bottom:25px}
  .form-section h2{background:var(--primary-color);color:#fff;padding:10px 15px;margin-bottom:15px;border-radius:4px;font-size:18px}
  .form-group{margin-bottom:15px}
  .form-group label{display:block;margin-bottom:5px;font-weight:600;color:var(--primary-color)}
  .form-group input,.form-group textarea,.form-group select{width:100%;padding:10px 15px;border:1px solid #ddd;border-radius:4px;font-size:15px;transition:border-color .3s;min-height:40px}
  .form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:var(--primary-color);outline:none;box-shadow:0 0 0 2px rgba(43,102,37,.2)}
  .form-row{display:flex;gap:20px;margin-bottom:15px}
  .form-row .form-group{flex:1}
  .required::after{content:" *";color:#ff4757}

  /* Suppliers (shared look) */
  #suppliers-wrap,#r-suppliers-wrap{display:flex;flex-direction:column;gap:12px}
  .supplier-row{padding:12px;border:1px solid #eee;border-radius:6px;background:#fafafa}
  .remove-btn{background:#ff4757;color:#fff;border:none;padding:10px 14px;border-radius:4px;cursor:pointer;font-size:14px;height:40px}

  /* Declaration + signature (preview only) */
  .declaration{background:#f9f9f9;padding:20px;border-radius:4px;border-left:4px solid var(--primary-color);margin-bottom:25px}
  .declaration-input{border:none;border-bottom:1px solid #999;padding:0 5px;width:300px;display:inline-block;background:transparent}
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
          <a href="useraddtreecut.php" class="dropdown-item"><i class="fas fa-tree"></i><span>Tree Cutting Permit</span></a>
          <a href="useraddlumber.php" class="dropdown-item active-page"><i class="fas fa-boxes"></i><span>Lumber Dealers Permit</span></a>
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
        <h2 id="formTitle">Lumber Dealer Permit – Application (New)</h2>
      </div>

      <!-- mode toggle -->
      <div class="permit-type-selector">
        <button class="permit-type-btn active" data-type="dealer_new" type="button">New Lumber Dealer</button>
        <button class="permit-type-btn" data-type="dealer_renewal" type="button">Lumber Dealer Renewal</button>
      </div>

      <div class="form-body">
        <!-- ================= NEW ================= -->
        <div id="section-new">
          <div class="form-section">
            <h2>APPLICANT INFORMATION</h2>
            <div class="form-row">
              <div class="form-group">
                <label for="first-name" class="required">First Name:</label>
                <input type="text" id="first-name" placeholder="First name">
              </div>
              <div class="form-group">
                <label for="middle-name">Middle Name:</label>
                <input type="text" id="middle-name" placeholder="Middle name">
              </div>
              <div class="form-group">
                <label for="last-name" class="required">Last Name:</label>
                <input type="text" id="last-name" placeholder="Last name">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="sitio-street" class="required">Street Name/Sitio:</label>
                <input type="text" id="sitio-street" placeholder="Street / Sitio">
              </div>
              <div class="form-group">
                <label for="province" class="required">Province:</label>
                <input type="text" id="province" placeholder="Province">
              </div>
              <div class="form-group">
                <label for="contact-number" class="required">Contact Number:</label>
                <input type="text" id="contact-number" placeholder="09XX…">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="applicant-age" class="required">Age:</label>
                <input type="number" id="applicant-age" min="18" placeholder="18+">
              </div>
              <div class="form-group">
                <label for="business-address" class="required">Business Address:</label>
                <input type="text" id="business-address" placeholder="Full business address">
              </div>
              <div class="form-group">
                <label class="required">Government Employee:</label>
                <div style="display:flex;gap:20px;align-items:center">
                  <label style="display:flex;gap:8px;align-items:center"><input type="radio" name="govt-employee" value="no" checked> No</label>
                  <label style="display:flex;gap:8px;align-items:center"><input type="radio" name="govt-employee" value="yes"> Yes</label>
                </div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h2>BUSINESS INFORMATION</h2>
            <div class="form-group">
              <label for="operation-place" class="required">Proposed Place of Operation:</label>
              <input type="text" id="operation-place" placeholder="Full address of operation place">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="annual-volume" class="required">Expected Gross Annual Volume of Business:</label>
                <input type="text" id="annual-volume" placeholder="e.g., 1000 board feet">
              </div>
              <div class="form-group">
                <label for="annual-worth" class="required">Worth:</label>
                <input type="text" id="annual-worth" placeholder="e.g., ₱500,000">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="employees-count" class="required">Total Number of Employees:</label>
                <input type="number" id="employees-count" min="0">
              </div>
              <div class="form-group">
                <label for="dependents-count" class="required">Total Number of Dependents:</label>
                <input type="number" id="dependents-count" min="0">
              </div>
            </div>
          </div>

          <div class="form-section">
            <h2>SUPPLIERS INFORMATION</h2>
            <div id="suppliers-wrap">
              <div class="supplier-row">
                <div class="form-row" style="margin-bottom:0">
                  <div class="form-group">
                    <label>Supplier Name/Company</label>
                    <input type="text" class="supplier-name" placeholder="Supplier name">
                  </div>
                  <div class="form-group">
                    <label>Volume</label>
                    <input type="text" class="supplier-volume" placeholder="Volume">
                  </div>
                  <div class="form-group" style="flex:0 0 auto;display:flex;align-items:flex-end">
                    <button type="button" class="remove-btn">Remove</button>
                  </div>
                </div>
              </div>
            </div>
            <button type="button" class="btn btn-primary" id="add-supplier-row"><i class="fas fa-plus-circle"></i>Add Supplier</button>
          </div>

          <div class="form-section">
            <h2>MARKET INFORMATION</h2>
            <div class="form-group">
              <label for="intended-market" class="required">Intended Market (Barangays and Municipalities to be served):</label>
              <textarea id="intended-market" rows="3" placeholder="List barangays and municipalities"></textarea>
            </div>
            <div class="form-group">
              <label for="experience" class="required">Experience as a Lumber Dealer:</label>
              <textarea id="experience" rows="3" placeholder="Describe your experience in the lumber business"></textarea>
            </div>
          </div>
        </div><!-- /#section-new -->

        <!-- ================= RENEWAL ================= -->
        <div id="section-renewal" style="display:none">
          <div class="form-section">
  <h2>APPLICANT INFORMATION (Renewal)</h2>

  <div class="form-row">
    <div class="form-group">
      <label for="r-first-name" class="required">First Name:</label>
      <input type="text" id="r-first-name" placeholder="First name">
    </div>
    <div class="form-group">
      <label for="r-middle-name">Middle Name:</label>
      <input type="text" id="r-middle-name" placeholder="Middle name">
    </div>
    <div class="form-group">
      <label for="r-last-name" class="required">Last Name:</label>
      <input type="text" id="r-last-name" placeholder="Last name">
    </div>
  </div>

  <!-- NEW: same set you requested for Renewal -->
  <div class="form-row">
    <div class="form-group">
      <label for="r-sitio-street" class="required">Street Name/Sitio:</label>
      <input type="text" id="r-sitio-street" placeholder="Street / Sitio">
    </div>
    <div class="form-group">
      <label for="r-province" class="required">Province:</label>
      <input type="text" id="r-province" placeholder="Province">
    </div>
    <div class="form-group">
      <label for="r-contact-number" class="required">Contact Number:</label>
      <input type="text" id="r-contact-number" placeholder="09XX…">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="r-applicant-age" class="required">Age:</label>
      <input type="number" id="r-applicant-age" min="18">
    </div>
    <div class="form-group">
      <label for="r-business-address" class="required">Business Address:</label>
      <input type="text" id="r-business-address" placeholder="Full business address">
    </div>
    <div class="form-group">
      <label class="required">Government Employee:</label>
      <div style="display:flex;gap:20px;align-items:center">
        <label style="display:flex;gap:8px;align-items:center"><input type="radio" name="r-govt-employee" value="no" checked> No</label>
        <label style="display:flex;gap:8px;align-items:center"><input type="radio" name="r-govt-employee" value="yes"> Yes</label>
      </div>
    </div>
  </div>
</div>


          <div class="form-section">
            <h2>BUSINESS INFORMATION</h2>
            <div class="form-group">
              <label for="r-operation-place" class="required">Place of Operation:</label>
              <input type="text" id="r-operation-place" placeholder="Full address of operation place">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="r-annual-volume" class="required">Expected Gross Annual Volume of Business:</label>
                <input type="text" id="r-annual-volume" placeholder="e.g., 1000 board feet">
              </div>
              <div class="form-group">
                <label for="r-annual-worth" class="required">Value:</label>
                <input type="text" id="r-annual-worth" placeholder="e.g., ₱500,000">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="r-employees-count" class="required">Total Number of Employees:</label>
                <input type="number" id="r-employees-count" min="0">
              </div>
              <div class="form-group">
                <label for="r-dependents-count" class="required">Total Number of Dependents:</label>
                <input type="number" id="r-dependents-count" min="0">
              </div>
            </div>
          </div>

          <div class="form-section">
            <h2>SUPPLIERS INFORMATION</h2>
            <div id="r-suppliers-wrap">
              <div class="supplier-row">
                <div class="form-row" style="margin-bottom:0">
                  <div class="form-group">
                    <label>Supplier Name/Company</label>
                    <input type="text" class="supplier-name" placeholder="Supplier name">
                  </div>
                  <div class="form-group">
                    <label>Volume</label>
                    <input type="text" class="supplier-volume" placeholder="Volume">
                  </div>
                  <div class="form-group" style="flex:0 0 auto;display:flex;align-items:flex-end">
                    <button type="button" class="remove-btn">Remove</button>
                  </div>
                </div>
              </div>
            </div>
            <button type="button" class="btn btn-primary" id="r-add-supplier-row"><i class="fas fa-plus-circle"></i>Add Supplier</button>
          </div>

          <div class="form-section">
            <h2>BUSINESS DETAILS</h2>
            <div class="form-group">
              <label for="r-intended-market" class="required">Selling Products To:</label>
              <textarea id="r-intended-market" rows="3" placeholder="List adjacent barangays and municipalities"></textarea>
            </div>
            <div class="form-group">
              <label for="r-experience" class="required">Experience as a Lumber Dealer:</label>
              <textarea id="r-experience" rows="3" placeholder="Describe your experience in the lumber business"></textarea>
            </div>

            <div class="form-group">
              <label for="r-prev-certificate">Previous Certificate of Registration No.:</label>
              <input type="text" id="r-prev-certificate" placeholder="Certificate number">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="r-issued-date">Issued On:</label>
                <input type="date" id="r-issued-date">
              </div>
              <div class="form-group">
                <label for="r-expiry-date">Expires On:</label>
                <input type="date" id="r-expiry-date">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="r-cr-license">C.R. License No.:</label>
                <input type="text" id="r-cr-license" placeholder="License number">
              </div>
              <div class="form-group">
                <label for="r-sawmill-permit">Sawmill Permit No.:</label>
                <input type="text" id="r-sawmill-permit" placeholder="Permit number">
              </div>
            </div>
            
          </div>
        </div><!-- /#section-renewal -->

        <!-- ================= Declaration (shared) ================= -->
        <div class="form-section">
          <h2>DECLARATION</h2>
          <div class="declaration">
            <p>I will fully comply with applicable laws and the rules and regulations of the Forest Management Bureau.</p>
            <p>I understand that false statements or omissions may result in disapproval, cancellation of registration, forfeiture of bond, and/or criminal liability.</p>
            <p>
              I, <input type="text" id="declaration-name" class="declaration-input" placeholder="Enter your full name">,
              after being sworn to upon my oath, depose and say that I have read the foregoing application and that every statement therein is true and correct to the best of my knowledge and belief.
            </p>
            <div style="margin-top:30px">
              <label>Signature of Applicant (optional preview):</label>
              <div class="signature-pad-container"><canvas id="signature-pad"></canvas></div>
              <div class="signature-actions">
                <button type="button" class="signature-btn clear-signature" id="clear-signature"><i class="fa-solid fa-eraser"></i> Clear</button>
                <button type="button" class="signature-btn save-signature" id="save-signature"><i class="fa-solid fa-floppy-disk"></i> Save Signature</button>
              </div>
              <div class="signature-preview"><img id="signature-image" class="hidden" alt="Signature Preview" /></div>
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
      <div class="dlg-b">Please confirm you want to submit this Lumber Dealer application.</div>
      <div class="dlg-f">
        <button id="btnCancelConfirm" class="btn btn-outline" type="button">Cancel</button>
        <button id="btnOkConfirm" class="btn btn-primary" type="button">Yes, submit</button>
      </div>
    </div>
  </div>

<script>
const CSRF = "<?=htmlspecialchars($CSRF, ENT_QUOTES)?>";
const SAVE_URL = new URL('../backend/users/lumber/save_lumber.php', window.location.href).toString();

/* ===== Mode toggle ===== */
let permitType = 'dealer_new';
const titleEl = document.getElementById('formTitle');
const btns = document.querySelectorAll('.permit-type-btn');
const secNew = document.getElementById('section-new');
const secRenew = document.getElementById('section-renewal');

function refreshMode(){
  titleEl.textContent = 'Lumber Dealer Permit – Application ' + (permitType === 'dealer_new' ? '(New)' : '(Renewal)');
  secNew.style.display = (permitType === 'dealer_new') ? '' : 'none';
  secRenew.style.display = (permitType === 'dealer_new') ? 'none' : '';
}
btns.forEach(b=>b.addEventListener('click', ()=>{
  btns.forEach(x=>x.classList.remove('active'));
  b.classList.add('active');
  permitType = b.dataset.type || 'dealer_new';
  refreshMode();
}));
refreshMode();

/* ===== Mobile nav toggle ===== */
document.querySelectorAll('.mobile-toggle').forEach(btn=>{
  btn.addEventListener('click', ()=>document.body.classList.toggle('nav-open'));
});

/* ===== Signature Pad (preview only) ===== */
const canvas = document.getElementById('signature-pad');
const sigPad = new SignaturePad(canvas, { backgroundColor: 'rgba(0,0,0,0)', penColor:'#000' });
function resizeCanvas(){
  const ratio = Math.max(window.devicePixelRatio||1,1);
  const rect = canvas.getBoundingClientRect();
  canvas.width = rect.width * ratio; canvas.height = rect.height * ratio;
  canvas.getContext('2d').scale(ratio, ratio);
}
window.addEventListener('resize', resizeCanvas); resizeCanvas();
document.getElementById('clear-signature').onclick = ()=>{ sigPad.clear(); document.getElementById('signature-image').classList.add('hidden'); };
document.getElementById('save-signature').onclick = ()=>{
  if(sigPad.isEmpty()) return alert('Please provide a signature first.');
  const img = document.getElementById('signature-image'); img.src = sigPad.toDataURL('image/png'); img.classList.remove('hidden');
};

/* ===== Suppliers (shared helpers) ===== */
function bindRemove(btn){
  btn.addEventListener('click', ()=>{
    const row = btn.closest('.supplier-row');
    const wrap = row?.parentElement;
    if (wrap && wrap.querySelectorAll('.supplier-row').length > 1) row.remove();
  });
}
function addSupplierRow(wrapId){
  const wrap = document.getElementById(wrapId);
  const div = document.createElement('div');
  div.className = 'supplier-row';
  div.innerHTML = `
    <div class="form-row" style="margin-bottom:0">
      <div class="form-group">
        <label>Supplier Name/Company</label>
        <input type="text" class="supplier-name" placeholder="Supplier name">
      </div>
      <div class="form-group">
        <label>Volume</label>
        <input type="text" class="supplier-volume" placeholder="Volume">
      </div>
      <div class="form-group" style="flex:0 0 auto;display:flex;align-items:flex-end">
        <button type="button" class="remove-btn">Remove</button>
      </div>
    </div>`;
  wrap.appendChild(div);
  bindRemove(div.querySelector('.remove-btn'));
}
document.getElementById('add-supplier-row').addEventListener('click', ()=>addSupplierRow('suppliers-wrap'));
document.getElementById('r-add-supplier-row').addEventListener('click', ()=>addSupplierRow('r-suppliers-wrap'));
document.querySelectorAll('.remove-btn').forEach(bindRemove);

/* Quick "Add" action button: add a supplier row in current section */
{
  const addRowBtn = document.getElementById('addRowBtn');
  if (addRowBtn) {
    addRowBtn.addEventListener('click', ()=>{
      if (permitType === 'dealer_new') addSupplierRow('suppliers-wrap');
      else addSupplierRow('r-suppliers-wrap');
    });
  }
}


/* ===== Toast ===== */
function toast(msg){
  const n = document.getElementById("profile-notification");
  n.textContent = msg; n.style.display = "block"; n.style.opacity = "1";
  setTimeout(()=>{ n.style.opacity = "0"; setTimeout(()=>{ n.style.display = "none"; n.style.opacity = "1"; }, 350); }, 2200);
}

/* ===== Gather Form ===== */
function gatherSuppliers(wrapId){
  const rows = Array.from(document.querySelectorAll(`#${wrapId} .supplier-row`));
  const arr = [];
  rows.forEach(r=>{
    const name = r.querySelector('.supplier-name')?.value.trim() || '';
    const volume = r.querySelector('.supplier-volume')?.value.trim() || '';
    if (name || volume) arr.push({name, volume});
  });
  return arr;
}

function gatherFormNew(){
  const val = id => (document.getElementById(id)?.value || '').trim();

  const firstName      = val('first-name');
  const middleName     = val('middle-name');
  const lastName       = val('last-name');
  const sitioStreet    = val('sitio-street');
  const province       = val('province');
  const contactNumber  = val('contact-number');

  const applicantAge   = val('applicant-age');
  const businessAddress= val('business-address');
  const isGovtEmployee = document.querySelector('input[name="govt-employee"]:checked').value;

  const operationPlace  = val('operation-place');
  const annualVolume    = val('annual-volume');
  const annualWorth     = val('annual-worth');
  const employeesCount  = val('employees-count');
  const dependentsCount = val('dependents-count');

  const intendedMarket  = val('intended-market');
  const experience      = val('experience');

  const fullName = [firstName, middleName, lastName].filter(Boolean).join(' ').replace(/\s+/g,' ').trim();
  const declarationName = (val('declaration-name') || fullName);
  const suppliers = gatherSuppliers('suppliers-wrap');

  return { firstName, middleName, lastName, fullName, sitioStreet, province, contactNumber,
           applicantAge, businessAddress, isGovtEmployee, operationPlace, annualVolume, annualWorth,
           employeesCount, dependentsCount, intendedMarket, experience, declarationName, suppliers };
}

function gatherFormRenewal(){
  const val = id => (document.getElementById(id)?.value || '').trim();

  const firstName       = val('r-first-name');
  const middleName      = val('r-middle-name');
  const lastName        = val('r-last-name');

  // NEW on renewal:
  const sitioStreet     = val('r-sitio-street');
  const province        = val('r-province');
  const contactNumber   = val('r-contact-number');

  const applicantAge    = val('r-applicant-age');
  const businessAddress = val('r-business-address');
  const isGovtEmployee  = document.querySelector('input[name="r-govt-employee"]:checked').value;

  const operationPlace  = val('r-operation-place');
  const annualVolume    = val('r-annual-volume');
  const annualWorth     = val('r-annual-worth');
  const employeesCount  = val('r-employees-count');
  const dependentsCount = val('r-dependents-count');

  const intendedMarket  = val('r-intended-market');
  const experience      = val('r-experience');

  const prevCertificate = val('r-prev-certificate');
  const issuedDate      = document.getElementById('r-issued-date').value;
  const expiryDate      = document.getElementById('r-expiry-date').value;
  const crLicense       = val('r-cr-license');
  const sawmillPermit   = val('r-sawmill-permit');

  const fullName        = [firstName, middleName, lastName].filter(Boolean).join(' ').replace(/\s+/g,' ').trim();
  const declarationName = (val('declaration-name') || fullName);
  const suppliers       = gatherSuppliers('r-suppliers-wrap');

  return {
    firstName, middleName, lastName, fullName,
    // now populated for renewal
    sitioStreet, province, contactNumber,
    applicantAge, businessAddress, isGovtEmployee,
    operationPlace, annualVolume, annualWorth,
    employeesCount, dependentsCount,
    intendedMarket, experience, declarationName, suppliers,
    renewal_extras: {
      prevCertificate, issuedDate, expiryDate, crLicense, sawmillPermit
      // removed: otherSources
    }
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
    toast("Submission failed. Please try again.");
  }finally{
    loading.style.display = "none";
  }
});

async function doSubmit(){
  const data = (permitType === 'dealer_new') ? gatherFormNew() : gatherFormRenewal();

// light validations
if (permitType === 'dealer_new') {
  if(!data.firstName || !data.lastName) throw new Error("Name required");
  if(!data.sitioStreet || !data.province || !data.contactNumber) throw new Error("Address & contact required");
} else { // dealer_renewal
  if(!data.firstName || !data.lastName) throw new Error("Applicant name (first & last) required");
  // NEW: require the same three fields on renewal
  if(!data.sitioStreet || !data.province || !data.contactNumber) throw new Error("Street/Sitio, Province, and Contact Number are required for Renewal.");
}
if(!data.operationPlace || !data.annualVolume || !data.annualWorth) throw new Error("Business info required");

  const fd = new FormData();
  fd.append('csrf', CSRF);
  fd.append('permit_type', permitType); // 'dealer_new' | 'dealer_renewal'

  // common/applicant mapping
  fd.append('first_name', data.firstName ?? data.first_name ?? '');
  fd.append('middle_name', data.middleName ?? data.middle_name ?? '');
  fd.append('last_name', data.lastName ?? data.last_name ?? '');
  fd.append('sitio_street', data.sitioStreet ?? '');
  fd.append('province', data.province ?? '');
  fd.append('contact_number', data.contactNumber ?? '');
  fd.append('applicant_age', data.applicantAge ?? '');
  fd.append('business_address', data.businessAddress ?? '');
  fd.append('govt_employee', data.isGovtEmployee ?? 'no');

  // business/others
  fd.append('operation_place', data.operationPlace ?? '');
  fd.append('annual_volume', data.annualVolume ?? '');
  fd.append('annual_worth', data.annualWorth ?? '');
  fd.append('employees_count', data.employeesCount ?? '');
  fd.append('dependents_count', data.dependentsCount ?? '');
  fd.append('intended_market', data.intendedMarket ?? '');
  fd.append('experience', data.experience ?? '');
  fd.append('declaration_name', data.declarationName ?? '');
  fd.append('suppliers_json', JSON.stringify(data.suppliers || []));

  // renewal-only extras
  if (permitType === 'dealer_renewal') {
    fd.append('renewal_extras_json', JSON.stringify(data.renewal_extras||{}));
  }

  // append transparent PNG (if any) as a file for backend
  if (!sigPad.isEmpty()) {
    const dataUrl = sigPad.toDataURL('image/png'); // keeps alpha
    const resp = await fetch(dataUrl);
    const blob = await resp.blob(); // type: image/png, transparent background preserved
    fd.append('signature_file', blob, 'signature.png');
  }

fd.append('debug','1');

  const res = await fetch(SAVE_URL, { method:'POST', body: fd, credentials:'include' });
  const json = await res.json().catch(()=>({ok:false,error:'Bad JSON'}));
  if(!res.ok || !json.ok) throw new Error(json.error || `HTTP ${res.status}`);
}

/* ===== Reset ===== */
function resetForm(){
  document.querySelectorAll("input[type='text'], input[type='number'], input[type='date'], textarea").forEach(inp => inp.value = "");
  (document.querySelector("input[name='govt-employee'][value='no']")||{}).checked = true;
  (document.querySelector("input[name='r-govt-employee'][value='no']")||{}).checked = true;
  (document.querySelector("input[name='r-other-sources'][value='no']")||{}).checked = true;

  // reset suppliers wraps
  ['suppliers-wrap','r-suppliers-wrap'].forEach(wrapId=>{
    const wrap = document.getElementById(wrapId);
    wrap.innerHTML = `
      <div class="supplier-row">
        <div class="form-row" style="margin-bottom:0">
          <div class="form-group">
            <label>Supplier Name/Company</label>
            <input type="text" class="supplier-name" placeholder="Supplier name">
          </div>
          <div class="form-group">
            <label>Volume</label>
            <input type="text" class="supplier-volume" placeholder="Volume">
          </div>
          <div class="form-group" style="flex:0 0 auto;display:flex;align-items:flex-end">
            <button type="button" class="remove-btn">Remove</button>
          </div>
        </div>
      </div>`;
    bindRemove(wrap.querySelector('.remove-btn'));
  });

  // signature preview reset
  sigPad.clear();
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
    <title>Lumber Dealer Permit Application</title>
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
            border: 1px solid #ddd;
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
            color: #555;
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
            border: 1px solid #ddd;
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
            color: #555;
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
            border-top: 1px solid #ddd;
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
            padding: 3px 10px;
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
                    <a href="user_requestseedlings.php" class="dropdown-item">
                        <i class="fas fa-seedling"></i>
                        <span>Request Seedlings</span>
                    </a>
                    <a href="user_chainsaw_renewal.php" class="dropdown-item active-page">
                        <i class="fas fa-tools"></i>
                        <span>Chainsaw Renewal</span>
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

                    <a href="useraddseed.php" class="dropdown-item">
                        <i class="fas fa-seedling"></i>
                        <span>Request Seedlings</span>
                    </a>
                    <a href="useraddwild.php" class="dropdown-item">
                        <i class="fas fa-paw"></i>
                        <span>Wildlife Permit</span>
                    </a>
                    <a href="useraddtreecut.php" class="dropdown-item">
                        <i class="fas fa-tree"></i>
                        <span>Tree Cutting Permit</span>
                    </a>
                    <a href="useraddlumber.php" class="dropdown-item active-page">
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
            <a href="usereditlumber.php" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="userviewlumber.php" class="btn btn-outline">
                <i class="fas fa-eye"></i> View
            </a>
        </div>

        <div class="requirements-form">
            <div class="form-header">
                <h2>Lumber Dealer Permit - Requirements</h2>
            </div>

            <div class="form-body">
                <!-- Permit Type Selector -->
                <div class="permit-type-selector">
                    <button class="permit-type-btn active" data-type="new">New Permit</button>
                    <button class="permit-type-btn" data-type="renewal">Renewal</button>
                </div>

                <!-- Name fields -->
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
                    <!-- Requirement 1 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">1</span>
                                <span class="requirement-number renewal-number" style="display:none">1</span>
                                Complete Staff Work (CSW) by the inspecting officer- 3 copies from inspecting officer for signature of RPS chief
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
                        </div>
                    </div>

                    <!-- Requirement 2 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">2</span>
                                <span class="requirement-number renewal-number" style="display:none">2</span>
                                Geo-tagged pictures of the business establishment (3 copies from inspecting officer for signature of RPS chief)
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
                        </div>
                    </div>

                    <!-- Requirement 3 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">3</span>
                                <span class="requirement-number renewal-number" style="display:none">3</span>
                                Application form duly accomplished (3 copies)
                                <div class="download-buttons">
                                    <a href="Lumber_applicationform.docx" class="download-btn new-form-btn" download="Lumber_Application_Form.docx">
                                        <i class="fas fa-download"></i> Download New Form
                                    </a>
                                    <a href="lumber_renewal.docx" class="download-btn renewal-form-btn" style="display:none" download="Lumber_Renewal_Form.docx">
                                        <i class="fas fa-download"></i> Download Renewal Form
                                    </a>
                                </div>
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
                        </div>
                    </div>

                    <!-- Requirement 4 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">4</span>
                                <span class="requirement-number renewal-number" style="display:none">4</span>
                                Log/Lumber Supply Contract (approved by RED) - 3 copies
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
                        </div>
                    </div>

                    <!-- Requirement 5 (New Only) -->
                    <div class="requirement-item" id="requirement-5">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">5</span>
                                Business Management Plan (3 copies)
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
                        </div>
                    </div>

                    <!-- Requirement 6 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">6</span>
                                <span class="requirement-number renewal-number" style="display:none">5</span>
                                Mayor's Permit - 3 copies
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
                        </div>
                    </div>

                    <!-- Requirement 7 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">7</span>
                                <span class="requirement-number renewal-number" style="display:none">6</span>
                                Certificate of Registration by DTI/SEC - 3 copies
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-7" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                        </div>
                    </div>

                    <!-- Requirement 8 (New Only) -->
                    <div class="requirement-item" id="requirement-8">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">8</span>
                                Latest Annual Income Tax Return - 3 copies
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
                        </div>
                    </div>

                    <!-- Requirement 9 (Renewal Only) -->
                    <div class="requirement-item" id="requirement-9">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number renewal-number">7</span>
                                Monthly and Quarterly Reports from the date issued to date (for renewal only) - 3 copies
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
                        </div>
                    </div>

                    <!-- Requirement 10 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">9</span>
                                <span class="requirement-number renewal-number">8</span>
                                Regulatory Fees
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Photocopy of Official Receipt</p>
                                <div class="file-input-container">
                                    <label for="file-10a" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-10a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Photocopy of Order of Payment</p>
                                <div class="file-input-container">
                                    <label for="file-10b" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-10b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content" style="max-width:400px;text-align:center;">
            <span id="closeConfirmModal" class="close-modal">&times;</span>
            <h3>Confirm Submission</h3>
            <p>Are you sure you want to submit this lumber dealer permit request?</p>
            <button id="confirmSubmitBtn" class="btn btn-primary" style="margin:10px 10px 0 0;">Yes, Submit</button>
            <button id="cancelSubmitBtn" class="btn btn-outline">Cancel</button>
        </div>
    </div>

    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

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

            // Permit type selector functionality
            const permitTypeBtns = document.querySelectorAll('.permit-type-btn');
            const requirement5 = document.getElementById('requirement-5');
            const requirement8 = document.getElementById('requirement-8');
            const requirement9 = document.getElementById('requirement-9');

            permitTypeBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    permitTypeBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    if (this.dataset.type === 'new') {
                        requirement5.style.display = 'flex';
                        requirement8.style.display = 'flex';
                        requirement9.style.display = 'none';
                        document.querySelectorAll('.new-number').forEach(el => el.style.display = 'inline');
                        document.querySelectorAll('.renewal-number').forEach(el => el.style.display = 'none');
                        document.querySelector('.new-form-btn').style.display = 'inline-flex';
                        document.querySelector('.renewal-form-btn').style.display = 'none';
                    } else {
                        requirement5.style.display = 'none';
                        requirement8.style.display = 'none';
                        requirement9.style.display = 'flex';
                        document.querySelectorAll('.new-number').forEach(el => el.style.display = 'none');
                        document.querySelectorAll('.renewal-number').forEach(el => el.style.display = 'inline');
                        document.querySelector('.new-form-btn').style.display = 'none';
                        document.querySelector('.renewal-form-btn').style.display = 'inline-flex';
                    }
                });
            });

            // Initialize with New Permit selected
            document.querySelector('.permit-type-btn[data-type="new"]').click();

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
                    id: 'file-7',
                    uploaded: 'uploaded-files-7'
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
                if (fileInput) {
                    fileInput.addEventListener('change', function() {
                        const file = this.files[0];
                        this.parentElement.querySelector('.file-name').textContent = file ? file.name : 'No file chosen';
                        if (file) {
                            selectedFiles[input.id] = file;
                        } else {
                            selectedFiles[input.id] = null;
                        }
                    });
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

                    // Check if required files are uploaded based on permit type
                    const isNewPermit = document.querySelector('.permit-type-btn[data-type="new"]').classList.contains('active');
                    const requiredFiles = isNewPermit ? ['file-1', 'file-2', 'file-3', 'file-4', 'file-5', 'file-6', 'file-7', 'file-8', 'file-10a', 'file-10b'] : ['file-1', 'file-2', 'file-3', 'file-4', 'file-6', 'file-7', 'file-9', 'file-10a', 'file-10b'];

                    let allRequiredFilesUploaded = true;
                    requiredFiles.forEach(fileId => {
                        if (!selectedFiles[fileId]) {
                            allRequiredFilesUploaded = false;
                            const requirementItem = document.getElementById(fileId).closest('.requirement-item');
                            if (requirementItem) {
                                requirementItem.style.borderLeft = '4px solid #ff4757';
                                setTimeout(() => {
                                    requirementItem.style.borderLeft = '4px solid var(--primary-color)';
                                }, 2000);
                            }
                        }
                    });

                    if (!allRequiredFilesUploaded) {
                        alert('Please upload all required files before submitting.');
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
                    const permitType = document.querySelector('.permit-type-btn.active').dataset.type;

                    const formData = new FormData();
                    formData.append('first_name', firstName);
                    formData.append('middle_name', middleName);
                    formData.append('last_name', lastName);
                    formData.append('permit_type', permitType);

                    // Append all files
                    fileInputs.forEach(input => {
                        if (selectedFiles[input.id]) {
                            formData.append(input.id.replace('-', '_'), selectedFiles[input.id]);
                        }
                    });

                    fetch('../backend/users/addlumber.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(err => {
                                    throw new Error(err.errors ? err.errors.join('\n') : 'Server error');
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Clear form and show success
                                document.querySelectorAll('input[type="text"]').forEach(input => input.value = '');
                                document.querySelectorAll('input[type="file"]').forEach(input => {
                                    input.value = '';
                                    input.parentElement.querySelector('.file-name').textContent = 'No file chosen';
                                });
                                selectedFiles = {};
                                showProfileNotification(data.message);
                            } else {
                                throw new Error(data.errors ? data.errors.join('\n') : 'Submission failed');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert(error.message || 'Network error. Please try again.');
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
