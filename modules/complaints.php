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

/* File Complaint */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_complaint'])) {
    $complainant = trim($_POST['complainant'] ?? $username);
    $respondent  = trim($_POST['respondent'] ?? '');
    $details     = trim($_POST['details'] ?? '');
    $location    = trim($_POST['location'] ?? '');

    if (!empty($details)) {
        try {
            $stmt = $conn->prepare("INSERT INTO cases (complainant, respondent, details, incident_location, status) VALUES (?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("ssss", $complainant, $respondent, $details, $location);
            if ($stmt->execute()) {
                $msg = "Matagumpay na naipasa ang reklamo / blotter!";
                $msg_type = "success";
            }
        } catch (Throwable $e) {
            $msg = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

/* Update Case Status */
if ($is_admin && isset($_GET['action']) && isset($_GET['case_id'])) {
    $case_id = intval($_GET['case_id']);
    $act     = $_GET['action'];
    $status  = ($act === 'resolve') ? 'Resolved' : (($act === 'ongoing') ? 'Ongoing' : '');

    if ($case_id > 0 && !empty($status)) {
        $conn->query("UPDATE cases SET status = '$status' WHERE case_id = $case_id OR id = $case_id");
        $msg = "Na-update ang status ng kaso!";
        $msg_type = "success";
    }
}

/* Universal Fetch Cases */
$cases = [];
try {
    $res = $conn->query("SELECT * FROM cases ORDER BY 1 DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cases[] = [
                'id'          => $row['case_id'] ?? $row['id'] ?? 0,
                'complainant' => $row['complainant'] ?? $row['complainant_name'] ?? $row['resident_name'] ?? 'Resident',
                'respondent'  => $row['respondent'] ?? $row['respondent_name'] ?? 'N/A',
                'details'     => $row['details'] ?? $row['description'] ?? $row['complaint_details'] ?? '',
                'status'      => $row['status'] ?? 'Pending'
            ];
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
    <title>Complaints & Blotter · E-Barangay</title>
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
        .grid { display: grid; grid-template-columns: 1fr 1.8fr; gap: 24px; }
        @media (max-width: 850px) { .grid { grid-template-columns: 1fr; } }
        .panel { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,.3); }
        .panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 22px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 10px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
        .form-group input, .form-group textarea { padding: 10px; border: 1.5px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; }
        .btn { background: var(--teal); color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #f1f5f9; color: var(--teal); text-align: left; padding: 12px; font-weight: 700; border-bottom: 2px solid var(--line); }
        td { padding: 12px; border-bottom: 1px solid var(--line); color: #334155; }
        .badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px; text-transform: uppercase; }
        .badge-Pending { background: #fef3c7; color: #d97706; }
        .badge-Ongoing { background: #e0f2fe; color: #0284c7; }
        .badge-Resolved { background: #d1e7dd; color: #0f5132; }
        .btn-sm { font-size: 11px; padding: 4px 8px; border-radius: 6px; text-decoration: none; font-weight: 700; }
        .btn-resolve { background: #d1e7dd; color: #0f5132; }
    </style>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1 style="font-family:'Bricolage Grotesque', sans-serif;">⚖️ Complaints & Blotter Module</h1>
        <a href="../<?= $is_admin ? 'dashboard.php' : 'citizen_dashboard.php' ?>" class="btn-back">← Bumalik sa Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div style="background:<?= $msg_type==='success'?'#d1e7dd':'#fee2e2' ?>; color:<?= $msg_type==='success'?'#0f5132':'#dc2626' ?>; padding:12px; border-radius:10px; margin-bottom:16px; font-weight:600; font-size:14px;">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <!-- Form -->
        <div class="panel">
            <h2>⚖️ Mag-sampa ng Reklamo / Blotter</h2>
            <form method="POST">
                <input type="hidden" name="file_complaint" value="1">
                <div class="form-group"><label>Naghahain ng Reklamo</label><input type="text" name="complainant" value="<?= e($username) ?>" required></div>
                <div class="form-group"><label>Inirerekla (Respondent)</label><input type="text" name="respondent" placeholder="Pangalan ng inirereklamo..." required></div>
                <div class="form-group"><label>Lugar ng Insidente</label><input type="text" name="location" placeholder="e.g. Purok 3 / Kalsada"></div>
                <div class="form-group"><label>Mga Detalye ng Reklamo</label><textarea name="details" rows="3" required placeholder="Isulat ang buong pangyayari..."></textarea></div>
                <button type="submit" class="btn">I-submit ang Blotter</button>
            </form>
        </div>

        <!-- Cases Table -->
        <div class="panel">
            <h2>📋 Listahan ng mga Reklamo / Kasalukuyang Kasaysayan</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Naghain</th>
                            <th>Inirereklamo</th>
                            <th>Detalye</th>
                            <th>Status</th>
                            <?php if ($is_admin): ?><th>Aksyon</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cases)): ?>
                            <tr><td colspan="<?= $is_admin ? 5 : 4 ?>" style="text-align:center; color:var(--muted);">Walang blotter records.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cases as $c): ?>
                                <tr>
                                    <td><b><?= e($c['complainant']) ?></b></td>
                                    <td><?= e($c['respondent']) ?></td>
                                    <td><?= e($c['details']) ?></td>
                                    <td><span class="badge badge-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                                    <?php if ($is_admin): ?>
                                        <td>
                                            <?php if (($c['status']) !== 'Resolved'): ?>
                                                <a href="complaints.php?action=resolve&case_id=<?= $c['id'] ?>" class="btn-sm btn-resolve">✓ I-resolve</a>
                                            <?php else: ?>
                                                <span style="font-size:12px; color:var(--muted);">Resolved</span>
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