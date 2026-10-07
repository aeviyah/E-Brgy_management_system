<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$role = $_SESSION['role'] ?? 'Resident';
if ($role === 'Resident') {
    header('Location: ../citizen_dashboard.php');
    exit();
}

/* Fetch Residents */
$residents = [];
try {
    $res = $conn->query("SELECT r.*, p.purok_name FROM residents r LEFT JOIN puroks p ON r.purok_id = p.purok_id ORDER BY r.last_name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) $residents[] = $row;
    }
} catch (Throwable $e) {}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Registry · E-Barangay</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --night: #0c2733; --teal: #14566b; --palm: #2f8a57; --gold: #f2b84b; --line: #d9e1e4; --ink: #16262e; --muted: #5f717a; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Figtree', sans-serif; color: #fff; background: var(--night); min-height: 100vh; }
        .scene { position: fixed; inset: 0; z-index: 0; background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%); }
        .scene::after { content: ""; position: absolute; inset: 0; background: rgba(8, 24, 33, 0.45); }
        .page { position: relative; z-index: 2; padding: 24px; max-width: 1240px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .btn-back { background: rgba(255,255,255,.15); color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 999px; font-weight: 600; font-size: 14px; backdrop-filter: blur(8px); }
        .panel { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,.3); }
        .panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 22px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 10px; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
        th { background: #f1f5f9; color: var(--teal); text-align: left; padding: 12px; font-weight: 700; border-bottom: 2px solid var(--line); }
        td { padding: 12px; border-bottom: 1px solid var(--line); color: #334155; }
        tr:hover { background: #f8fafc; }
        .badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px; background: #e0f2fe; color: #0369a1; }
    </style>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1 style="font-family:'Bricolage Grotesque', sans-serif;">👥 Resident Registry</h1>
        <a href="../dashboard.php" class="btn-back">← Bumalik sa Dashboard</a>
    </div>

    <div class="panel">
        <h2>Listahan ng mga Rehistradong Residente</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Pangalan</th>
                        <th>Purok / Zone</th>
                        <th>Kasarian</th>
                        <th>Civil Status</th>
                        <th>Contact Number</th>
                        <th>Tirahan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($residents)): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--muted);">Walang nakatalang residente sa database.</td></tr>
                    <?php else: ?>
                        <?php foreach ($residents as $r): ?>
                            <tr>
                                <td><b><?= e($r['last_name'] . ', ' . $r['first_name'] . ' ' . $r['middle_name']) ?></b></td>
                                <td><span class="badge"><?= e($r['purok_name'] ?? 'Unassigned') ?></span></td>
                                <td><?= e($r['gender'] ?? 'N/A') ?></td>
                                <td><?= e($r['civil_status'] ?? 'N/A') ?></td>
                                <td><?= e($r['contact_number'] ?? 'N/A') ?></td>
                                <td><?= e($r['address'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>