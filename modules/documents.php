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

/* Detect Table */
$tbl = null;
$chk1 = $conn->query("SHOW TABLES LIKE 'certificate_requests'");
if ($chk1 && $chk1->num_rows > 0) {
    $tbl = 'certificate_requests';
} else {
    $chk2 = $conn->query("SHOW TABLES LIKE 'service_requests'");
    if ($chk2 && $chk2->num_rows > 0) $tbl = 'service_requests';
}
if ($tbl === null) die("Error: Walang table na makita (certificate_requests / service_requests).");

/* Detect Columns */
$sr_cols = [];
try {
    $cc = $conn->query("SHOW COLUMNS FROM $tbl");
    if ($cc) { while ($c = $cc->fetch_assoc()) $sr_cols[] = $c['Field']; }
} catch (Throwable $e) {}

$sr_id_col = null;
foreach (['request_id', 'id', 'cert_request_id', 'service_request_id'] as $c) {
    if (in_array($c, $sr_cols)) { $sr_id_col = $c; break; }
}
if ($sr_id_col === null) $sr_id_col = 'id';

$sr_user_col     = in_array('user_id', $sr_cols)     ? 'user_id'     : null;
$sr_resident_col = in_array('resident_id', $sr_cols) ? 'resident_id' : null;

$sr_name_col = null;
foreach (['resident_name', 'full_name', 'name', 'applicant_name', 'requestor_name'] as $c) {
    if (in_array($c, $sr_cols)) { $sr_name_col = $c; break; }
}

$sr_type_col = null;
foreach (['certificate_type', 'service_type', 'document_type', 'doc_type', 'request_type', 'type'] as $c) {
    if (in_array($c, $sr_cols)) { $sr_type_col = $c; break; }
}
if ($sr_type_col === null) $sr_type_col = 'certificate_type';

$sr_purpose_col = null;
foreach (['purpose', 'details', 'reason', 'description'] as $c) {
    if (in_array($c, $sr_cols)) { $sr_purpose_col = $c; break; }
}

$sr_amount_col = null;
foreach (['amount', 'fee', 'total_amount', 'price'] as $c) {
    if (in_array($c, $sr_cols)) { $sr_amount_col = $c; break; }
}

$sr_date_col = null;
foreach (['requested_at', 'created_at', 'date_requested', 'date_filed', 'created'] as $c) {
    if (in_array($c, $sr_cols)) { $sr_date_col = $c; break; }
}

/* Document Fees */
$doc_fees = [
    'Barangay Clearance'         => 50.00,
    'Certificate of Indigency'   => 0.00,
    'Certificate of Residency'   => 50.00,
    'Business Permit Clearance'  => 150.00,
];

function normalize_type($raw) {
    $raw = trim((string)$raw);
    if (empty($raw)) return 'Barangay Clearance';
    $lower = strtolower($raw);
    if ($lower === 'barangay clearance' || $lower === 'clearance') return 'Barangay Clearance';
    if ($lower === 'certificate of indigency' || $lower === 'indigency') return 'Certificate of Indigency';
    if ($lower === 'certificate of residency' || $lower === 'residency') return 'Certificate of Residency';
    if ($lower === 'business permit clearance' || $lower === 'business permit') return 'Business Permit Clearance';
    if (stripos($raw, 'indig') !== false) return 'Certificate of Indigency';
    if (stripos($raw, 'resid') !== false) return 'Certificate of Residency';
    if (stripos($raw, 'business') !== false) return 'Business Permit Clearance';
    if (stripos($raw, 'clearance') !== false) return 'Barangay Clearance';
    return ucwords($raw);
}

/* Helpers */
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

