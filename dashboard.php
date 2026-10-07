<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

/* Security Check: Officials Only */
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'Resident') {
    header('Location: citizen_dashboard.php');
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Official';
$position = $_SESSION['position'] ?? 'Barangay Official';

$msg = '';

/* Create New Community Feed Post */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_feed_post'])) {
    $content = trim($_POST['feed_content'] ?? '');
    $image_path = NULL;

    if (!empty($_FILES['feed_image']['name'])) {
        $target_dir = "uploads/posts/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_name = time() . '_' . basename($_FILES['feed_image']['name']);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES['feed_image']['tmp_name'], $target_file)) {
            $image_path = $target_file;
        }
    }

    if (!empty($content) || $image_path) {
        try {
            $stmt = $conn->prepare("INSERT INTO posts (user_id, author_name, author_role, content, image_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $user_id, $username, $position, $content, $image_path);
            $stmt->execute();
            $msg = "Community Feed post published successfully!";
        } catch (Throwable $e) {
            $msg = "Error posting feed: " . $e->getMessage();
        }
    }
}

/* Delete Feed Post */
if (isset($_GET['action']) && $_GET['action'] === 'delete_post' && isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    if ($post_id > 0) {
        try {
            $img_q = $conn->query("SELECT image_path FROM posts WHERE post_id = $post_id");
            if ($img_q && $row = $img_q->fetch_assoc()) {
                if (!empty($row['image_path']) && file_exists($row['image_path'])) {
                    unlink($row['image_path']);
                }
            }
            $conn->query("DELETE FROM posts WHERE post_id = $post_id");
            header('Location: dashboard.php');
            exit();
        } catch (Throwable $e) {}
    }
}

/* Safe Fetch Statistics Metrics */
$metrics = ['residents' => 0, 'cases' => 0, 'documents' => 0];
try {
    if ($r = $conn->query("SELECT COUNT(*) AS t FROM residents")) $metrics['residents'] = $r->fetch_assoc()['t'] ?? 0;
} catch (Throwable $e) {}

try {
    if ($r = $conn->query("SELECT COUNT(*) AS t FROM cases WHERE status != 'Resolved'")) $metrics['cases'] = $r->fetch_assoc()['t'] ?? 0;
} catch (Throwable $e) {}

try {
    if ($r = $conn->query("SELECT COUNT(*) AS t FROM service_requests WHERE status = 'Pending'")) $metrics['documents'] = $r->fetch_assoc()['t'] ?? 0;
} catch (Throwable $e) {}

/* Safe Fetch Feed Posts */
$feed_posts = [];
try {
    $p_res = $conn->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 10");
    if ($p_res) {
        while ($row = $p_res->fetch_assoc()) $feed_posts[] = $row;
    }
} catch (Throwable $e) {}

