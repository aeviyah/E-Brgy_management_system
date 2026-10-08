<?php
/* ============================================================
   E-Barangay — Treasury Module (v3 - WITH DEBUG MODE)
   ============================================================ */

/* TEMPORARY DEBUG — remove these 3 lines once working */
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

/* NOTE: do NOT call mysqli_report() here — it belongs in config/db.php */

/* ============================================================
   DIAGNOSTIC MODE — visit treasury.php?debug=1 to inspect session
   ============================================================ */
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Treasury Debug</title></head><body style='font-family:monospace; background:#0c2733; color:#0f0; padding:30px;'>";
    echo "<h2 style='color:#f2b84b;'>🔍 Treasury Debug Info</h2>";
    echo "<pre style='background:#000; padding:20px; border:2px solid #f2b84b; border-radius:8px;'>";
    echo "PHP Version       : " . PHP_VERSION . "\n";
    echo "Session Status    : " . session_status() . "\n";
    echo "Session user_id   : " . ($_SESSION['user_id'] ?? '❌ NOT SET') . "\n";
    echo "Session username  : " . ($_SESSION['username'] ?? '❌ NOT SET') . "\n";
    echo "Session role      : " . ($_SESSION['role'] ?? '❌ NOT SET') . "\n";
    echo "Session position  : " . ($_SESSION['position'] ?? '(not set)') . "\n";
    echo "is_admin          : " . ((($_SESSION['role'] ?? 'Resident') !== 'Resident') ? '✅ YES' : '❌ NO (Resident)') . "\n";
    echo "DB connection     : " . (isset($conn) && $conn instanceof mysqli ? '✅ OK' : '❌ FAILED') . "\n";

    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $chk = $conn->query("SHOW TABLES LIKE 'treasury'");
            echo "treasury table    : " . ($chk && $chk->num_rows > 0 ? '✅ EXISTS' : '❌ MISSING') . "\n";
            if ($chk && $chk->num_rows > 0) {
                $cc = $conn->query("SHOW COLUMNS FROM treasury");
                $cols = [];
                if ($cc) { while ($c = $cc->fetch_assoc()) $cols[] = $c['Field']; }
                echo "treasury columns  : " . implode(', ', $cols) . "\n";
            }
        } catch (Throwable $e) {
            echo "DB probe error    : " . $e->getMessage() . "\n";
        }
    }

    echo "\nFULL SESSION:\n";
    print_r($_SESSION);
    echo "</pre></body></html>";
    exit();
}

/* ============================================================
   ACCESS CONTROL — ADMIN ONLY
   ============================================================ */
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$role     = $_SESSION['role'] ?? 'Resident';
$is_admin = ($role !== 'Resident');

