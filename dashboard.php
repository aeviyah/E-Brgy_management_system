<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

/* ============================================================
   ACCESS CONTROL — ADMIN DASHBOARD ONLY
   Residents are bounced to their own dashboard.
   ============================================================ */
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role     = $_SESSION['role'] ?? 'Resident';
$is_admin = ($role !== 'Resident');

if (!$is_admin) {
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

/* ============================================
   EVENTS CRUD (Admin / Official)
   ============================================ */

/* Create Event */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $event_title  = trim($_POST['event_title'] ?? '');
    $event_desc   = trim($_POST['event_description'] ?? '');
    $event_date   = $_POST['event_date'] ?? '';
    $event_loc    = trim($_POST['event_location'] ?? '');
    $event_time   = $_POST['event_time'] ?? '';

    if (!empty($event_title) && !empty($event_date)) {
        try {
            $ev_cols = [];
            $cc = $conn->query("SHOW COLUMNS FROM events");
            if ($cc) { while ($c = $cc->fetch_assoc()) $ev_cols[] = $c['Field']; }

            $col_title = in_array('title', $ev_cols) ? 'title' : (in_array('event_title', $ev_cols) ? 'event_title' : 'title');
            $col_desc  = in_array('description', $ev_cols) ? 'description' : (in_array('details', $ev_cols) ? 'details' : (in_array('content', $ev_cols) ? 'content' : 'description'));
            $col_date  = in_array('event_date', $ev_cols) ? 'event_date' : (in_array('date', $ev_cols) ? 'date' : 'event_date');
            $col_loc   = in_array('location', $ev_cols) ? 'location' : (in_array('venue', $ev_cols) ? 'venue' : null);
            $col_time  = in_array('event_time', $ev_cols) ? 'event_time' : (in_array('start_time', $ev_cols) ? 'start_time' : null);

            $cols = [$col_title, $col_desc, $col_date];
            $placeholders = ['?', '?', '?'];
            $params = [$event_title, $event_desc, $event_date];
            $types = 'sss';

            if ($col_loc) { $cols[] = $col_loc; $placeholders[] = '?'; $params[] = $event_loc; $types .= 's'; }
            if ($col_time && $event_time) { $cols[] = $col_time; $placeholders[] = '?'; $params[] = $event_time; $types .= 's'; }

            $sql = "INSERT INTO events (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $msg = "Matagumpay na naidagdag ang event!";
                header('Location: dashboard.php');
                exit();
            }
        } catch (Throwable $e) {
            $msg = "Error adding event: " . $e->getMessage();
        }
    }
}

/* Update Event */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $ev_id        = intval($_POST['event_id'] ?? 0);
    $event_title  = trim($_POST['event_title'] ?? '');
    $event_desc   = trim($_POST['event_description'] ?? '');
    $event_date   = $_POST['event_date'] ?? '';
    $event_loc    = trim($_POST['event_location'] ?? '');
    $event_time   = $_POST['event_time'] ?? '';

    if ($ev_id > 0 && !empty($event_title) && !empty($event_date)) {
        try {
            $ev_cols = [];
            $cc = $conn->query("SHOW COLUMNS FROM events");
            if ($cc) { while ($c = $cc->fetch_assoc()) $ev_cols[] = $c['Field']; }

            $col_id    = in_array('event_id', $ev_cols) ? 'event_id' : 'id';
            $col_title = in_array('title', $ev_cols) ? 'title' : 'event_title';
            $col_desc  = in_array('description', $ev_cols) ? 'description' : (in_array('details', $ev_cols) ? 'details' : 'content');
            $col_date  = in_array('event_date', $ev_cols) ? 'event_date' : 'date';
            $col_loc   = in_array('location', $ev_cols) ? 'location' : (in_array('venue', $ev_cols) ? 'venue' : null);
            $col_time  = in_array('event_time', $ev_cols) ? 'event_time' : (in_array('start_time', $ev_cols) ? 'start_time' : null);

            $sets = ["$col_title = ?", "$col_desc = ?", "$col_date = ?"];
            $params = [$event_title, $event_desc, $event_date];
            $types = 'sss';

            if ($col_loc) { $sets[] = "$col_loc = ?"; $params[] = $event_loc; $types .= 's'; }
            if ($col_time && $event_time) { $sets[] = "$col_time = ?"; $params[] = $event_time; $types .= 's'; }

            $params[] = $ev_id;
            $types .= 'i';

            $sql = "UPDATE events SET " . implode(', ', $sets) . " WHERE $col_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $msg = "Matagumpay na na-update ang event!";
                header('Location: dashboard.php');
                exit();
            }
        } catch (Throwable $e) {
            $msg = "Error updating event: " . $e->getMessage();
        }
    }
}

/* Delete Event */
if (isset($_GET['action']) && $_GET['action'] === 'delete_event' && isset($_GET['event_id'])) {
    $ev_id = intval($_GET['event_id']);
    if ($ev_id > 0) {
        try {
            $ev_cols = [];
            $cc = $conn->query("SHOW COLUMNS FROM events");
            if ($cc) { while ($c = $cc->fetch_assoc()) $ev_cols[] = $c['Field']; }
            $col_id = in_array('event_id', $ev_cols) ? 'event_id' : 'id';

            $stmt = $conn->prepare("DELETE FROM events WHERE $col_id = ?");
            $stmt->bind_param("i", $ev_id);
            $stmt->execute();
            header('Location: dashboard.php');
            exit();
        } catch (Throwable $e) {}
    }
}

/* ============================================
   Safe Fetch Statistics Metrics
   AUTO-DETECT: Kunin ang totoong table names
   ============================================ */
$metrics = ['residents' => 0, 'cases' => 0, 'documents' => 0, 'facilities' => 0];

/* -------- RESIDENTS -------- */
try {
    if ($r = $conn->query("SELECT COUNT(*) AS t FROM residents"))
        $metrics['residents'] = intval($r->fetch_assoc()['t'] ?? 0);
} catch (Throwable $e) {}

/* -------- COMPLAINTS & BLOTTER -------- */
$complaint_tables = ['complaints', 'cases', 'blotter', 'complaints_and_blotter'];
foreach ($complaint_tables as $ct) {
    try {
        $chk = $conn->query("SHOW TABLES LIKE '$ct'");
        if ($chk && $chk->num_rows > 0) {
            $cc_cols = [];
            $cc_res = $conn->query("SHOW COLUMNS FROM $ct");
            if ($cc_res) { while ($c = $cc_res->fetch_assoc()) $cc_cols[] = $c['Field']; }

            if (in_array('status', $cc_cols)) {
                $q = "SELECT COUNT(*) AS t FROM $ct WHERE status NOT IN ('Resolved', 'Dismissed', 'Closed')";
            } else {
                $q = "SELECT COUNT(*) AS t FROM $ct";
            }
            if ($r = $conn->query($q)) {
                $metrics['cases'] = intval($r->fetch_assoc()['t'] ?? 0);
                break;
            }
        }
    } catch (Throwable $e) {}
}

/* -------- DOCUMENT / CERTIFICATE REQUESTS -------- */
$doc_tables = ['certificate_requests', 'service_requests', 'document_requests', 'certificate_issuances'];
foreach ($doc_tables as $dt) {
    try {
        $chk = $conn->query("SHOW TABLES LIKE '$dt'");
        if ($chk && $chk->num_rows > 0) {
            $dc_cols = [];
            $dc_res = $conn->query("SHOW COLUMNS FROM $dt");
            if ($dc_res) { while ($c = $dc_res->fetch_assoc()) $dc_cols[] = $c['Field']; }

            if (in_array('status', $dc_cols)) {
                $q = "SELECT COUNT(*) AS t FROM $dt WHERE status != 'Rejected'";
            } else {
                $q = "SELECT COUNT(*) AS t FROM $dt";
            }
            if ($r = $conn->query($q)) {
                $metrics['documents'] = intval($r->fetch_assoc()['t'] ?? 0);
                break;
            }
        }
    } catch (Throwable $e) {}
}

/* -------- FACILITY BOOKINGS -------- */
$fac_tables = ['facility_reservations', 'facilities', 'facility_bookings', 'reservations'];
foreach ($fac_tables as $ft) {
    try {
        $chk = $conn->query("SHOW TABLES LIKE '$ft'");
        if ($chk && $chk->num_rows > 0) {
            $fc_cols = [];
            $fc_res = $conn->query("SHOW COLUMNS FROM $ft");
            if ($fc_res) { while ($c = $fc_res->fetch_assoc()) $fc_cols[] = $c['Field']; }

            if (in_array('status', $fc_cols)) {
                $q = "SELECT COUNT(*) AS t FROM $ft WHERE status NOT IN ('Resolved', 'Rejected', 'Cancelled', 'Completed')";
            } else {
                $q = "SELECT COUNT(*) AS t FROM $ft";
            }
            if ($r = $conn->query($q)) {
                $metrics['facilities'] = intval($r->fetch_assoc()['t'] ?? 0);
                break;
            }
        }
    } catch (Throwable $e) {}
}

/* ============================================
   Safe Fetch Feed Posts
   ============================================ */
$feed_posts = [];
try {
    $p_res = $conn->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 10");
    if ($p_res) { while ($row = $p_res->fetch_assoc()) $feed_posts[] = $row; }
} catch (Throwable $e) {}

/* ============================================
   Safe Fetch Announcements
   ============================================ */
$announcements = [];
try {
    $a_res = $conn->query("SELECT * FROM announcements WHERE status = 'Published' ORDER BY date_posted DESC LIMIT 5");
    if ($a_res) {
        while ($row = $a_res->fetch_assoc()) {
            $announcements[] = [
                'title'   => $row['title'] ?? 'Announcement',
                'content' => $row['content'] ?? '',
                'date'    => $row['date_posted'] ?? date('Y-m-d H:i:s')
            ];
        }
    }
} catch (Throwable $e) {
    try {
        $a_res = $conn->query("SELECT * FROM announcements ORDER BY 1 DESC LIMIT 5");
        if ($a_res) {
            while ($row = $a_res->fetch_assoc()) {
                $announcements[] = [
                    'title'   => $row['title'] ?? 'Announcement',
                    'content' => $row['content'] ?? '',
                    'date'    => $row['date_posted'] ?? $row['created_at'] ?? date('Y-m-d H:i:s')
                ];
            }
        }
    } catch (Throwable $e2) {}
}

