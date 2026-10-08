<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id  = $_SESSION['user_id'];
$role     = $_SESSION['role'] ?? 'Resident';
$is_admin = ($role !== 'Resident');

$request_id = intval($_GET['id'] ?? 0);
if ($request_id <= 0) die("Invalid request ID.");

/* Detect table */
$tbl = null;
foreach (['certificate_requests', 'service_requests'] as $t) {
    $chk = $conn->query("SHOW TABLES LIKE '$t'");
    if ($chk && $chk->num_rows > 0) { $tbl = $t; break; }
}
if ($tbl === null) die("Walang table na makita.");

/* Detect columns */
$sr_cols = [];
$cc = $conn->query("SHOW COLUMNS FROM $tbl");
if ($cc) { while ($c = $cc->fetch_assoc()) $sr_cols[] = $c['Field']; }

$sr_id_col = 'id';
foreach (['request_id', 'id', 'cert_request_id'] as $c) {
    if (in_array($c, $sr_cols)) { $sr_id_col = $c; break; }
}
$sr_resident_col = in_array('resident_id', $sr_cols) ? 'resident_id' : null;

$sr_type_col = null;
foreach (['certificate_type', 'service_type', 'document_type', 'doc_type', 'request_type', 'type'] as $c) {
    if (in_array($c, $sr_cols)) { $sr_type_col = $c; break; }
}
if ($sr_type_col === null) {
    $sr_type_col = 'service_type'; // fallback
}

$sr_purpose_col = 'purpose';
foreach (['purpose', 'details', 'reason', 'description'] as $c) {
    if (in_array($c, $sr_cols)) { $sr_purpose_col = $c; break; }
}

$sr_date_col = null;
foreach (['requested_at', 'created_at', 'date_requested', 'date_filed', 'created'] as $c) {
    if (in_array($c, $sr_cols)) { $sr_date_col = $c; break; }
}

