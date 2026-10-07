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
$username = $_SESSION['username'] ?? 'User';
$role     = $_SESSION['role'] ?? 'Resident';
$is_admin = ($role !== 'Resident');

$msg = '';
$msg_type = '';

/* Rates for Documents */
$doc_fees = [
    'Barangay Clearance' => 50.00,
    'Certificate of Indigency' => 0.00,
    'Certificate of Residency' => 50.00,
    'Business Permit Clearance' => 150.00
];

/* Process Request (Citizen) */
if (!$is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_doc'])) {
    $doc_type = trim($_POST['doc_type'] ?? '');
    $purpose  = trim($_POST['purpose'] ?? '');
    $method   = trim($_POST['payment_method'] ?? 'Cash');
    $ref_num  = trim($_POST['reference_number'] ?? '');
    $fee      = $doc_fees[$doc_type] ?? 0.00;

    if (!empty($doc_type) && !empty($purpose)) {
        try {
            // Flexible query insertion
            $stmt = $conn->prepare("INSERT INTO service_requests (user_id, resident_name, service_type, purpose, amount, payment_method, reference_number, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'Unpaid')");
            $stmt->bind_param("isssdss", $user_id, $username, $doc_type, $purpose, $fee, $method, $ref_num);
            if ($stmt->execute()) {
                $msg = "Matagumpay na naipasa ang request! Bayad: ₱" . number_format($fee, 2);
                $msg_type = "success";
            }
        } catch (Throwable $e) {
            try {
                // Fallback query if columns vary
                $stmt = $conn->prepare("INSERT INTO service_requests (resident_name, service_type, purpose, status) VALUES (?, ?, ?, 'Pending')");
                $stmt->bind_param("sss", $username, $doc_type, $purpose);
                $stmt->execute();
                $msg = "Matagumpay na naipasa ang request!";
                $msg_type = "success";
            } catch (Throwable $ex) {
                $msg = "Error: " . $ex->getMessage();
                $msg_type = "danger";
            }
        }
    }
}

/* Update Request & Mark Paid (Official) */
if ($is_admin && isset($_GET['action']) && isset($_GET['id'])) {
    $req_id = intval($_GET['id']);
    $act    = $_GET['action'];

    if ($req_id > 0) {
        if ($act === 'approve') {
            $conn->query("UPDATE service_requests SET status = 'Approved', payment_status = 'Paid' WHERE request_id = $req_id OR id = $req_id");
            
            // Auto-log to Treasury
            $fetch_req = $conn->query("SELECT * FROM service_requests WHERE request_id = $req_id OR id = $req_id");
            if ($fetch_req && $r = $fetch_req->fetch_assoc()) {
                $payer  = $conn->real_escape_string($r['resident_name'] ?? 'Resident');
                $stype  = $conn->real_escape_string($r['service_type'] ?? 'Clearance Fee');
                $amt    = floatval($r['amount'] ?? 50.00);
                $pmeth  = $conn->real_escape_string($r['payment_method'] ?? 'Cash');
                $ref    = $conn->real_escape_string($r['reference_number'] ?? '');
                $or_num = 'OR-' . date('Ymd') . '-' . rand(100, 999);

                $conn->query("INSERT INTO treasury (payer_name, payment_type, amount, payment_method, reference_number, or_number) VALUES ('$payer', '$stype', $amt, '$pmeth', '$ref', '$or_num')");
            }
            $msg = "Inaprubahan at naisama na sa Treasury Records!";
            $msg_type = "success";
        } elseif ($act === 'reject') {
            $conn->query("UPDATE service_requests SET status = 'Rejected' WHERE request_id = $req_id OR id = $req_id");
            $msg = "Tinanggihan ang request.";
            $msg_type = "danger";
        }
    }
}

