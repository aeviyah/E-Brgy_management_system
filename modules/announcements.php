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

/* Post Announcement (Official Only) */
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_ann'])) {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!empty($title) && !empty($content)) {
        try {
            $stmt = $conn->prepare("INSERT INTO announcements (title, content, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ss", $title, $content);
            if ($stmt->execute()) {
                $msg = "Matagumpay na na-post ang opisyal na anunsyo!";
            }
        } catch (Throwable $e) {
            $msg = "Error: " . $e->getMessage();
        }
    }
}

/* Delete Announcement (Official Only) */
if ($is_admin && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $ann_id = intval($_GET['id']);
    if ($ann_id > 0) {
        $conn->query("DELETE FROM announcements WHERE announcement_id = $ann_id OR id = $ann_id");
        $msg = "Nabura ang anunsyo!";
    }
}

/* Fetch Announcements */
$announcements = [];
try {
    $res = $conn->query("SELECT * FROM announcements ORDER BY 1 DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $announcements[] = [
                'id'      => $row['announcement_id'] ?? $row['id'] ?? 0,
                'title'   => $row['title'] ?? 'Announcement',
                'content' => $row['content'] ?? $row['details'] ?? $row['description'] ?? '',
                'date'    => $row['created_at'] ?? $row['date_posted'] ?? date('Y-m-d H:i:s')
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
        .form-group input, .form-group textarea { padding: 10px; border: 1.5px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; }
        .btn { background: var(--teal); color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        
        .anc-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 14px; box-shadow: 0 4px 12px rgba(0,0,0,.04); }
        .btn-delete { background: #fee2e2; color: #dc2626; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 8px; }
    </style>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1 style="font-family:'Bricolage Grotesque', sans-serif;">📢 Official Barangay Announcements</h1>
        <a href="../<?= $is_admin ? 'dashboard.php' : 'citizen_dashboard.php' ?>" class="btn-back">← Bumalik sa Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div style="background:#d1e7dd; color:#0f5132; padding:12px; border-radius:10px; margin-bottom:16px; font-weight:600; font-size:14px;">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <?php if ($is_admin): ?>
            <!-- Post Announcement Form -->
            <div class="panel">
                <h2>📢 Mag-post ng Anunsyo</h2>
                <form method="POST">
                    <input type="hidden" name="post_ann" value="1">
                    <div class="form-group"><label>Pamagat ng Anunsyo</label><input type="text" name="title" required placeholder="e.g. Barangay General Assembly"></div>
                    <div class="form-group"><label>Nilalaman / Detalye</label><textarea name="content" rows="4" required placeholder="Isulat ang kumpletong impormasyon..."></textarea></div>
                    <button type="submit" class="btn">I-post ang Advisory</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Announcements List -->
        <div class="panel">
            <h2>📢 Listahan ng mga Opisyal na Anunsyo</h2>
            <?php if (empty($announcements)): ?>
                <p style="font-size:13px; color:var(--muted);">Walang pampublikong anunsyo sa ngayon.</p>
            <?php else: ?>
                <?php foreach ($announcements as $anc): ?>
                    <div class="anc-card">
                        <b style="color:var(--teal); font-size:17px; display:block;"><?= e($anc['title']) ?></b>
                        <span style="font-size:11px; color:var(--muted);">📅 Naipost noong <?= date('M d, Y h:i A', strtotime($anc['date'])) ?></span>
                        <p style="font-size:14px; color:#334155; margin-top:8px; line-height:1.5;"><?= nl2br(e($anc['content'])) ?></p>
                        <?php if ($is_admin): ?>
                            <a href="announcements.php?action=delete&id=<?= $anc['id'] ?>" class="btn-delete" onclick="return confirm('Gusto mo bang burahin ang anunsyo na ito?');">🗑️ Burahin</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>