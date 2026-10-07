<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// LUMABAS SA MODULES FOLDER GAMIT ANG '../'
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$request_id = $_GET['id'] ?? 0;

// FETCH CERTIFICATE DETAILS
$stmt = $conn->prepare("
    SELECT cr.*, r.first_name, r.last_name, r.middle_name, r.address 
    FROM certificate_requests cr 
    JOIN residents r ON cr.resident_id = r.resident_id 
    WHERE cr.request_id = ?
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Hindi mahanap ang dokumentong hinahanap.");
}

$full_name = htmlspecialchars($data['first_name'] . ' ' . ($data['middle_name'] ? $data['middle_name'][0].'. ' : '') . $data['last_name']);
$address   = htmlspecialchars($data['address']);
$type      = htmlspecialchars($data['certificate_type']);
$purpose   = htmlspecialchars($data['purpose']);
$date      = date('F d, Y', strtotime($data['requested_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $type; ?> - <?= $full_name; ?></title>
    <style>
        /* FORMAL A4 PRINT PAGE SETUP */
        @page {
            size: A4 portrait;
            margin: 15mm; /* PORMAL NA MARGIN SA MISMONG PAGE PRINT */
        }
        
        * { box-sizing: border-box; }

        body { 
            font-family: 'Georgia', serif; 
            background: #0f172a; 
            padding: 30px 0; 
            color: #1e293b; 
            margin: 0; 
        }
        
        /* ACTION CONTROL BAR (PREVIEW VIEW ONLY) */
        .preview-bar { 
            max-width: 750px; 
            margin: 0 auto 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            background: #1e293b;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 1px solid #334155;
        }
        
        .preview-title { color: #f8fafc; font-family: sans-serif; font-size: 14px; font-weight: bold; }
        
        .btn-download-pdf { 
            background: #16a34a; 
            color: white; 
            border: none; 
            padding: 10px 22px; 
            font-size: 14px; 
            font-weight: bold; 
            border-radius: 6px; 
            cursor: pointer; 
            font-family: sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        
        .btn-download-pdf:hover { background: #15803d; }
        .btn-back { color: #94a3b8; text-decoration: none; font-family: sans-serif; font-size: 13px; font-weight: bold; }
        .btn-back:hover { color: white; }

        /* FORMAL & PROPORTIONAL A4 CONTAINER (WITH MARGIN SPACE) */
        .cert-paper { 
            width: 750px; 
            min-height: 960px; 
            background: white; 
            margin: 0 auto; 
            padding: 45px 55px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); 
            border: 6px double #1e3a8a; /* PORMAL NA NAKAPALIGID NA DOUBLE BORDER */
            position: relative; 
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .cert-header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 12px; }
        .cert-header h3 { margin: 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; color: #475569; font-family: 'Arial', sans-serif; }
        .cert-header h2 { margin: 6px 0 2px; font-size: 21px; color: #1e3a8a; font-family: 'Arial', sans-serif; font-weight: 800; letter-spacing: 0.5px; }
        .cert-title { text-align: center; font-size: 24px; font-weight: bold; text-transform: uppercase; text-decoration: underline; color: #0f172a; margin: 35px 0 30px; letter-spacing: 2px; }
        .cert-body { font-size: 15px; line-height: 2; text-align: justify; text-indent: 40px; color: #1e293b; }
        .cert-footer { margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end; }
        .seal-place { width: 105px; height: 105px; border: 2px dashed #94a3b8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 9px; color: #94a3b8; text-align: center; }
        .signature-block { text-align: center; }
        .signature-line { border-top: 1px solid #0f172a; width: 210px; margin-top: 40px; font-weight: bold; font-family: sans-serif; font-size: 13px; color: #0f172a; }
        
        /* PRINT SPECIFIC FORMAL STYLES */
        @media print {
            .preview-bar { display: none !important; }
            body { background: white; padding: 0; margin: 0; }
            .cert-paper { 
                box-shadow: none; 
                margin: 0 auto; 
                border: 6px double #1e3a8a; 
                width: 100%; 
                height: 100%; 
                padding: 40px 50px;
                page-break-after: avoid;
                page-break-before: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- PREVIEW / CONTROL BAR -->
    <div class="preview-bar">
        <div>
            <a href="documents.php" class="btn-back">← Bumalik sa Document Requests</a>
            <div class="preview-title" style="margin-top: 4px;">📄 Official Certificate Preview</div>
        </div>
        <button onclick="window.print()" class="btn-download-pdf">
            📥 Save as PDF / Print
        </button>
    </div>

    <!-- CERTIFICATE PAPER CONTAINER -->
    <div class="cert-paper">
        <div>
            <div class="cert-header">
                <h3>Republika ng Pilipinas • Lalawigan ng Laguna</h3>
                <h2>BARANGAY CENTRAL PORTAL</h2>
                <p style="margin:0; font-size: 11px; color: #64748b; font-family: sans-serif;">Tanggapan ng Punong Barangay</p>
            </div>

            <div class="cert-title"><?= strtoupper($type); ?></div>

            <div class="cert-body">
                <p><strong>SA MGA KINAUUKULAN:</strong></p>
                <p>Ito ay pagpapatunay na si <strong><u><?= $full_name; ?></u></strong>, na naninirahan sa <u><?= $address; ?></u>, ay isang opisyal at rehistradong mamamayan ng ating barangay.</p>
                <p>Pinatutunayan na si <?= $full_name; ?> ay may mabuting pag-uugali, walang anumang masamang rekord sa ating barangay, at kwalipikado para sa layuning: <strong><u><?= $purpose; ?></u></strong>.</p>
                <p>Ibinigay ang sertipikasyong ito ngayong ika-<strong><?= $date; ?></strong> sa kahilingan ng nabanggit na mamamayan para sa anumang legal na layunin.</p>
            </div>
        </div>

        <div class="cert-footer">
            <div class="seal-place">OPISYAL NA TIAP / DRY SEAL</div>
            <div class="signature-block">
                <div class="signature-line">HON. BARANGAY CAPTAIN</div>
                <span style="font-size: 11px; font-family: sans-serif; color: #64748b;">Punong Barangay</span>
            </div>
        </div>
    </div>

</body>
</html>