/* Fetch Document Requests */
$requests = [];
try {
    $sql = $is_admin 
        ? "SELECT * FROM service_requests ORDER BY created_at DESC" 
        : "SELECT * FROM service_requests WHERE user_id = $user_id OR resident_name = '$username' ORDER BY created_at DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) $requests[] = $row;
    }
} catch (Throwable $e) {}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Requests · E-Barangay</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --night: #0c2733; --teal: #14566b; --palm: #2f8a57; --gold: #f2b84b; --line: #d9e1e4; --ink: #16262e; --muted: #5f717a; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Figtree', sans-serif; color: #fff; background: var(--night); min-height: 100vh; }
        .scene { position: fixed; inset: 0; z-index: 0; background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%); }
        .scene::after { content: ""; position: absolute; inset: 0; background: rgba(8, 24, 33, 0.45); }
        .page { position: relative; z-index: 2; padding: 24px; max-width: 1240px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .btn-back { background: rgba(255,255,255,.15); color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 999px; font-weight: 600; font-size: 14px; }
        .grid { display: grid; grid-template-columns: <?= $is_admin ? '1fr' : '1fr 1.8fr' ?>; gap: 24px; }
        .panel { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,.3); }
        .panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 22px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 10px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px; border: 1.5px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; }
        .btn { background: var(--teal); color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #f1f5f9; color: var(--teal); text-align: left; padding: 12px; font-weight: 700; border-bottom: 2px solid var(--line); }
        td { padding: 12px; border-bottom: 1px solid var(--line); color: #334155; }
        .badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px; text-transform: uppercase; }
        .badge-Pending { background: #fef3c7; color: #d97706; }
        .badge-Approved { background: #d1e7dd; color: #0f5132; }
        .badge-Rejected { background: #fee2e2; color: #dc2626; }
        .btn-sm { font-size: 11px; padding: 4px 8px; border-radius: 6px; text-decoration: none; font-weight: 700; }
        .btn-approve { background: #d1e7dd; color: #0f5132; }
        .btn-reject { background: #fee2e2; color: #dc2626; }
    </style>
    <script>
        function updateFee() {
            var doc = document.getElementById('doc_select').value;
            var fee = 0;
            if (doc === 'Barangay Clearance') fee = 50;
            if (doc === 'Certificate of Residency') fee = 50;
            if (doc === 'Business Permit Clearance') fee = 150;
            document.getElementById('fee_disp').innerText = '₱' + fee.toFixed(2);
        }
        function toggleRef() {
            var method = document.getElementById('pay_method').value;
            var refBox = document.getElementById('ref_box');
            if (method === 'Cash') {
                refBox.style.display = 'none';
            } else {
                refBox.style.display = 'flex';
            }
        }
    </script>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1 style="font-family:'Bricolage Grotesque', sans-serif;">📄 Document & Clearance Requests</h1>
        <a href="../<?= $is_admin ? 'dashboard.php' : 'citizen_dashboard.php' ?>" class="btn-back">← Bumalik sa Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div style="background:<?= $msg_type==='success'?'#d1e7dd':'#fee2e2' ?>; color:<?= $msg_type==='success'?'#0f5132':'#dc2626' ?>; padding:12px; border-radius:10px; margin-bottom:16px; font-weight:600; font-size:14px;">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <?php if (!$is_admin): ?>
            <!-- Request Form (Citizen) -->
            <div class="panel">
                <h2>📝 Mag-request ng Dokumento</h2>
                <form method="POST">
                    <input type="hidden" name="request_doc" value="1">
                    <div class="form-group">
                        <label>Uri ng Dokumento</label>
                        <select name="doc_type" id="doc_select" required onchange="updateFee()">
                            <option value="">-- Pumili ng Uri --</option>
                            <option value="Barangay Clearance">Barangay Clearance (₱50.00)</option>
                            <option value="Certificate of Indigency">Certificate of Indigency (LIBRE)</option>
                            <option value="Certificate of Residency">Certificate of Residency (₱50.00)</option>
                            <option value="Business Permit Clearance">Business Permit Clearance (₱150.00)</option>
                        </select>
                    </div>

                    <div style="background:#f0f9ff; border:1px solid #bae6fd; padding:10px; border-radius:10px; margin-bottom:12px; font-size:13px; color:#0369a1;">
                        <b>Certification Fee:</b> <span id="fee_disp" style="font-size:16px; font-weight:800; color:var(--teal);">₱0.00</span>
                    </div>

                    <div class="form-group">
                        <label>Paraan ng Pagbabayad (Payment Method)</label>
                        <select name="payment_method" id="pay_method" onchange="toggleRef()" required>
                            <option value="Cash">Cash (Over the counter sa Brgy Hall)</option>
                            <option value="GCash">GCash E-Wallet</option>
                            <option value="Maya">Maya E-Wallet</option>
                            <option value="MariBank">MariBank Digital Banking</option>
                        </select>
                    </div>

                    <div class="form-group" id="ref_box" style="display:none;">
                        <label>GCash / Maya / MariBank Reference No.</label>
                        <input type="text" name="reference_number" placeholder="e.g. 100239485712">
                    </div>

                    <div class="form-group">
                        <label>Layunin (Purpose)</label>
                        <textarea name="purpose" rows="3" required placeholder="Isulat ang dahilan ng pag-request..."></textarea>
                    </div>

                    <button type="submit" class="btn">I-submit at Mag-request</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Requests Table -->
        <div class="panel">
            <h2>📋 <?= $is_admin ? 'Lahat ng Hiling na Dokumento' : 'Aking mga Inilapit na Request' ?></h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Naghiling</th>
                            <th>Dokumento</th>
                            <th>Bayad & Method</th>
                            <th>Status</th>
                            <?php if ($is_admin): ?><th>Aksyon</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="<?= $is_admin ? 5 : 4 ?>" style="text-align:center; color:var(--muted);">Walang record na nahanap.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td><b><?= e($r['resident_name'] ?? 'Resident') ?></b></td>
                                    <td>
                                        <b><?= e($r['service_type'] ?? 'Clearance') ?></b><br>
                                        <small style="color:var(--muted);"><?= e($r['purpose'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        ₱<?= number_format(floatval($r['amount'] ?? 0), 2) ?><br>
                                        <small class="badge" style="background:#f1f5f9; color:#475569;"><?= e($r['payment_method'] ?? 'Cash') ?></small>
                                        <?php if (!empty($r['reference_number'])): ?>
                                            <br><small style="font-size:10px; color:var(--muted);">Ref: <?= e($r['reference_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-<?= e($r['status'] ?? 'Pending') ?>"><?= e($r['status'] ?? 'Pending') ?></span></td>
                                    <?php if ($is_admin): ?>
                                        <td>
                                            <?php if (($r['status'] ?? '') === 'Pending'): ?>
                                                <a href="documents.php?action=approve&id=<?= $r['request_id'] ?? $r['id'] ?>" class="btn-sm btn-approve">✓ Aprubahan & Resibo</a>
                                                <a href="documents.php?action=reject&id=<?= $r['request_id'] ?? $r['id'] ?>" class="btn-sm btn-reject">✕ Tanggihan</a>
                                            <?php else: ?>
                                                <span style="font-size:12px; color:var(--muted);">Naisagawa na</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>