<?php
// --- Sample Data (static, not from DB) ---
date_default_timezone_set('Asia/Manila');

$sample = [
    'wfp_no'               => 'WFP-2025-00123',
    'series'               => '2025',
    'meeting_date'         => 'April 30, 2025',
    'establishment_name'   => 'Sample Wildlife Farm, Inc.',
    'client_name'          => 'Juan Dela Cruz',
    'establishment_address' => '1234 Green Hills, Sudlon, Lahug, Cebu City 6000',
    // Table rows
    'animals' => [
        ['common' => 'Philippine Serpent Eagle', 'scientific' => 'Spilornis holospilus', 'quantity' => 2],
        ['common' => 'Brahminy Kite',            'scientific' => 'Haliastur indus',      'quantity' => 1],
        ['common' => 'Changeable Hawk-Eagle',    'scientific' => 'Nisaetus cirrhatus',    'quantity' => 1],
    ],
];

// Auto dates
$dateIssued = new DateTime('now');
$expiryDate = (clone $dateIssued)->modify('+2 years');

// Formatters
$fmt = function (DateTime $d) {
    return $d->format('F j, Y');
};
$totalQty = array_sum(array_map(fn($a) => (int)$a['quantity'], $sample['animals']));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Wildlife Farm Permit</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Times New Roman', serif;
        }

        body {
            background: #f0f0f0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 8.5in;
            min-height: 11in;
            margin: 0 auto;
            background: #fff;
            padding: 0.5in;
            box-shadow: 0 0 10px rgba(0, 0, 0, .1);
            position: relative;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 4px solid #ff0000;
        }

        .left-section {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            flex: 1;
        }

        .logo {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .logo-placeholder {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            font-size: 10px;
            text-align: center;
            background: #f9f9f9;
            flex-shrink: 0;
        }

        .denr-info {
            text-align: left;
        }

        .denr-info h1 {
            font-size: 17px;
            color: #000;
            margin-bottom: 4px;
            line-height: 1.2;
        }

        .denr-info p {
            font-size: 13px;
            margin-bottom: 1px;
            color: #000;
            line-height: 1.1;
        }

        .right-logo {
            width: 110px;
            height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            overflow: hidden;
            flex-shrink: 0;
            margin-left: 15px;
        }

        .right-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .right-logo-placeholder {
            width: 110px;
            height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            font-size: 12px;
            text-align: center;
            background: #f9f9f9;
            flex-shrink: 0;
            margin-left: 15px;
        }

        .permit-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .permit-number {
            text-align: left;
            font-size: 15px;
            color: #000;
        }

        .permit-title {
            text-align: center;
            margin: 15px 0;
            font-size: 20px;
            font-weight: bold;
            text-decoration: underline;
            color: #000;
        }

        .subtitle {
            text-align: center;
            margin-bottom: 15px;
            font-size: 16px;
            color: #000;
        }

        .permit-body {
            line-height: 1.5;
            margin-bottom: 20px;
            color: #000;
            font-size: 14px;
        }

        .permit-body p {
            color: #000;
            margin-bottom: 8px;
        }

        .permit-body strong {
            color: #000;
        }

        .underline-field,
        .small-underline,
        .inline-underline {
            border-bottom: 1px solid #000;
            display: inline-block;
            margin: 0 3px;
            padding: 0 3px;
        }

        .underline-field {
            min-width: 280px;
        }

        .inline-underline {
            min-width: 200px;
        }

        .small-underline {
            min-width: 140px;
        }

        .info-table {
            width: 100%;
            margin: 15px 0;
            color: #000;
            border-collapse: collapse;
            font-size: 13px;
            border: 1px solid #000;
        }

        .info-table th,
        .info-table td {
            padding: 8px;
            border: 1px solid #000;
            text-align: left;
        }

        .info-table th {
            background: #f0f0f0;
            font-weight: bold;
        }

        .terms-section {
            margin-top: 25px;
            text-align: left;
            color: #000;
        }

        .terms-section h2 {
            font-size: 16px;
            margin-bottom: 8px;
            text-decoration: underline;
            color: #000;
        }

        .terms-list {
            margin-left: 20px;
            margin-bottom: 15px;
        }

        .terms-list li {
            margin-bottom: 8px;
            font-size: 13px;
        }

        .contact-info {
            text-align: center;
            font-size: 13px;
            color: #000;
            padding-top: 10px;
            margin-top: 25px;
        }

        .controls {
            margin-top: 20px;
            text-align: center;
        }

        .download-btn {
            background: #2b6625;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color .3s;
            margin: 0 5px;
        }

        .download-btn:hover {
            background: #1e4a1a;
        }

        .loading {
            display: none;
            margin-top: 10px;
            color: #000;
            font-size: 14px;
        }

        .success-message {
            display: none;
            margin-top: 10px;
            color: #2b6625;
            font-weight: bold;
            font-size: 14px;
        }

        .nothing-follows {
            text-align: center;
            margin-top: 10px;
            font-weight: normal;
            font-style: normal;
        }

        .single-line-field {
            display: inline-block;
            width: 100%;
            margin: 5px 0;
        }

        .continuous-sentence {
            display: inline;
        }

        @media print {
            @page {
                margin: 0.5in;
                size: letter;
            }

            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                height: auto !important;
            }

            .container {
                box-shadow: none !important;
                padding: 0.5in !important;
                margin: 0 auto !important;
                width: 100% !important;
                height: auto !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
                page-break-before: avoid !important;
                overflow: visible !important;
            }

            .controls {
                display: none !important;
            }

            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
            }

            @page :first {
                margin-top: 0.5in;
            }

            @page :left {
                margin-left: 0.5in;
            }

            @page :right {
                margin-right: 0.5in;
            }
        }
    </style>
