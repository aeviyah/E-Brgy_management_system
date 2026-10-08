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

$current_user_id = $_SESSION['user_id'] ?? 0;

$msg = '';
$msg_type = 'success';

/* ============================================
   Post Announcement (Official Only)
   ============================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_ann'])) {
    $title            = trim($_POST['title'] ?? '');
    $content          = trim($_POST['content'] ?? '');
    $target_audience  = trim($_POST['target_audience'] ?? 'All');
    $channel          = trim($_POST['channel'] ?? 'In-App');
    $status           = trim($_POST['status'] ?? 'Published');

    if (!empty($title) && !empty($content)) {
        try {
            $sql = "INSERT INTO announcements 
                    (title, content, target_audience, channel, status, posted_by, date_posted) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $title, $content, $target_audience, $channel, $status, $current_user_id);
            if ($stmt->execute()) {
                $msg = "Matagumpay na na-post ang opisyal na anunsyo!";
                $msg_type = "success";
            }
        } catch (Throwable $e) {
            $msg = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    } else {
        $msg = "Kailangan punan ang Pamagat at Nilalaman.";
        $msg_type = "danger";
    }
}

/* ============================================
   Delete Announcement (Official Only)
   ============================================ */
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $ann_id = intval($_GET['id']);
    if ($ann_id > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
            $stmt->bind_param("i", $ann_id);
            if ($stmt->execute()) {
                $msg = "Nabura ang anunsyo!";
                $msg_type = "success";
            }
        } catch (Throwable $e) {
            $msg = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

/* ============================================
   Fetch Announcements
   ============================================ */
$announcements = [];
try {
    $res = $conn->query("SELECT * FROM announcements ORDER BY date_posted DESC, announcement_id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $announcements[] = [
                'id'              => $row['announcement_id'] ?? 0,
                'title'           => $row['title'] ?? 'Announcement',
                'content'         => $row['content'] ?? '',
                'target_audience' => $row['target_audience'] ?? 'All',
                'channel'         => $row['channel'] ?? 'In-App',
                'status'          => $row['status'] ?? 'Published',
                'posted_by'       => $row['posted_by'] ?? null,
                'date'            => $row['date_posted'] ?? date('Y-m-d H:i:s')
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
    <title>Announcements · E-Barangay</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --night: #0c2733; --teal: #14566b; --palm: #2f8a57; --gold: #f2b84b; --line: #d9e1e4; --ink: #16262e; --muted: #5f717a; --danger:#a4262c; --danger-bg:#fdecec; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Figtree', sans-serif; color: #fff; background: var(--night); min-height: 100vh; }
        .scene { position: fixed; inset: 0; z-index: 0; background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%); }
        .scene::after { content: ""; position: absolute; inset: 0; background: rgba(8, 24, 33, 0.5); }
        .page { position: relative; z-index: 2; padding: 24px; max-width: 1240px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 12px; flex-wrap: wrap; }
        .top-bar h1 { font-family:'Bricolage Grotesque', sans-serif; display:flex; align-items:center; gap:10px; font-size: clamp(20px, 2.6vw, 26px); }
        .btn-back { background: rgba(255,255,255,.15); color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 999px; font-weight: 600; font-size: 14px; display:inline-flex; align-items:center; gap:7px; transition: background .2s; }
        .btn-back:hover { background: rgba(255,255,255,.25); }
        .grid { display: grid; grid-template-columns: 1fr 1.6fr; gap: 24px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .panel { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,.3); }
        .panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 20px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 10px; margin-bottom: 18px; display:flex; align-items:center; gap:10px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing:.4px; }
        .form-group input, .form-group textarea, .form-group select {
            padding: 12px; border: 2px solid var(--line); border-radius: 10px;
            font-family: inherit; font-size: 14px; background:#f5f8f9;
            transition: border-color .2s, background .2s;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline:none; border-color: var(--teal); background:#fff;
        }
        .btn { background: linear-gradient(135deg, var(--teal), #0d3f50); color: #fff; border: none; padding: 13px 22px; border-radius: 10px; font-weight: 800; cursor: pointer; font-family:inherit; font-size:14px; box-shadow: 0 8px 20px rgba(20,86,107,.3); width:100%; display:inline-flex; justify-content:center; align-items:center; gap:8px; transition: transform .2s; }
        .btn:hover { transform: translateY(-2px); }
        .anc-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 14px; box-shadow: 0 4px 12px rgba(0,0,0,.04); border-left: 4px solid var(--gold); }
        .anc-card .anc-title { color:var(--teal); font-size:17px; display:block; font-family:'Bricolage Grotesque', sans-serif; font-weight:800; margin-bottom:6px; }
        .anc-meta-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
        .anc-meta { font-size:10.5px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; font-weight:700; padding:3px 8px; background:#f0f4f6; border-radius:999px; }
        .anc-meta.status { background: rgba(47,138,87,.12); color: #1f6e41; }
        .anc-card .anc-body { font-size:14px; color:#334155; margin-top:8px; line-height:1.6; }
        .btn-delete { background: var(--danger-bg); color: var(--danger); font-size: 11.5px; font-weight: 700; padding: 6px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items:center; gap:5px; margin-top: 10px; transition: background .2s; }
        .btn-delete:hover { background: #fbd5d7; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: 600; font-size: 14px; display:flex; align-items:center; gap:8px; }
        .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #a3cfbb; }
        .alert-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid #f4c4c6; }
        .empty-state { padding: 30px; text-align:center; color:var(--muted); font-style:italic; font-size:14px; }
    </style>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1>
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h3l4 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M16 8a4 4 0 0 1 0 8"/></svg>
            Official Barangay Announcements
        </h1>
        <a href="../dashboard.php" class="btn-back">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Bumalik sa Dashboard
        </a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?>">
            <?php if ($msg_type === 'success'): ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="9 12 12 15 16 10"/></svg>
            <?php else: ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php endif; ?>
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <!-- Post Announcement Form -->
        <div class="panel">
            <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Mag-post ng Anunsyo
            </h2>
            <form method="POST">
                <input type="hidden" name="post_ann" value="1">
                <div class="form-group">
                    <label>Pamagat ng Anunsyo</label>
                    <input type="text" name="title" required placeholder="e.g. Barangay General Assembly">
                </div>
                <div class="form-group">
                    <label>Nilalaman / Detalye</label>
                    <textarea name="content" rows="6" required placeholder="Isulat ang kumpletong impormasyon..."></textarea>
                </div>
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target_audience">
                        <option value="All">All (Lahat ng Residente)</option>
                        <option value="Youth">Youth (Kabataan)</option>
                        <option value="Senior">Senior Citizens</option>
                        <option value="PWD">PWD</option>
                        <option value="Solo Parent">Solo Parent</option>
                        <option value="Official">Officials Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Channel</label>
                    <select name="channel">
                        <option value="In-App">In-App</option>
                        <option value="SMS">SMS</option>
                        <option value="Email">Email</option>
                        <option value="Bulletin">Bulletin Board</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Published">Published</option>
                        <option value="Draft">Draft</option>
                        <option value="Archived">Archived</option>
                    </select>
                </div>
                <button type="submit" class="btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    I-post ang Anunsyo
                </button>
            </form>
        </div>

        <!-- Announcements List -->
        <div class="panel">
            <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h3l4 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M16 8a4 4 0 0 1 0 8"/></svg>
                Listahan ng mga Opisyal na Anunsyo
            </h2>
            <?php if (empty($announcements)): ?>
                <p class="empty-state">Walang pampublikong anunsyo sa ngayon.</p>
            <?php else: ?>
                <?php foreach ($announcements as $anc): ?>
                    <div class="anc-card">
                        <b class="anc-title"><?= e($anc['title']) ?></b>
                        <div class="anc-meta-row">
                            <span class="anc-meta"><?= date('M d, Y · h:i A', strtotime($anc['date'])) ?></span>
                            <span class="anc-meta"><?= e($anc['target_audience']) ?></span>
                            <span class="anc-meta"><?= e($anc['channel']) ?></span>
                            <span class="anc-meta status"><?= e($anc['status']) ?></span>
                        </div>
                        <p class="anc-body"><?= nl2br(e($anc['content'])) ?></p>
                        <a href="announcements.php?action=delete&id=<?= $anc['id'] ?>" class="btn-delete" onclick="return confirm('Gusto mo bang burahin ang anunsyo na ito?');">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                            Burahin
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>