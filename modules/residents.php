<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

/* ============================================================
   ACCESS CONTROL — ADMIN ONLY
   Residents are bounced to their dashboard.
   ============================================================ */
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$role     = $_SESSION['role'] ?? 'Resident';
$is_admin = ($role !== 'Resident');

if (!$is_admin) {
    header('Location: ../citizen_dashboard.php');
    exit();
}

$current_user_id   = $_SESSION['user_id'] ?? 0;
$current_user_name = $_SESSION['username'] ?? 'Admin';

$msg = '';
$msg_type = '';

$valid_statuses = ['Active', 'Deceased', 'Moved Out', 'Abroad'];

/* SEAL SVG */
$SEAL_SVG_MARKUP = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="64" height="64">'
. '<defs>'
. '<linearGradient id="esky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6fc3e6"/><stop offset=".5" stop-color="#bfe7f1"/><stop offset="1" stop-color="#fbe4a8"/></linearGradient>'
. '<linearGradient id="ehill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f4c262"/><stop offset="1" stop-color="#dc9a38"/></linearGradient>'
. '<linearGradient id="egrass" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#86c96b"/><stop offset="1" stop-color="#3f9250"/></linearGradient>'
. '<radialGradient id="egloss" cx=".3" cy=".22" r=".8"><stop offset="0" stop-color="#fff" stop-opacity=".2"/><stop offset=".55" stop-color="#fff" stop-opacity="0"/></radialGradient>'
. '<clipPath id="eclip"><circle cx="100" cy="100" r="66"/></clipPath>'
. '</defs>'
. '<circle cx="100" cy="100" r="99" fill="#fffaf0"/>'
. '<circle cx="100" cy="100" r="97" fill="none" stroke="#5a3a28" stroke-width="3.6"/>'
. '<circle cx="100" cy="100" r="68.5" fill="none" stroke="#5a3a28" stroke-width="3.2"/>'
. '<g clip-path="url(#eclip)">'
. '<rect x="30" y="30" width="140" height="140" fill="url(#esky)"/>'
. '<circle cx="110" cy="54" r="19" fill="#fff4c8" opacity=".55"/><circle cx="110" cy="54" r="8.5" fill="#fff8dc"/>'
. '<path d="M30,116 C58,86 90,94 120,84 C146,76 160,90 172,100 V172 H30Z" fill="url(#ehill)"/>'
. '<path d="M30,130 C56,114 82,124 102,128 C126,132 150,120 172,124 V172 H30Z" fill="#e8ad45"/>'
. '<path d="M30,138 C52,126 78,136 96,152 L104,172 H30Z" fill="url(#egrass)"/>'
. '<path d="M172,146 C152,138 130,146 112,160 L108,172 H172Z" fill="#3a8a48"/>'
. '<path d="M57,160 C61,132 56,102 66,74" fill="none" stroke="#6b4426" stroke-width="4.6" stroke-linecap="round"/>'
. '<g transform="translate(66 74) scale(1.0)">'
. '<g transform="rotate(-172)" fill="#2f7d3f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g>'
. '<g transform="rotate(-140)" fill="#4aa457"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g>'
. '<g transform="rotate(-108)" fill="#2a6f38"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g>'
. '<g transform="rotate(-72)" fill="#56b45f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g>'
. '<g transform="rotate(-40)" fill="#2f7d3f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g>'
. '<g transform="rotate(-10)" fill="#4aa457"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g>'
. '<g transform="rotate(20)" fill="#2a6f38"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g>'
. '</g>'
. '</g>'
. '<circle cx="100" cy="100" r="99" fill="url(#egloss)"/>'
. '</svg>';

/* Auto-detect columns */
$res_cols = [];
try { $cc = $conn->query("SHOW COLUMNS FROM residents"); if ($cc) { while ($c = $cc->fetch_assoc()) $res_cols[] = $c['Field']; } } catch (Throwable $e) {}

$has_status       = in_array('status', $res_cols);
$has_status_date  = in_array('status_changed_at', $res_cols);
$has_status_notes = in_array('status_notes', $res_cols);
$has_household_id = in_array('household_id', $res_cols);
$has_relationship = in_array('relationship_to_head', $res_cols);

if (!$has_status) { try { $conn->query("ALTER TABLE residents ADD COLUMN status VARCHAR(40) DEFAULT 'Active'"); $has_status = true; } catch (Throwable $e) {} }
if (!$has_status_date) { try { $conn->query("ALTER TABLE residents ADD COLUMN status_changed_at DATETIME NULL"); $has_status_date = true; } catch (Throwable $e) {} }
if (!$has_status_notes) { try { $conn->query("ALTER TABLE residents ADD COLUMN status_notes TEXT NULL"); $has_status_notes = true; } catch (Throwable $e) {} }
if (!$has_household_id) { try { $conn->query("ALTER TABLE residents ADD COLUMN household_id INT NULL"); $has_household_id = true; } catch (Throwable $e) {} }
if (!$has_relationship) { try { $conn->query("ALTER TABLE residents ADD COLUMN relationship_to_head VARCHAR(60) NULL"); $has_relationship = true; } catch (Throwable $e) {} }

try { $conn->query("CREATE TABLE IF NOT EXISTS resident_status_history (history_id INT AUTO_INCREMENT PRIMARY KEY, resident_id INT NOT NULL, old_status VARCHAR(40), new_status VARCHAR(40), notes TEXT, changed_by INT, changed_by_name VARCHAR(100), changed_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_resident (resident_id))"); } catch (Throwable $e) {}

$hh_cols = [];
try { $cc2 = $conn->query("SHOW COLUMNS FROM households"); if ($cc2) { while ($c = $cc2->fetch_assoc()) $hh_cols[] = $c['Field']; } } catch (Throwable $e) {}
if (!in_array('household_name', $hh_cols)) { try { $conn->query("ALTER TABLE households ADD COLUMN household_name VARCHAR(120) NULL"); $hh_cols[] = 'household_name'; } catch (Throwable $e) {} }
if (!in_array('household_head_id', $hh_cols)) { try { $conn->query("ALTER TABLE households ADD COLUMN household_head_id INT NULL"); $hh_cols[] = 'household_head_id'; } catch (Throwable $e) {} }
if (!in_array('created_at', $hh_cols)) { try { $conn->query("ALTER TABLE households ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"); $hh_cols[] = 'created_at'; } catch (Throwable $e) {} }