function get_resident_name($conn, $resident_id) {
    try {
        $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM residents WHERE resident_id = ?");
        $stmt->bind_param("i", $resident_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        if ($r) {
            $mid = !empty($r['middle_name']) ? $r['middle_name'][0] . '. ' : '';
            return trim(($r['first_name'] ?? '') . ' ' . $mid . ($r['last_name'] ?? ''));
        }
    } catch (Throwable $e) {}
    return null;
}

function check_ownership($row, $real_res_id, $user_id, $username, $sr_resident_col, $sr_user_col, $sr_name_col) {
    if ($sr_user_col && intval($row[$sr_user_col] ?? 0) === intval($user_id))               return true;
    if ($sr_resident_col && $real_res_id !== null && intval($row[$sr_resident_col] ?? 0) === intval($real_res_id)) return true;
    if ($sr_name_col && ($row[$sr_name_col] ?? '') === $username)                            return true;
    return false;
}

/* Process Request (Citizen only) */
if (!$is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_doc'])) {
    $doc_type = trim($_POST['doc_type'] ?? '');
    $purpose  = trim($_POST['purpose'] ?? '');
    $method   = trim($_POST['payment_method'] ?? 'Cash');
    $ref_num  = trim($_POST['reference_number'] ?? '');
    $fee      = $doc_fees[$doc_type] ?? 0.00;

    if (!empty($doc_type) && !empty($purpose)) {
        try {
            $real_resident_id = null;
            if ($sr_resident_col) {
                $real_resident_id = get_real_resident_id($conn, $user_id);
                if ($real_resident_id === null) {
                    throw new Exception("Kailangan mo munang i-complete ang iyong Profile. Pumunta sa 'Edit Profile'.");
                }
            }

            $insert_cols = []; $placeholders = []; $params = []; $types = '';

            if ($sr_resident_col && $real_resident_id !== null) {
                $insert_cols[] = $sr_resident_col; $placeholders[] = '?';
                $params[] = $real_resident_id; $types .= 'i';
            }
            if ($sr_user_col) {
                $insert_cols[] = $sr_user_col; $placeholders[] = '?';
                $params[] = $user_id; $types .= 'i';
            }
            if ($sr_name_col) {
                $insert_cols[] = $sr_name_col; $placeholders[] = '?';
                $params[] = $username; $types .= 's';
            }
            if ($sr_type_col && in_array($sr_type_col, $sr_cols)) {
                $insert_cols[] = $sr_type_col; $placeholders[] = '?';
                $params[] = $doc_type; $types .= 's';
            }
            if ($sr_purpose_col && in_array($sr_purpose_col, $sr_cols)) {
                $insert_cols[] = $sr_purpose_col; $placeholders[] = '?';
                $params[] = $purpose; $types .= 's';
            }
            if (in_array('payment_method', $sr_cols)) {
                $insert_cols[] = 'payment_method'; $placeholders[] = '?';
                $params[] = $method; $types .= 's';
            }
            if (in_array('reference_number', $sr_cols)) {
                $insert_cols[] = 'reference_number'; $placeholders[] = '?';
                $params[] = $ref_num; $types .= 's';
            }
            if ($sr_amount_col && in_array($sr_amount_col, $sr_cols)) {
                $insert_cols[] = $sr_amount_col; $placeholders[] = '?';
                $params[] = $fee; $types .= 'd';
            }
            if (in_array('status', $sr_cols)) {
                $insert_cols[] = 'status'; $placeholders[] = "'Pending'";
            }
            if (in_array('payment_status', $sr_cols)) {
                $insert_cols[] = 'payment_status'; $placeholders[] = "'Unpaid'";
            }
            if ($sr_date_col && in_array($sr_date_col, $sr_cols)) {
                $insert_cols[] = $sr_date_col; $placeholders[] = 'NOW()';
            }

            $sql = "INSERT INTO $tbl (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $conn->prepare($sql);
            if (!empty($params)) $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $msg = "Matagumpay na naipasa ang request! Bayad: ₱" . number_format($fee, 2);
                $msg_type = "success";
            }
        } catch (Throwable $e) {
            $msg = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    } else {
        $msg = "Kailangan punan ang Uri ng Dokumento at Layunin.";
        $msg_type = "danger";
    }
}

/* Update Status (Admin only) */
if ($is_admin && isset($_GET['action']) && isset($_GET['id'])
    && in_array($_GET['action'], ['approve', 'reject', 'pending'])) {
    $req_id = intval($_GET['id']);
    $act    = $_GET['action'];

    if ($req_id > 0) {
        try {
            if (!in_array('status', $sr_cols)) {
                throw new Exception("Walang 'status' column.");
            }

            if ($act === 'approve') {
                $update_sql = "UPDATE $tbl SET status = 'Approved'";
                if (in_array('payment_status', $sr_cols)) $update_sql .= ", payment_status = 'Paid'";
                $update_sql .= " WHERE $sr_id_col = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("i", $req_id);
                $stmt->execute();

                /* Treasury log */
                $fetch = $conn->query("SELECT * FROM $tbl WHERE $sr_id_col = " . intval($req_id));
                if ($fetch && $r = $fetch->fetch_assoc()) {
                    $payer = $r[$sr_name_col] ?? '';
                    if (empty($payer) && $sr_resident_col) {
                        $payer = get_resident_name($conn, $r[$sr_resident_col]) ?: 'Resident';
                    }
                    $raw = $r[$sr_type_col] ?? '';
                    $stype = normalize_type($raw);
                    $amt = floatval($r[$sr_amount_col] ?? 0);
                    if ($amt == 0) $amt = $doc_fees[$stype] ?? 50.00;
                    $pmeth = $r['payment_method'] ?? 'Cash';
                    $ref = $r['reference_number'] ?? '';
                    $or_num = 'OR-' . date('Ymd') . '-' . rand(100, 999);

                    $t_check = $conn->query("SHOW TABLES LIKE 'treasury'");
                    if ($t_check && $t_check->num_rows > 0) {
                        $treasury_cols = [];
                        $tc = $conn->query("SHOW COLUMNS FROM treasury");
                        if ($tc) { while ($c = $tc->fetch_assoc()) $treasury_cols[] = $c['Field']; }

                        $ins_cols = []; $ins_vals = []; $ins_params = []; $ins_types = '';
                        if (in_array('payer_name', $treasury_cols))       { $ins_cols[]='payer_name';        $ins_vals[]='?'; $ins_params[]=$payer;  $ins_types.='s'; }
                        if (in_array('payment_type', $treasury_cols))     { $ins_cols[]='payment_type';      $ins_vals[]='?'; $ins_params[]=$stype;  $ins_types.='s'; }
                        if (in_array('amount', $treasury_cols))           { $ins_cols[]='amount';            $ins_vals[]='?'; $ins_params[]=$amt;    $ins_types.='d'; }
                        if (in_array('payment_method', $treasury_cols))   { $ins_cols[]='payment_method';    $ins_vals[]='?'; $ins_params[]=$pmeth;  $ins_types.='s'; }
                        if (in_array('reference_number', $treasury_cols)) { $ins_cols[]='reference_number';  $ins_vals[]='?'; $ins_params[]=$ref;    $ins_types.='s'; }
                        if (in_array('or_number', $treasury_cols))        { $ins_cols[]='or_number';         $ins_vals[]='?'; $ins_params[]=$or_num; $ins_types.='s'; }

                        if (!empty($ins_cols)) {
                            $tsql = "INSERT INTO treasury (" . implode(', ', $ins_cols) . ") VALUES (" . implode(', ', $ins_vals) . ")";
                            $tstmt = $conn->prepare($tsql);
                            if ($tstmt) { $tstmt->bind_param($ins_types, ...$ins_params); $tstmt->execute(); }
                        }
                    }
                }
                $msg = "Inaprubahan! Pwede nang i-print ang certificate.";
                $msg_type = "success";

            } elseif ($act === 'reject') {
                $stmt = $conn->prepare("UPDATE $tbl SET status = 'Rejected' WHERE $sr_id_col = ?");
                $stmt->bind_param("i", $req_id);
                $stmt->execute();
                $msg = "Tinanggihan ang request.";
                $msg_type = "warning";

            } elseif ($act === 'pending') {
                $stmt = $conn->prepare("UPDATE $tbl SET status = 'Pending' WHERE $sr_id_col = ?");
                $stmt->bind_param("i", $req_id);
                $stmt->execute();
                $msg = "Ibinalik sa Pending status.";
                $msg_type = "success";
            }
        } catch (Throwable $e) {
            $msg = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

/* Delete Request */
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);

    if ($del_id > 0) {
        try {
            $can_delete = false;
            $real_resident_id = get_real_resident_id($conn, $user_id);

            if ($is_admin) {
                $can_delete = true;
            } else {
                $check = $conn->prepare("SELECT * FROM $tbl WHERE $sr_id_col = ?");
                $check->bind_param("i", $del_id);
                $check->execute();
                $row = $check->get_result()->fetch_assoc();

                if ($row && check_ownership($row, $real_resident_id, $user_id, $username, $sr_resident_col, $sr_user_col, $sr_name_col)) {
                    $can_delete = true;
                }
            }

            if ($can_delete) {
                $stmt = $conn->prepare("DELETE FROM $tbl WHERE $sr_id_col = ?");
                $stmt->bind_param("i", $del_id);
                if ($stmt->execute()) {
                    $msg = "Nabura ang record sa history.";
                    $msg_type = "success";
                }
            } else {
                $msg = "Hindi ka pwedeng magbura ng record na ito.";
                $msg_type = "danger";
            }
        } catch (Throwable $e) {
            $msg = "Delete error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

/* Fetch Requests */
$requests = [];
try {
    $select_extra = "";
    $join_extra   = "";

    if ($sr_resident_col) {
        $select_extra = ", r.first_name, r.middle_name, r.last_name";
        $join_extra   = "LEFT JOIN residents r ON t.$sr_resident_col = r.resident_id";
    }

    $base_from = "$tbl t $join_extra";
    $order_by  = $sr_date_col ? "ORDER BY t.$sr_date_col DESC" : "";

    if ($is_admin) {
        $sql = "SELECT t.*, t.$sr_id_col AS _id $select_extra FROM $base_from $order_by";
        $res = $conn->query($sql);
    } else {
        if ($sr_user_col) {
            $sql = "SELECT t.*, t.$sr_id_col AS _id $select_extra FROM $base_from WHERE t.$sr_user_col = ? $order_by";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
        } elseif ($sr_resident_col) {
            $real_resident_id = get_real_resident_id($conn, $user_id);
            if ($real_resident_id === null) {
                $res = null;
            } else {
                $sql = "SELECT t.*, t.$sr_id_col AS _id $select_extra FROM $base_from WHERE t.$sr_resident_col = ? $order_by";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $real_resident_id);
                $stmt->execute();
                $res = $stmt->get_result();
            }
        } elseif ($sr_name_col) {
            $sql = "SELECT t.*, t.$sr_id_col AS _id $select_extra FROM $base_from WHERE t.$sr_name_col = ? $order_by";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = null;
        }
    }

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $full_name = null;
            if ($sr_name_col && !empty($row[$sr_name_col])) {
                $full_name = $row[$sr_name_col];
            } elseif (!empty($row['first_name']) || !empty($row['last_name'])) {
                $mid = !empty($row['middle_name']) ? $row['middle_name'][0] . '. ' : '';
                $full_name = trim(($row['first_name'] ?? '') . ' ' . $mid . ($row['last_name'] ?? ''));
            }
            if (empty($full_name)) $full_name = $username;

            $row['_display_name'] = $full_name;
            $requests[] = $row;
        }
    }
} catch (Throwable $e) {
    $msg = "Fetch error: " . $e->getMessage();
    $msg_type = "danger";
}

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
        :root { --night: #0c2733; --teal: #14566b; --palm: #2f8a57; --gold: #f2b84b; --line: #d9e1e4; --ink: #16262e; --muted: #5f717a; --danger: #a4262c; --danger-bg: #fdecec; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Figtree', sans-serif; color: #fff; background: var(--night); min-height: 100vh; }
        .scene { position: fixed; inset: 0; z-index: 0; background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%); }
        .scene::after { content: ""; position: absolute; inset: 0; background: rgba(8, 24, 33, 0.5); }
        .page { position: relative; z-index: 2; padding: 24px; max-width: 1240px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 12px; flex-wrap: wrap; }
        .top-bar h1 { font-family: 'Bricolage Grotesque', sans-serif; font-size: clamp(20px, 2.6vw, 26px); display: flex; align-items: center; gap: 10px; }
        .btn-back { background: rgba(255,255,255,.15); color: #fff; text-decoration: none; padding: 9px 18px; border-radius: 999px; font-weight: 600; font-size: 13.5px; display: inline-flex; align-items: center; gap: 7px; border: none; cursor: pointer; font-family: inherit; transition: background .2s; }
        .btn-back:hover { background: rgba(255,255,255,.28); }
        .grid { display: grid; grid-template-columns: <?= $is_admin ? '1fr' : '1fr 1.8fr' ?>; gap: 24px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .panel { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,.3); }
        .panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 20px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 10px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; }
        .form-group input, .form-group select, .form-group textarea { padding: 11px 13px; border: 2px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; background: #f5f8f9; transition: border-color .2s, background .2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--teal); background: #fff; box-shadow: 0 0 0 4px rgba(20,86,107,.12); }
        .btn { background: linear-gradient(135deg, var(--teal), #0d3f50); color: #fff; border: none; padding: 12px 22px; border-radius: 10px; font-weight: 800; cursor: pointer; font-family: inherit; font-size: 14px; width: 100%; box-shadow: 0 8px 20px rgba(20,86,107,.3); transition: transform .2s; }
        .btn:hover { transform: translateY(-2px); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #f1f5f9; color: var(--teal); text-align: left; padding: 12px; font-weight: 700; border-bottom: 2px solid var(--line); font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
        td { padding: 12px; border-bottom: 1px solid var(--line); color: #334155; vertical-align: top; }
        .badge { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 999px; text-transform: uppercase; display: inline-block; }
        .badge-Pending { background: #fef3c7; color: #b45309; }
        .badge-Approved { background: #d1e7dd; color: #0f5132; }
        .badge-Rejected { background: #fee2e2; color: #b91c1c; }
        .action-cell { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
        .btn-sm { font-size: 11px; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; transition: background .2s; }
        .btn-approve { background: #d1e7dd; color: #0f5132; }
        .btn-approve:hover { background: #b8dcc5; }
        .btn-reject { background: #fee2e2; color: #b91c1c; }
        .btn-reject:hover { background: #fbd5d7; }
        .btn-pending { background: #fef3c7; color: #b45309; }
        .btn-pending:hover { background: #fde68a; }
        .btn-certificate { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; }
        .btn-certificate:hover { background: linear-gradient(135deg, #15803d, #166534); }
        .btn-delete { background: #fee2e2; color: #a4262c; border: 1px solid #f4c4c6; }
        .btn-delete:hover { background: #fbd5d7; }
        .alert { padding: 13px 18px; border-radius: 12px; margin-bottom: 18px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #a3cfbb; }
        .alert-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid #f4c4c6; }
        .alert-warning { background: #fff3cd; color: #92400e; border: 1px solid #ffe69c; }
        .fee-box { background: #f0f9ff; border: 1px solid #bae6fd; padding: 10px; border-radius: 10px; margin-bottom: 12px; font-size: 13px; color: #0369a1; display: flex; justify-content: space-between; align-items: center; }
        .fee-box span.fee-amount { font-size: 18px; font-weight: 800; color: var(--teal); }
    </style>
    <script>
        function updateFee() {
            var doc = document.getElementById('doc_select').value;
            var fees = {
                'Barangay Clearance': 50,
                'Certificate of Indigency': 0,
                'Certificate of Residency': 50,
                'Business Permit Clearance': 150
            };
            var fee = fees[doc] || 0;
            document.getElementById('fee_disp').innerText = '₱' + fee.toFixed(2);
        }
        function toggleRef() {
            var method = document.getElementById('pay_method').value;
            document.getElementById('ref_box').style.display = (method === 'Cash') ? 'none' : 'flex';
        }
    </script>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1>Document & Clearance Requests</h1>
        <a href="../<?= $is_admin ? 'dashboard.php' : 'citizen_dashboard.php' ?>" class="btn-back">← Bumalik sa Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type === 'success' ? 'success' : ($msg_type === 'warning' ? 'warning' : 'danger') ?>">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <?php if (!$is_admin): ?>
            <div class="panel">
                <h2>Mag-request ng Dokumento</h2>
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
                    <div class="fee-box">
                        <span><b>Certification Fee:</b></span>
                        <span class="fee-amount" id="fee_disp">₱0.00</span>
                    </div>
                    <div class="form-group">
                        <label>Paraan ng Pagbabayad</label>
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

        <div class="panel">
            <h2><?= $is_admin ? 'Lahat ng Hiling na Dokumento' : 'Aking mga Inilapit na Request' ?></h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Naghiling</th>
                            <th>Dokumento</th>
                            <th>Bayad & Method</th>
                            <th>Status</th>
                            <th>Aksyon</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; color:var(--muted); padding: 30px;">
                                    Walang record na nahanap.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $r):
                                $row_id      = intval($r['_id'] ?? 0);
                                $row_status  = $r['status'] ?? 'Pending';
                                $row_name    = $r['_display_name'] ?? $username;

                                $raw_type = '';
                                if ($sr_type_col && isset($r[$sr_type_col])) {
                                    $raw_type = $r[$sr_type_col];
                                }
                                $row_type = normalize_type($raw_type);

                                $row_purpose = '';
                                if ($sr_purpose_col && isset($r[$sr_purpose_col])) {
                                    $row_purpose = $r[$sr_purpose_col];
                                }

                                $row_amount = floatval($r[$sr_amount_col] ?? 0);
                                if ($row_amount == 0 && isset($doc_fees[$row_type])) {
                                    $row_amount = $doc_fees[$row_type];
                                }

                                $real_res_id = get_real_resident_id($conn, $user_id);
                                $is_own_row  = check_ownership($r, $real_res_id, $user_id, $username, $sr_resident_col, $sr_user_col, $sr_name_col);
                                $can_delete_row = ($is_admin || $is_own_row);
                            ?>
                                <tr>
                                    <td><b><?= e($row_name) ?></b></td>
                                    <td>
                                        <b><?= e($row_type) ?></b><br>
                                        <small style="color:var(--muted);"><?= e($row_purpose) ?></small>
                                    </td>
                                    <td>
                                        ₱<?= number_format($row_amount, 2) ?><br>
                                        <small class="badge" style="background:#f1f5f9; color:#475569;"><?= e($r['payment_method'] ?? 'Cash') ?></small>
                                        <?php if (!empty($r['reference_number'])): ?>
                                            <br><small style="font-size:10px; color:var(--muted);">Ref: <?= e($r['reference_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-<?= e($row_status) ?>"><?= e($row_status) ?></span></td>
                                    <td>
                                        <div class="action-cell">
                                            <?php if ($is_admin): ?>
                                                <?php if ($row_id > 0 && $row_status === 'Pending'): ?>
                                                    <a href="documents.php?action=approve&id=<?= $row_id ?>" class="btn-sm btn-approve" onclick="return confirm('Aprubahan?');">Aprubahan</a>
                                                    <a href="documents.php?action=reject&id=<?= $row_id ?>" class="btn-sm btn-reject" onclick="return confirm('Tanggihan?');">Tanggihan</a>
                                                <?php elseif ($row_id > 0 && $row_status === 'Approved'): ?>
                                                    <a href="print_certificate.php?id=<?= $row_id ?>" target="_blank" class="btn-sm btn-certificate">Print Certificate</a>
                                                    <a href="documents.php?action=pending&id=<?= $row_id ?>" class="btn-sm btn-pending">Ibalik sa Pending</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($row_id > 0 && $row_status === 'Approved'): ?>
                                                    <a href="print_certificate.php?id=<?= $row_id ?>" target="_blank" class="btn-sm btn-certificate">Print Certificate</a>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if ($can_delete_row && $row_id > 0): ?>
                                                <a href="documents.php?action=delete&id=<?= $row_id ?>" class="btn-sm btn-delete" onclick="return confirm('Burahin?');">Delete</a>
                                            <?php endif; ?>
                                        </div>
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