/* Safe Fetch Published Announcements */
$announcements = [];
try {
    $a_res = $conn->query("SELECT * FROM announcements ORDER BY 1 DESC LIMIT 5");
    if ($a_res) {
        while ($row = $a_res->fetch_assoc()) {
            $announcements[] = [
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
    <title>Official Dashboard · E-Barangay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --night: #0c2733; --teal: #14566b; --palm: #2f8a57; --gold: #f2b84b;
            --paper: #fffdf7; --ink: #16262e; --muted: #5f717a; --line: #d9e1e4;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Figtree', sans-serif; color: #fff; background: var(--night); min-height: 100vh; }
        
        .scene { position: fixed; inset: 0; z-index: 0; background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%); }
        .scene svg { position: absolute; inset: 0; width: 100%; height: 100%; }
        .scene::after { content: ""; position: absolute; inset: 0; background: rgba(8, 24, 33, 0.45); }
        
        .page { position: relative; z-index: 2; min-height: 100vh; display: flex; flex-direction: column; }
        .nav { display: flex; align-items: center; justify-content: space-between; padding: 14px 48px; background: rgba(8,24,33,.75); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255,255,255,.1); }
        .brand b { font-family: 'Bricolage Grotesque', sans-serif; font-size: 18px; color: #fff; }
        .role-badge { background: rgba(242,184,75,.2); color: var(--gold); border: 1px solid rgba(242,184,75,.4); padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .btn-logout { background: rgba(255,255,255,.12); color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 999px; font-size: 14px; font-weight: 600; }
        
        .container { max-width: 1240px; margin: 0 auto; padding: 32px 24px 60px; width: 100%; }
        .welcome-panel { background: rgba(10,30,42,.8); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,.15); border-radius: 20px; padding: 28px; margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center; }
        
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .metric-card { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 16px; padding: 20px; box-shadow: 0 10px 25px rgba(0,0,0,.2); }
        .metric-card .title { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
        .metric-card .value { font-family: 'Bricolage Grotesque', sans-serif; font-size: 32px; font-weight: 800; margin: 4px 0; }

        .main-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 900px) { .main-layout { grid-template-columns: 1fr; } }

        .panel-card { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 24px; box-shadow: 0 15px 35px rgba(0,0,0,.3); margin-bottom: 24px; }
        .panel-card h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 20px; margin-bottom: 16px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 8px; }

        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .action-card { display: flex; flex-direction: column; padding: 16px; background: #f5f8f9; border: 1.5px solid var(--line); border-radius: 12px; text-decoration: none; color: var(--ink); transition: all .2s; }
        .action-card:hover { background: #fff; border-color: var(--teal); transform: translateY(-2px); }
        .action-card b { font-size: 14px; color: var(--teal); }
        .action-card span { font-size: 12px; color: var(--muted); }

        .form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; }
        .form-group label { font-size: 13px; font-weight: 700; color: var(--muted); }
        .form-group input, .form-group textarea { padding: 10px; border: 1.5px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; }
        .btn-post { background: var(--teal); color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; }

        .anc-card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 14px; margin-bottom: 10px; }
        .feed-item { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 14px; margin-top: 12px; }
        .feed-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .feed-author { font-weight: 700; font-size: 13.5px; color: var(--ink); }
        .feed-role { font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 999px; }
        .feed-img { width: 100%; max-height: 280px; object-fit: cover; border-radius: 8px; margin-top: 8px; }

        .btn-delete { background: #fee2e2; color: #dc2626; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 6px; }
    </style>
</head>
<body>

<div class="scene">
    <svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMax slice">
        <path d="M0 640 L170 470 L470 400 L690 600 L1060 620 L1430 590 L1600 500 V900 H0Z" fill="#1c6a72" opacity=".9"/>
        <path d="M0 740 Q400 700 800 716 T1600 700 V900 H0Z" fill="#b98a2a"/>
    </svg>
</div>

<div class="page">
    <nav class="nav">
        <div class="brand">
            <b>E-Barangay Admin</b>
            <small>Management System</small>
        </div>
        <div style="display:flex; align-items:center; gap:16px;">
            <span class="role-badge"><?= e($position) ?></span>
            <a href="logout.php" class="btn-logout">Sign Out</a>
        </div>
    </nav>

    <main class="container">
        <div class="welcome-panel">
            <div>
                <h1 style="font-family:'Bricolage Grotesque', sans-serif;">Maligayang pagdating, <?= e($username) ?>!</h1>
                <p style="color:#cbd5e1; margin-top:4px;">Barangay Administrative Management Panel</p>
            </div>
            <div style="color:var(--gold); font-weight:700;">📅 <?= date('F j, Y') ?></div>
        </div>

        <?php if ($msg): ?>
            <div style="background:#d1e7dd; color:#0f5132; padding:10px 14px; border-radius:10px; margin-bottom:16px; font-weight:600; font-size:14px;">
                <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <!-- Key Metrics -->
        <div class="metrics-grid">
            <div class="metric-card"><div class="title">Resident Registry</div><div class="value"><?= number_format($metrics['residents']) ?></div></div>
            <div class="metric-card"><div class="title">Document Requests</div><div class="value"><?= number_format($metrics['documents']) ?></div></div>
            <div class="metric-card"><div class="title">Complaints & Blotter</div><div class="value"><?= number_format($metrics['cases']) ?></div></div>
        </div>

        <div class="main-layout">
            <!-- Left Side: Official Modules -->
            <div>
                <div class="panel-card">
                    <h2>⚙️ Barangay Management Modules</h2>
                    <div class="actions-grid">
                        <a href="modules/residents.php" class="action-card">
                            <b>👥 Resident Registry</b>
                            <span>Manage registered residents list</span>
                        </a>
                        <a href="modules/documents.php" class="action-card">
                            <b>📄 Document Requests</b>
                            <span>Process clearances & certificates</span>
                        </a>
                        <a href="modules/complaints.php" class="action-card">
                            <b>⚖️ Complaints & Blotter</b>
                            <span>Blotters, disputes & mediation</span>
                        </a>
                        <a href="modules/treasury.php" class="action-card">
                            <b>💳 Treasury & Revenue</b>
                            <span>Financial records & OR payments</span>
                        </a>
                        <a href="modules/facilities.php" class="action-card">
                            <b>🏛️ Facilities Management</b>
                            <span>Approve & manage facility reservations</span>
                        </a>
                        <a href="modules/events.php" class="action-card">
                            <b>📅 Events Management</b>
                            <span>Organize community events & requests</span>
                        </a>
                        <a href="modules/announcements.php" class="action-card">
                            <b>📢 Announcements</b>
                            <span>Post & edit official advisories</span>
                        </a>
                    </div>
                </div>

                <!-- Published Announcements -->
                <div class="panel-card">
                    <h2>📢 Published Announcements</h2>
                    <?php if (empty($announcements)): ?>
                        <p style="font-size:13px; color:var(--muted);">Walang nakapost na opisyal na anunsyo sa ngayon.</p>
                    <?php else: ?>
                        <?php foreach ($announcements as $anc): ?>
                            <div class="anc-card">
                                <b style="color:var(--teal); font-size:15px; display:block;"><?= e($anc['title']) ?></b>
                                <span style="font-size:11px; color:var(--muted);">Posted on <?= date('M d, Y h:i A', strtotime($anc['date'])) ?></span>
                                <p style="font-size:13px; color:#334155; margin-top:6px; line-height:1.4;"><?= nl2br(e($anc['content'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Community Feed Control -->
            <div>
                <div class="panel-card">
                    <h2>🖼️ Post Activity to Community Feed</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="create_feed_post" value="1">
                        <div class="form-group">
                            <label>Caption / Activity Description</label>
                            <textarea name="feed_content" rows="3" placeholder="Write caption or photo details..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Upload Photo</label>
                            <input type="file" name="feed_image" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn-post" style="background:var(--palm);">📷 Publish Photo Post</button>
                    </form>

                    <!-- Feed Items -->
                    <div style="margin-top:20px;">
                        <h3 style="font-size:16px; color:var(--teal); margin-bottom:10px;">Published Feed Posts</h3>
                        <?php if (empty($feed_posts)): ?>
                            <p style="font-size:13px; color:var(--muted);">No feed posts yet.</p>
                        <?php else: ?>
                            <?php foreach ($feed_posts as $post): ?>
                                <div class="feed-item">
                                    <div class="feed-header">
                                        <span class="feed-author"><?= e($post['author_name']) ?> <span class="feed-role"><?= e($post['author_role']) ?></span></span>
                                        <span style="font-size:11px; color:var(--muted);"><?= date('M d, g:i a', strtotime($post['created_at'])) ?></span>
                                    </div>
                                    <p style="font-size:13.5px; color:#334155; line-height:1.4;"><?= nl2br(e($post['content'])) ?></p>
                                    <?php if (!empty($post['image_path'])): ?>
                                        <img src="<?= e($post['image_path']) ?>" class="feed-img" alt="Feed Image">
                                    <?php endif; ?>
                                    <br>
                                    <a href="dashboard.php?action=delete_post&post_id=<?= $post['post_id'] ?>" class="btn-delete" onclick="return confirm('Gusto mo bang burahin ang feed post na ito?');">🗑️ Delete Feed</a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>