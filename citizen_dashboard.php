<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Resident';

$msg = '';
$msg_type = '';
$view_mode = $_GET['view'] ?? 'dashboard';

/* Process Resident Profile Update */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $middle_name    = trim($_POST['middle_name'] ?? '');
    $birthdate      = $_POST['birthdate'] ?? '';
    $gender         = $_POST['gender'] ?? 'Male';
    $civil_status   = $_POST['civil_status'] ?? 'Single';
    $purok_id       = (!empty($_POST['purok_id']) && intval($_POST['purok_id']) > 0) ? intval($_POST['purok_id']) : NULL;
    $address        = trim($_POST['address'] ?? '');
    $contact        = trim($_POST['contact_number'] ?? '');

    try {
        $check = $conn->prepare("SELECT resident_id FROM residents WHERE resident_id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE residents SET first_name=?, last_name=?, middle_name=?, birthdate=?, gender=?, civil_status=?, purok_id=?, address=?, contact_number=?, profile_completed=1 WHERE resident_id=?");
            $stmt->bind_param("ssssssissi", $first_name, $last_name, $middle_name, $birthdate, $gender, $civil_status, $purok_id, $address, $contact, $user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO residents (resident_id, first_name, last_name, middle_name, birthdate, gender, civil_status, purok_id, address, contact_number, profile_completed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("issssssiss", $user_id, $first_name, $last_name, $middle_name, $birthdate, $gender, $civil_status, $purok_id, $address, $contact);
        }

        if ($stmt->execute()) {
            $msg = "Na-update nang matagumpay ang iyong profile!";
            $msg_type = "success";
            $view_mode = 'dashboard';
        }
    } catch (Throwable $e) {
        $msg = "Error: " . $e->getMessage();
        $msg_type = "danger";
    }
}

