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
$msg_type = 'success';

/* Helper: resolve real resident_id */
function get_real_resident_id($conn, $user_id) {
    try {
        $stmt = $conn->prepare("SELECT resident_id FROM residents WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        if ($r && intval($r['resident_id']) > 0) return intval($r['resident_id']);

        $stmt2 = $conn->prepare("SELECT resident_id FROM residents WHERE resident_id = ? LIMIT 1");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        $r2 = $stmt2->get_result()->fetch_assoc();
        return $r2 ? intval($r2['resident_id']) : null;
    } catch (Throwable $e) { return null; }
}

/* 1. FILE COMPLAINT */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_complaint'])) {
    $respondent = trim($_POST['respondent'] ?? '');
    $details    = trim($_POST['details'] ?? '');
    $location   = trim($_POST['location'] ?? '');

    $full_details = $details;
    if (!empty($location)) {
        $full_details = "Lugar: " . $location . "\n\n" . $details;
    }

    if (!empty($details) && !empty($respondent)) {
        try {
            $complainant_id = get_real_resident_id($conn, $user_id);

            if ($complainant_id === null) {
                $msg = "Kailangan mo munang i-complete ang iyong profile bago mag-sampa ng reklamo. Pumunta sa Edit Profile.";
                $msg_type = "danger";
            } else {
                $sql = "INSERT INTO complaints 
                        (complainant_id, respondent_name, incident_details, status, date_filed) 
                        VALUES (?, ?, ?, 'Pending', NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $complainant_id, $respondent, $full_details);
                if ($stmt->execute()) {
                    $msg = "Matagumpay na naipasa ang reklamo / blotter!";
                    $msg_type = "success";
                }
            }
        } catch (Throwable $e) {
            $msg = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    } else {
        $msg = "Kailangan punan ang Inirereklamo at Detalye.";
        $msg_type = "danger";
    }
}

/* 2. UPDATE STATUS (Admin only) */
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $complaint_id = intval($_POST['complaint_id'] ?? 0);
    $new_status   = trim($_POST['new_status'] ?? '');
    $valid_statuses = ['Pending', 'Ongoing', 'Resolved', 'Dismissed'];

    if ($complaint_id > 0 && in_array($new_status, $valid_statuses)) {
        try {
            $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE complaint_id = ?");
            $stmt->bind_param("si", $new_status, $complaint_id);
            if ($stmt->execute()) {
                $msg = "Na-update ang status ng complaint #$complaint_id!";
                $msg_type = "success";
            }
        } catch (Throwable $e) {
            $msg = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

/* 3. DELETE COMPLAINT */
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);

    if ($del_id > 0) {
        try {
            $can_delete = false;

            if ($is_admin) {
                $can_delete = true;
            } else {
                $check = $conn->prepare("SELECT complainant_id FROM complaints WHERE complaint_id = ?");
                $check->bind_param("i", $del_id);
                $check->execute();
                $row = $check->get_result()->fetch_assoc();

                $real_resident_id = get_real_resident_id($conn, $user_id);

                if ($row && $real_resident_id !== null &&
                    intval($row['complainant_id']) === intval($real_resident_id)) {
                    $can_delete = true;
                }
            }

            if ($can_delete) {
                $stmt = $conn->prepare("DELETE FROM complaints WHERE complaint_id = ?");
                $stmt->bind_param("i", $del_id);
                if ($stmt->execute()) {
                    $msg = "Nabura ang reklamo / blotter record.";
                    $msg_type = "success";
                }
            } else {
                $msg = "Hindi ka pwedeng magbura ng reklamong ito.";
                $msg_type = "danger";
            }
        } catch (Throwable $e) {
            $msg = "Delete error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

/* 4. FETCH COMPLAINTS */
$complaints = [];
try {
    if ($is_admin) {
        $sql = "SELECT c.*, 
                       r.first_name, r.last_name, r.middle_name
                FROM complaints c
                LEFT JOIN residents r ON c.complainant_id = r.resident_id
                ORDER BY c.date_filed DESC";
        $res = $conn->query($sql);
    } else {
        $real_resident_id = get_real_resident_id($conn, $user_id);

        if ($real_resident_id === null) {
            $res = null;
        } else {
            $sql = "SELECT c.*, 
                           r.first_name, r.last_name, r.middle_name
                    FROM complaints c
                    LEFT JOIN residents r ON c.complainant_id = r.resident_id
                    WHERE c.complainant_id = ?
                    ORDER BY c.date_filed DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $real_resident_id);
            $stmt->execute();
            $res = $stmt->get_result();
        }
    }

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $full_name = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if (empty($full_name)) $full_name = $username;

            $complaints[] = [
                'id'             => $row['complaint_id'] ?? 0,
                'complainant'    => $full_name,
                'complainant_id' => $row['complainant_id'] ?? 0,
                'respondent'     => $row['respondent_name'] ?? 'N/A',
                'details'        => $row['incident_details'] ?? '',
                'status'         => $row['status'] ?? 'Pending',
                'date_filed'     => $row['date_filed'] ?? date('Y-m-d H:i:s')
            ];
        }
    }
} catch (Throwable $e) {
    $msg = "Fetch error: " . $e->getMessage();
    $msg_type = "danger";
}

$pending_count  = 0;
$ongoing_count  = 0;
$resolved_count = 0;
foreach ($complaints as $c) {
    if ($c['status'] === 'Pending')       $pending_count++;
    elseif ($c['status'] === 'Ongoing')   $ongoing_count++;
    elseif ($c['status'] === 'Resolved')  $resolved_count++;
}

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
        :root { --night: #0c2733; --teal: #14566b; --palm: #2f8a57; --gold: #f2b84b; --line: #d9e1e4; --ink: #16262e; --muted: #5f717a; --danger:#a4262c; --danger-bg:#fdecec; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Figtree', sans-serif; color: #fff; background: var(--night); min-height: 100vh; }
        .scene { position: fixed; inset: 0; z-index: 0; background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%); }
        .scene::after { content: ""; position: absolute; inset: 0; background: rgba(8, 24, 33, 0.5); }
        .page { position: relative; z-index: 2; padding: 24px; max-width: 1340px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 12px; flex-wrap: wrap; }
        .top-bar h1 { font-family:'Bricolage Grotesque', sans-serif; display:flex; align-items:center; gap:10px; font-size: clamp(20px, 2.6vw, 26px); }
        .btn-back { background: rgba(255,255,255,.15); color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 999px; font-weight: 600; font-size: 14px; display:inline-flex; align-items:center; gap:7px; transition: background .2s; }
        .btn-back:hover { background: rgba(255,255,255,.25); }
        .role-badge { font-size: 11px; font-weight: 800; padding: 5px 12px; border-radius: 999px; text-transform: uppercase; letter-spacing: .6px; }
        .role-badge.citizen { background: rgba(47,138,87,.25); color: #6ee7b7; border: 1px solid rgba(47,138,87,.4); }
        .role-badge.admin { background: rgba(242,184,75,.25); color: var(--gold); border: 1px solid rgba(242,184,75,.4); }
        .grid { display: grid; grid-template-columns: 1fr 1.8fr; gap: 24px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .panel { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,.3); }
        .panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 20px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 10px; margin-bottom: 16px; display:flex; align-items:center; gap:10px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing:.4px; }
        .form-group input, .form-group textarea, .form-group select { padding: 12px; border: 2px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; background:#f5f8f9; transition: border-color .2s, background .2s; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline:none; border-color: var(--teal); background:#fff; }
        .btn { background: linear-gradient(135deg, var(--teal), #0d3f50); color: #fff; border: none; padding: 13px 22px; border-radius: 10px; font-weight: 800; cursor: pointer; font-family:inherit; font-size:14px; box-shadow: 0 8px 20px rgba(20,86,107,.3); width:100%; display:inline-flex; justify-content:center; align-items:center; gap:8px; transition: transform .2s; }
        .btn:hover { transform: translateY(-2px); }
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: #f5f8f9; border: 1px solid var(--line); border-radius: 12px; padding: 14px; text-align: center; }
        .stat-card .stat-value { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: 24px; line-height: 1; margin-bottom: 4px; }
        .stat-card .stat-label { font-size: 10.5px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
        .stat-card.pending .stat-value { color: #d97706; }
        .stat-card.ongoing .stat-value { color: #0284c7; }
        .stat-card.resolved .stat-value { color: #1f6e41; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #f1f5f9; color: var(--teal); text-align: left; padding: 12px; font-weight: 700; border-bottom: 2px solid var(--line); font-size: 11.5px; text-transform: uppercase; letter-spacing: .4px; }
        td { padding: 14px 12px; border-bottom: 1px solid var(--line); color: #334155; vertical-align: top; }
        td.details-cell { max-width: 280px; white-space: pre-wrap; word-break: break-word; line-height: 1.5; font-size: 13px; }
        td .date-cell { display:block; font-size: 11px; color: var(--muted); margin-top: 4px; }
        .badge { font-size: 11px; font-weight: 800; padding: 4px 11px; border-radius: 999px; text-transform: uppercase; letter-spacing: .4px; display:inline-block; }
        .badge-Pending { background: #fef3c7; color: #b45309; }
        .badge-Ongoing { background: #e0f2fe; color: #0284c7; }
        .badge-Resolved { background: #d1e7dd; color: #0f5132; }
        .badge-Dismissed { background: #e5e7eb; color: #4b5563; }
        .status-form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .status-form select { padding: 6px 8px; border-radius: 8px; border: 1.5px solid var(--line); font-size: 12.5px; font-family: inherit; background: #fff; }
        .status-form button { padding: 6px 12px; border-radius: 8px; border: none; background: var(--palm); color: #fff; font-weight: 700; font-size: 12px; cursor: pointer; font-family: inherit; }
        .status-form button:hover { background: #1f6e41; }
        .btn-delete { background: var(--danger-bg); color: var(--danger); font-size: 11.5px; font-weight: 700; padding: 6px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-top: 8px; transition: background .2s; }
        .btn-delete:hover { background: #fbd5d7; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: 600; font-size: 14px; display:flex; align-items:center; gap:8px; }
        .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #a3cfbb; }
        .alert-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid #f4c4c6; }
        .empty-state { padding: 40px 20px; text-align: center; color: var(--muted); font-style: italic; font-size: 14px; }
    </style>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1>
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M5 8l7-5 7 5M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/></svg>
            Complaints & Blotter Module
            <span class="role-badge <?= $is_admin ? 'admin' : 'citizen' ?>"><?= e($role) ?></span>
        </h1>
        <a href="../<?= $is_admin ? 'dashboard.php' : 'citizen_dashboard.php' ?>" class="btn-back">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Bumalik sa Dashboard
        </a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?>">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <div class="panel">
            <h2>Mag-sampa ng Reklamo</h2>

            <?php if (!$is_admin): ?>
                <p style="font-size:12.5px; color:var(--muted); margin-bottom:12px; line-height:1.5;">
                    Ang iyong reklamo ay direktang maiuugnay sa iyong account at makikita mo lang ang sarili mong mga reklamo.
                </p>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="file_complaint" value="1">
                <div class="form-group">
                    <label><?= $is_admin ? 'Naghahain ng Reklamo' : 'Ikaw (Naghahain)' ?></label>
                    <input type="text" value="<?= e($username) ?>" <?= $is_admin ? '' : 'disabled style="background:#eef1f3; cursor:not-allowed;"' ?>>
                </div>
                <div class="form-group">
                    <label>Inirereklamo (Respondent)</label>
                    <input type="text" name="respondent" placeholder="Pangalan ng inirereklamo..." required>
                </div>
                <div class="form-group">
                    <label>Lugar ng Insidente</label>
                    <input type="text" name="location" placeholder="e.g. Purok 3 / Kalsada">
                </div>
                <div class="form-group">
                    <label>Mga Detalye ng Reklamo</label>
                    <textarea name="details" rows="4" required placeholder="Isulat ang buong pangyayari..."></textarea>
                </div>
                <button type="submit" class="btn">I-submit ang Reklamo</button>
            </form>
        </div>

        <div class="panel">
            <h2><?= $is_admin ? 'Lahat ng Reklamo ng Barangay' : 'Aking mga Reklamo' ?></h2>

            <div class="stats-row">
                <div class="stat-card pending"><div class="stat-value"><?= $pending_count ?></div><div class="stat-label">Pending</div></div>
                <div class="stat-card ongoing"><div class="stat-value"><?= $ongoing_count ?></div><div class="stat-label">Ongoing</div></div>
                <div class="stat-card resolved"><div class="stat-value"><?= $resolved_count ?></div><div class="stat-label">Resolved</div></div>
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <?php if ($is_admin): ?><th>Naghain</th><?php endif; ?>
                            <th>Inirereklamo</th>
                            <th>Detalye</th>
                            <th>Status</th>
                            <th>Aksyon</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($complaints)): ?>
                            <tr>
                                <td colspan="<?= $is_admin ? 5 : 4 ?>">
                                    <div class="empty-state">
                                        <?= $is_admin ? 'Walang reklamo sa barangay.' : 'Wala ka pang naisusumiteng reklamo.' ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($complaints as $c):
                                $can_delete_row = $is_admin || (intval($c['complainant_id']) > 0);
                            ?>
                                <tr>
                                    <?php if ($is_admin): ?>
                                        <td><b><?= e($c['complainant']) ?></b><span class="date-cell"><?= date('M d, Y', strtotime($c['date_filed'])) ?></span></td>
                                    <?php endif; ?>
                                    <td><?= e($c['respondent']) ?></td>
                                    <td class="details-cell"><?= e($c['details']) ?></td>
                                    <td><span class="badge badge-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                                    <td>
                                        <?php if ($is_admin): ?>
                                            <form method="POST" class="status-form">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                                                <select name="new_status">
                                                    <?php foreach (['Pending','Ongoing','Resolved','Dismissed'] as $st): ?>
                                                        <option value="<?= $st ?>" <?= $c['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit">Update</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($can_delete_row): ?>
                                            <a href="complaints.php?action=delete&id=<?= $c['id'] ?>" class="btn-delete" onclick="return confirm('Sigurado ka bang gusto mong burahin ang reklamong ito?');">
                                                Delete
                                            </a>
                                        <?php endif; ?>
                                    </td>
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