if (!$is_admin) {
    /* Show a clear message instead of silent redirect for debugging */
    if (isset($_GET['force']) && $_GET['force'] === '1') {
        die("<div style='font-family:sans-serif; padding:40px; background:#fdecec; color:#a4262c;'>
                <h2>🔒 Access Denied</h2>
                <p>Ang Treasury module ay para lamang sa mga <b>Barangay Officials</b>.</p>
                <p>Ang iyong role: <b>" . htmlspecialchars($role) . "</b></p>
                <p>Kung admin ka at nakikita mo ito, may problema sa session. I-visit ang <code>treasury.php?debug=1</code> para makita ang session data.</p>
                <a href='../citizen_dashboard.php' style='display:inline-block; margin-top:20px; padding:10px 24px; background:#14566b; color:#fff; text-decoration:none; border-radius:8px;'>← Bumalik sa Dashboard</a>
             </div>");
    }
    header('Location: ../citizen_dashboard.php');
    exit();
}

$msg = '';
$msg_type = '';

/* ============================================================
   Ensure treasury table exists
   ============================================================ */
try {
    $conn->query("CREATE TABLE IF NOT EXISTS `treasury` (
        `treasury_id`      INT(11) NOT NULL AUTO_INCREMENT,
        `or_number`        VARCHAR(50) NOT NULL,
        `payer_name`       VARCHAR(150) NOT NULL,
        `payment_type`     VARCHAR(120) NOT NULL,
        `amount`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_method`   ENUM('Cash','GCash','Maya','MariBank') DEFAULT 'Cash',
        `reference_number` VARCHAR(100) DEFAULT NULL,
        `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`treasury_id`),
        UNIQUE KEY `or_number` (`or_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $e) {
    /* table may already exist */
}

/* ============================================================
   Detect treasury columns
   ============================================================ */
$tre_cols = [];
try {
    $cc = $conn->query("SHOW COLUMNS FROM treasury");
    if ($cc) { while ($c = $cc->fetch_assoc()) $tre_cols[] = $c['Field']; }
} catch (Throwable $e) {}

$tre_id_col   = 'treasury_id';
foreach (['treasury_id','transaction_id','id'] as $c) {
    if (in_array($c, $tre_cols)) { $tre_id_col = $c; break; }
}

$has_payer_col   = in_array('payer_name', $tre_cols);
$has_type_col    = in_array('payment_type', $tre_cols);
$has_amount_col  = in_array('amount', $tre_cols);
$has_method_col  = in_array('payment_method', $tre_cols);
$has_or_col      = in_array('or_number', $tre_cols);
$has_ref_col     = in_array('reference_number', $tre_cols);
$has_created_col = in_array('created_at', $tre_cols);

/* ============================================================
   Handle POST — Add Collection
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {

    $payer  = trim($_POST['payer_name']     ?? '');
    $type   = trim($_POST['payment_type']   ?? 'Barangay Clearance Fee');
    $amount = floatval($_POST['amount']     ?? 0);
    $method = trim($_POST['payment_method'] ?? 'Cash');
    $or_in  = trim($_POST['or_number']      ?? '');

    $valid_methods = ['Cash','GCash','Maya','MariBank'];
    if (!in_array($method, $valid_methods, true)) $method = 'Cash';

    if (empty($or_in)) {
        $or_in = 'OR-' . date('Ymd-His') . '-' . rand(100, 999);
    }

    if (empty($payer)) {
        $msg = "❌ Kailangan mong ilagay ang pangalan ng nagbayad.";
        $msg_type = "danger";
    } elseif ($amount <= 0) {
        $msg = "❌ Ang halaga ay dapat higit sa ₱0.00.";
        $msg_type = "danger";
    } else {
        try {
            $ins_cols = [];
            $placeholders = [];
            $params = [];
            $types = '';

            if ($has_payer_col)   { $ins_cols[]='payer_name';       $placeholders[]='?'; $params[]=$payer;  $types.='s'; }
            if ($has_type_col)    { $ins_cols[]='payment_type';     $placeholders[]='?'; $params[]=$type;   $types.='s'; }
            if ($has_amount_col)  { $ins_cols[]='amount';           $placeholders[]='?'; $params[]=$amount; $types.='d'; }
            if ($has_method_col)  { $ins_cols[]='payment_method';   $placeholders[]='?'; $params[]=$method; $types.='s'; }
            if ($has_or_col)      { $ins_cols[]='or_number';        $placeholders[]='?'; $params[]=$or_in;  $types.='s'; }
            if ($has_ref_col)     { $ins_cols[]='reference_number'; $placeholders[]='?'; $params[]='';      $types.='s'; }

            if (empty($ins_cols)) {
                throw new Exception("Walang tamang columns ang treasury table.");
            }

            $sql = "INSERT INTO treasury (" . implode(', ', $ins_cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) throw new Exception("Prepare failed: " . $conn->error);

            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
            $stmt->close();

            header("Location: treasury.php?saved=1&or=" . urlencode($or_in));
            exit();

        } catch (Throwable $e) {
            $msg = "❌ Error sa pag-save: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

/* Show success after redirect */
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $saved_or = $_GET['or'] ?? '';
    $msg = "✅ Matagumpay na naitala ang koleksyon! OR#: " . htmlspecialchars($saved_or);
    $msg_type = "success";
}

/* ============================================================
   Delete Treasury Record
   ============================================================ */
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    if ($del_id > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM treasury WHERE $tre_id_col = ?");
            $stmt->bind_param("i", $del_id);
            $stmt->execute();
            $stmt->close();
            header('Location: treasury.php?deleted=1');
            exit();
        } catch (Throwable $e) {
            $msg = "❌ Delete error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

if (isset($_GET['deleted'])) {
    $msg = "✅ Nabura ang treasury record.";
    $msg_type = "success";
}

/* ============================================================
   Fetch Treasury Records
   ============================================================ */
$records = [];
$total_revenue = 0;

try {
    $order_by = $has_created_col ? "ORDER BY created_at DESC" : "ORDER BY $tre_id_col DESC";
    $res = $conn->query("SELECT * FROM treasury $order_by");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['_row_id'] = intval($row[$tre_id_col] ?? 0);
            $records[] = $row;
            $total_revenue += floatval($row['amount'] ?? 0);
        }
    }
} catch (Throwable $e) {
    if (empty($msg)) {
        $msg = "⚠️ Fetch error: " . $e->getMessage();
        $msg_type = "danger";
    }
}

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
        :root { --night: #0c2733; --teal: #14566b; --palm: #2f8a57; --gold: #f2b84b; --line: #d9e1e4; --ink: #16262e; --muted: #5f717a; --danger:#a4262c; --danger-bg:#fdecec; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Figtree', sans-serif; color: #fff; background: var(--night); min-height: 100vh; }
        .scene { position: fixed; inset: 0; z-index: 0; background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%); }
        .scene::after { content: ""; position: absolute; inset: 0; background: rgba(8, 24, 33, 0.45); }
        .page { position: relative; z-index: 2; padding: 24px; max-width: 1240px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 12px; flex-wrap: wrap; }
        .btn-back { background: rgba(255,255,255,.15); color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 999px; font-weight: 600; font-size: 14px; }
        .grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .panel { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,.3); }
        .panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 22px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 10px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
        .form-group input, .form-group select { padding: 10px; border: 1.5px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; background: #f5f8f9; transition: border-color .2s, background .2s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--teal); background: #fff; }
        .btn { background: var(--teal); color: #fff; border: none; padding: 12px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; font-family: inherit; font-size: 14px; width: 100%; transition: background .2s, transform .2s; }
        .btn:hover { background: #0d3f50; transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }

        .stat-box { background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 12px; padding: 16px; margin-bottom: 16px; color: #0369a1; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #f1f5f9; color: var(--teal); text-align: left; padding: 12px; font-weight: 700; border-bottom: 2px solid var(--line); font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
        td { padding: 12px; border-bottom: 1px solid var(--line); color: #334155; vertical-align: middle; }
        .badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px; background: #f1f5f9; color: #475569; }

        .btn-delete {
            background: var(--danger-bg); color: var(--danger);
            font-size: 11px; font-weight: 700; padding: 6px 10px; border-radius: 6px;
            text-decoration: none; display: inline-flex; align-items: center; gap: 4px;
            transition: background .2s; border: 1px solid #f4c4c6;
        }
        .btn-delete:hover { background: #fbd5d7; }

        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #a3cfbb; }
        .alert-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid #f4c4c6; }
        .empty-state { text-align: center; color: var(--muted); padding: 30px; font-style: italic; }
    </style>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1 style="font-family:'Bricolage Grotesque', sans-serif;">💳 Treasury & Financial Transparency</h1>
        <a href="../dashboard.php" class="btn-back">← Bumalik sa Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?>">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <!-- Form -->
        <div class="panel">
            <h2>➕ Magtala ng Koleksyon / Bayad</h2>
            <form method="POST" action="treasury.php" autocomplete="off">
                <input type="hidden" name="add_payment" value="1">

                <div class="form-group">
                    <label>Nagbayad (Payer Name)</label>
                    <input type="text" name="payer_name" required placeholder="Pangalan ng residente">
                </div>

                <div class="form-group">
                    <label>Uri ng Bayad</label>
                    <select name="payment_type" required>
                        <option value="Barangay Clearance Fee">Barangay Clearance Fee</option>
                        <option value="Certificate of Indigency Fee">Certificate of Indigency Fee</option>
                        <option value="Certificate of Residency Fee">Certificate of Residency Fee</option>
                        <option value="Business Permit Fee">Business Permit Fee</option>
                        <option value="Facility Rental Fee">Facility Rental Fee</option>
                        <option value="Other Fee">Other Fee</option>
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

                <div class="form-group">
                    <label>Halaga (PHP)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required placeholder="50.00">
                </div>

                <div class="form-group">
                    <label>OR Number <small style="text-transform:none; font-weight:400;">(kung blangko, kusang gagawa)</small></label>
                    <input type="text" name="or_number" placeholder="Halimbawa: OR-20260108-001">
                </div>

                <button type="submit" class="btn">💾 I-save ang Koleksyon</button>
            </form>
        </div>

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
                            <th>Aksyon</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">Walang treasury records sa ngayon. Mag-add gamit ang form sa kaliwa.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td><b><?= e($r['or_number'] ?? 'N/A') ?></b></td>
                                    <td><?= e($r['payer_name'] ?? 'N/A') ?></td>
                                    <td><?= e($r['payment_type'] ?? 'Fee') ?></td>
                                    <td><span class="badge"><?= e($r['payment_method'] ?? 'Cash') ?></span></td>
                                    <td style="color:var(--palm); font-weight:700;">₱<?= number_format(floatval($r['amount'] ?? 0), 2) ?></td>
                                    <td><?= !empty($r['created_at']) ? date('M d, Y', strtotime($r['created_at'])) : '—' ?></td>
                                    <td>
                                        <a href="treasury.php?action=delete&id=<?= intval($r['_row_id']) ?>"
                                           class="btn-delete"
                                           onclick="return confirm('Sigurado ka bang gusto mong burahin ang treasury record na ito?');">
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                                            Delete
                                        </a>
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