/* ============================================
   Fetch Upcoming Events (only)
   ============================================ */
$upcoming_events = [];
try {
    $ev_cols2 = [];
    $cc2 = $conn->query("SHOW COLUMNS FROM events");
    if ($cc2) { while ($c = $cc2->fetch_assoc()) $ev_cols2[] = $c['Field']; }

    $col_id2    = in_array('event_id', $ev_cols2) ? 'event_id' : 'id';
    $col_title2 = in_array('title', $ev_cols2) ? 'title' : 'event_title';
    $col_desc2  = in_array('description', $ev_cols2) ? 'description' : (in_array('details', $ev_cols2) ? 'details' : 'content');
    $col_date2  = in_array('event_date', $ev_cols2) ? 'event_date' : 'date';
    $col_loc2   = in_array('location', $ev_cols2) ? 'location' : (in_array('venue', $ev_cols2) ? 'venue' : null);
    $col_time2  = in_array('event_time', $ev_cols2) ? 'event_time' : (in_array('start_time', $ev_cols2) ? 'start_time' : null);

    $select = "e.*, e.$col_id2 AS _id, e.$col_title2 AS _title, e.$col_desc2 AS _desc, e.$col_date2 AS _date";
    if ($col_loc2)  $select .= ", e.$col_loc2 AS _loc";
    if ($col_time2) $select .= ", e.$col_time2 AS _time";

    $ev_query = "SELECT $select FROM events e 
                 WHERE e.$col_date2 >= CURDATE() 
                 ORDER BY e.$col_date2 ASC 
                 LIMIT 20";
    $ev_res = $conn->query($ev_query);
    if ($ev_res) {
        while ($row = $ev_res->fetch_assoc()) {
            $upcoming_events[] = [
                'id'    => $row['_id'] ?? 0,
                'title' => $row['_title'] ?? 'Event',
                'desc'  => $row['_desc'] ?? '',
                'date'  => $row['_date'] ?? date('Y-m-d'),
                'loc'   => $row['_loc'] ?? '',
                'time'  => $row['_time'] ?? '',
            ];
        }
    }
} catch (Throwable $e) {}

/* ============================================
   Fetch ALL Events (para sa modal)
   ============================================ */
$all_events = [];
try {
    $ev_cols3 = [];
    $cc3 = $conn->query("SHOW COLUMNS FROM events");
    if ($cc3) { while ($c = $cc3->fetch_assoc()) $ev_cols3[] = $c['Field']; }

    $col_id3    = in_array('event_id', $ev_cols3) ? 'event_id' : 'id';
    $col_title3 = in_array('title', $ev_cols3) ? 'title' : 'event_title';
    $col_desc3  = in_array('description', $ev_cols3) ? 'description' : (in_array('details', $ev_cols3) ? 'details' : 'content');
    $col_date3  = in_array('event_date', $ev_cols3) ? 'event_date' : 'date';
    $col_loc3   = in_array('location', $ev_cols3) ? 'location' : (in_array('venue', $ev_cols3) ? 'venue' : null);
    $col_time3  = in_array('event_time', $ev_cols3) ? 'event_time' : (in_array('start_time', $ev_cols3) ? 'start_time' : null);

    $select = "e.*, e.$col_id3 AS _id, e.$col_title3 AS _title, e.$col_desc3 AS _desc, e.$col_date3 AS _date";
    if ($col_loc3)  $select .= ", e.$col_loc3 AS _loc";
    if ($col_time3) $select .= ", e.$col_time3 AS _time";

    $ev_query = "SELECT $select FROM events e ORDER BY e.$col_date3 DESC LIMIT 50";
    $ev_res = $conn->query($ev_query);
    if ($ev_res) {
        while ($row = $ev_res->fetch_assoc()) {
            $all_events[] = [
                'id'    => $row['_id'] ?? 0,
                'title' => $row['_title'] ?? 'Event',
                'desc'  => $row['_desc'] ?? '',
                'date'  => $row['_date'] ?? date('Y-m-d'),
                'loc'   => $row['_loc'] ?? '',
                'time'  => $row['_time'] ?? '',
                'is_upcoming' => (strtotime($row['_date']) >= strtotime(date('Y-m-d')))
            ];
        }
    }
} catch (Throwable $e) {}