/* EDIT RESIDENT */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_resident'])) {
    $rid          = intval($_POST['resident_id'] ?? 0);
    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $suffix       = trim($_POST['suffix'] ?? '');
    $birthdate    = $_POST['birthdate'] ?? '';
    $gender       = $_POST['gender'] ?? 'Male';
    $civil_status = $_POST['civil_status'] ?? 'Single';
    $contact      = trim($_POST['contact_number'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $purok_id     = intval($_POST['purok_id'] ?? 0);
    $occupation   = trim($_POST['occupation'] ?? '');

    if ($rid > 0 && !empty($first_name) && !empty($last_name)) {
        try {
            $purok_val = $purok_id > 0 ? $purok_id : 0;
            $bd_val = !empty($birthdate) ? $birthdate : null;
            $stmt = $conn->prepare("UPDATE residents SET first_name=?, middle_name=?, last_name=?, suffix=?, birthdate=?, gender=?, civil_status=?, contact_number=?, email=?, address=?, purok_id=?, occupation=? WHERE resident_id=?");
            $stmt->bind_param("ssssssssssiii", $first_name, $middle_name, $last_name, $suffix, $bd_val, $gender, $civil_status, $contact, $email, $address, $purok_val, $occupation, $rid);
            if ($stmt->execute()) {
                $msg = "✅ Na-update ang resident record!";
                $msg_type = "success";
            }
        } catch (Throwable $e) {
            $msg = "❌ Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

/* Change Status */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $resident_id = intval($_POST['resident_id'] ?? 0);
    $new_status  = trim($_POST['new_status'] ?? '');
    $notes       = trim($_POST['status_notes'] ?? '');
    if ($resident_id > 0 && in_array($new_status, $valid_statuses) && $has_status) {
        try {
            $old_q = $conn->prepare("SELECT status FROM residents WHERE resident_id = ?");
            $old_q->bind_param("i", $resident_id);
            $old_q->execute();
            $old_status = $old_q->get_result()->fetch_assoc()['status'] ?? 'Active';
            $stmt = $conn->prepare("UPDATE residents SET status = ?, status_changed_at = NOW(), status_notes = ? WHERE resident_id = ?");
            $stmt->bind_param("ssi", $new_status, $notes, $resident_id);
            $stmt->execute();
            try {
                $hist = $conn->prepare("INSERT INTO resident_status_history (resident_id, old_status, new_status, notes, changed_by, changed_by_name, changed_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $hist->bind_param("isssis", $resident_id, $old_status, $new_status, $notes, $current_user_id, $current_user_name);
                $hist->execute();
            } catch (Throwable $e) {}
            $msg = "✅ Na-update ang status!"; $msg_type = "success";
        } catch (Throwable $e) { $msg = "❌ " . $e->getMessage(); $msg_type = "danger"; }
    }
}

/* Restore */
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    $rid = intval($_GET['id']);
    if ($rid > 0) { try { $conn->query("UPDATE residents SET status = 'Active', status_changed_at = NOW(), status_notes = NULL WHERE resident_id = $rid"); $msg = "✅ Restored."; $msg_type = "success"; } catch (Throwable $e) {} }
}

/* Create Household */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_household'])) {
    $hh_name  = trim($_POST['household_name'] ?? '');
    $hh_head  = intval($_POST['household_head_id'] ?? 0);
    $hh_addr  = trim($_POST['address'] ?? '');
    $hh_purok = intval($_POST['purok_id'] ?? 0);
    if (!empty($hh_name)) {
        try {
            $hh_cols_now = [];
            $cc3 = $conn->query("SHOW COLUMNS FROM households");
            if ($cc3) { while ($c = $cc3->fetch_assoc()) $hh_cols_now[] = $c['Field']; }
            $head_val = $hh_head > 0 ? $hh_head : 0;
            $purok_val = $hh_purok > 0 ? $hh_purok : 0;
            $hh_number = 'HH-' . date('Ymd') . '-' . rand(1000, 9999);
            $ins_cols = []; $placeholders = []; $params = []; $types = '';
            if (in_array('household_number', $hh_cols_now)) { $ins_cols[] = 'household_number'; $placeholders[] = '?'; $params[] = $hh_number; $types .= 's'; }
            if (in_array('household_name', $hh_cols_now)) { $ins_cols[] = 'household_name'; $placeholders[] = '?'; $params[] = $hh_name; $types .= 's'; }
            if (in_array('household_head_id', $hh_cols_now)) { $ins_cols[] = 'household_head_id'; $placeholders[] = '?'; $params[] = $head_val; $types .= 'i'; }
            if (in_array('address', $hh_cols_now)) { $ins_cols[] = 'address'; $placeholders[] = '?'; $params[] = $hh_addr; $types .= 's'; }
            if (in_array('purok_id', $hh_cols_now)) { $ins_cols[] = 'purok_id'; $placeholders[] = '?'; $params[] = $purok_val; $types .= 'i'; }
            $sql = "INSERT INTO households (" . implode(', ', $ins_cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $conn->prepare($sql);
            if (!empty($types)) $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $new_hh_id = $conn->insert_id;
                if ($head_val) { $conn->query("UPDATE residents SET household_id = $new_hh_id, relationship_to_head = 'Head' WHERE resident_id = $head_val"); }
                $msg = "✅ Nagawa ang household!"; $msg_type = "success";
            }
        } catch (Throwable $e) { $msg = "❌ " . $e->getMessage(); $msg_type = "danger"; }
    }
}

/* Assign Household */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_household'])) {
    $rid = intval($_POST['resident_id'] ?? 0);
    $hid = intval($_POST['household_id'] ?? 0);
    $rel = trim($_POST['relationship'] ?? 'Member');
    if ($rid > 0) {
        try {
            if ($hid > 0) {
                $stmt = $conn->prepare("UPDATE residents SET household_id = ?, relationship_to_head = ? WHERE resident_id = ?");
                $stmt->bind_param("isi", $hid, $rel, $rid);
            } else {
                $stmt = $conn->prepare("UPDATE residents SET household_id = NULL, relationship_to_head = NULL WHERE resident_id = ?");
                $stmt->bind_param("i", $rid);
            }
            $stmt->execute(); $msg = "✅ Saved."; $msg_type = "success";
        } catch (Throwable $e) { $msg = "❌ " . $e->getMessage(); $msg_type = "danger"; }
    }
}

/* Delete */
if (isset($_POST['confirm_delete']) && isset($_POST['resident_id'])) {
    $del_id = intval($_POST['resident_id']);
    if ($del_id > 0) {
        try {
            if ($del_id === intval($current_user_id)) throw new Exception("Hindi mo pwedeng burahin ang sarili mo.");
            $conn->query("DELETE FROM resident_status_history WHERE resident_id = $del_id");
            $stmt = $conn->prepare("DELETE FROM residents WHERE resident_id = ?");
            $stmt->bind_param("i", $del_id);
            if ($stmt->execute()) { $msg = "✅ Nabura."; $msg_type = "success"; }
        } catch (Throwable $e) { $msg = "❌ " . $e->getMessage(); $msg_type = "danger"; }
    }
}

/* Fetch Residents */
$filter_status = $_GET['status'] ?? 'Active';
if (!in_array($filter_status, array_merge($valid_statuses, ['All']))) $filter_status = 'Active';
$search = trim($_GET['q'] ?? '');
$view_mode = $_GET['view'] ?? 'list';

$residents = [];
$counts = ['Active' => 0, 'Deceased' => 0, 'Moved Out' => 0, 'Abroad' => 0, 'All' => 0];

try {
    $where = []; $params = []; $types = '';
    if ($filter_status !== 'All') { $where[] = "r.status = ?"; $params[] = $filter_status; $types .= 's'; }
    if (!empty($search)) {
        $where[] = "(r.first_name LIKE ? OR r.middle_name LIKE ? OR r.last_name LIKE ? OR r.address LIKE ?)";
        $like = "%$search%";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'ssss';
    }
    $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    $sql = "SELECT r.*, p.purok_name, h.household_name FROM residents r LEFT JOIN puroks p ON r.purok_id = p.purok_id LEFT JOIN households h ON r.household_id = h.household_id $where_sql ORDER BY r.last_name ASC";
    if (!empty($params)) {
        $stmt = $conn->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result();
    } else { $res = $conn->query($sql); }
    if ($res) { while ($row = $res->fetch_assoc()) { if (!isset($row['status']) || !in_array($row['status'], $valid_statuses)) $row['status'] = 'Active'; $residents[] = $row; } }
    $count_res = $conn->query("SELECT status, COUNT(*) AS c FROM residents GROUP BY status");
    if ($count_res) { while ($row = $count_res->fetch_assoc()) { $s = $row['status'] ?? 'Active'; if (isset($counts[$s])) $counts[$s] = intval($row['c']); $counts['All'] += intval($row['c']); } }
} catch (Throwable $e) {}

$households = [];
try {
    $hh_q = $conn->query("SELECT h.*, p.purok_name, (SELECT COUNT(*) FROM residents WHERE household_id = h.household_id) AS member_count, (SELECT CONCAT(first_name, ' ', last_name) FROM residents WHERE resident_id = h.household_head_id) AS head_name FROM households h LEFT JOIN puroks p ON h.purok_id = p.purok_id ORDER BY h.household_id DESC");
    if ($hh_q) { while ($row = $hh_q->fetch_assoc()) $households[] = $row; }
} catch (Throwable $e) {}

$all_residents = [];
try { $ar_q = $conn->query("SELECT resident_id, first_name, last_name FROM residents WHERE status = 'Active' ORDER BY last_name ASC"); if ($ar_q) while ($r = $ar_q->fetch_assoc()) $all_residents[] = $r; } catch (Throwable $e) {}

$puroks = [];
try { $p_q = $conn->query("SELECT purok_id, purok_name FROM puroks ORDER BY purok_name ASC"); if ($p_q) while ($p = $p_q->fetch_assoc()) $puroks[] = $p; } catch (Throwable $e) {}

$status_meta = [
    'Active'    => ['label'=>'Active',    'icon'=>'🟢', 'color'=>'#1f6e41', 'bg'=>'#d1e7dd'],
    'Deceased'  => ['label'=>'Deceased',  'icon'=>'⚫', 'color'=>'#374151', 'bg'=>'#e5e7eb'],
    'Moved Out' => ['label'=>'Moved Out', 'icon'=>'🔵', 'color'=>'#0369a1', 'bg'=>'#e0f2fe'],
    'Abroad'    => ['label'=>'Abroad',    'icon'=>'🌐', 'color'=>'#7c3aed', 'bg'=>'#ede9fe'],
];

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Registry · E-Barangay</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root { --night: #0c2733; --teal: #14566b; --palm: #2f8a57; --gold: #f2b84b; --line: #d9e1e4; --ink: #16262e; --muted: #5f717a; --danger:#a4262c; --danger-bg:#fdecec; --purple:#6d28d9; --purple-bg:#ede9fe; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Figtree', sans-serif; color: #fff; background: var(--night); min-height: 100vh; }
        .scene { position: fixed; inset: 0; z-index: 0; background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%); }
        .scene::after { content: ""; position: absolute; inset: 0; background: rgba(8, 24, 33, 0.45); }
        .page { position: relative; z-index: 2; padding: 24px; max-width: 1440px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 12px; flex-wrap: wrap; }
        .top-bar h1 { font-family:'Bricolage Grotesque', sans-serif; font-size: clamp(20px, 2.6vw, 26px); display: flex; align-items: center; gap: 10px; }
        .btn-back { background: rgba(255,255,255,.15); color: #fff; text-decoration: none; padding: 9px 18px; border-radius: 999px; font-weight: 600; font-size: 13.5px; display: inline-flex; align-items: center; gap: 7px; }
        .panel { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 24px; box-shadow: 0 15px 35px rgba(0,0,0,.3); margin-bottom: 22px; }
        .panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 20px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 10px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
        .view-toggle { display: inline-flex; gap: 4px; background: #eef1f3; border-radius: 999px; padding: 4px; margin-bottom: 18px; }
        .view-toggle a { padding: 8px 18px; border-radius: 999px; font-size: 13.5px; font-weight: 700; color: var(--muted); text-decoration: none; }
        .view-toggle a.active { background: linear-gradient(135deg, var(--teal), #0d3f50); color: #fff; }
        .toolbar-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; align-items: center; justify-content: space-between; }
        .export-btns { display: flex; gap: 8px; }
        .btn-export { padding: 10px 18px; border-radius: 10px; font-family: inherit; font-size: 13.5px; font-weight: 700; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px; color: #fff; }
        .btn-export.excel { background: linear-gradient(135deg, #16a34a, #15803d); }
        .btn-export.pdf { background: linear-gradient(135deg, #dc2626, #991b1b); }
        .btn-export.gold { background: linear-gradient(135deg, #b45309, #78350f); }
        .tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid var(--line); }
        .tab-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 16px; border-radius: 999px; font-size: 13px; font-weight: 700; border: 2px solid var(--line); background: #fff; color: var(--muted); text-decoration: none; }
        .tab-btn.active { background: linear-gradient(135deg, var(--teal), #0d3f50); color: #fff; }
        .tab-btn .count { background: rgba(0,0,0,.08); font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px; }
        .tab-btn.active .count { background: rgba(255,255,255,.25); }
        .search-bar { display: flex; gap: 10px; flex-wrap: wrap; }
        .search-input { flex: 1; min-width: 200px; padding: 11px 14px; border: 2px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; background: #f5f8f9; }
        .search-btn { background: var(--teal); color: #fff; border: none; padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
        th { background: #f1f5f9; color: var(--teal); text-align: left; padding: 12px; font-weight: 700; border-bottom: 2px solid var(--line); font-size: 12px; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid var(--line); color: #334155; vertical-align: top; }
        tr:hover { background: #f8fafc; }
        tr.row-deceased { opacity: .72; }
        .badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px; background: #e0f2fe; color: #0369a1; display: inline-block; }
        .status-badge { font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
        .action-cell { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
        .btn-sm { font-size: 11px; padding: 6px 12px; border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; border: none; font-family: inherit; text-decoration: none; }
        .btn-edit { background: #dbeafe; color: #1d4ed8; }
        .btn-edit:hover { background: #bfdbfe; }
        .btn-status { background: var(--purple-bg); color: var(--purple); }
        .btn-restore { background: #d1e7dd; color: #0f5132; }
        .btn-history { background: #e0f2fe; color: #0369a1; }
        .btn-household { background: #fef3c7; color: #b45309; }
        .btn-delete { background: var(--danger-bg); color: var(--danger); border: 1px solid #f4c4c6; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #d1e7dd; color: #0f5132; }
        .alert-danger { background: var(--danger-bg); color: var(--danger); }
        .empty-state { padding: 60px 20px; text-align: center; color: var(--muted); font-style: italic; }
        .household-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-top: 16px; }
        .household-card { background: #fff; border: 1px solid var(--line); border-left: 5px solid var(--gold); border-radius: 16px; padding: 18px; }
        .household-card .hh-title { font-family: 'Bricolage Grotesque', sans-serif; font-size: 16px; font-weight: 800; color: var(--teal); margin-bottom: 6px; }
        .household-card .hh-meta { font-size: 12.5px; color: var(--muted); margin-bottom: 10px; line-height: 1.6; }
        .hh-member-count { display: inline-flex; align-items: center; gap: 6px; background: var(--purple-bg); color: var(--purple); font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 999px; }
        .modal-overlay { position: fixed; inset: 0; z-index: 999; background: rgba(8,24,33,.72); display: none; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fffdf7; color: var(--ink); border-radius: 22px; width: 100%; max-width: 720px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; }
        .modal-head { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; background: linear-gradient(135deg, var(--danger), #7a1c22); color: #fff; }
        .modal-head.status-head { background: linear-gradient(135deg, var(--purple), #4c1d95); }
        .modal-head.history-head { background: linear-gradient(135deg, var(--teal), #0d3f50); }
        .modal-head.household-head { background: linear-gradient(135deg, #b45309, #78350f); }
        .modal-head h3 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 18px; font-weight: 800; }
        .modal-close { background: rgba(255,255,255,.18); color: #fff; border: none; cursor: pointer; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; font-size: 18px; }
        .modal-body { padding: 22px 24px; overflow-y: auto; flex: 1; }
        .modal-resident-name { background: #f5f8f9; border: 2px solid var(--line); border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; display: flex; align-items: center; gap: 12px; }
        .modal-resident-name .avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, var(--teal), var(--palm)); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 18px; }
        .modal-resident-name b { font-size: 16px; color: var(--teal); display: block; }
        .warn-note { margin-top: 16px; padding: 12px 14px; background: var(--danger-bg); border: 1px solid #f4c4c6; border-radius: 10px; color: var(--danger); font-size: 13px; font-weight: 600; }
        .modal-foot { display: flex; gap: 10px; justify-content: flex-end; padding: 16px 24px; background: #f8fafc; border-top: 1px solid var(--line); }
        .btn-modal { padding: 12px 22px; border-radius: 10px; font-weight: 800; font-size: 14px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-modal.cancel { background: #eef1f3; color: var(--muted); }
        .btn-modal.danger { background: linear-gradient(135deg, var(--danger), #7a1c22); color: #fff; }
        .btn-modal.purple { background: linear-gradient(135deg, var(--purple), #4c1d95); color: #fff; }
        .btn-modal.gold { background: linear-gradient(135deg, #b45309, #78350f); color: #fff; }
        .btn-modal.teal { background: linear-gradient(135deg, var(--teal), #0d3f50); color: #fff; }
        .status-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-field label { display: block; font-size: 12.5px; font-weight: 700; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; }
        .form-field input, .form-field textarea, .form-field select { width: 100%; padding: 11px 13px; border: 2px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; background: #f5f8f9; }
        .form-field input:focus, .form-field textarea:focus, .form-field select:focus { outline: none; border-color: var(--purple); background: #fff; }
        .form-field.full { grid-column: 1 / -1; }
        .status-option-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .status-radio { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 16px 10px; background: #fff; border: 2px solid var(--line); border-radius: 12px; cursor: pointer; text-align: center; }
        .status-radio input { display: none; }
        .status-radio .icon { font-size: 24px; }
        .status-radio .label { font-size: 13px; font-weight: 800; }
        .status-radio.selected { border-color: var(--purple); background: var(--purple-bg); }
    </style>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1>👥 Resident Registry</h1>
        <a href="../dashboard.php" class="btn-back">← Bumalik sa Dashboard</a>
    </div>

    <?php if ($msg): ?><div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?>"><?= $msg ?></div><?php endif; ?>

    <div class="view-toggle">
        <a href="?view=list&status=<?= e($filter_status) ?>" class="<?= $view_mode === 'list' ? 'active' : '' ?>">📋 Resident List</a>
        <a href="?view=households&status=<?= e($filter_status) ?>" class="<?= $view_mode === 'households' ? 'active' : '' ?>">🏠 Households (<?= count($households) ?>)</a>
    </div>

    <?php if ($view_mode === 'households'): ?>
        <div class="panel">
            <div class="toolbar-row">
                <h2 style="border-bottom:none; padding-bottom:0; margin-bottom:0;">🏠 Family Households</h2>
                <button type="button" class="btn-export gold" onclick="openModal('createHouseholdModal')">+ Add Household</button>
            </div>
            <?php if (empty($households)): ?><div class="empty-state">Walang nakatalang household.</div>
            <?php else: ?>
                <div class="household-grid">
                    <?php foreach ($households as $hh): ?>
                        <div class="household-card">
                            <div class="hh-title">🏠 <?= e($hh['household_name'] ?? $hh['household_number'] ?? 'Household #' . $hh['household_id']) ?></div>
                            <div class="hh-meta"><b>Head:</b> <?= e($hh['head_name'] ?? 'Hindi nakatakda') ?><br><b>Purok:</b> <?= e($hh['purok_name'] ?? 'Unassigned') ?><br><b>Address:</b> <?= e($hh['address'] ?? 'Walang nakalagay') ?></div>
                            <div class="hh-member-count">👥 <?= intval($hh['member_count'] ?? 0) ?> miyembro</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="panel">
            <h2>Listahan ng mga Residente</h2>

            <div class="tabs">
                <?php
                $tab_meta = ['Active'=>['l'=>'Active','i'=>'🟢'],'Deceased'=>['l'=>'Deceased','i'=>'⚫'],'Moved Out'=>['l'=>'Moved Out','i'=>'🔵'],'Abroad'=>['l'=>'Abroad','i'=>'🌐'],'All'=>['l'=>'All','i'=>'📋']];
                foreach ($tab_meta as $key => $m):
                    $is_active = ($filter_status === $key);
                    $url_params = http_build_query(array_merge($_GET, ['status' => $key, 'view' => 'list']));
                ?>
                    <a href="?<?= $url_params ?>" class="tab-btn <?= $is_active ? 'active' : '' ?>"><span><?= $m['i'] ?></span> <?= e($m['l']) ?><span class="count"><?= intval($counts[$key] ?? 0) ?></span></a>
                <?php endforeach; ?>
            </div>

            <div class="toolbar-row">
                <form method="GET" class="search-bar" style="flex:1;">
                    <input type="hidden" name="status" value="<?= e($filter_status) ?>">
                    <input type="hidden" name="view" value="list">
                    <input type="text" name="q" class="search-input" placeholder="Hanapin..." value="<?= e($search) ?>">
                    <button type="submit" class="search-btn">🔍 Search</button>
                </form>
                <div class="export-btns">
                    <button type="button" class="btn-export excel" onclick="exportExcel()">📊 Excel</button>
                    <button type="button" class="btn-export pdf" onclick="exportPDF()">📄 PDF</button>
                </div>
            </div>

            <div id="printableArea" style="overflow-x: auto;">
                <table id="residentsTable">
                    <thead><tr>
                        <th>Pangalan</th><th>Purok</th><th>Status</th><th>Household</th><th>Kasarian</th><th>Contact</th><th class="no-export">Aksyon</th>
                    </tr></thead>
                    <tbody>
                        <?php if (empty($residents)): ?>
                            <tr><td colspan="7"><div class="empty-state">Walang nahanap na records.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($residents as $r):
                                $rid = intval($r['resident_id']);
                                $r_status = $r['status'] ?? 'Active';
                                $meta = $status_meta[$r_status] ?? $status_meta['Active'];
                                $full_name = trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                                $full_name_display = trim(($r['last_name'] ?? '') . ', ' . ($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? ''));
                                $is_deceased = ($r_status === 'Deceased');
                                $hh_name = $r['household_name'] ?? '';
                            ?>
                                <tr class="<?= $is_deceased ? 'row-deceased' : '' ?>">
                                    <td><b><?= e($full_name_display) ?></b></td>
                                    <td><span class="badge"><?= e($r['purok_name'] ?? 'Unassigned') ?></span></td>
                                    <td><span class="status-badge" style="background:<?= $meta['bg'] ?>; color:<?= $meta['color'] ?>;"><?= $meta['icon'] ?> <?= e($meta['label']) ?></span></td>
                                    <td><?php if (!empty($hh_name)): ?>🏠 <?= e($hh_name) ?><?php else: ?><span style="color:var(--muted); font-size:12px;">Walang household</span><?php endif; ?></td>
                                    <td><?= e($r['gender'] ?? 'N/A') ?></td>
                                    <td><?= e($r['contact_number'] ?? 'N/A') ?></td>
                                    <td class="no-export">
                                        <div class="action-cell">
                                            <button type="button" class="btn-sm btn-edit" onclick="openEditResident(<?= $rid ?>, '<?= e($r['first_name'] ?? '') ?>', '<?= e($r['middle_name'] ?? '') ?>', '<?= e($r['last_name'] ?? '') ?>', '<?= e($r['suffix'] ?? '') ?>', '<?= e($r['birthdate'] ?? '') ?>', '<?= e($r['gender'] ?? 'Male') ?>', '<?= e($r['civil_status'] ?? 'Single') ?>', '<?= e($r['contact_number'] ?? '') ?>', '<?= e($r['email'] ?? '') ?>', '<?= e($r['address'] ?? '') ?>', <?= intval($r['purok_id'] ?? 0) ?>, '<?= e($r['occupation'] ?? '') ?>')">✏️ Edit</button>
                                            <button type="button" class="btn-sm btn-status" onclick="openStatusModal(<?= $rid ?>, '<?= e($full_name) ?>', '<?= e($r_status) ?>', '<?= e($r['status_notes'] ?? '') ?>')">Change Status</button>
                                            <?php if ($r_status !== 'Active'): ?>
                                                <a href="?action=restore&id=<?= $rid ?>&status=<?= e($filter_status) ?>" class="btn-sm btn-restore" onclick="return confirm('Ibalik sa Active?');">Restore</a>
                                            <?php endif; ?>
                                            <button type="button" class="btn-sm btn-history" onclick="openHistoryModal(<?= $rid ?>, '<?= e($full_name) ?>')">History</button>
                                            <button type="button" class="btn-sm btn-household" onclick="openHouseholdAssign(<?= $rid ?>, '<?= e($full_name) ?>', <?= intval($r['household_id'] ?? 0) ?>)">Household</button>
                                            <button type="button" class="btn-sm btn-delete" onclick="openDeleteModal(<?= $rid ?>, '<?= e($full_name) ?>')">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- EDIT RESIDENT MODAL -->
<div class="modal-overlay" id="editResidentModal">
    <div class="modal-box">
        <div class="modal-head" style="background:linear-gradient(135deg,#14566b,#0d3f50);">
            <h3>✏️ Edit Resident</h3>
            <button type="button" class="modal-close" onclick="closeModal('editResidentModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="edit_resident" value="1">
            <input type="hidden" name="resident_id" id="eResidentId" value="">
            <div class="modal-body">
                <div class="status-form-grid">
                    <div class="form-field"><label>First Name *</label><input type="text" name="first_name" id="eFirstName" required></div>
                    <div class="form-field"><label>Middle Name</label><input type="text" name="middle_name" id="eMiddleName"></div>
                    <div class="form-field"><label>Last Name *</label><input type="text" name="last_name" id="eLastName" required></div>
                    <div class="form-field"><label>Suffix</label><input type="text" name="suffix" id="eSuffix" placeholder="Jr., Sr., III"></div>
                    <div class="form-field"><label>Birthdate</label><input type="date" name="birthdate" id="eBirthdate"></div>
                    <div class="form-field"><label>Gender</label>
                        <select name="gender" id="eGender"><option value="Male">Male</option><option value="Female">Female</option></select>
                    </div>
                    <div class="form-field"><label>Civil Status</label>
                        <select name="civil_status" id="eCivilStatus"><option value="Single">Single</option><option value="Married">Married</option><option value="Widowed">Widowed</option><option value="Separated">Separated</option></select>
                    </div>
                    <div class="form-field"><label>Contact Number</label><input type="text" name="contact_number" id="eContact"></div>
                    <div class="form-field"><label>Email</label><input type="email" name="email" id="eEmail"></div>
                    <div class="form-field"><label>Occupation</label><input type="text" name="occupation" id="eOccupation"></div>
                    <div class="form-field"><label>Purok</label>
                        <select name="purok_id" id="ePurok">
                            <option value="0">— Walang Purok —</option>
                            <?php foreach ($puroks as $p): ?>
                                <option value="<?= intval($p['purok_id']) ?>"><?= e($p['purok_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field full"><label>Full Address</label><input type="text" name="address" id="eAddress"></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-modal cancel" onclick="closeModal('editResidentModal')">Cancel</button>
                <button type="submit" class="btn-modal teal">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- STATUS MODAL -->
<div class="modal-overlay" id="statusModal">
    <div class="modal-box">
        <div class="modal-head status-head"><h3>🔄 Change Status</h3><button class="modal-close" onclick="closeModal('statusModal')">✕</button></div>
        <form method="POST">
            <input type="hidden" name="change_status" value="1">
            <input type="hidden" name="resident_id" id="sRid">
            <input type="hidden" name="new_status" id="sNewStatus">
            <div class="modal-body">
                <div class="modal-resident-name"><div class="avatar" id="sAvatar">?</div><div><b id="sName"></b></div></div>
                <div class="status-option-grid">
                    <label class="status-radio" data-status="Active"><input type="radio" name="sc"><span class="icon">🟢</span><span class="label">Active</span></label>
                    <label class="status-radio" data-status="Deceased"><input type="radio" name="sc"><span class="icon">⚫</span><span class="label">Deceased</span></label>
                    <label class="status-radio" data-status="Moved Out"><input type="radio" name="sc"><span class="icon">🔵</span><span class="label">Moved Out</span></label>
                    <label class="status-radio" data-status="Abroad"><input type="radio" name="sc"><span class="icon">🌐</span><span class="label">Abroad</span></label>
                </div>
                <div class="form-field" style="margin-top:14px;"><label>Notes</label><textarea name="status_notes" id="sNotes" rows="3"></textarea></div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-modal cancel" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn-modal purple">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- HISTORY MODAL -->
<div class="modal-overlay" id="historyModal">
    <div class="modal-box">
        <div class="modal-head history-head"><h3>📜 Status History</h3><button class="modal-close" onclick="closeModal('historyModal')">✕</button></div>
        <div class="modal-body">
            <div class="modal-resident-name"><div class="avatar" id="hAvatar">?</div><div><b id="hName"></b></div></div>
            <div id="hList" style="font-size:13px;">Loading...</div>
        </div>
        <div class="modal-foot"><button type="button" class="btn-modal cancel" onclick="closeModal('historyModal')">Close</button></div>
    </div>
</div>

<!-- HOUSEHOLD ASSIGN -->
<div class="modal-overlay" id="householdModal">
    <div class="modal-box">
        <div class="modal-head household-head"><h3>🏠 Assign Household</h3><button class="modal-close" onclick="closeModal('householdModal')">✕</button></div>
        <form method="POST">
            <input type="hidden" name="assign_household" value="1">
            <input type="hidden" name="resident_id" id="hhRid">
            <div class="modal-body">
                <div class="modal-resident-name"><div class="avatar" id="hhAvatar">?</div><div><b id="hhName"></b></div></div>
                <div class="form-field"><label>Household</label>
                    <select name="household_id" id="hhSelect"><option value="0">— Walang Household —</option>
                        <?php foreach ($households as $hh): ?><option value="<?= intval($hh['household_id']) ?>"><?= e($hh['household_name'] ?? 'Household #' . $hh['household_id']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field" style="margin-top:12px;"><label>Relationship</label>
                    <select name="relationship"><option value="Member">Member</option><option value="Head">Head</option><option value="Spouse">Spouse</option><option value="Child">Child</option><option value="Parent">Parent</option><option value="Sibling">Sibling</option></select>
                </div>
            </div>
            <div class="modal-foot"><button type="button" class="btn-modal cancel" onclick="closeModal('householdModal')">Cancel</button><button type="submit" class="btn-modal gold">Save</button></div>
        </form>
    </div>
</div>

<!-- CREATE HOUSEHOLD -->
<div class="modal-overlay" id="createHouseholdModal">
    <div class="modal-box">
        <div class="modal-head household-head"><h3>🏠 Create Household</h3><button class="modal-close" onclick="closeModal('createHouseholdModal')">✕</button></div>
        <form method="POST">
            <input type="hidden" name="create_household" value="1">
            <div class="modal-body">
                <div class="form-field"><label>Household Name *</label><input type="text" name="household_name" required></div>
                <div class="form-field" style="margin-top:12px;"><label>Head</label>
                    <select name="household_head_id"><option value="0">— Walang Head —</option>
                        <?php foreach ($all_residents as $ar): ?><option value="<?= intval($ar['resident_id']) ?>"><?= e($ar['last_name'] . ', ' . $ar['first_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field" style="margin-top:12px;"><label>Purok</label>
                    <select name="purok_id"><option value="0">— Walang Purok —</option>
                        <?php foreach ($puroks as $p): ?><option value="<?= intval($p['purok_id']) ?>"><?= e($p['purok_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field" style="margin-top:12px;"><label>Address</label><input type="text" name="address"></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn-modal cancel" onclick="closeModal('createHouseholdModal')">Cancel</button><button type="submit" class="btn-modal gold">Create</button></div>
        </form>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-head"><h3>⚠️ Confirm Delete</h3><button class="modal-close" onclick="closeModal('deleteModal')">✕</button></div>
        <form method="POST">
            <input type="hidden" name="confirm_delete" value="1">
            <input type="hidden" name="resident_id" id="delRid">
            <div class="modal-body">
                <div class="modal-resident-name"><div class="avatar" id="delAvatar">?</div><div><b id="delName"></b></div></div>
                <div class="warn-note">⚠️ Permanent ang deletion.</div>
            </div>
            <div class="modal-foot"><button type="button" class="btn-modal cancel" onclick="closeModal('deleteModal')">Cancel</button><button type="submit" class="btn-modal danger">Delete</button></div>
        </form>
    </div>
</div>

<script>
var SEAL_SVG = <?= json_encode($SEAL_SVG_MARKUP) ?>;

function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

function openEditResident(id, fn, mn, ln, sfx, bd, gd, cs, ct, em, ad, pk, oc) {
    document.getElementById('eResidentId').value = id;
    document.getElementById('eFirstName').value = fn;
    document.getElementById('eMiddleName').value = mn;
    document.getElementById('eLastName').value = ln;
    document.getElementById('eSuffix').value = sfx;
    document.getElementById('eBirthdate').value = bd;
    document.getElementById('eGender').value = gd;
    document.getElementById('eCivilStatus').value = cs;
    document.getElementById('eContact').value = ct;
    document.getElementById('eEmail').value = em;
    document.getElementById('eAddress').value = ad;
    document.getElementById('ePurok').value = pk;
    document.getElementById('eOccupation').value = oc;
    openModal('editResidentModal');
}

function openStatusModal(rid, name, status, notes) {
    document.getElementById('sRid').value = rid;
    document.getElementById('sName').textContent = name;
    document.getElementById('sAvatar').textContent = (name || '?').charAt(0).toUpperCase();
    document.getElementById('sNotes').value = notes || '';
    document.querySelectorAll('.status-radio').forEach(function (r) {
        r.classList.remove('selected');
        r.querySelector('input').checked = false;
        if (r.getAttribute('data-status') === status) {
            r.classList.add('selected');
            r.querySelector('input').checked = true;
            document.getElementById('sNewStatus').value = status;
        }
    });
    openModal('statusModal');
}
document.querySelectorAll('.status-radio').forEach(function (r) {
    r.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelectorAll('.status-radio').forEach(function (x) { x.classList.remove('selected'); x.querySelector('input').checked = false; });
        this.classList.add('selected');
        this.querySelector('input').checked = true;
        document.getElementById('sNewStatus').value = this.getAttribute('data-status');
    });
});

function openHistoryModal(rid, name) {
    document.getElementById('hName').textContent = name;
    document.getElementById('hAvatar').textContent = (name || '?').charAt(0).toUpperCase();
    document.getElementById('hList').innerHTML = 'Loading...';
    openModal('historyModal');
    fetch('residents.php?get_history=1&resident_id=' + rid).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.history || d.history.length === 0) { document.getElementById('hList').innerHTML = '<div class="warn-note">📭 Walang history.</div>'; return; }
        var html = '';
        d.history.forEach(function (h) {
            html += '<div style="padding:10px; background:#f8fafc; border-left:3px solid #6d28d9; margin-bottom:8px; border-radius:6px;">';
            html += '<b>' + (h.old_status || '—') + ' → ' + (h.new_status || '—') + '</b><br>';
            html += '<small>' + (h.changed_by_name || '') + ' · ' + (h.changed_at || '') + '</small>';
            if (h.notes) html += '<br><i>' + h.notes + '</i>';
            html += '</div>';
        });
        document.getElementById('hList').innerHTML = html;
    });
}

function openHouseholdAssign(rid, name, hhId) {
    document.getElementById('hhRid').value = rid;
    document.getElementById('hhName').textContent = name;
    document.getElementById('hhAvatar').textContent = (name || '?').charAt(0).toUpperCase();
    document.getElementById('hhSelect').value = hhId;
    openModal('householdModal');
}

function openDeleteModal(rid, name) {
    document.getElementById('delRid').value = rid;
    document.getElementById('delName').textContent = name;
    document.getElementById('delAvatar').textContent = (name || '?').charAt(0).toUpperCase();
    openModal('deleteModal');
}

function exportExcel() {
    var table = document.getElementById('residentsTable');
    if (!table) return;
    var clone = table.cloneNode(true);
    clone.querySelectorAll('.no-export').forEach(function (el) { el.remove(); });
    var now = new Date();
    var dateStr = now.toLocaleString('en-PH', { dateStyle: 'long', timeStyle: 'short' });
    var totalRows = clone.querySelectorAll('tbody tr').length;
    var seal = SEAL_SVG;
    var html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"><style>';
    html += 'table { border-collapse: collapse; font-family: Arial, sans-serif; }';
    html += '.data-table th { background: #14566b; color: #fff; padding: 10px 12px; border: 1px solid #b8c5cb; font-size: 12px; text-align: left; }';
    html += '.data-table td { padding: 8px 12px; border: 1px solid #d9e1e4; font-size: 12px; vertical-align: top; }';
    html += '.data-table tr:nth-child(even) td { background: #f5f8f9; }';
    html += '.hdr-title { font-size: 18px; font-weight: bold; color: #14566b; }';
    html += '.hdr-sub { font-size: 11px; color: #5f717a; }';
    html += '.hdr-org { font-size: 10px; color: #5f717a; letter-spacing: 2px; }';
    html += '.sign-line { border-top: 1.5px solid #16262e; width: 220px; margin: 0 auto; padding-top: 5px; font-weight: bold; font-size: 12px; color: #16262e; text-align: center; }';
    html += '.sign-title { font-size: 10.5px; color: #5f717a; text-align: center; margin-top: 3px; }';
    html += '</style></head><body>';
    html += '<table style="width:100%; border:none;"><tr>';
    html += '<td style="width:80px; text-align:center; vertical-align:middle; border:none;">' + seal + '</td>';
    html += '<td style="text-align:center; vertical-align:middle; border:none; padding:0 10px;">';
    html += '<div class="hdr-org">REPUBLIKA NG PILIPINAS · E-BARANGAY SYSTEM</div>';
    html += '<div class="hdr-title" style="margin:6px 0 3px;">Barangay Bigaan · Resident Registry</div>';
    html += '<div class="hdr-sub">Lalawigan ng Quezon · Bayan ng Calauag</div></td>';
    html += '<td style="width:80px; text-align:center; vertical-align:middle; border:none;">' + seal + '</td>';
    html += '</tr><tr><td colspan="3" style="border:none; padding-top:10px; border-bottom:3px double #14566b;"></td></tr>';
    html += '<tr><td style="border:none; padding-top:8px; font-size:11px;"><b>Filter:</b> <?= e($filter_status) ?> · <b>Total:</b> ' + totalRows + '</td>';
    html += '<td colspan="2" style="border:none; padding-top:8px; text-align:right; font-size:11px;"><b>Generated:</b> ' + dateStr + '</td></tr>';
    html += '</table>';
    clone.className = 'data-table';
    html += clone.outerHTML;
    html += '<table style="width:100%; margin-top:50px; border:none;"><tr>';
    html += '<td style="width:50%; border:none; text-align:center;"><div class="sign-line">JUAN DELA CRUZ</div><div class="sign-title">Barangay Secretary</div></td>';
    html += '<td style="width:50%; border:none; text-align:center;"><div class="sign-line">HON. EDWIN B. BARCENAS</div><div class="sign-title">Punong Barangay</div></td>';
    html += '</tr></table></body></html>';
    var blob = new Blob(['\uFEFF' + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'residents-<?= e($filter_status) ?>-' + now.toISOString().slice(0, 10) + '.xls';
    document.body.appendChild(link); link.click(); document.body.removeChild(link);
}

function exportPDF() {
    var element = document.getElementById('printableArea');
    if (!element || typeof html2pdf === 'undefined') return;
    var actions = element.querySelectorAll('.no-export');
    actions.forEach(function (a) { a.style.display = 'none'; });
    var now = new Date();
    var dateStr = now.toLocaleString('en-PH', { dateStyle: 'long', timeStyle: 'short' });
    var totalRows = element.querySelectorAll('tbody tr').length;
    var seal = SEAL_SVG;
    var header = document.createElement('div');
    header.innerHTML = '<table style="width:100%; border-collapse:collapse;"><tr>' +
        '<td style="width:80px; text-align:center; vertical-align:middle; padding:0;">' + seal + '</td>' +
        '<td style="text-align:center; vertical-align:middle; padding:0 10px;"><div style="font-size:10px; color:#5f717a; letter-spacing:2px; text-transform:uppercase; font-weight:600;">Republika ng Pilipinas · E-Barangay System</div>' +
        '<div style="font-size:20px; font-weight:bold; color:#14566b; margin:6px 0 3px;">Barangay Bigaan · Resident Registry</div>' +
        '<div style="font-size:11.5px; color:#5f717a;">Lalawigan ng Quezon · Bayan ng Calauag</div></td>' +
        '<td style="width:80px; text-align:center; vertical-align:middle; padding:0;">' + seal + '</td></tr>' +
        '<tr><td colspan="3" style="padding-top:10px; border-bottom:3px double #14566b;"></td></tr>' +
        '<tr><td style="text-align:left; padding-top:8px; font-size:11px; color:#5f717a;"><b>Filter:</b> <?= e($filter_status) ?> · <b>Total:</b> ' + totalRows + '</td>' +
        '<td colspan="2" style="text-align:right; padding-top:8px; font-size:11px; color:#5f717a;"><b>Generated:</b> ' + dateStr + '</td></tr></table>';
    element.insertBefore(header, element.firstChild);
    var signatory = document.createElement('div');
    signatory.style.marginTop = '50px';
    signatory.innerHTML = '<table style="width:100%; border-collapse:collapse;"><tr>' +
        '<td style="width:50%; text-align:center; padding-top:60px;"><div style="border-top:1.5px solid #16262e; width:220px; margin:0 auto; padding-top:6px; font-weight:bold; font-size:12px;">JUAN DELA CRUZ</div><div style="font-size:10.5px; color:#5f717a; margin-top:3px;">Barangay Secretary</div></td>' +
        '<td style="width:50%; text-align:center; padding-top:60px;"><div style="border-top:1.5px solid #16262e; width:220px; margin:0 auto; padding-top:6px; font-weight:bold; font-size:12px;">HON. EDWIN B. BARCENAS</div><div style="font-size:10.5px; color:#5f717a; margin-top:3px;">Punong Barangay</div></td>' +
        '</tr></table>';
    element.appendChild(signatory);
    html2pdf().set({
        margin: [12, 10, 14, 10],
        filename: 'residents-<?= e($filter_status) ?>-' + now.toISOString().slice(0, 10) + '.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, backgroundColor: '#ffffff', useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    }).from(element).save().then(function () {
        actions.forEach(function (a) { a.style.display = ''; });
        if (header.parentNode) element.removeChild(header);
        if (signatory.parentNode) element.removeChild(signatory);
    });
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(function (m) { m.classList.remove('show'); });
    }
});
</script>
</body>
</html>