</head>

<body>
    <div class="container" id="permit">
        <div class="header">
            <div class="left-section">
                <div class="logo">
                    <img src="denr.png" alt="DENR Logo" id="denr-logo" onerror="handleImageError(this,'DENR')">
                </div>
                <div class="denr-info">
                    <h1>Department of Environment and Natural Resources</h1>
                    <p>Region 7</p>
                </div>
            </div>
            <div class="right-logo">
                <img src="pilipinas.png" alt="Philippines Flag" id="ph-flag" onerror="handleImageError(this,'PH')">
            </div>
        </div>

        <div class="permit-details">
            <div class="permit-number">
                WFP No. <span class="small-underline"><?= htmlspecialchars($sample['wfp_no']) ?></span><br>
                SERIES OF <?= htmlspecialchars($sample['series']) ?><br>
                Date Issued: <span class="small-underline"><?= htmlspecialchars($fmt($dateIssued)) ?></span><br>
                Expiry Date: <span class="small-underline"><?= htmlspecialchars($fmt($expiryDate)) ?></span>
            </div>
        </div>

        <div class="permit-title">WILDLIFE FARM PERMIT</div>
        <div class="subtitle">(Small Scale Farming)</div>

        <div class="permit-body">
            <p>
                Pursuant to the provisions of Republic Act No. 9147 otherwise known as the
                "Wildlife Resources Conservation and Protection Act" of 2001, as implemented by the
                Joint DENR-DA-PCSD Administrative Order No. 1, Series of 2004 and in consonance with
                the provisions of Section 5-9 of DENR Administrative Order No. 2004-55 dated August 31, 2004,
                and upon the recommendation of the Regional Wildlife Committee during its meeting on
                <strong><?= htmlspecialchars($sample['meeting_date']) ?></strong> through RWC Resolution No. 04-<?= htmlspecialchars($sample['series']) ?>,
            </p>

            <div class="single-line-field">
                <span class="underline-field"><?= htmlspecialchars($sample['establishment_name']) ?></span>
                represented by
                <span class="inline-underline"><?= htmlspecialchars($sample['client_name']) ?></span>
                with facility located at
                <span class="inline-underline"><?= htmlspecialchars($sample['establishment_address']) ?></span>
                is hereby granted a Wildlife Farm Permit (WFP) subject to the terms, conditions and restrictions herein specified:
                valid until <strong><?= htmlspecialchars($fmt($expiryDate)) ?></strong>.
            </div>

            <ol class="terms-list">
                <li>
                    The Permittee shall maintain and operate a wildlife breeding farm facility in
                    <span class="underline-field"><?= htmlspecialchars($sample['establishment_address']) ?></span>
                    with wildlife species for breeding, educational and trading/commercial purposes.
                </li>

                <table class="info-table">
                    <tr>
                        <th>Common Name</th>
                        <th>Scientific Name</th>
                        <th>Quantity</th>
                    </tr>
                    <?php foreach ($sample['animals'] as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['common']) ?></td>
                            <td><?= htmlspecialchars($a['scientific']) ?></td>
                            <td><?= (int)$a['quantity'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><strong>TOTAL</strong></td>
                        <td></td>
                        <td><strong><?= (int)$totalQty ?></strong></td>
                    </tr>
                </table>

                <p class="nothing-follows">NOTHING FOLLOWS</p>

                <li>The Permittee shall allow, upon notice, any DENR authorized representative(s) to visit and/or inspect the farm facility or premises and conduct an inventory of existing stocks;</li>
                <li>The Permittee shall submit monthly production and quarterly reports to the DENR Region 7 for the accreditation of wildlife within the farm. A report on the acquisition of wildlife shall likewise be submitted. In case of mortalities, necropsy reports should be submitted immediately;</li>
                <li>In the acquisition of breeding stock, the Permittee shall first secure the required permit prior to actual acquisition/importation. The importation of wildlife shall be subject to inspection by the DENR Wildlife Monitoring Team upon entry at the airports/seaports. Further, a quarantine certificate from the Bureau of Animal Industry shall be secured;</li>
                <li>Any collection/acquisition of additional stock of the herein listed species shall be in accordance with existing policies, rules and regulations of the DENR. The additional stock shall be sourced out from accredited breeders only and the quantity shall depend on the carrying capacity of existing farm facilities.</li>
                <li>The Permittee should develop and install a marking system (e.g., microchip, tags, leg band, etc.) for all reported breeding stock and progenies (if applicable) within sixty (60) days upon approval of this permit;</li>
                <li>In case of disposition, only those progenies captive-bred birds, reptiles and mammals as well as unproductive parent stock appropriately marked may be disposed, either through sale, exchange or donation;</li>
                <li>In case of exportation of captive-bred wildlife, the Permittee shall present the wildlife to the DENR or BMB for inspection and verification. Only upon satisfaction of the requirements/standards for exportation and payment of the required fees shall the CITES Export Permit/Wildlife Certification be issued;</li>
                <li>The Permittee shall file his/her application for a Wildlife Certification/CITES Permit at least three (3) days before the intended date of shipment. In case of re-issuance of CITES Export/Import permit or re-export permit/Wildlife Certification, the original copy of the permit shall be surrendered and the applicant shall inform BMB in writing of the reasons or grounds necessitating such re-issuance;</li>
                <li>The Permittee shall secure Local Transport Permit from the DENR PENRO or CENRO nearest to the business/operating farm for the transport of wildlife, its parts and derivatives from one place to another within the country;</li>
                <li>The Permittee shall ensure the safety and proper maintenance of the wildlife in its facilities, observe hygiene and strict quarantine procedures in operation and assume full responsibility and accountability over any disease outbreak or epidemic that might arise or originate from its facility;</li>
                <li>In case of release or accidental escape of wildlife, resulting to damage to life and property, the Permittee shall be liable for such incident and shall be penalized in accordance with PD 1586 without prejudice to the existing laws. Such incident shall also result to the automatic cancellation of this Permit and confiscation of the remaining stock in its facilities in favor of the Philippine Government;</li>
                <li>Any Bioprospecting activity shall be subject to prior clearance from BMB and should be undertaken in accordance with the pertinent provisions of R.A. 9147 or Wildlife Resources Conservation and Protection Act or other applicable laws, rules and regulations;</li>
                <li>The Permittee shall make a commitment in writing to pursue an Environmental Conservation Program of his own or to be undertaken collectively with other Permittees of DENR, to be signed by the Permittee or the highest official of the company, as the case maybe. The plan which includes concept and budget must be submitted within the first three (3) months upon the effectivity of this permit. Compliance to the said commitment shall be one of the basis for renewal hereof;</li>
                <li>In case of incapability to sustain the accredited wildlife, the Permittee shall be responsible for the turn-over or transfer of wildlife to the nearest DENR - Wildlife Rescue Center (WRC) at their own expense.</li>
                <li>Any alteration, erasure or obliteration in this permit shall be sufficient ground for the cancellation/revocation of this permit without prejudice to criminal and other liabilities of the offender;</li>
            </ol>

            <div class="contact-info">
                <p>National Government Center, Sudlon, Lahug, Cebu City, Philippines 6000</p>
                <p>Tel. Nos: (+6332) 346-9612, 328-3335 Fax No: 328-3336</p>
                <p>E-mail: t7@denr.gov.ph / redeenr7@yahoo.com</p>
            </div>
        </div>
    </div>

    <div class="controls">
        <button class="download-btn" id="downloadPdfBtn">Download as PDF</button>
        <div class="loading" id="loadingIndicator">Generating PDF, please wait...</div>
        <div class="success-message" id="successMessage">PDF downloaded successfully!</div>
    </div>

    <script>
        // Logo fallback
        function handleImageError(img, type) {
            const ph = document.createElement('div');
            ph.style.cssText = 'text-align:center;padding:10px;display:flex;align-items:center;justify-content:center;';
            ph.innerHTML = `[${type} LOGO]`;
            if (type === 'PH') {
                ph.style.width = '110px';
                ph.style.height = '110px';
                ph.style.fontSize = '12px';
            } else {
                ph.style.width = '80px';
                ph.style.height = '80px';
                ph.style.fontSize = '10px';
            }
            img.parentNode.replaceChild(ph, img);
        }

        // Wait for images
        function waitForImages() {
            return new Promise((resolve) => {
                const imgs = document.querySelectorAll('img');
                let loaded = 0,
                    total = imgs.length;
                if (total === 0) {
                    resolve();
                    return;
                }
                const done = () => {
                    loaded++;
                    if (loaded === total) resolve();
                }
                imgs.forEach(img => {
                    if (img.complete && img.naturalHeight !== 0) done();
                    else {
                        img.addEventListener('load', done);
                        img.addEventListener('error', done);
                    }
                });
                setTimeout(resolve, 3000); // fallback
            });
        }

        // PDF download
        document.getElementById('downloadPdfBtn').addEventListener('click', async function() {
            const loading = document.getElementById('loadingIndicator');
            const btn = this;
            const ok = document.getElementById('successMessage');
            loading.style.display = 'block';
            btn.disabled = true;
            btn.textContent = 'Generating PDF...';
            ok.style.display = 'none';
            try {
                await waitForImages();
                const el = document.getElementById('permit');
                const options = {
                    margin: [0.5, 0.5, 0.5, 0.5],
                    filename: 'Wildlife_Farm_Permit.pdf',
                    image: {
                        type: 'jpeg',
                        quality: 1.0
                    },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        allowTaint: true,
                        logging: false,
                        scrollX: 0,
                        scrollY: 0,
                        backgroundColor: '#FFFFFF',
                        width: el.scrollWidth,
                        height: el.scrollHeight
                    },
                    jsPDF: {
                        unit: 'in',
                        format: 'letter',
                        orientation: 'portrait',
                        putOnlyUsedFonts: true,
                        hotfixes: ["px_scaling"]
                    }
                };
                await html2pdf().set(options).from(el).save();
                loading.style.display = 'none';
                btn.disabled = false;
                btn.textContent = 'Download as PDF';
                ok.style.display = 'block';
                setTimeout(() => ok.style.display = 'none', 3000);
            } catch (err) {
                console.error('PDF generation error:', err);
                try {
                    const el = document.getElementById('permit');
                    await html2pdf().set({
                        margin: [0.5, 0.5, 0.5, 0.5],
                        filename: 'Wildlife_Farm_Permit.pdf',
                        html2canvas: {
                            scale: 2,
                            useCORS: true,
                            backgroundColor: '#FFFFFF'
                        },
                        jsPDF: {
                            unit: 'in',
                            format: 'letter',
                            orientation: 'portrait',
                            putOnlyUsedFonts: true,
                            hotfixes: ["px_scaling"]
                        }
                    }).from(el).save();
                    loading.style.display = 'none';
                    btn.disabled = false;
                    btn.textContent = 'Download as PDF';
                    ok.style.display = 'block';
                    setTimeout(() => ok.style.display = 'none', 3000);
                } catch (err2) {
                    console.error('Second PDF error:', err2);
                    alert('Please select "Save as PDF" in the print dialog.');
                    window.print();
                    loading.style.display = 'none';
                    btn.disabled = false;
                    btn.textContent = 'Download as PDF';
                }
            }
        });

        // Ctrl+P print
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>

</html>