/* Determine edit mode */
$edit_event = null;
if (isset($_GET['edit_event'])) {
    $edit_id = intval($_GET['edit_event']);
    foreach ($all_events as $ev) {
        if ($ev['id'] == $edit_id) { $edit_event = $ev; break; }
    }
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Dashboard · E-Barangay Bigaan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --night: #0c2733; --teal: #14566b; --palm: #2f8a57; --gold: #f2b84b;
            --gold-deep: #c98a1c; --paper: #fffdf7; --ink: #16262e; --muted: #5f717a;
            --line: #d9e1e4; --danger: #a4262c; --danger-bg: #fdecec;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Figtree', system-ui, sans-serif; color: #fff; background: var(--night); min-height: 100vh; overflow-x: clip; }
        :focus-visible { outline: 3px solid var(--gold); outline-offset: 2px; }

        .scene { position: fixed; inset: 0; z-index: 0; background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%); }
        .scene svg { position: absolute; inset: 0; width: 100%; height: 100%; }
        .scene::after { content: ""; position: absolute; inset: 0; background: rgba(8, 24, 33, 0.5); }
        .rays { transform-origin: 520px 560px; animation: spin 90s linear infinite; }
        .sun-glow { animation: breathe 6s ease-in-out infinite; transform-origin: 520px 560px; }
        .cloud { animation: drift linear infinite; }
        .cloud.c1 { animation-duration: 120s; }
        .cloud.c2 { animation-duration: 160s; animation-delay: -60s; }
        .cloud.c3 { animation-duration: 200s; animation-delay: -110s; }
        .palm-sway { transform-box: fill-box; transform-origin: 50% 100%; animation: sway 7s ease-in-out infinite alternate; }
        .palm-sway.slow { animation-duration: 9s; animation-delay: -3s; }
        .frond-wave { transform-box: fill-box; transform-origin: 0% 100%; animation: wave 4s ease-in-out infinite alternate; }
        .train { animation: roll 34s linear infinite; animation-delay: 1.5s; transform: translateX(-900px); }
        .wheel { transform-box: fill-box; transform-origin: center; animation: spin 1.2s linear infinite; }
        .puff { opacity: 0; transform-box: fill-box; transform-origin: center; animation: puff 3.2s ease-out infinite; }
        .puff:nth-child(2) { animation-delay: 1.1s; }
        .puff:nth-child(3) { animation-delay: 2.2s; }
        .bird { animation: fly 40s linear infinite; }
        .bird.b2 { animation-delay: -14s; animation-duration: 52s; }

        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes breathe { 0%,100% { transform: scale(1); opacity: .9; } 50% { transform: scale(1.08); opacity: 1; } }
        @keyframes drift { from { transform: translateX(-400px); } to { transform: translateX(2000px); } }
        @keyframes sway { from { transform: rotate(-2.2deg); } to { transform: rotate(2.2deg); } }
        @keyframes wave { from { transform: rotate(-3deg); } to { transform: rotate(4deg); } }
        @keyframes roll { from { transform: translateX(-900px); } to { transform: translateX(1900px); } }
        @keyframes puff { 0% { opacity: .7; transform: translate(0,0) scale(.4); } 100% { opacity: 0; transform: translate(-70px,-60px) scale(1.6); } }
        @keyframes fly { from { transform: translate(-100px, 0); } to { transform: translate(1800px, -120px); } }

        .page { position: relative; z-index: 2; min-height: 100vh; display: flex; flex-direction: column; }

        .nav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px clamp(20px, 4vw, 56px);
            position: sticky; top: 0; z-index: 50;
            background: rgba(8,24,33,.88); backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,.1);
            gap: 12px; flex-wrap: wrap;
        }
        .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff; }
        .logo { display: block; position: relative; overflow: hidden; border-radius: 50%; }
        .logo svg { display: block; width: 100%; height: 100%; }
        .logo::after {
            content: ""; position: absolute; inset: 0; pointer-events: none;
            background: linear-gradient(115deg, transparent 40%, rgba(255,255,255,.6) 50%, transparent 60%);
            transform: translateX(-130%); animation: logoShine 7s ease-in-out infinite;
        }
        @keyframes logoShine { 0%, 62% { transform: translateX(-130%); } 100% { transform: translateX(130%); } }
        .brand .logo { width: 48px; height: 48px; flex: none; box-shadow: 0 0 0 3px rgba(255,255,255,.95), 0 6px 16px rgba(0,0,0,.35); }
        .brand b { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: 18px; letter-spacing: .2px; line-height: 1.1; display: block; }
        .brand small { font-size: 12px; color: #cfe3ea; }

        .nav-links { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; list-style: none; order: 2; }
        .nav-item {
            color: #cfe3ea; text-decoration: none; font-size: 13px; font-weight: 600;
            padding: 8px 13px; border-radius: 999px; transition: all .2s;
            white-space: nowrap; display: inline-flex; align-items: center; gap: 7px;
        }
        .nav-item svg { width: 15px; height: 15px; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,.14); color: var(--gold); }

        .nav-right { display: flex; align-items: center; gap: 12px; order: 3; }
        .role-badge { background: rgba(242,184,75,.2); color: var(--gold); border: 1px solid rgba(242,184,75,.4); padding: 5px 14px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
        .btn-logout { background: rgba(255,255,255,.12); color: #fff; text-decoration: none; padding: 8px 18px; border-radius: 999px; font-size: 13.5px; font-weight: 600; transition: background .2s; display: inline-flex; align-items: center; gap: 7px; }
        .btn-logout:hover { background: rgba(255,255,255,.22); }
        .btn-logout svg { width: 15px; height: 15px; }

        .container { width: 100%; max-width: 1440px; margin: 0 auto; padding: 24px clamp(20px, 4vw, 56px) 60px; }

        .welcome-panel {
            background: rgba(10,30,42,.85); backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,.15); border-radius: 22px;
            padding: clamp(20px, 3vw, 30px); margin-bottom: 22px;
            display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap;
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
            position: relative; overflow: hidden;
        }
        .welcome-panel::before {
            content: ""; position: absolute; top: 0; left: 34px; right: 34px; height: 4px;
            border-radius: 0 0 6px 6px;
            background: repeating-linear-gradient(90deg, var(--gold) 0 14px, transparent 14px 20px);
        }
        .welcome-panel h1 { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: clamp(22px, 3vw, 28px); letter-spacing: -.5px; }
        .welcome-panel p { color: #cbd5e1; margin-top: 4px; font-size: 14.5px; }
        .welcome-date { color: var(--gold); font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .welcome-date svg { width: 17px; height: 17px; }

        .section-head {
            display: flex; align-items: center; justify-content: space-between;
            margin: 32px 0 18px; gap: 16px; flex-wrap: wrap;
        }
        .section-head h2 {
            font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800;
            font-size: clamp(20px, 2.6vw, 26px); color: #fff;
            display: flex; align-items: center; gap: 12px;
            letter-spacing: -.4px;
        }
        .section-head h2 .head-icon {
            display: inline-grid; place-items: center;
            width: 42px; height: 42px; border-radius: 12px;
            background: rgba(242,184,75,.18); color: var(--gold);
            border: 1px solid rgba(242,184,75,.4);
        }
        .section-head h2 .head-icon svg { width: 22px; height: 22px; }
        .section-head .section-link {
            color: var(--gold); font-size: 13.5px; font-weight: 700;
            text-decoration: none; padding: 8px 16px; border-radius: 999px;
            background: rgba(242,184,75,.14); border: 1px solid rgba(242,184,75,.35);
            transition: background .2s;
            display: inline-flex; align-items: center; gap: 7px;
        }
        .section-head .section-link:hover { background: rgba(242,184,75,.28); }
        .section-head .section-link svg { width: 15px; height: 15px; }

        /* ANNOUNCEMENTS PANEL */
        .announcement-panel {
            background: linear-gradient(135deg, #f2b84b 0%, #e9a85c 100%);
            color: #3a2605; border-radius: 22px;
            box-shadow: 0 20px 50px rgba(242,184,75,.35);
            position: relative; overflow: hidden;
            border: 2px solid rgba(255,255,255,.3);
        }
        .announcement-panel::before {
            content: ""; position: absolute; inset: 0;
            background: radial-gradient(circle at 90% 10%, rgba(255,255,255,.35), transparent 45%);
            pointer-events: none;
        }
        .announcement-panel-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 24px; background: rgba(58,38,5,.18);
            border-bottom: 1px solid rgba(58,38,5,.15);
            gap: 12px; flex-wrap: wrap;
        }
        .announcement-panel-header h3 {
            font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800;
            font-size: 18px; color: #3a2605;
            display: flex; align-items: center; gap: 10px;
        }
        .announcement-panel-header h3 .bell {
            display: inline-grid; place-items: center; width: 34px; height: 34px;
            background: #3a2605; color: var(--gold); border-radius: 50%;
            animation: ring 3s ease-in-out infinite;
        }
        .announcement-panel-header h3 .bell svg { width: 17px; height: 17px; }
        @keyframes ring {
            0%, 80%, 100% { transform: rotate(0); }
            85% { transform: rotate(15deg); }
            90% { transform: rotate(-15deg); }
            95% { transform: rotate(10deg); }
        }
        .announcement-panel-header .view-all {
            font-size: 13px; font-weight: 800; color: #3a2605;
            text-decoration: none; padding: 8px 16px; border-radius: 999px;
            background: rgba(255,255,255,.5); transition: background .2s;
            white-space: nowrap;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .announcement-panel-header .view-all:hover { background: #fff; }
        .announcement-panel-header .view-all svg { width: 14px; height: 14px; }

        .announcement-grid {
            padding: 20px 24px 24px;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 14px;
        }
        .announcement-item {
            display: flex; gap: 14px; align-items: flex-start;
            padding: 16px 18px; background: rgba(255,255,255,.6);
            border-radius: 14px;
            border-left: 4px solid #3a2605;
            transition: transform .2s, background .2s;
        }
        .announcement-item:hover { transform: translateY(-3px); background: rgba(255,255,255,.85); }
        .announcement-item .icon {
            flex: none; width: 42px; height: 42px; border-radius: 11px;
            display: grid; place-items: center;
            background: #3a2605; color: var(--gold);
        }
        .announcement-item .icon svg { width: 20px; height: 20px; }
        .announcement-item .text b {
            font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800;
            font-size: 15.5px; color: #2b1a03; display: block; margin-bottom: 4px;
        }
        .announcement-item .text .meta {
            font-size: 11.5px; font-weight: 700; color: #6b4a1a;
            text-transform: uppercase; letter-spacing: .5px;
        }
        .announcement-item .text p {
            font-size: 13.5px; color: #3a2605; margin-top: 6px; line-height: 1.5;
        }
        .announcement-empty {
            grid-column: 1 / -1;
            text-align: center; padding: 24px; color: #6b4a1a;
            font-size: 14px; font-weight: 600; font-style: italic;
        }

        /* UPCOMING EVENTS CARDS */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
        }
        .event-card {
            display: flex; gap: 16px;
            background: rgba(255,253,247,.97);
            border-radius: 18px; padding: 18px;
            box-shadow: 0 15px 35px rgba(0,0,0,.3);
            border-left: 5px solid var(--palm);
            transition: transform .2s, box-shadow .2s;
        }
        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 22px 45px rgba(0,0,0,.4);
        }
        .event-date-badge {
            flex: none;
            width: 62px; height: 62px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--palm), #1f6e41);
            color: #fff;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            box-shadow: 0 8px 18px rgba(47,138,87,.3);
        }
        .event-date-badge .edb-month {
            font-size: 10.5px; font-weight: 800;
            text-transform: uppercase; letter-spacing: .8px;
            opacity: .9;
        }
        .event-date-badge .edb-day {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 24px; font-weight: 800;
            line-height: 1;
        }
        .event-card-body { flex: 1; min-width: 0; color: var(--ink); }
        .event-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 15.5px; font-weight: 800;
            color: var(--teal); display: block;
            margin-bottom: 6px; line-height: 1.25;
        }
        .event-card-meta {
            display: flex; gap: 10px; flex-wrap: wrap;
            margin-bottom: 8px;
            font-size: 12px; color: var(--muted); font-weight: 600;
        }
        .event-card-meta span { display: inline-flex; align-items: center; gap: 4px; }
        .event-card-desc { font-size: 13px; color: #334155; line-height: 1.5; margin-top: 4px; }
        .events-empty {
            padding: 40px 24px; text-align: center;
            background: rgba(255,253,247,.97);
            border-radius: 18px;
            color: var(--muted); font-style: italic; font-size: 14px;
            box-shadow: 0 15px 35px rgba(0,0,0,.3);
        }

        /* EVENT MODAL */
        .event-modal-overlay {
            position: fixed; inset: 0; z-index: 999;
            background: rgba(8,24,33,.72);
            backdrop-filter: blur(6px);
            display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }
        .event-modal {
            background: rgba(255,253,247,.99);
            color: var(--ink);
            border-radius: 22px;
            width: 100%; max-width: 720px;
            max-height: 90vh;
            display: flex; flex-direction: column;
            box-shadow: 0 30px 80px rgba(0,0,0,.55);
            overflow: hidden;
        }
        .event-modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 24px;
            background: linear-gradient(135deg, var(--teal), #0d3f50);
            color: #fff;
        }
        .event-modal-header h2 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 18px; font-weight: 800;
            display: flex; align-items: center; gap: 10px;
            color: #fff;
        }
        .event-modal-close {
            background: rgba(255,255,255,.15);
            color: #fff; border: none; cursor: pointer;
            width: 36px; height: 36px; border-radius: 50%;
            display: grid; place-items: center;
            transition: background .2s;
        }
        .event-modal-close:hover { background: rgba(255,255,255,.28); }

        .event-modal-tabs {
            display: flex; gap: 4px;
            padding: 14px 20px 0;
            background: rgba(255,253,247,.99);
            border-bottom: 1px solid var(--line);
        }
        .event-tab {
            background: transparent;
            border: none; cursor: pointer;
            font-family: inherit; font-size: 13.5px; font-weight: 700;
            padding: 10px 18px;
            color: var(--muted);
            border-bottom: 3px solid transparent;
            transition: color .2s, border-color .2s;
            border-radius: 8px 8px 0 0;
        }
        .event-tab:hover { color: var(--teal); background: #f5f8f9; }
        .event-tab.active { color: var(--teal); border-bottom-color: var(--palm); background: #f5f8f9; }

        .event-modal-body {
            padding: 20px 24px 24px;
            overflow-y: auto;
            max-height: calc(90vh - 160px);
        }

        .event-modal-item {
            display: flex; gap: 14px;
            padding: 14px 16px;
            background: #fff;
            border: 1px solid var(--line);
            border-left: 4px solid var(--palm);
            border-radius: 14px;
            margin-bottom: 10px;
            transition: transform .2s, box-shadow .2s;
        }
        .event-modal-item:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(0,0,0,.06); }
        .event-modal-item.past { border-left-color: #c9ccd1; opacity: .72; }
        .emi-date {
            flex: none;
            width: 56px; height: 56px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--palm), #1f6e41);
            color: #fff;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
        }
        .emi-date.past { background: linear-gradient(135deg, #94a3b8, #64748b); }
        .emi-date .emi-month { font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .7px; opacity: .9; }
        .emi-date .emi-day { font-family: 'Bricolage Grotesque', sans-serif; font-size: 20px; font-weight: 800; line-height: 1; }
        .emi-body { flex: 1; min-width: 0; }
        .emi-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 15px; font-weight: 800;
            color: var(--teal); display: block;
            margin-bottom: 4px; line-height: 1.25;
        }
        .emi-past-tag { font-size: 10px; color: #94a3b8; font-weight: 600; text-transform: uppercase; margin-left: 6px; letter-spacing: .5px; }
        .emi-meta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 6px; font-size: 11.5px; color: var(--muted); font-weight: 600; }
        .emi-meta span { display: inline-flex; align-items: center; gap: 4px; }
        .emi-desc { font-size: 12.5px; color: #475569; line-height: 1.5; margin-bottom: 8px; }
        .emi-actions { display: flex; gap: 6px; flex-wrap: wrap; }

        .btn-event-edit, .btn-event-delete {
            font-size: 11.5px; font-weight: 700;
            padding: 6px 12px; border-radius: 8px;
            text-decoration: none; border: none; cursor: pointer;
            font-family: inherit;
            display: inline-flex; align-items: center; gap: 5px;
            transition: background .2s;
        }
        .btn-event-edit { background: #eef5f7; color: var(--teal); }
        .btn-event-edit:hover { background: #dcebef; }
        .btn-event-delete { background: var(--danger-bg); color: var(--danger); }
        .btn-event-delete:hover { background: #fbd5d7; }

        .event-modal-form .form-group { margin-bottom: 14px; }
        .event-modal-form label {
            font-size: 12.5px; font-weight: 700;
            color: var(--muted);
            display: block; margin-bottom: 5px;
            text-transform: uppercase; letter-spacing: .4px;
        }
        .event-modal-form input, .event-modal-form textarea {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--line);
            border-radius: 11px;
            font-family: inherit; font-size: 14px;
            background: #f5f8f9;
            transition: border-color .2s, background .2s;
        }
        .event-modal-form input:focus, .event-modal-form textarea:focus {
            outline: none; border-color: var(--teal); background: #fff;
            box-shadow: 0 0 0 4px rgba(20,86,107,.12);
        }
        .event-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .event-form-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; align-items: center; }
        .btn-event-submit {
            background: linear-gradient(135deg, var(--palm), #1f6e41);
            color: #fff; border: none;
            padding: 12px 24px; border-radius: 11px;
            font-weight: 800; font-size: 14px;
            font-family: inherit; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            box-shadow: 0 8px 20px rgba(47,138,87,.3);
            transition: transform .2s, box-shadow .2s;
        }
        .btn-event-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(47,138,87,.4); }
        .btn-event-cancel {
            background: #eef1f3; color: var(--muted);
            padding: 10px 18px; border-radius: 10px;
            font-size: 13px; font-weight: 700;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-event-cancel:hover { background: #dde3e7; }

        /* FEED CAROUSEL */
        .feed-carousel { position: relative; border-radius: 22px; overflow: hidden; background: rgba(255,253,247,.97); box-shadow: 0 20px 50px rgba(0,0,0,.35); }
        .feed-carousel-viewport { position: relative; overflow: hidden; }
        .feed-carousel-track { display: flex; transition: transform .7s cubic-bezier(.5,0,.2,1); will-change: transform; }
        .feed-slide { flex: 0 0 100%; display: grid; grid-template-columns: 1.2fr 1fr; min-height: 420px; background: rgba(255,253,247,.97); }
        @media (max-width: 860px) { .feed-slide { grid-template-columns: 1fr; min-height: 0; } }
        .feed-slide-image { position: relative; background: #0c2733; display: grid; place-items: center; min-height: 280px; overflow: hidden; }
        .feed-slide-image img { display: block; width: 100%; height: 100%; max-height: 520px; object-fit: cover; }
        .feed-slide-image.no-image { background: linear-gradient(135deg, var(--teal), #0c2733); }
        .feed-slide-image.no-image::after { content: "Walang larawan"; color: rgba(255,255,255,.45); font-size: 13px; font-weight: 600; letter-spacing: .5px; }
        .feed-slide-content { padding: clamp(24px, 3.5vw, 40px); color: var(--ink); display: flex; flex-direction: column; justify-content: center; }
        .feed-slide-content .feed-tag { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .8px; color: var(--palm); background: rgba(47,138,87,.12); padding: 5px 11px; border-radius: 999px; align-self: flex-start; margin-bottom: 14px; }
        .feed-slide-content .feed-tag svg { width: 12px; height: 12px; }
        .feed-slide-content h3 { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: clamp(20px, 2.4vw, 26px); color: var(--ink); line-height: 1.2; letter-spacing: -.4px; margin-bottom: 12px; }
        .feed-slide-content p { font-size: 15px; line-height: 1.65; color: #334155; margin-bottom: 20px; }
        .feed-slide-content .feed-author-row { display: flex; align-items: center; gap: 12px; padding-top: 18px; border-top: 1px solid var(--line); flex-wrap: wrap; }
        .feed-slide-content .author-avatar { width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, var(--teal), var(--palm)); color: #fff; display: grid; place-items: center; font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: 16px; flex: none; }
        .feed-slide-content .author-meta { flex: 1; min-width: 0; }
        .feed-slide-content .author-meta b { font-size: 14px; font-weight: 700; color: var(--ink); display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .feed-slide-content .author-meta span { font-size: 12px; color: var(--muted); }
        .feed-slide-content .feed-delete { background: var(--danger-bg); color: var(--danger); font-size: 12px; font-weight: 700; padding: 7px 12px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: background .2s; }
        .feed-slide-content .feed-delete:hover { background: #fbd5d7; }
        .feed-slide-content .feed-delete svg { width: 13px; height: 13px; }

        .carousel-nav { position: absolute; top: 50%; transform: translateY(-50%); width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,.95); color: var(--ink); border: none; cursor: pointer; display: grid; place-items: center; box-shadow: 0 8px 22px rgba(0,0,0,.3); transition: transform .2s, background .2s; z-index: 5; }
        .carousel-nav:hover { background: #fff; transform: translateY(-50%) scale(1.08); }
        .carousel-nav:active { transform: translateY(-50%) scale(.95); }
        .carousel-nav svg { width: 22px; height: 22px; }
        .carousel-nav.prev { left: 14px; }
        .carousel-nav.next { right: 14px; }

        .carousel-dots { position: absolute; bottom: 18px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 5; padding: 7px 12px; background: rgba(0,0,0,.5); border-radius: 999px; backdrop-filter: blur(8px); }
        .carousel-dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,.5); border: none; cursor: pointer; transition: background .2s, transform .2s; padding: 0; }
        .carousel-dot.active { background: var(--gold); transform: scale(1.35); }

        .carousel-progress { position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: rgba(0,0,0,.1); overflow: hidden; z-index: 5; }
        .carousel-progress-bar { height: 100%; width: 0; background: linear-gradient(90deg, var(--gold), var(--palm)); }
        .carousel-progress-bar.animating { animation: progressFill var(--slide-duration, 6000ms) linear forwards; }
        @keyframes progressFill { from { width: 0; } to { width: 100%; } }

        .carousel-counter { position: absolute; top: 16px; right: 16px; background: rgba(0,0,0,.55); color: #fff; font-size: 12.5px; font-weight: 700; padding: 6px 13px; border-radius: 999px; backdrop-filter: blur(6px); z-index: 5; letter-spacing: .4px; }
        .carousel-pause { position: absolute; top: 16px; left: 16px; background: rgba(0,0,0,.55); color: #fff; border: none; cursor: pointer; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; z-index: 5; transition: background .2s; backdrop-filter: blur(6px); }
        .carousel-pause:hover { background: rgba(0,0,0,.75); }
        .carousel-pause svg { width: 16px; height: 16px; }

        .feed-empty { padding: 60px 24px; text-align: center; color: var(--muted); font-size: 14.5px; font-style: italic; background: rgba(255,253,247,.97); border-radius: 22px; box-shadow: 0 20px 50px rgba(0,0,0,.35); }

        /* METRICS */
        .admin-metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 22px; }
        .admin-metric { background: rgba(255,253,247,.97); color: var(--ink); border-radius: 18px; padding: 20px; box-shadow: 0 15px 35px rgba(0,0,0,.28); position: relative; overflow: hidden; transition: transform .25s ease, box-shadow .25s ease; display: flex; align-items: center; gap: 14px; }
        .admin-metric:hover { transform: translateY(-4px); box-shadow: 0 22px 45px rgba(0,0,0,.35); }
        .admin-metric::after { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--teal), var(--palm)); }
        .admin-metric .m-icon { flex: none; width: 52px; height: 52px; border-radius: 14px; display: grid; place-items: center; background: rgba(20,86,107,.1); color: var(--teal); }
        .admin-metric .m-icon svg { width: 26px; height: 26px; }
        .admin-metric .title { font-size: 11.5px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; }
        .admin-metric .value { font-family: 'Bricolage Grotesque', sans-serif; font-size: 30px; font-weight: 800; margin: 2px 0 0; letter-spacing: -1px; color: var(--ink); }

        /* QUICK ACTIONS */
        .quick-actions-panel { background: rgba(255,253,247,.97); color: var(--ink); border-radius: 22px; padding: clamp(22px, 3vw, 32px); box-shadow: 0 20px 50px rgba(0,0,0,.35); margin-top: 4px; }
        .quick-actions-intro { text-align: center; margin-bottom: 26px; }
        .quick-actions-intro h2 { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: clamp(20px, 2.4vw, 24px); color: var(--teal); letter-spacing: -.4px; }
        .quick-actions-intro p { color: var(--muted); font-size: 14px; margin-top: 6px; }
        .quick-actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .quick-action { display: flex; align-items: center; gap: 16px; padding: 22px 20px; background: linear-gradient(135deg, #f5f8f9, #ffffff); border: 2px solid var(--line); border-radius: 18px; text-decoration: none; color: var(--ink); transition: all .25s cubic-bezier(.2,.8,.2,1); position: relative; overflow: hidden; min-height: 96px; }
        .quick-action::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: var(--teal); transform: scaleY(0); transform-origin: top; transition: transform .3s ease; }
        .quick-action:hover { border-color: var(--teal); transform: translateY(-4px); box-shadow: 0 15px 32px rgba(20,86,107,.18); background: #fff; }
        .quick-action:hover::before { transform: scaleY(1); }
        .quick-action:active { transform: translateY(-1px); }
        .quick-action .qa-icon { flex: none; width: 58px; height: 58px; border-radius: 16px; display: grid; place-items: center; background: linear-gradient(135deg, var(--teal), #0d3f50); color: #fff; box-shadow: 0 8px 18px rgba(20,86,107,.3); transition: transform .25s; }
        .quick-action .qa-icon svg { width: 26px; height: 26px; }
        .quick-action:hover .qa-icon { transform: scale(1.08) rotate(-3deg); }
        .quick-action .qa-text { flex: 1; min-width: 0; }
        .quick-action b { display: block; font-family: 'Bricolage Grotesque', sans-serif; font-size: 16.5px; font-weight: 800; color: var(--teal); margin-bottom: 3px; letter-spacing: -.2px; }
        .quick-action span { font-size: 13px; color: var(--muted); line-height: 1.45; display: block; }
        .quick-action .qa-arrow { flex: none; color: var(--muted); transition: transform .25s, color .25s; }
        .quick-action .qa-arrow svg { width: 20px; height: 20px; }
        .quick-action:hover .qa-arrow { transform: translateX(4px); color: var(--teal); }

        /* COMPOSE */
        .compose-panel { background: rgba(255,253,247,.97); color: var(--ink); border-radius: 22px; padding: clamp(20px, 3vw, 28px); box-shadow: 0 20px 50px rgba(0,0,0,.32); margin-top: 24px; }
        .compose-panel h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 19px; font-weight: 800; margin-bottom: 16px; color: var(--teal); border-bottom: 2px solid var(--line); padding-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
        .form-group label { font-size: 13px; font-weight: 700; color: var(--muted); }
        .form-group input, .form-group textarea, .form-group select { padding: 12px 14px; border: 2px solid var(--line); border-radius: 12px; font-family: inherit; font-size: 14px; transition: border-color .2s, box-shadow .2s; background: #f5f8f9; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--teal); background: #fff; box-shadow: 0 0 0 4px rgba(20,86,107,.12); }
        .btn-post { background: linear-gradient(135deg, var(--palm), #1f6e41); color: #fff; border: none; padding: 14px 26px; border-radius: 12px; font-weight: 800; cursor: pointer; font-size: 15px; font-family: inherit; box-shadow: 0 8px 20px rgba(47,138,87,.3); transition: transform .2s, box-shadow .2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; }
        .btn-post:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(47,138,87,.4); }
        .btn-post svg { width: 17px; height: 17px; }

        .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #a3cfbb; padding: 13px 18px; border-radius: 12px; margin-bottom: 18px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .alert-success svg { width: 18px; height: 18px; }

        @media (max-width: 1280px) { .nav-item { font-size: 12.5px; padding: 7px 11px; } .nav-item svg { width: 14px; height: 14px; } }
        @media (max-width: 1150px) {
            .nav { flex-wrap: wrap; }
            .brand { order: 1; flex: 1; }
            .nav-right { order: 2; }
            .nav-links { order: 3; width: 100%; justify-content: flex-start; padding-top: 10px; border-top: 1px solid rgba(255,255,255,.08); margin-top: 6px; gap: 4px; }
        }
        @media (max-width: 560px) {
            .nav-item { padding: 6px 10px; font-size: 12px; }
            .nav-item svg { width: 13px; height: 13px; }
            .quick-action { padding: 18px 16px; min-height: 84px; }
            .quick-action .qa-icon { width: 50px; height: 50px; border-radius: 13px; }
            .quick-action .qa-icon svg { width: 22px; height: 22px; }
            .quick-action b { font-size: 15px; }
            .carousel-nav { width: 40px; height: 40px; }
            .carousel-nav.prev { left: 8px; }
            .carousel-nav.next { right: 8px; }
            .event-form-row { grid-template-columns: 1fr; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .train { transform: translateX(520px); }
        }
    </style>
</head>
<body><!-- ============ SHARED SVG DEFS (Seal) ============ -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><symbol id="seal" viewBox="0 0 200 200">
<defs>
<linearGradient id="sl-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6fc3e6"/><stop offset=".5" stop-color="#bfe7f1"/><stop offset="1" stop-color="#fbe4a8"/></linearGradient>
<linearGradient id="sl-hill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f4c262"/><stop offset="1" stop-color="#dc9a38"/></linearGradient>
<linearGradient id="sl-grass" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#86c96b"/><stop offset="1" stop-color="#3f9250"/></linearGradient>
<radialGradient id="sl-gloss" cx=".3" cy=".22" r=".8"><stop offset="0" stop-color="#fff" stop-opacity=".2"/><stop offset=".55" stop-color="#fff" stop-opacity="0"/></radialGradient>
<clipPath id="sl-clip"><circle cx="100" cy="100" r="66"/></clipPath>
<path id="sl-top" d="M20,100 A80,80 0 0 1 180,100"/>
<path id="sl-bot" d="M8,100 A92,92 0 0 0 192,100"/>
</defs>
<circle cx="100" cy="100" r="99" fill="#fffaf0"/>
<circle cx="100" cy="100" r="97" fill="none" stroke="#5a3a28" stroke-width="3.6"/>
<circle cx="100" cy="100" r="68.5" fill="none" stroke="#5a3a28" stroke-width="3.2"/>
<g clip-path="url(#sl-clip)">
<rect x="30" y="30" width="140" height="140" fill="url(#sl-sky)"/>
<circle cx="110" cy="54" r="19" fill="#fff4c8" opacity=".55"/><circle cx="110" cy="54" r="8.5" fill="#fff8dc"/>
<path d="M30,116 C58,86 90,94 120,84 C146,76 160,90 172,100 V172 H30Z" fill="url(#sl-hill)"/>
<path d="M30,130 C56,114 82,124 102,128 C126,132 150,120 172,124 V172 H30Z" fill="#e8ad45"/>
<path d="M30,138 C52,126 78,136 96,152 L104,172 H30Z" fill="url(#sl-grass)"/>
<path d="M172,146 C152,138 130,146 112,160 L108,172 H172Z" fill="#3a8a48"/>
<path d="M57,160 C61,132 56,102 66,74" fill="none" stroke="#6b4426" stroke-width="4.6" stroke-linecap="round"/><g transform="translate(66 74) scale(1.0)"><g transform="rotate(-172)" fill="#2f7d3f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-140)" fill="#4aa457"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-108)" fill="#2a6f38"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-72)" fill="#56b45f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-40)" fill="#2f7d3f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-10)" fill="#4aa457"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(20)" fill="#2a6f38"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g></g>
<g transform="translate(56 140) rotate(-16) scale(.88)"><ellipse rx="15" ry="11" fill="#7b4a2b"/><ellipse rx="15" ry="11" fill="none" stroke="#5b3520" stroke-width="1.2"/><ellipse cx="1" cy="-1" rx="11" ry="7.6" fill="#fdf0e2"/><ellipse cx="3" cy="-2" rx="6" ry="3.6" fill="#f3cfcf"/></g><g transform="translate(79 147) rotate(14) scale(.88)"><ellipse rx="15" ry="11" fill="#7b4a2b"/><ellipse rx="15" ry="11" fill="none" stroke="#5b3520" stroke-width="1.2"/><ellipse cx="1" cy="-1" rx="11" ry="7.6" fill="#fdf0e2"/><ellipse cx="3" cy="-2" rx="6" ry="3.6" fill="#f3cfcf"/></g>
<ellipse cx="142" cy="80" rx="25" ry="27" fill="#16341f" opacity=".75"/><path d="M141,106 C141,96 140,90 141,84" stroke="#2c6b36" stroke-width="2.2" fill="none" stroke-linecap="round"/><g transform="translate(131 88) rotate(-22) scale(.62)"><path d="M0,-18 C10,-20 22,-8 18,6 C14,18 4,22 0,26 C-4,22 -14,18 -18,6 C-22,-8 -10,-20 0,-18Z" fill="#3f9a4c"/><path d="M0,-14 L0,22" stroke="#9ad39b" stroke-width="1.6"/></g><g transform="translate(143 72) rotate(10) scale(.9)"><path d="M0,-18 C10,-20 22,-8 18,6 C14,18 4,22 0,26 C-4,22 -14,18 -18,6 C-22,-8 -10,-20 0,-18Z" fill="#2f8340"/><path d="M0,-15 L0,23 M0,6 L-9,-4 M0,6 L9,-4 M0,14 L-8,7 M0,14 L8,7" stroke="#a6dca4" stroke-width="1.3" fill="none" stroke-linecap="round"/></g>
<g transform="translate(140 138) scale(.95)"><path d="M-17,16 C-21,2 -13,-8 0,-8 C13,-8 21,2 17,16 Z" fill="#d9a05b"/><path d="M-17,16 C-21,2 -13,-8 0,-8 C-6,2 -10,10 -17,16Z" fill="#e9b872" opacity=".7"/><ellipse cx="0" cy="-8" rx="13" ry="4.6" fill="#c28542"/><ellipse cx="0" cy="-9" rx="11.5" ry="3.8" fill="#eec45f"/><circle cx="-4" cy="-9.5" r=".9" fill="#c28a2c"/><circle cx="1" cy="-8.5" r=".9" fill="#c28a2c"/><circle cx="5" cy="-10" r=".9" fill="#c28a2c"/></g>
</g>
<text font-family="'Bricolage Grotesque','Arial Black',sans-serif" font-weight="800" fill="#4a2f1f" font-size="19"><textPath href="#sl-top" startOffset="50%" text-anchor="middle" textLength="206" lengthAdjust="spacing">BARANGAY BIGAAN</textPath></text>
<text font-family="'Bricolage Grotesque','Arial Black',sans-serif" font-weight="800" fill="#4a2f1f" font-size="16"><textPath href="#sl-bot" startOffset="50%" text-anchor="middle" textLength="226" lengthAdjust="spacing">CALAUAG, QUEZON</textPath></text>
<circle cx="16.5" cy="100" r="3.2" fill="#5a3a28"/><circle cx="183.5" cy="100" r="3.2" fill="#5a3a28"/>
<circle cx="100" cy="100" r="99" fill="url(#sl-gloss)"/>
</symbol></svg>

<!-- ============ ANIMATED SCENE ============ -->
<div class="scene" aria-hidden="true">
    <svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMax slice">
        <defs>
            <radialGradient id="sunG" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff3c4"/><stop offset=".45" stop-color="#ffd272"/><stop offset="1" stop-color="#ffd272" stop-opacity="0"/></radialGradient>
            <linearGradient id="field" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e2b84e"/><stop offset="1" stop-color="#b98a2a"/></linearGradient>
            <pattern id="rows" width="40" height="14" patternUnits="userSpaceOnUse"><rect width="40" height="14" fill="none"/><path d="M0 7 H40" stroke="#9c7219" stroke-opacity=".35" stroke-width="2"/></pattern>
            <g id="palm">
                <path d="M0 0 C10 -110 -8 -220 16 -330" stroke="#5a3a28" stroke-width="17" fill="none" stroke-linecap="round"/>
                <path d="M0 0 C10 -110 -8 -220 16 -330" stroke="#7a5238" stroke-width="5" fill="none" stroke-linecap="round" stroke-dasharray="3 14" transform="translate(-3 0)"/>
                <g transform="translate(16 -330)">
                    <g class="frond-wave"><path d="M0 0 C60 -70 150 -60 215 10 C150 -22 70 -24 0 0Z" fill="#2f8a57" transform="rotate(-18)"/></g>
                    <g class="frond-wave" style="animation-delay:-1s"><path d="M0 0 C60 -70 150 -60 215 10 C150 -22 70 -24 0 0Z" fill="#256f45" transform="rotate(-62)"/></g>
                    <g class="frond-wave" style="animation-delay:-2s"><path d="M0 0 C60 -70 150 -60 215 10 C150 -22 70 -24 0 0Z" fill="#37a066" transform="rotate(-108)"/></g>
                    <g class="frond-wave" style="animation-delay:-.5s"><path d="M0 0 C60 -70 150 -60 215 10 C150 -22 70 -24 0 0Z" fill="#2f8a57" transform="rotate(-150)"/></g>
                    <g class="frond-wave" style="animation-delay:-1.5s"><path d="M0 0 C60 -70 150 -60 215 10 C150 -22 70 -24 0 0Z" fill="#256f45" transform="rotate(-195)"/></g>
                    <g class="frond-wave" style="animation-delay:-2.5s"><path d="M0 0 C60 -70 150 -60 215 10 C150 -22 70 -24 0 0Z" fill="#37a066" transform="rotate(25)"/></g>
                    <circle cx="-6" cy="14" r="11" fill="#6b4a2b"/><circle cx="12" cy="18" r="11" fill="#7c5833"/><circle cx="2" cy="28" r="10" fill="#5e4025"/>
                </g>
            </g>
        </defs>
        <g class="sun-glow"><circle cx="520" cy="560" r="330" fill="url(#sunG)"/></g>
        <g class="rays" opacity=".16" fill="#fff0b8">
            <polygon points="520,560 480,-400 560,-400"/><polygon points="520,560 480,1520 560,1520"/>
            <polygon points="520,560 -400,520 -400,600"/><polygon points="520,560 1440,520 1440,600"/>
        </g>
        <circle cx="520" cy="560" r="78" fill="#fff6d0"/>
        <g class="cloud c1" fill="#fff" opacity=".55"><ellipse cx="0" cy="170" rx="120" ry="24"/><ellipse cx="-60" cy="155" rx="70" ry="22"/><ellipse cx="60" cy="152" rx="60" ry="20"/></g>
        <g class="cloud c2" fill="#fff" opacity=".4"><ellipse cx="0" cy="300" rx="150" ry="28"/><ellipse cx="70" cy="284" rx="80" ry="24"/></g>
        <g class="cloud c3" fill="#ffe9c0" opacity=".5"><ellipse cx="0" cy="420" rx="110" ry="20"/><ellipse cx="-50" cy="406" rx="60" ry="18"/></g>
        <g class="bird" fill="none" stroke="#10303d" stroke-width="3" stroke-linecap="round" opacity=".7"><path d="M0 260 q10 -12 20 0 q10 -12 20 0"/><path d="M60 290 q8 -9 16 0 q8 -9 16 0"/></g>
        <g class="bird b2" fill="none" stroke="#10303d" stroke-width="3" stroke-linecap="round" opacity=".6"><path d="M0 220 q9 -10 18 0 q9 -10 18 0"/></g>
        <path d="M0 640 L170 470 L300 570 L470 400 L690 600 L860 480 L1060 620 L1250 450 L1430 590 L1600 500 V760 H0Z" fill="#1c6a72" opacity=".9"/>
        <path d="M0 690 Q220 560 460 650 T900 640 T1340 620 T1600 660 V780 H0Z" fill="#287d56"/>
        <g transform="translate(850 560)">
            <line x1="62" y1="-16" x2="62" y2="-86" stroke="#2b1c14" stroke-width="3"/>
            <path d="M62 -86 h34 l-8 8 l8 8 h-34Z" fill="#e0453c"/>
            <path d="M-8 28 L62 -18 L132 28Z" fill="#a62f35"/>
            <rect x="4" y="28" width="116" height="48" fill="#f6ecd4"/>
            <rect x="14" y="40" width="20" height="20" fill="#5fa8c8"/><rect x="90" y="40" width="20" height="20" fill="#5fa8c8"/>
            <rect x="50" y="42" width="24" height="34" fill="#1a3a68"/>
        </g>
        <path d="M0 740 Q400 700 800 716 T1600 700 V820 H0Z" fill="url(#field)"/>
        <path d="M0 740 Q400 700 800 716 T1600 700 V820 H0Z" fill="url(#rows)"/>
        <rect x="0" y="796" width="1600" height="104" fill="#2d6d44"/>
        <line x1="0" y1="824" x2="1600" y2="824" stroke="#6b4630" stroke-width="16" stroke-dasharray="7 22"/>
        <line x1="0" y1="818" x2="1600" y2="818" stroke="#c9ccd1" stroke-width="3.5"/>
        <line x1="0" y1="830" x2="1600" y2="830" stroke="#c9ccd1" stroke-width="3.5"/>
        <g class="train">
            <g transform="translate(0 746)">
                <rect x="360" y="12" width="120" height="58" rx="6" fill="#f2b84b"/>
                <rect x="372" y="-14" width="70" height="30" rx="4" fill="#12303f"/>
                <rect x="380" y="-6" width="24" height="16" fill="#9fd8ee"/><rect x="410" y="-6" width="24" height="16" fill="#9fd8ee"/>
                <rect x="448" y="-6" width="14" height="22" fill="#12303f"/>
                <rect x="360" y="50" width="120" height="8" fill="#c0392b"/>
                <circle cx="486" cy="40" r="7" fill="#fff6c4"/>
                <g><circle class="puff" cx="455" cy="-12" r="14" fill="#fff" opacity=".7"/><circle class="puff" cx="455" cy="-12" r="14" fill="#fff" opacity=".7"/><circle class="puff" cx="455" cy="-12" r="14" fill="#fff" opacity=".7"/></g>
                <g fill="#fffdf7"><rect x="230" y="14" width="120" height="56" rx="6"/><rect x="100" y="14" width="120" height="56" rx="6"/><rect x="-30" y="14" width="120" height="56" rx="6"/></g>
                <g fill="#5fa8c8">
                    <rect x="244" y="26" width="20" height="16" rx="2"/><rect x="274" y="26" width="20" height="16" rx="2"/><rect x="304" y="26" width="20" height="16" rx="2"/>
                    <rect x="114" y="26" width="20" height="16" rx="2"/><rect x="144" y="26" width="20" height="16" rx="2"/><rect x="174" y="26" width="20" height="16" rx="2"/>
                    <rect x="-16" y="26" width="20" height="16" rx="2"/><rect x="14" y="26" width="20" height="16" rx="2"/><rect x="44" y="26" width="20" height="16" rx="2"/>
                </g>
                <g fill="#2f8a57"><rect x="230" y="52" width="120" height="8"/><rect x="100" y="52" width="120" height="8"/><rect x="-30" y="52" width="120" height="8"/></g>
                <rect x="-30" y="66" width="510" height="8" fill="#12303f"/>
                <g fill="#12303f" stroke="#c9ccd1" stroke-width="2">
                    <circle class="wheel" cx="0" cy="78" r="9"/><circle class="wheel" cx="60" cy="78" r="9"/><circle class="wheel" cx="130" cy="78" r="9"/><circle class="wheel" cx="190" cy="78" r="9"/>
                    <circle class="wheel" cx="260" cy="78" r="9"/><circle class="wheel" cx="320" cy="78" r="9"/><circle class="wheel" cx="390" cy="78" r="11"/><circle class="wheel" cx="450" cy="78" r="11"/>
                </g>
            </g>
        </g>
        <g transform="translate(150 900) scale(1.25)"><g class="palm-sway"><use href="#palm"/></g></g>
        <g transform="translate(310 900) scale(.8)"><g class="palm-sway slow"><use href="#palm"/></g></g>
        <g transform="translate(1470 900) scale(1.1)"><g class="palm-sway slow"><use href="#palm"/></g></g>
        <g transform="translate(1340 900) scale(.7)"><g class="palm-sway"><use href="#palm"/></g></g>
    </svg>
</div>

<div class="page">
    <nav class="nav">
        <a class="brand" href="dashboard.php">
            <span class="logo" role="img" aria-label="Barangay Bigaan seal"><svg viewBox="0 0 200 200" aria-hidden="true"><use href="#seal"/></svg></span>
            <span><b>E-Barangay</b><small>Official Management Panel</small></span>
        </a>

        <?php
        $current_page = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
        $current_dir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
        $is_in_modules = ($current_dir === 'modules');
        $current_module = $is_in_modules ? $current_page : '';

        function nav_cls($is_active) {
            return $is_active ? 'nav-item active' : 'nav-item';
        }
        ?>

        <div class="nav-links">
            <a href="dashboard.php" class="<?= nav_cls($current_page === 'dashboard.php') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                Dashboard
            </a>
            <a href="modules/residents.php" class="<?= nav_cls($current_module === 'residents.php') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3.6-7 7-7s7 3 7 7"/><path d="M17 11a3 3 0 1 0 0-6M22 21c0-3-2-5-5-5.5"/></svg>
                Residents
            </a>
            <a href="modules/documents.php" class="<?= nav_cls($current_module === 'documents.php') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                Documents
            </a>
            <a href="modules/complaints.php" class="<?= nav_cls($current_module === 'complaints.php') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M5 8l7-5 7 5M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/></svg>
                Complaints
            </a>
            <a href="modules/treasury.php" class="<?= nav_cls($current_module === 'treasury.php') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="13" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><circle cx="17" cy="15" r="1.2"/></svg>
                Treasury
            </a>
            <a href="modules/facilities.php" class="<?= nav_cls($current_module === 'facilities.php') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
                Facilities
            </a>
            <a href="modules/events.php" class="<?= nav_cls($current_module === 'events.php') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Events
            </a>
            <a href="modules/announcements.php" class="<?= nav_cls($current_module === 'announcements.php') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h3l4 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M16 8a4 4 0 0 1 0 8"/></svg>
                Announcements
            </a>
        </div>

        <div class="nav-right">
            <span class="role-badge"><?= e($position) ?></span>
            <a href="logout.php" class="btn-logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Sign Out
            </a>
        </div>
    </nav>

    <main class="container">
        <!-- Welcome -->
        <div class="welcome-panel">
            <div>
                <h1>Maligayang pagdating, <?= e($username) ?></h1>
                <p>Barangay Administrative Management Panel</p>
            </div>
            <div class="welcome-date">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?= date('F j, Y') ?>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="9 12 12 15 16 10"/></svg>
                <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <!-- ============================================
             1. OPERATIONS OVERVIEW
             ============================================ -->
        <div class="section-head" style="margin-top:0;">
            <h2>
                <span class="head-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg></span>
                Operations Overview
            </h2>
        </div>

        <div class="admin-metrics-grid">
            <div class="admin-metric">
                <div class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3.6-7 7-7s7 3 7 7"/><path d="M17 11a3 3 0 1 0 0-6M22 21c0-3-2-5-5-5.5"/></svg></div>
                <div>
                    <div class="title">Resident Registry</div>
                    <div class="value"><?= number_format($metrics['residents']) ?></div>
                </div>
            </div>
            <div class="admin-metric">
                <div class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></div>
                <div>
                    <div class="title">Document Requests</div>
                    <div class="value"><?= number_format($metrics['documents']) ?></div>
                </div>
            </div>
            <div class="admin-metric">
                <div class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M5 8l7-5 7 5M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/></svg></div>
                <div>
                    <div class="title">Complaints & Blotter</div>
                    <div class="value"><?= number_format($metrics['cases']) ?></div>
                </div>
            </div>
            <div class="admin-metric">
                <div class="m-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg></div>
                <div>
                    <div class="title">Facility Bookings</div>
                    <div class="value"><?= number_format($metrics['facilities']) ?></div>
                </div>
            </div>
        </div>

        <!-- ============================================
             2. ANNOUNCEMENTS
             ============================================ -->
        <div class="section-head">
            <h2>
                <span class="head-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
                Official Announcements
            </h2>
            <a href="modules/announcements.php" class="section-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Manage Announcements
            </a>
        </div>

        <section class="announcement-panel">
            <div class="announcement-panel-header">
                <h3>
                    <span class="bell" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
                    Latest Announcements
                </h3>
                <a href="modules/announcements.php" class="view-all">
                    Tingnan ang Lahat
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>
            <div class="announcement-grid">
                <?php if (empty($announcements)): ?>
                    <div class="announcement-empty">Walang nakapost na opisyal na anunsyo sa ngayon.</div>
                <?php else: ?>
                    <?php foreach ($announcements as $anc): ?>
                        <div class="announcement-item">
                            <div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h3l4 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M16 8a4 4 0 0 1 0 8"/></svg></div>
                            <div class="text">
                                <b><?= e($anc['title']) ?></b>
                                <span class="meta"><?= date('M d, Y · h:i A', strtotime($anc['date'])) ?></span>
                                <p><?= nl2br(e($anc['content'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- ============================================
             3. UPCOMING EVENTS
             ============================================ -->
        <div class="section-head">
            <h2>
                <span class="head-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                Upcoming Events
            </h2>
            <button type="button" class="section-link" id="openEventModal" style="border:none; cursor:pointer; font-family:inherit;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Manage Events
            </button>
        </div>

        <?php if (empty($upcoming_events)): ?>
            <div class="events-empty">Walang upcoming events sa ngayon.</div>
        <?php else: ?>
            <section class="events-grid">
                <?php foreach ($upcoming_events as $ev): ?>
                    <div class="event-card">
                        <div class="event-date-badge">
                            <span class="edb-month"><?= date('M', strtotime($ev['date'])) ?></span>
                            <span class="edb-day"><?= date('d', strtotime($ev['date'])) ?></span>
                        </div>
                        <div class="event-card-body">
                            <b class="event-title"><?= e($ev['title']) ?></b>
                            <?php if (!empty($ev['time']) || !empty($ev['loc'])): ?>
                                <div class="event-card-meta">
                                    <?php if (!empty($ev['time'])): ?>
                                        <span>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            <?= date('g:i A', strtotime($ev['time'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($ev['loc'])): ?>
                                        <span>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            <?= e($ev['loc']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($ev['desc'])): ?>
                                <p class="event-card-desc"><?= e(mb_substr($ev['desc'], 0, 130)) ?><?= mb_strlen($ev['desc']) > 130 ? '...' : '' ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <!-- ============================================
             4. COMMUNITY FEED CAROUSEL
             ============================================ -->
        <div class="section-head">
            <h2>
                <span class="head-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></span>
                Community Feed
            </h2>
            <a href="#" class="section-link" id="feedTogglePlay">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                Pause Slideshow
            </a>
        </div>

        <?php if (empty($feed_posts)): ?>
            <div class="feed-empty">Walang feed posts sa ngayon.</div>
        <?php else: ?>
            <div class="feed-carousel" id="feedCarousel" style="--slide-duration: 7000ms">
                <div class="feed-carousel-viewport">
                    <div class="feed-carousel-track" id="feedTrack">
                        <?php foreach ($feed_posts as $idx => $post): ?>
                            <div class="feed-slide">
                                <div class="feed-slide-image <?= empty($post['image_path']) ? 'no-image' : '' ?>">
                                    <?php if (!empty($post['image_path'])): ?>
                                        <img src="<?= e($post['image_path']) ?>" alt="Feed image from <?= e($post['author_name']) ?>" loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="feed-slide-content">
                                    <span class="feed-tag">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        Community Update
                                    </span>
                                    <h3><?= e($post['author_role']) ?> Update</h3>
                                    <p><?= nl2br(e($post['content'])) ?></p>
                                    <div class="feed-author-row">
                                        <div class="author-avatar"><?= e(strtoupper(substr($post['author_name'] ?? 'O', 0, 1))) ?></div>
                                        <div class="author-meta">
                                            <b><?= e($post['author_name']) ?></b>
                                            <span><?= date('F j, Y · g:i a', strtotime($post['created_at'])) ?></span>
                                        </div>
                                        <a href="dashboard.php?action=delete_post&post_id=<?= $post['post_id'] ?>" class="feed-delete" onclick="return confirm('Gusto mo bang burahin ang feed post na ito?');">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                                            Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="button" class="carousel-nav prev" id="feedPrev" aria-label="Previous post">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button type="button" class="carousel-nav next" id="feedNext" aria-label="Next post">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
                <button type="button" class="carousel-pause" id="feedPause" aria-label="Pause slideshow">
                    <svg viewBox="0 0 24 24" fill="currentColor" id="pauseIcon"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                    <svg viewBox="0 0 24 24" fill="currentColor" id="playIcon" style="display:none"><polygon points="6 4 20 12 6 20"/></svg>
                </button>

                <div class="carousel-counter" id="feedCounter">1 / <?= count($feed_posts) ?></div>
                <div class="carousel-dots" id="feedDots"></div>
                <div class="carousel-progress"><div class="carousel-progress-bar" id="feedProgress"></div></div>
            </div>
        <?php endif; ?>

        <!-- ============================================
             5. QUICK NAVIGATION
             ============================================ -->
        <div class="section-head">
            <h2>
                <span class="head-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
                Quick Navigation
            </h2>
        </div>

        <section class="quick-actions-panel">
            <div class="quick-actions-intro">
                <h2>Barangay Management Modules</h2>
                <p>Pindutin ang kahon para buksan ang module. Bawat kahon ay may label at icon para madaling mahanap.</p>
            </div>
            <div class="quick-actions-grid">
                <a href="modules/residents.php" class="quick-action">
                    <span class="qa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3.6-7 7-7s7 3 7 7"/><path d="M17 11a3 3 0 1 0 0-6M22 21c0-3-2-5-5-5.5"/></svg></span>
                    <span class="qa-text"><b>Resident Registry</b><span>Manage registered residents list</span></span>
                    <span class="qa-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </a>
                <a href="modules/documents.php" class="quick-action">
                    <span class="qa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></span>
                    <span class="qa-text"><b>Document Requests</b><span>Process clearances & certificates</span></span>
                    <span class="qa-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </a>
                <a href="modules/complaints.php" class="quick-action">
                    <span class="qa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M5 8l7-5 7 5M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/></svg></span>
                    <span class="qa-text"><b>Complaints & Blotter</b><span>Blotters, disputes & mediation</span></span>
                    <span class="qa-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </a>
                <a href="modules/treasury.php" class="quick-action">
                    <span class="qa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="13" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><circle cx="17" cy="15" r="1.2"/></svg></span>
                    <span class="qa-text"><b>Treasury & Revenue</b><span>Financial records & OR payments</span></span>
                    <span class="qa-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </a>
                <a href="modules/facilities.php" class="quick-action">
                    <span class="qa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg></span>
                    <span class="qa-text"><b>Facilities Management</b><span>Approve & manage reservations</span></span>
                    <span class="qa-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </a>
                <a href="modules/events.php" class="quick-action">
                    <span class="qa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                    <span class="qa-text"><b>Events Management</b><span>Organize community events</span></span>
                    <span class="qa-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </a>
                <a href="modules/announcements.php" class="quick-action">
                    <span class="qa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h3l4 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M16 8a4 4 0 0 1 0 8"/></svg></span>
                    <span class="qa-text"><b>Announcements</b><span>Post & edit official advisories</span></span>
                    <span class="qa-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </a>
            </div>
        </section>

        <!-- Compose -->
        <div class="compose-panel">
            <h2>
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Post to Community Feed
            </h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="create_feed_post" value="1">
                <div class="form-group">
                    <label>Caption / Activity Description</label>
                    <textarea name="feed_content" rows="3" placeholder="Isulat ang caption o detalye ng larawan..."></textarea>
                </div>
                <div class="form-group">
                    <label>Upload Photo</label>
                    <input type="file" name="feed_image" accept="image/*" required>
                </div>
                <button type="submit" class="btn-post">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    I-publish ang Photo Post
                </button>
            </form>
        </div>
    </main>
</div>

<!-- ============================================
     EVENT MODAL
     ============================================ -->
<div class="event-modal-overlay" id="eventModalOverlay" style="display:none;">
    <div class="event-modal">
        <div class="event-modal-header">
            <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Manage Events
            </h2>
            <button type="button" class="event-modal-close" id="closeEventModal" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="event-modal-tabs">
            <button type="button" class="event-tab active" id="tabList">All Events</button>
            <button type="button" class="event-tab" id="tabAdd">+ Add New Event</button>
        </div>

        <!-- Tab 1: All Events List -->
        <div class="event-modal-body" id="eventListPane">
            <?php if (empty($all_events)): ?>
                <div class="events-empty">Walang events sa database. Mag-add ng bago gamit ang "+ Add New Event" tab.</div>
            <?php else: ?>
                <?php foreach ($all_events as $ev): ?>
                    <div class="event-modal-item <?= !$ev['is_upcoming'] ? 'past' : '' ?>">
                        <div class="emi-date <?= !$ev['is_upcoming'] ? 'past' : '' ?>">
                            <span class="emi-month"><?= date('M', strtotime($ev['date'])) ?></span>
                            <span class="emi-day"><?= date('d', strtotime($ev['date'])) ?></span>
                        </div>
                        <div class="emi-body">
                            <b class="emi-title">
                                <?= e($ev['title']) ?>
                                <?php if (!$ev['is_upcoming']): ?>
                                    <span class="emi-past-tag">(Past)</span>
                                <?php endif; ?>
                            </b>
                            <?php if (!empty($ev['time']) || !empty($ev['loc'])): ?>
                                <div class="emi-meta">
                                    <?php if (!empty($ev['time'])): ?>
                                        <span>
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            <?= date('g:i A', strtotime($ev['time'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($ev['loc'])): ?>
                                        <span>
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            <?= e($ev['loc']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($ev['desc'])): ?>
                                <p class="emi-desc"><?= e(mb_substr($ev['desc'], 0, 140)) ?><?= mb_strlen($ev['desc']) > 140 ? '...' : '' ?></p>
                            <?php endif; ?>
                            <div class="emi-actions">
                                <a href="dashboard.php?edit_event=<?= $ev['id'] ?>" class="btn-event-edit">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                    Edit
                                </a>
                                <a href="dashboard.php?action=delete_event&event_id=<?= $ev['id'] ?>" class="btn-event-delete" onclick="return confirm('Sigurado ka bang gusto mong burahin ang event na ito?');">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
                                    Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Tab 2: Add/Edit Event Form -->
        <div class="event-modal-body" id="eventFormPane" style="display:none;">
            <form method="POST" class="event-modal-form">
                <?php if ($edit_event): ?>
                    <input type="hidden" name="update_event" value="1">
                    <input type="hidden" name="event_id" value="<?= $edit_event['id'] ?>">
                <?php else: ?>
                    <input type="hidden" name="create_event" value="1">
                <?php endif; ?>

                <div class="form-group">
                    <label>Event Title *</label>
                    <input type="text" name="event_title" required
                           value="<?= e($edit_event['title'] ?? '') ?>"
                           placeholder="e.g. Barangay Clean-up Drive">
                </div>
                <div class="event-form-row">
                    <div class="form-group">
                        <label>Event Date *</label>
                        <input type="date" name="event_date" required
                               value="<?= e($edit_event['date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Event Time</label>
                        <input type="time" name="event_time"
                               value="<?= e($edit_event['time'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Location / Venue</label>
                    <input type="text" name="event_location"
                           value="<?= e($edit_event['loc'] ?? '') ?>"
                           placeholder="e.g. Barangay Hall / Covered Court">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="event_description" rows="4"
                              placeholder="Isulat ang detalye ng event..."><?= e($edit_event['desc'] ?? '') ?></textarea>
                </div>

                <div class="event-form-actions">
                    <button type="submit" class="btn-event-submit">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <?php if ($edit_event): ?>
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                            <?php else: ?>
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            <?php endif; ?>
                        </svg>
                        <?= $edit_event ? 'I-save ang Pagbabago' : 'I-post ang Event' ?>
                    </button>
                    <?php if ($edit_event): ?>
                        <a href="dashboard.php" class="btn-event-cancel">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================
     JAVASCRIPT
     ============================================ -->
<script>
(function () {
    /* ============ EVENT MODAL LOGIC ============ */
    var modalOverlay = document.getElementById('eventModalOverlay');
    var openBtn = document.getElementById('openEventModal');
    var closeBtn = document.getElementById('closeEventModal');
    var tabList = document.getElementById('tabList');
    var tabAdd = document.getElementById('tabAdd');
    var listPane = document.getElementById('eventListPane');
    var formPane = document.getElementById('eventFormPane');

    function openModal(tab) {
        if (modalOverlay) {
            modalOverlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            switchTab(tab || 'list');
        }
    }
    function closeModal() {
        if (modalOverlay) {
            modalOverlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    function switchTab(tab) {
        if (tab === 'add') {
            tabList.classList.remove('active');
            tabAdd.classList.add('active');
            listPane.style.display = 'none';
            formPane.style.display = 'block';
        } else {
            tabList.classList.add('active');
            tabAdd.classList.remove('active');
            listPane.style.display = 'block';
            formPane.style.display = 'none';
        }
    }

    if (openBtn) openBtn.addEventListener('click', function() { openModal('list'); });
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (tabList) tabList.addEventListener('click', function() { switchTab('list'); });
    if (tabAdd) tabAdd.addEventListener('click', function() { switchTab('add'); });
    if (modalOverlay) {
        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) closeModal();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modalOverlay && modalOverlay.style.display === 'flex') {
            closeModal();
        }
    });

    <?php if ($edit_event): ?>
        openModal('add');
    <?php endif; ?>

    /* ============ CAROUSEL LOGIC ============ */
    var carousel = document.getElementById('feedCarousel');
    if (!carousel) return;

    var track = document.getElementById('feedTrack');
    var slides = track.querySelectorAll('.feed-slide');
    var total = slides.length;
    if (total === 0) return;

    var counter = document.getElementById('feedCounter');
    var dotsWrap = document.getElementById('feedDots');
    var progress = document.getElementById('feedProgress');
    var pauseBtn = document.getElementById('feedPause');
    var pauseIcon = document.getElementById('pauseIcon');
    var playIcon = document.getElementById('playIcon');
    var toggleLink = document.getElementById('feedTogglePlay');
    var prevBtn = document.getElementById('feedPrev');
    var nextBtn = document.getElementById('feedNext');

    var SLIDE_DURATION = 7000;
    var index = 0;
    var isPaused = false;
    var timer = null;

    var dots = [];
    for (var i = 0; i < total; i++) {
        var d = document.createElement('button');
        d.className = 'carousel-dot';
        d.type = 'button';
        d.setAttribute('aria-label', 'Go to slide ' + (i + 1));
        (function (idx) {
            d.addEventListener('click', function () { goTo(idx, true); });
        })(i);
        dotsWrap.appendChild(d);
        dots.push(d);
    }

    if (total === 1) {
        if (prevBtn) prevBtn.style.display = 'none';
        if (nextBtn) nextBtn.style.display = 'none';
        if (dotsWrap) dotsWrap.style.display = 'none';
        if (pauseBtn) pauseBtn.style.display = 'none';
        if (toggleLink) toggleLink.style.display = 'none';
    }

    function goTo(i, userTriggered) {
        index = (i + total) % total;
        track.style.transform = 'translateX(-' + (index * 100) + '%)';
        if (counter) counter.textContent = (index + 1) + ' / ' + total;
        dots.forEach(function (dot, di) {
            dot.classList.toggle('active', di === index);
        });
        restartProgress();
        if (userTriggered) restartTimer();
    }

    function restartProgress() {
        if (!progress || total <= 1) return;
        progress.classList.remove('animating');
        void progress.offsetWidth;
        progress.style.width = '0%';
        if (!isPaused) {
            progress.style.animationDuration = SLIDE_DURATION + 'ms';
            progress.classList.add('animating');
        }
    }

    function restartTimer() {
        if (timer) clearTimeout(timer);
        if (isPaused || total <= 1) return;
        timer = setTimeout(function () {
            goTo(index + 1, false);
        }, SLIDE_DURATION);
    }

    function setPaused(paused) {
        isPaused = paused;
        if (paused) {
            if (timer) clearTimeout(timer);
            if (pauseIcon) pauseIcon.style.display = 'none';
            if (playIcon) playIcon.style.display = 'block';
            if (progress) {
                progress.classList.remove('animating');
                var computed = getComputedStyle(progress).width;
                progress.style.width = computed;
            }
            if (