/* Fetch resident's OWN information */
$resident_info = null;
try {
    $stmt = $conn->prepare("SELECT r.*, p.purok_name FROM residents r LEFT JOIN puroks p ON r.purok_id = p.purok_id WHERE r.resident_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $resident_info = $stmt->get_result()->fetch_assoc();
} catch (Throwable $e) {}

$is_profile_completed = !empty($resident_info['profile_completed']);

/* Fetch Puroks */
$puroks = [];
try {
    $p_res = $conn->query("SELECT * FROM puroks ORDER BY purok_name ASC");
    while ($row = $p_res->fetch_assoc()) $puroks[] = $row;
} catch (Throwable $e) {}

/* Fetch Feed Posts */
$feed_posts = [];
try {
    $p_res = $conn->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 10");
    if ($p_res) {
        while ($row = $p_res->fetch_assoc()) $feed_posts[] = $row;
    }
} catch (Throwable $e) {}

/* Fetch Official Announcements */
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
    <title>Citizen Dashboard · E-Barangay</title>
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
        
        /* Navigation Bar Base sa Luma Interface */
        .nav { display: flex; align-items: center; justify-content: space-between; padding: 14px 32px; background: rgba(8,24,33,.75); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255,255,255,.1); position: sticky; top: 0; z-index: 50; }
        .brand b { font-family: 'Bricolage Grotesque', sans-serif; font-size: 18px; color: #fff; }
        
        .nav-links { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .nav-item { color: #cfe3ea; text-decoration: none; font-size: 13.5px; font-weight: 600; padding: 6px 12px; border-radius: 8px; transition: all .2s; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,.12); color: var(--gold); }

        .role-badge { background: rgba(47,138,87,.25); color: #6ee7b7; border: 1px solid rgba(47,138,87,.4); padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .btn-logout { background: rgba(255,255,255,.12); color: #fff; text-decoration: none; padding: 6px 14px; border-radius: 999px; font-size: 13.5px; font-weight: 600; }

        .container { max-width: 1240px; margin: 0 auto; padding: 32px 24px 60px; width: 100%; }
        .welcome-panel { background: rgba(10,30,42,.8); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,.15); border-radius: 20px; padding: 28px; margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center; }
        
        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }

        .panel-card { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 28px; box-shadow: 0 15px 35px rgba(0,0,0,.3); margin-bottom: 24px; }
        .panel-card h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 22px; margin-bottom: 16px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 8px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-size: 13px; font-weight: 700; color: var(--muted); }
        .form-group input, .form-group select { padding: 10px 12px; border: 1.5px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; }

        .btn-primary { background: var(--teal); color: #fff; border: none; padding: 12px 24px; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; margin-top: 14px; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; font-weight: 600; }
        .alert-success { background: #d1e7dd; color: #0f5132; }

        .quick-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
        .link-card { background: #f5f8f9; border: 1.5px solid var(--line); border-radius: 12px; padding: 16px; text-decoration: none; color: var(--ink); font-weight: 700; display: flex; flex-direction: column; gap: 4px; transition: all .2s; }
        .link-card:hover { background: #fff; border-color: var(--teal); transform: translateY(-2px); }

        .profile-summary-box { background: #f5f8f9; border: 1.5px solid var(--line); border-radius: 16px; padding: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .summary-item label { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; display: block; }
        .summary-item span { font-size: 14px; font-weight: 600; color: var(--ink); }

        .anc-card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 12px; }
        .feed-item { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 14px; }
        .feed-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .feed-author { font-weight: 700; font-size: 13.5px; color: var(--ink); }
        .feed-role { font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 999px; }
        .feed-img { width: 100%; max-height: 350px; object-fit: cover; border-radius: 10px; margin-top: 10px; }
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
        <div class="brand"><b>E-Barangay Citizen Portal</b></div>
        <div class="nav-links">
            <a href="citizen_dashboard.php?view=dashboard" class="nav-item <?= $view_mode === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
            <a href="modules/documents.php" class="nav-item">📄 Document Requests</a>
            <a href="modules/complaints.php" class="nav-item">⚖️ Complaints & Blotter</a>
            <a href="modules/treasury.php" class="nav-item">💳 Treasury & Revenue</a>
            <a href="modules/facilities.php" class="nav-item">🏛️ Facilities Reservation</a>
            <a href="modules/events.php" class="nav-item">📅 Event Requests</a>
            <a href="citizen_dashboard.php?view=edit_profile" class="nav-item <?= $view_mode === 'edit_profile' ? 'active' : '' ?>">✏️ Edit Profile</a>
            <span class="role-badge">Resident</span>
            <a href="logout.php" class="btn-logout">Sign Out</a>
        </div>
    </nav>

    <main class="container">
        <div class="welcome-panel">
            <div>
                <h1 style="font-family:'Bricolage Grotesque', sans-serif;">Maligayang pagdating, <?= e($username) ?>!</h1>
                <p style="color:#cbd5e1; margin-top:4px;">Barangay Citizen Service Portal</p>
            </div>
            <div style="color:var(--gold); font-weight:700;">📅 Ngayon ay <?= date('F j, Y') ?></div>
        </div>

        <?php if ($msg): ?><div class="alert alert-<?= $msg_type ?>"><?= e($msg) ?></div><?php endif; ?>

        <?php if ($view_mode === 'edit_profile' || !$is_profile_completed): ?>
            <!-- Profile Form View -->
            <div class="panel-card">
                <h2>👤 Complete / Edit My Profile</h2>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-grid">
                        <div class="form-group"><label>First Name</label><input type="text" name="first_name" value="<?= e($resident_info['first_name'] ?? '') ?>" required></div>
                        <div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="<?= e($resident_info['last_name'] ?? '') ?>" required></div>
                        <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" value="<?= e($resident_info['middle_name'] ?? '') ?>"></div>
                        <div class="form-group"><label>Birthdate</label><input type="date" name="birthdate" value="<?= e($resident_info['birthdate'] ?? '') ?>" required></div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="Male" <?= ($resident_info['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($resident_info['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Civil Status</label>
                            <select name="civil_status">
                                <option value="Single" <?= ($resident_info['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                                <option value="Married" <?= ($resident_info['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Purok / Zone</label>
                            <select name="purok_id" required>
                                <option value="">-- Select Purok --</option>
                                <?php foreach ($puroks as $p): ?>
                                    <option value="<?= $p['purok_id'] ?>" <?= ($resident_info['purok_id'] ?? '') == $p['purok_id'] ? 'selected' : '' ?>><?= e($p['purok_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Contact Number</label><input type="text" name="contact_number" value="<?= e($resident_info['contact_number'] ?? '') ?>"></div>
                        <div class="form-group full"><label>Full Address</label><input type="text" name="address" value="<?= e($resident_info['address'] ?? '') ?>" required></div>
                    </div>
                    <button type="submit" class="btn-primary">Save Profile</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Main Dashboard View -->
            <div class="grid">
                <div>
                    <!-- Profile Summary Card -->
                    <div class="panel-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid var(--line); padding-bottom:8px; margin-bottom:16px;">
                            <h2 style="border-bottom:none; margin-bottom:0; padding-bottom:0;">👤 Personal Profile Record</h2>
                            <a href="citizen_dashboard.php?view=edit_profile" style="font-size:13px; font-weight:700; color:var(--teal); text-decoration:none;">✏️ Edit Profile</a>
                        </div>
                        <div class="profile-summary-box">
                            <div class="summary-item"><label>Full Name</label><span><?= e(($resident_info['first_name'] ?? '') . ' ' . ($resident_info['last_name'] ?? '')) ?></span></div>
                            <div class="summary-item"><label>Purok</label><span><?= e($resident_info['purok_name'] ?? 'Not Assigned') ?></span></div>
                            <div class="summary-item"><label>Contact</label><span><?= e($resident_info['contact_number'] ?? 'N/A') ?></span></div>
                            <div class="summary-item"><label>Civil Status</label><span><?= e($resident_info['civil_status'] ?? 'N/A') ?></span></div>
                            <div class="summary-item" style="grid-column: span 2;"><label>Address</label><span><?= e($resident_info['address'] ?? 'N/A') ?></span></div>
                        </div>
                    </div>

                    <!-- Online Citizen Services (Base sa Luma) -->
                    <div class="panel-card">
                        <h2>⚡ Citizen Online Services</h2>
                        <div class="quick-links">
                            <a href="modules/documents.php" class="link-card">
                                <b>📄 Document Requests</b>
                                <span>Request Indigency & Clearance</span>
                            </a>
                            <a href="modules/complaints.php" class="link-card">
                                <b>⚖️ Complaints & Blotter</b>
                                <span>Report Citizen Incidents</span>
                            </a>
                            <a href="modules/treasury.php" class="link-card">
                                <b>💳 Treasury & Revenue</b>
                                <span>Transparency Financial Logs</span>
                            </a>
                            <a href="modules/facilities.php" class="link-card">
                                <b>🏛️ Facilities Reservation</b>
                                <span>Reserve Barangay Hall / Gym</span>
                            </a>
                            <a href="modules/events.php" class="link-card">
                                <b>📅 Brgy Events</b>
                                 <span>Upcoming Activities and Events</span>
                            </a>
                        </div>
                    </div>

                    <!-- Community Activity Feed -->
                    <div class="panel-card">
                        <h2>🖼️ Community Activity Feed</h2>
                        <?php if (empty($feed_posts)): ?>
                            <p style="font-size:13px; color:var(--muted);">Walang mga larawan o feed activity sa ngayon.</p>
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
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Side: Official Announcements -->
                <div>
                    <div class="panel-card">
                        <h2>📢 Announcements</h2>
                        <?php if (empty($announcements)): ?>
                            <p style="font-size:13px; color:var(--muted);">Walang opisyal na anunsyo sa ngayon.</p>
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
            </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>