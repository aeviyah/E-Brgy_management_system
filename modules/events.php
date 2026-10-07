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

/* OFFICIAL ACTIONS: Post / Manage Event */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_event'])) {
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $date     = $_POST['event_date'] ?? '';
    $time     = $_POST['event_time'] ?? '';

    if (!empty($title) && !empty($date)) {
        $stmt = $conn->prepare("INSERT INTO events (title, description, location, event_date, event_time, organizer) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $title, $desc, $location, $date, $time, $username);
        if ($stmt->execute()) {
            $msg = "Community event posted successfully!";
            $msg_type = "success";
        }
    }
}

/* Delete Event (Official Only) */
if ($is_admin && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['event_id'])) {
    $ev_id = intval($_GET['event_id']);
    if ($ev_id > 0) {
        $conn->query("DELETE FROM events WHERE event_id = $ev_id");
        $msg = "Event deleted successfully!";
        $msg_type = "success";
    }
}

/* FETCH EVENTS */
$events = [];
$ev_q = $conn->query("SELECT * FROM events ORDER BY event_date ASC");
if ($ev_q) while ($r = $ev_q->fetch_assoc()) $events[] = $r;

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Community Events · E-Barangay</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --night: #0c2733; --teal: #14566b; --palm: #2f8a57; --gold: #f2b84b; --line: #d9e1e4; --ink: #16262e; --muted: #5f717a; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Figtree', sans-serif; color: #fff; background: var(--night); min-height: 100vh; }
        .scene { position: fixed; inset: 0; z-index: 0; background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%); }
        .scene::after { content: ""; position: absolute; inset: 0; background: rgba(8, 24, 33, 0.45); }
        .page { position: relative; z-index: 2; padding: 24px; max-width: 1200px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .btn-back { background: rgba(255,255,255,.15); color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 999px; font-weight: 600; font-size: 14px; }
        .grid { display: grid; grid-template-columns: <?= $is_admin ? '1fr 2fr' : '1fr' ?>; gap: 24px; }
        .panel { background: rgba(255,253,247,.96); color: var(--ink); border-radius: 20px; padding: 24px; box-shadow: 0 15px 35px rgba(0,0,0,.3); margin-bottom: 24px; }
        .panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 20px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 8px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
        .form-group input, .form-group textarea { padding: 10px; border: 1.5px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; }
        .btn { background: var(--teal); color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .events-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
        .event-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 18px; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
        .event-date-badge { background: #e0f2fe; color: #0369a1; font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 999px; display: inline-block; margin-bottom: 8px; }
        .btn-delete { background: #fee2e2; color: #dc2626; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; text-decoration: none; margin-top: 10px; display: inline-block; }
    </style>
</head>
<body>
<div class="scene"></div>
<div class="page">
    <div class="top-bar">
        <h1 style="font-family:'Bricolage Grotesque', sans-serif;">📅 Community Events & Activities</h1>
        <a href="../<?= $is_admin ? 'dashboard.php' : 'citizen_dashboard.php' ?>" class="btn-back">← Back to Dashboard</a>
    </div>

    <?php if ($msg): ?>
        <div style="background:#d1e7dd; color:#0f5132; padding:12px; border-radius:10px; margin-bottom:16px; font-weight:600;">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <?php if ($is_admin): ?>
            <!-- Official Form to Create Event -->
            <div>
                <div class="panel">
                    <h2>📢 Post Community Event</h2>
                    <form method="POST">
                        <input type="hidden" name="post_event" value="1">
                        <div class="form-group"><label>Event Title</label><input type="text" name="title" required placeholder="e.g. Barangay Medical Mission"></div>
                        <div class="form-group"><label>Description</label><textarea name="description" rows="3" placeholder="Write event details..."></textarea></div>
                        <div class="form-group"><label>Venue / Location</label><input type="text" name="location" required placeholder="e.g. Covered Court"></div>
                        <div class="form-group"><label>Event Date</label><input type="date" name="event_date" required></div>
                        <div class="form-group"><label>Event Time</label><input type="time" name="event_time" required></div>
                        <button type="submit" class="btn">Publish Community Event</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Events List -->
        <div>
            <div class="panel">
                <h2>🎉 Upcoming Barangay Events</h2>
                <?php if (empty($events)): ?>
                    <p style="font-size:13px; color:var(--muted);">Walang nakatalagang kaganapan sa ngayon.</p>
                <?php else: ?>
                    <div class="events-cards">
                        <?php foreach ($events as $ev): ?>
                            <div class="event-card">
                                <span class="event-date-badge">📅 <?= date('M d, Y', strtotime($ev['event_date'])) ?> | <?= date('h:i A', strtotime($ev['event_time'])) ?></span>
                                <b style="font-size:17px; color:var(--teal); display:block; margin-bottom:4px;"><?= e($ev['title']) ?></b>
                                <p style="font-size:13px; color:#334155; margin-bottom:8px; line-height:1.4;"><?= nl2br(e($ev['description'])) ?></p>
                                <div style="font-size:12px; color:var(--muted); border-top:1px solid var(--line); padding-top:8px;">
                                    📍 <b>Venue:</b> <?= e($ev['location']) ?><br>
                                    👤 <b>Organizer:</b> <?= e($ev['organizer']) ?>
                                </div>
                                <?php if ($is_admin): ?>
                                    <a href="events.php?action=delete&event_id=<?= $ev['event_id'] ?>" class="btn-delete" onclick="return confirm('Gusto mo bang burahin ang event na ito?');">🗑️ Delete Event</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>