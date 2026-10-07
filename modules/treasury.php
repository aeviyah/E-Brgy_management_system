<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$role     = $_SESSION['role'] ?? 'Resident';
$is_admin = ($role !== 'Resident');

$msg = '';

/* Add Manual Treasury Collection */
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $payer  = trim($_POST['payer_name'] ?? '');
    $type   = trim($_POST['payment_type'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $method = trim($_POST['payment_method'] ?? 'Cash');
    $or_num = trim($_POST['or_number'] ?? ('OR-' . date('Ymd') . '-' . rand(100, 999)));

    if (!empty($payer) && $amount > 0) {
        try {
            $stmt = $conn->prepare("INSERT INTO treasury (payer_name, payment_type, amount, payment_method, or_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdss", $payer, $type, $amount, $method, $or_num);
            if ($stmt->execute()) {
                $msg = "Matagumpay na naitala ang koleksyon!";
            }
        } catch (Throwable $e) {
            $msg = "Error: " . $e->getMessage();
        }
    }
}

/* Fetch Treasury Records */
$records = [];
$total_revenue = 0;
try {
    $res = $conn->query("SELECT * FROM treasury ORDER BY created_at DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $records[] = $row;
            $total_revenue += floatval($row['amount'] ?? 0);
        }
    }
} catch (Throwable $e) {}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treasury & Revenue · E-Barangay</title>
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
        .grid { display: grid; grid-template-columns: <?= $is_admin ? '1fr 2fr' : '1fr' ?>; gap: 24px; }
        .panel { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,.3); }
        .panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 22px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 10px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
        .form-group input, .form-group select { padding: 10px; border: 1.5px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; }
        .btn { background: var(--teal); color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        
        .stat-box { background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 12px; padding: 16px; margin-bottom: 16px; color: #0369a1; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #f1f5f9; color: var(--teal); text-align: left; padding: 12px; font-weight: 700; border-bottom: 2px solid var(--line); }
        td { padding: 12px; border-bottom: 1px solid var(--line); color: #334155; }
        .badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px; background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1 style="font-family:'Bricolage Grotesque', sans-serif;">💳 Treasury & Financial Transparency</h1>
        <a href="../<?= $is_admin ? 'dashboard.php' : 'citizen_dashboard.php' ?>" class="btn-back">← Bumalik sa Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div style="background:#d1e7dd; color:#0f5132; padding:12px; border-radius:10px; margin-bottom:16px; font-weight:600; font-size:14px;">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <?php if ($is_admin): ?>
            <!-- Form -->
            <div class="panel">
                <h2>➕ Magtala ng Koleksyon / Bayad</h2>
                <form method="POST">
                    <input type="hidden" name="add_payment" value="1">
                    <div class="form-group"><label>Nagbayad (Payer Name)</label><input type="text" name="payer_name" required placeholder="Pangalan ng residente"></div>
                    <div class="form-group">
                        <label>Uri ng Bayad</label>
                        <select name="payment_type" required>
                            <option value="Barangay Clearance Fee">Barangay Clearance Fee</option>
                            <option value="Business Permit Fee">Business Permit Fee</option>
                            <option value="Facility Rental Fee">Facility Rental Fee</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Paraan ng Bayad</label>
                        <select name="payment_method">
                            <option value="Cash">Cash</option>
                            <option value="GCash">GCash</option>
                            <option value="Maya">Maya</option>
                            <option value="MariBank">MariBank</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Halaga (PHP)</label><input type="number" step="0.01" name="amount" required placeholder="50.00"></div>
                    <div class="form-group"><label>OR Number</label><input type="text" name="or_number" placeholder="Kusa itong gagawin kung walang nakatala"></div>
                    <button type="submit" class="btn">I-save ang Koleksyon</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Treasury Table -->
        <div class="panel">
            <div class="stat-box">
                <span style="font-size:12px; font-weight:700; text-transform:uppercase;">Kabuuan ng Naitalang Koleksyon</span>
                <h2 style="font-size:28px; border-bottom:none; margin-bottom:0; padding-bottom:0; color:#0284c7;">₱<?= number_format($total_revenue, 2) ?></h2>
            </div>

            <h2>📋 Financial Transaction Records</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>OR Number</th>
                            <th>Nagbayad</th>
                            <th>Uri ng Bayad</th>
                            <th>Method</th>
                            <th>Halaga</th>
                            <th>Petsa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--muted);">Walang treasury records.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td><b><?= e($r['or_number'] ?? 'N/A') ?></b></td>
                                    <td><?= e($r['payer_name'] ?? 'N/A') ?></td>
                                    <td><?= e($r['payment_type'] ?? 'Fee') ?></td>
                                    <td><span class="badge"><?= e($r['payment_method'] ?? 'Cash') ?></span></td>
                                    <td style="color:var(--palm); font-weight:700;">₱<?= number_format(floatval($r['amount'] ?? 0), 2) ?></td>
                                    <td><?= date('M d, Y', strtotime($r['created_at'] ?? 'now')) ?></td>
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