/* Fetch request */
$stmt = $conn->prepare("SELECT * FROM $tbl WHERE $sr_id_col = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();

if (!$req) die("Hindi mahanap ang request.");

/* RBAC: Citizen cannot view others' requests */
if (!$is_admin && $sr_resident_col) {
    if (intval($req[$sr_resident_col]) !== intval($user_id)) {
        die("Hindi mo pwedeng tingnan ang request na ito.");
    }
}

$status = $req['status'] ?? 'Pending';
if ($status !== 'Approved') {
    die("<div style='font-family:sans-serif; padding:40px; text-align:center; background:#fdecec; color:#a4262c;'>
            <h2>Hindi pa Aprubado</h2>
            <p>Status: <b>" . htmlspecialchars($status) . "</b></p>
            <a href='documents.php' style='display:inline-block; margin-top:20px; padding:10px 24px; background:#14566b; color:#fff; text-decoration:none; border-radius:8px;'>← Bumalik</a>
         </div>");
}

/* Fetch resident */
$resident = null;
if ($sr_resident_col && !empty($req[$sr_resident_col])) {
    $r_stmt = $conn->prepare("SELECT * FROM residents WHERE resident_id = ?");
    $r_stmt->bind_param("i", $req[$sr_resident_col]);
    $r_stmt->execute();
    $resident = $r_stmt->get_result()->fetch_assoc();
}

/* Build display */
$first_name  = $resident['first_name']  ?? '';
$middle_name = $resident['middle_name'] ?? '';
$last_name   = $resident['last_name']   ?? '';
$address     = $resident['address']     ?? 'Barangay Bigaan, Calauag, Quezon';
$contact     = $resident['contact_number'] ?? '';

$full_name = trim($first_name . ' ' . ($middle_name ? $middle_name[0].'. ' : '') . $last_name);
if (empty($full_name)) $full_name = 'Resident';

/* Kunin ang certificate type — try all possible columns */
$cert_type = '';
foreach (['certificate_type', 'service_type', 'document_type', 'doc_type', 'request_type', 'type'] as $c) {
    if (in_array($c, $sr_cols) && !empty($req[$c])) {
        $cert_type = $req[$c];
        break;
    }
}
if (empty($cert_type)) $cert_type = 'Barangay Clearance';

$purpose   = $req[$sr_purpose_col] ?? '';

$date_raw = $sr_date_col && !empty($req[$sr_date_col]) ? $req[$sr_date_col] : date('Y-m-d');
$day_num    = date('j', strtotime($date_raw));
$month_name = strtoupper(date('F', strtotime($date_raw)));
$year_num   = date('Y', strtotime($date_raw));

/* Title per type */
$cert_title = 'PAGPAPATUNY';
if (stripos($cert_type, 'Indigency') !== false) $cert_title = 'PAGPAPATUNAY NG KAHIRAPAN';
elseif (stripos($cert_type, 'Residency') !== false) $cert_title = 'PAGPAPATUNAY NG PANINIRAHAN';
elseif (stripos($cert_type, 'Business') !== false) $cert_title = 'PAGPAPATUNAY NG NEGOSYO';

/* Body para per type */
$body_para1 = "ITO AY PAGPAPATUNAY na si <u>" . htmlspecialchars($full_name) . "</u>, may sapat na taong gulang at lehitimong mamamayan ng Barangay Bigaan, Calauag Quezon, na may contact no. na <u>" . htmlspecialchars($contact ?: '_______________') . "</u>.";

if (stripos($cert_type, 'Indigency') !== false) {
    $body_para2 = "PAGPAPATUNAY PARIN, na siya rin ay nabibilang sa mahihirap na pamilya sa aming barangay.";
} else {
    $body_para2 = "Pinatutunayan na siya ay may mabuting pag-uugali at walang anumang masamang rekord sa aming barangay.";
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($cert_title) ?> · <?= e($full_name) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background: #0f172a;
            padding: 30px 0;
            color: #1e293b;
        }

        .preview-bar {
            max-width: 210mm;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1e293b;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid #334155;
            gap: 12px;
            flex-wrap: wrap;
        }
        .preview-title { color: #f8fafc; font-family: sans-serif; font-size: 14px; font-weight: bold; }
        .preview-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .btn-print-cert, .btn-pdf-cert, .btn-back-cert {
            padding: 10px 20px;
            font-size: 13.5px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            font-family: sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-print-cert { background: #16a34a; color: white; }
        .btn-print-cert:hover { background: #15803d; }
        .btn-pdf-cert { background: #14566b; color: white; }
        .btn-pdf-cert:hover { background: #0d3f50; }
        .btn-back-cert { background: #475569; color: white; }
        .btn-back-cert:hover { background: #334155; }

        /* CERTIFICATE PAPER */
        .cert-paper {
            width: 210mm;
            min-height: 297mm;
            background: #fff;
            margin: 0 auto;
            padding: 15mm 18mm;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 4px double #1e3a8a;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* HEADER */
        .cert-header {
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 12px;
        }
        .cert-header h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #475569;
            font-family: 'Arial', sans-serif;
            font-weight: normal;
        }
        .cert-header h2 {
            margin: 4px 0 2px;
            font-size: 20px;
            color: #1e3a8a;
            font-family: 'Arial', sans-serif;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .cert-header .sub {
            font-size: 11px;
            color: #64748b;
            font-family: 'Arial', sans-serif;
            letter-spacing: 0.5px;
        }

        /* TITLE */
        .cert-title {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            color: #0f172a;
            margin: 30px 0;
            letter-spacing: 3px;
        }

        /* BODY */
        .cert-body {
            font-size: 15px;
            line-height: 2;
            text-align: justify;
            color: #1e293b;
        }
        .cert-body p {
            margin-bottom: 18px;
            text-indent: 40px;
        }
        .cert-body p.no-indent { text-indent: 0; }
        .cert-body u {
            font-weight: bold;
            text-decoration: underline;
            padding: 0 4px;
        }
        .cert-body .date-line { text-indent: 0; margin-top: 30px; }

        /* FOOTER */
        .cert-footer {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .seal-place {
            width: 110px;
            height: 110px;
            border: 2px dashed #94a3b8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
            padding: 8px;
            font-family: sans-serif;
            letter-spacing: 0.5px;
        }

        /* BLANK SIGNATURE — for future use */
        .signature-block { text-align: center; }
        .signature-line {
            border-top: 1.5px solid #0f172a;
            width: 260px;
            margin-top: 60px;
            padding-top: 6px;
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            color: #0f172a;
            min-height: 30px;
        }
        .signature-block .position {
            font-size: 11px;
            font-family: sans-serif;
            color: #64748b;
            display: block;
            margin-top: 4px;
        }

        /* PRINT */
        @media print {
            .preview-bar { display: none !important; }
            body { background: white; padding: 0; margin: 0; }
            .cert-paper {
                box-shadow: none;
                margin: 0 auto;
                border: 4px double #1e3a8a;
                width: 100%;
                height: 100%;
                padding: 15mm 18mm;
                page-break-after: avoid;
                page-break-before: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="preview-bar">
        <div>
            <a href="documents.php" class="btn-back-cert">← Bumalik sa Documents</a>
            <div class="preview-title" style="margin-top:6px;">📄 Official Certificate Preview</div>
        </div>
        <div class="preview-actions">
            <button type="button" class="btn-print-cert" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print Certificate
            </button>
            <button type="button" class="btn-pdf-cert" onclick="downloadCertPDF()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download as PDF
            </button>
        </div>
    </div>

    <div class="cert-paper" id="certPaper">
        <div>
            <div class="cert-header">
                <h3>Republika ng Pilipinas</h3>
                <h3>Lalawigan ng Quezon · Bayan ng Calauag</h3>
                <h2>BARANGAY BIGAAN</h2>
                <div class="sub">Tanggapan ng Punong Barangay</div>
            </div>

            <div class="cert-title"><?= e($cert_title) ?></div>

            <div class="cert-body">
                <p class="no-indent"><strong>SA KINAUUKULAN:</strong></p>
                <p><?= $body_para1 ?></p>
                <p><?= $body_para2 ?></p>
                <p class="date-line">
                    Pinatutunayan ngayong ika-<strong><?= $day_num ?></strong> ng
                    <strong><?= $month_name ?></strong>, taong <strong><?= $year_num ?></strong>,
                    sa Barangay Bigaan, Calauag Quezon upang gamitin sa anumang layunin
                    na naaayon sa batas<?= !empty($purpose) ? ': <u>' . htmlspecialchars($purpose) . '</u>' : '' ?>.
                </p>
            </div>
        </div>

        <div class="cert-footer">
            <div class="seal-place">OPISYAL NA TIAP<br>/ DRY SEAL</div>
            <div class="signature-block">
                <div class="signature-line">
                    <!-- BLANK — para sa future signature -->
                </div>
                <span class="position">Punong Barangay</span>
            </div>
        </div>
    </div>

    <script>
        function downloadCertPDF() {
            var element = document.getElementById('certPaper');
            var opt = {
                margin:       0,
                filename:     '<?= preg_replace('/[^a-z0-9]+/i', '-', $cert_type) ?>-<?= preg_replace('/[^a-z0-9]+/i', '-', $full_name) ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#ffffff' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>

</body>
</html>