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

/* OFFICIAL ACTIONS */
if ($is_admin) {
    // Add New Facility
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_facility'])) {
        $name = trim($_POST['facility_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $cap  = intval($_POST['capacity'] ?? 0);

        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO facilities (facility_name, description, capacity) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $name, $desc, $cap);
            if ($stmt->execute()) {
                $msg = "Facility added successfully!";
                $msg_type = "success";
            }
        }
    }

    // Update Reservation Status (Approve/Reject)
    if (isset($_GET['action']) && isset($_GET['res_id'])) {
        $res_id = intval($_GET['res_id']);
        $act    = $_GET['action'];
        $status = ($act === 'approve') ? 'Approved' : (($act === 'reject') ? 'Rejected' : '');

        if ($res_id > 0 && !empty($status)) {
            $stmt = $conn->prepare("UPDATE facility_reservations SET status = ? WHERE reservation_id = ?");
            $stmt->bind_param("si", $status, $res_id);
            $stmt->execute();
            $msg = "Reservation $status!";
            $msg_type = "success";
        }
    }
}

/* ============================================
   DELETE FACILITY (Admin only — BAGONG DAGDAG)
   ============================================ */
if ($is_admin && isset($_GET['action']) && $_GET['action'] === 'delete_facility' && isset($_GET['facility_id'])) {
    $fac_id = intval($_GET['facility_id']);
    if ($fac_id > 0) {
        try {
            /* Check kung may active reservations */
            $chk = $conn->prepare("SELECT COUNT(*) AS c FROM facility_reservations WHERE facility_id = ? AND status IN ('Pending', 'Approved')");
            $chk->bind_param("i", $fac_id);
            $chk->execute();
            $cnt = $chk->get_result()->fetch_assoc()['c'] ?? 0;

            if ($cnt > 0) {
                $msg = "Hindi pwedeng burahin — may $cnt active reservations pa sa facility na ito.";
                $msg_type = "danger";
            } else {
                $stmt = $conn->prepare("DELETE FROM facilities WHERE facility_id = ?");
                $stmt->bind_param("i", $fac_id);
                if ($stmt->execute()) {
                    $msg = "Nabura ang facility.";
                    $msg_type = "success";
                }
            }
        } catch (Throwable $e) {
            $msg = "Delete error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

/* ============================================
   DELETE RESERVATION (Admin: lahat, Citizen: sarili — BAGONG DAGDAG)
   ============================================ */
if (isset($_GET['action']) && $_GET['action'] === 'delete_res' && isset($_GET['res_id'])) {
    $del_res_id = intval($_GET['res_id']);

    if ($del_res_id > 0) {
        try {
            $can_delete = false;

            if ($is_admin) {
                $can_delete = true;
            } else {
                $chk = $conn->prepare("SELECT user_id FROM facility_reservations WHERE reservation_id = ?");
                $chk->bind_param("i", $del_res_id);
                $chk->execute();
                $row = $chk->get_result()->fetch_assoc();
                if ($row && intval($row['user_id']) === intval($user_id)) {
                    $can_delete = true;
                }
            }

            if ($can_delete) {
                $stmt = $conn->prepare("DELETE FROM facility_reservations WHERE reservation_id = ?");
                $stmt->bind_param("i", $del_res_id);
                if ($stmt->execute()) {
                    $msg = "Nabura ang reservation record.";
                    $msg_type = "success";
                }
            } else {
                $msg = "Hindi ka pwedeng magbura ng reservation na ito.";
                $msg_type = "danger";
            }
        } catch (Throwable $e) {
            $msg = "Delete error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

/* CITIZEN ACTIONS */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reservation'])) {
    $facility_id = intval($_POST['facility_id'] ?? 0);
    $purpose     = trim($_POST['purpose'] ?? '');
    $res_date    = $_POST['reservation_date'] ?? '';
    $start_time  = $_POST['start_time'] ?? '';
    $end_time    = $_POST['end_time'] ?? '';

    if ($facility_id > 0 && !empty($purpose) && !empty($res_date)) {
        $stmt = $conn->prepare("INSERT INTO facility_reservations (facility_id, user_id, applicant_name, purpose, reservation_date, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssss", $facility_id, $user_id, $username, $purpose, $res_date, $start_time, $end_time);
        if ($stmt->execute()) {
            $msg = "Reservation request submitted successfully! Pending approval.";
            $msg_type = "success";
        }
    }
}

/* FETCH DATA */
$facilities = [];
$fac_q = $conn->query("SELECT * FROM facilities ORDER BY facility_name ASC");
if ($fac_q) while ($r = $fac_q->fetch_assoc()) $facilities[] = $r;

$reservations = [];
$res_sql = $is_admin 
    ? "SELECT r.*, f.facility_name FROM facility_reservations r JOIN facilities f ON r.facility_id = f.facility_id ORDER BY r.created_at DESC"
    : "SELECT r.*, f.facility_name FROM facility_reservations r JOIN facilities f ON r.facility_id = f.facility_id WHERE r.user_id = $user_id ORDER BY r.created_at DESC";
$res_q = $conn->query($res_sql);
if ($res_q) while ($r = $res_q->fetch_assoc()) $reservations[] = $r;

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilities & Reservations · E-Barangay</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --night: #0c2733; --teal: #14566b; --palm: #2f8a57; --gold: #f2b84b; --line: #d9e1e4; --ink: #16262e; --muted: #5f717a; --danger:#a4262c; --danger-bg:#fdecec; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Figtree', sans-serif; color: #fff; background: var(--night); min-height: 100vh; }
        .scene { position: fixed; inset: 0; z-index: 0; background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%); }
        .scene::after { content: ""; position: absolute; inset: 0; background: rgba(8, 24, 33, 0.45); }
        .page { position: relative; z-index: 2; padding: 24px; max-width: 1200px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 12px; flex-wrap: wrap; }
        .btn-back { background: rgba(255,255,255,.15); color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 999px; font-weight: 600; font-size: 14px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 850px) { .grid { grid-template-columns: 1fr; } }
        .panel { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 24px; box-shadow: 0 15px 35px rgba(0,0,0,.3); margin-bottom: 24px; }
        .panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 20px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 8px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px; border: 1.5px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; }
        .btn { background: var(--teal); color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .item-card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 14px; margin-bottom: 10px; }
        .badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px; text-transform: uppercase; }
        .badge-Pending { background: #fef3c7; color: #d97706; }
        .badge-Approved { background: #d1e7dd; color: #0f5132; }
        .badge-Rejected { background: #fee2e2; color: #dc2626; }
        .btn-sm { font-size: 11px; padding: 5px 10px; border-radius: 6px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .btn-approve { background: #d1e7dd; color: #0f5132; }
        .btn-reject { background: #fee2e2; color: #dc2626; }
        .btn-delete { background: var(--danger-bg); color: var(--danger); border: 1px solid #f4c4c6; }
        .btn-delete:hover { background: #fbd5d7; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .alert-success { background: #d1e7dd; color: #0f5132; }
        .alert-danger { background: var(--danger-bg); color: var(--danger); }
    </style>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1 style="font-family:'Bricolage Grotesque', sans-serif;">🏛️ Facilities & Venue Reservations</h1>
        <a href="../<?= $is_admin ? 'dashboard.php' : 'citizen_dashboard.php' ?>" class="btn-back">← Back to Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?>">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <!-- Left: Available Facilities or Add Facility -->
        <div>
            <?php if ($is_admin): ?>
                <div class="panel">
                    <h2>➕ Add New Barangay Facility</h2>
                    <form method="POST">
                        <input type="hidden" name="add_facility" value="1">
                        <div class="form-group"><label>Facility Name</label><input type="text" name="facility_name" required placeholder="e.g. Barangay Basketball Court"></div>
                        <div class="form-group"><label>Description</label><textarea name="description" rows="2" placeholder="Facility specs or details..."></textarea></div>
                        <div class="form-group"><label>Capacity (Persons)</label><input type="number" name="capacity" value="100"></div>
                        <button type="submit" class="btn">Add Facility</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="panel">
                    <h2>📝 Request Venue Reservation</h2>
                    <form method="POST">
                        <input type="hidden" name="request_reservation" value="1">
                        <div class="form-group">
                            <label>Select Facility</label>
                            <select name="facility_id" required>
                                <option value="">-- Choose Venue --</option>
                                <?php foreach ($facilities as $f): ?>
                                    <option value="<?= $f['facility_id'] ?>"><?= e($f['facility_name']) ?> (Capacity: <?= $f['capacity'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Purpose / Event Name</label><input type="text" name="purpose" required placeholder="e.g. Birthday Party / Sports Practice"></div>
                        <div class="form-group"><label>Reservation Date</label><input type="date" name="reservation_date" required></div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div class="form-group"><label>Start Time</label><input type="time" name="start_time" required></div>
                            <div class="form-group"><label>End Time</label><input type="time" name="end_time" required></div>
                        </div>
                        <button type="submit" class="btn">Submit Reservation Request</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="panel">
                <h2>🏛️ Barangay Facilities List</h2>
                <?php if (empty($facilities)): ?>
                    <p style="font-size:13px; color:var(--muted);">Walang nakatalang facility.</p>
                <?php else: ?>
                    <?php foreach ($facilities as $f): ?>
                        <div class="item-card">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                                <div style="flex:1;">
                                    <b style="color:var(--teal); font-size:16px;"><?= e($f['facility_name']) ?></b>
                                    <p style="font-size:13px; color:var(--muted); margin:4px 0;"><?= e($f['description']) ?></p>
                                    <small style="font-weight:700;">Capacity: <?= e($f['capacity']) ?> persons</small>
                                </div>
                                <?php if ($is_admin): ?>
                                    <a href="facilities.php?action=delete_facility&facility_id=<?= $f['facility_id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Sigurado ka bang gusto mong burahin ang facility na ito?');">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                                        Delete
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Reservations Log -->
        <div>
            <div class="panel">
                <h2>📋 <?= $is_admin ? 'Manage All Reservations' : 'My Reservation Requests' ?></h2>
                <?php if (empty($reservations)): ?>
                    <p style="font-size:13px; color:var(--muted);">No reservation records found.</p>
                <?php else: ?>
                    <?php foreach ($reservations as $r):
                        $can_delete_res = $is_admin || (intval($r['user_id']) === intval($user_id));
                    ?>
                        <div class="item-card">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                                <b style="color:var(--teal);"><?= e($r['facility_name']) ?></b>
                                <span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span>
                            </div>
                            <div style="font-size:13px; margin:6px 0; color:#334155;">
                                <b>Applicant:</b> <?= e($r['applicant_name']) ?><br>
                                <b>Purpose:</b> <?= e($r['purpose']) ?><br>
                                <b>Date & Time:</b> <?= date('M d, Y', strtotime($r['reservation_date'])) ?> (<?= date('h:i A', strtotime($r['start_time'])) ?> - <?= date('h:i A', strtotime($r['end_time'])) ?>)
                            </div>
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <?php if ($is_admin && $r['status'] === 'Pending'): ?>
                                    <a href="facilities.php?action=approve&res_id=<?= $r['reservation_id'] ?>" class="btn-sm btn-approve">✓ Approve</a>
                                    <a href="facilities.php?action=reject&res_id=<?= $r['reservation_id'] ?>" class="btn-sm btn-reject">✕ Reject</a>
                                <?php endif; ?>

                                <?php if ($can_delete_res): ?>
                                    <a href="facilities.php?action=delete_res&res_id=<?= $r['reservation_id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Burahin ang reservation na ito?');">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                                        Delete
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>