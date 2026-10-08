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
    $first_name             = trim($_POST['first_name'] ?? '');
    $middle_name            = trim($_POST['middle_name'] ?? '');
    $last_name              = trim($_POST['last_name'] ?? '');
    $suffix                 = trim($_POST['suffix'] ?? '');
    $birthdate              = $_POST['birthdate'] ?? '';
    $gender                 = $_POST['gender'] ?? 'Male';
    $address                = trim($_POST['address'] ?? '');
    $civil_status           = $_POST['civil_status'] ?? 'Single';
    $occupation             = trim($_POST['occupation'] ?? '');
    $voter_status           = $_POST['voter_status'] ?? 'Registered';
    $special_classification = trim($_POST['special_classification'] ?? '');
    $contact                = trim($_POST['contact_number'] ?? '');
    $email                  = trim($_POST['email'] ?? '');
    $purok_id               = (!empty($_POST['purok_id']) && intval($_POST['purok_id']) > 0) ? intval($_POST['purok_id']) : 0;

    $is_head_of_family     = !empty($_POST['is_head_of_family']) ? 1 : 0;
    $is_senior             = !empty($_POST['is_senior']) ? 1 : 0;
    $is_pwd                = !empty($_POST['is_pwd']) ? 1 : 0;
    $pwd_id_number         = trim($_POST['pwd_id_number'] ?? '');
    $is_solo_parent        = !empty($_POST['is_solo_parent']) ? 1 : 0;
    $solo_parent_id_number = trim($_POST['solo_parent_id_number'] ?? '');
    $is_youth              = !empty($_POST['is_youth']) ? 1 : 0;

    try {
        $check = $conn->prepare("SELECT resident_id FROM residents WHERE resident_id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();

        if ($exists) {
            $sql = "UPDATE residents SET
                        first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
                        birthdate = ?, gender = ?, address = ?, civil_status = ?, occupation = ?,
                        voter_status = ?, special_classification = ?,
                        contact_number = ?, email = ?,
                        purok_id = ?, profile_completed = 1,
                        is_head_of_family = ?, is_senior = ?,
                        is_pwd = ?, pwd_id_number = ?,
                        is_solo_parent = ?, solo_parent_id_number = ?,
                        is_youth = ?
                    WHERE resident_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sssssssssssssiiiiisisi",
                $first_name, $middle_name, $last_name, $suffix,
                $birthdate, $gender, $address, $civil_status, $occupation,
                $voter_status, $special_classification,
                $contact, $email,
                $purok_id,
                $is_head_of_family, $is_senior,
                $is_pwd, $pwd_id_number,
                $is_solo_parent, $solo_parent_id_number,
                $is_youth,
                $user_id
            );
        } else {
            $sql = "INSERT INTO residents (
                        resident_id, first_name, middle_name, last_name, suffix,
                        birthdate, gender, address, civil_status, occupation,
                        voter_status, special_classification,
                        contact_number, email, purok_id, profile_completed,
                        is_head_of_family, is_senior,
                        is_pwd, pwd_id_number,
                        is_solo_parent, solo_parent_id_number,
                        is_youth
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isssssssssssssiiiiisisi",
                $user_id, $first_name, $middle_name, $last_name, $suffix,
                $birthdate, $gender, $address, $civil_status, $occupation,
                $voter_status, $special_classification,
                $contact, $email, $purok_id,
                $is_head_of_family, $is_senior,
                $is_pwd, $pwd_id_number,
                $is_solo_parent, $solo_parent_id_number,
                $is_youth
            );
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
    if ($p_res) { while ($row = $p_res->fetch_assoc()) $feed_posts[] = $row; }
} catch (Throwable $e) {}

/* Fetch Official Announcements (Published only) */
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
} catch (Throwable $e) {}

/* Fetch Upcoming Events */
$upcoming_events = [];
try {
    $ev_res = $conn->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 6");
    if ($ev_res) {
        while ($row = $ev_res->fetch_assoc()) {
            $upcoming_events[] = [
                'title'       => $row['title'] ?? $row['event_title'] ?? 'Event',
                'description' => $row['description'] ?? $row['details'] ?? $row['content'] ?? '',
                'date'        => $row['event_date'] ?? $row['date'] ?? date('Y-m-d'),
                'location'    => $row['location'] ?? $row['venue'] ?? ''
            ];
        }
    }
} catch (Throwable $e) {}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function yesno($v) { return !empty($v) ? 'Oo' : 'Hindi'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard · E-Barangay Bigaan</title>
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

        .nav-links { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; list-style: none; }
        .nav-item {
            color: #cfe3ea; text-decoration: none; font-size: 13.5px; font-weight: 600;
            padding: 8px 14px; border-radius: 999px; transition: all .2s;
            white-space: nowrap;
            display: inline-flex; align-items: center; gap: 7px;
        }
        .nav-item svg { width: 15px; height: 15px; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,.14); color: var(--gold); }

        .role-badge { background: rgba(47,138,87,.25); color: #6ee7b7; border: 1px solid rgba(47,138,87,.4); padding: 5px 14px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
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

        /* ============================================
           ANNOUNCEMENTS PANEL
           ============================================ */
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

        /* ============================================
           UPCOMING EVENTS (hiwalay na section)
           ============================================ */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
        .event-card-body { flex: 1; min-width: 0; }
        .event-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 15.5px; font-weight: 800;
            color: var(--teal); display: block;
            margin-bottom: 6px; line-height: 1.25;
        }
        .event-location {
            font-size: 12px; color: var(--muted);
            display: inline-flex; align-items: center; gap: 4px;
            margin-bottom: 8px; font-weight: 600;
        }
        .event-desc {
            font-size: 13px; color: #334155;
            line-height: 1.5; margin-top: 4px;
        }
        .events-empty {
            padding: 40px 24px; text-align: center;
            background: rgba(255,253,247,.97);
            border-radius: 18px;
            color: var(--muted); font-style: italic; font-size: 14px;
            box-shadow: 0 15px 35px rgba(0,0,0,.3);
        }

        /* ============================================
           AUTO-SLIDING COMMUNITY FEED CAROUSEL
           ============================================ */
        .feed-carousel {
            position: relative;
            border-radius: 22px;
            overflow: hidden;
            background: rgba(255,253,247,.97);
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
        }
        .feed-carousel-viewport { position: relative; overflow: hidden; }
        .feed-carousel-track {
            display: flex;
            transition: transform .7s cubic-bezier(.5,0,.2,1);
            will-change: transform;
        }
        .feed-slide {
            flex: 0 0 100%;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            min-height: 440px;
            background: rgba(255,253,247,.97);
        }
        @media (max-width: 860px) {
            .feed-slide { grid-template-columns: 1fr; min-height: 0; }
        }
        .feed-slide-image {
            position: relative;
            background: #0c2733;
            display: grid; place-items: center;
            min-height: 280px;
            overflow: hidden;
        }
        .feed-slide-image img {
            display: block; width: 100%; height: 100%;
            max-height: 560px; object-fit: cover;
        }
        .feed-slide-image.no-image {
            background: linear-gradient(135deg, var(--teal), #0c2733);
        }
        .feed-slide-image.no-image::after {
            content: "Walang larawan";
            color: rgba(255,255,255,.45);
            font-size: 13px; font-weight: 600; letter-spacing: .5px;
        }
        .feed-slide-content {
            padding: clamp(24px, 3.5vw, 44px);
            color: var(--ink);
            display: flex; flex-direction: column;
            justify-content: center;
        }
        .feed-slide-content .feed-tag {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: .8px; color: var(--palm);
            background: rgba(47,138,87,.12);
            padding: 5px 11px; border-radius: 999px;
            align-self: flex-start; margin-bottom: 14px;
        }
        .feed-slide-content .feed-tag svg { width: 12px; height: 12px; }
        .feed-slide-content h3 {
            font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800;
            font-size: clamp(20px, 2.4vw, 26px); color: var(--ink);
            line-height: 1.2; letter-spacing: -.4px;
            margin-bottom: 12px;
        }
        .feed-slide-content p {
            font-size: 15px; line-height: 1.65; color: #334155;
            margin-bottom: 20px;
        }
        .feed-slide-content .feed-author-row {
            display: flex; align-items: center; gap: 12px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            flex-wrap: wrap;
        }
        .feed-slide-content .author-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), var(--palm));
            color: #fff;
            display: grid; place-items: center;
            font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800;
            font-size: 16px; flex: none;
        }
        .feed-slide-content .author-meta { flex: 1; min-width: 0; }
        .feed-slide-content .author-meta b {
            font-size: 14px; font-weight: 700; color: var(--ink); display: block;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .feed-slide-content .author-meta span { font-size: 12px; color: var(--muted); }

        .carousel-nav {
            position: absolute; top: 50%; transform: translateY(-50%);
            width: 48px; height: 48px; border-radius: 50%;
            background: rgba(255,255,255,.95); color: var(--ink);
            border: none; cursor: pointer;
            display: grid; place-items: center;
            box-shadow: 0 8px 22px rgba(0,0,0,.3);
            transition: transform .2s, background .2s;
            z-index: 5;
        }
        .carousel-nav:hover { background: #fff; transform: translateY(-50%) scale(1.08); }
        .carousel-nav:active { transform: translateY(-50%) scale(.95); }
        .carousel-nav svg { width: 22px; height: 22px; }
        .carousel-nav.prev { left: 14px; }
        .carousel-nav.next { right: 14px; }

        .carousel-dots {
            position: absolute; bottom: 18px; left: 50%; transform: translateX(-50%);
            display: flex; gap: 8px; z-index: 5;
            padding: 7px 12px; background: rgba(0,0,0,.5); border-radius: 999px;
            backdrop-filter: blur(8px);
        }
        .carousel-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: rgba(255,255,255,.5); border: none; cursor: pointer;
            transition: background .2s, transform .2s;
            padding: 0;
        }
        .carousel-dot.active { background: var(--gold); transform: scale(1.35); }

        .carousel-progress {
            position: absolute; bottom: 0; left: 0; right: 0;
            height: 4px; background: rgba(0,0,0,.1);
            overflow: hidden; z-index: 5;
        }
        .carousel-progress-bar {
            height: 100%; width: 0;
            background: linear-gradient(90deg, var(--gold), var(--palm));
        }
        .carousel-progress-bar.animating {
            animation: progressFill var(--slide-duration, 7000ms) linear forwards;
        }
        @keyframes progressFill { from { width: 0; } to { width: 100%; } }

        .carousel-counter {
            position: absolute; top: 16px; right: 16px;
            background: rgba(0,0,0,.55); color: #fff;
            font-size: 12.5px; font-weight: 700;
            padding: 6px 13px; border-radius: 999px;
            backdrop-filter: blur(6px); z-index: 5;
            letter-spacing: .4px;
        }
        .carousel-pause {
            position: absolute; top: 16px; left: 16px;
            background: rgba(0,0,0,.55); color: #fff;
            border: none; cursor: pointer;
            width: 36px; height: 36px; border-radius: 50%;
            display: grid; place-items: center;
            z-index: 5; transition: background .2s;
            backdrop-filter: blur(6px);
        }
        .carousel-pause:hover { background: rgba(0,0,0,.75); }
        .carousel-pause svg { width: 16px; height: 16px; }

        .feed-empty {
            padding: 60px 24px; text-align: center;
            color: var(--muted); font-size: 14.5px; font-style: italic;
            background: rgba(255,253,247,.97);
            border-radius: 22px;
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
        }

        /* ============================================
           QUICK ACTIONS
           ============================================ */
        .quick-actions-panel {
            background: rgba(255,253,247,.97); color: var(--ink);
            border-radius: 22px; padding: clamp(22px, 3vw, 32px);
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
            margin-top: 4px;
        }
        .quick-actions-intro { text-align: center; margin-bottom: 26px; }
        .quick-actions-intro h2 {
            font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800;
            font-size: clamp(20px, 2.4vw, 24px); color: var(--teal);
            letter-spacing: -.4px;
        }
        .quick-actions-intro p { color: var(--muted); font-size: 14px; margin-top: 6px; }
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .quick-action {
            display: flex; align-items: center; gap: 16px;
            padding: 22px 20px;
            background: linear-gradient(135deg, #f5f8f9, #ffffff);
            border: 2px solid var(--line); border-radius: 18px;
            text-decoration: none; color: var(--ink);
            transition: all .25s cubic-bezier(.2,.8,.2,1);
            position: relative; overflow: hidden;
            min-height: 96px;
        }
        .quick-action::before {
            content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px;
            background: var(--teal); transform: scaleY(0); transform-origin: top;
            transition: transform .3s ease;
        }
        .quick-action:hover {
            border-color: var(--teal);
            transform: translateY(-4px);
            box-shadow: 0 15px 32px rgba(20,86,107,.18);
            background: #fff;
        }
        .quick-action:hover::before { transform: scaleY(1); }
        .quick-action:active { transform: translateY(-1px); }
        .quick-action .qa-icon {
            flex: none;
            width: 58px; height: 58px; border-radius: 16px;
            display: grid; place-items: center;
            background: linear-gradient(135deg, var(--teal), #0d3f50);
            color: #fff;
            box-shadow: 0 8px 18px rgba(20,86,107,.3);
            transition: transform .25s;
        }
        .quick-action .qa-icon svg { width: 26px; height: 26px; }
        .quick-action:hover .qa-icon { transform: scale(1.08) rotate(-3deg); }
        .quick-action .qa-text { flex: 1; min-width: 0; }
        .quick-action b {
            display: block;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 16.5px; font-weight: 800;
            color: var(--teal); margin-bottom: 3px;
            letter-spacing: -.2px;
        }
        .quick-action span { font-size: 13px; color: var(--muted); line-height: 1.45; display: block; }
        .quick-action .qa-arrow {
            flex: none; color: var(--muted);
            transition: transform .25s, color .25s;
        }
        .quick-action .qa-arrow svg { width: 20px; height: 20px; }
        .quick-action:hover .qa-arrow { transform: translateX(4px); color: var(--teal); }

        /* ============================================
           PROFILE
           ============================================ */
        .profile-panel {
            background: rgba(255,253,247,.97); color: var(--ink);
            border-radius: 22px; padding: clamp(22px, 3vw, 32px);
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
            margin-top: 24px;
        }
        .profile-panel-head {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 2px solid var(--line); padding-bottom: 14px; margin-bottom: 22px;
            gap: 12px; flex-wrap: wrap;
        }
        .profile-panel-head h2 {
            font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800;
            font-size: 20px; color: var(--teal);
            display: flex; align-items: center; gap: 10px;
        }
        .profile-panel-head h2 svg { width: 22px; height: 22px; }

        .profile-section-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 12px; font-weight: 800; color: var(--muted);
            text-transform: uppercase; letter-spacing: 1.2px;
            margin: 22px 0 12px;
            display: flex; align-items: center; gap: 8px;
        }
        .profile-section-title:first-of-type { margin-top: 0; }
        .profile-section-title::after {
            content: ""; flex: 1; height: 1px; background: var(--line);
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .profile-field {
            background: #f5f8f9;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 14px;
        }
        .profile-field.wide { grid-column: 1 / -1; }
        .profile-field .pf-label {
            font-size: 10.5px; font-weight: 800; color: var(--muted);
            text-transform: uppercase; letter-spacing: .7px; display: block;
            margin-bottom: 4px;
        }
        .profile-field .pf-value {
            font-size: 14.5px; font-weight: 600; color: var(--ink);
            word-break: break-word;
        }
        .pf-value.muted { color: var(--muted); font-weight: 500; font-style: italic; }
        .pf-badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 12px; font-weight: 800;
            padding: 3px 10px; border-radius: 999px;
        }
        .pf-badge.yes { background: rgba(47,138,87,.15); color: #1f6e41; }
        .pf-badge.no { background: #eef1f3; color: #5f717a; }

        /* ============================================
           FORM
           ============================================ */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-size: 13px; font-weight: 700; color: var(--muted); }
        .form-group input, .form-group select {
            padding: 12px 14px; border: 2px solid var(--line); border-radius: 12px;
            font-family: inherit; font-size: 14px; background: #f5f8f9;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--teal); background: #fff; box-shadow: 0 0 0 4px rgba(20,86,107,.12); }

        .checkbox-row {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 14px; background: #f5f8f9;
            border: 2px solid var(--line); border-radius: 12px;
            cursor: pointer; transition: border-color .2s, background .2s;
        }
        .checkbox-row:hover { border-color: var(--teal); background: #fff; }
        .checkbox-row input[type="checkbox"] {
            width: 20px; height: 20px; accent-color: var(--palm);
            cursor: pointer; flex: none;
        }
        .checkbox-row span { font-size: 14px; font-weight: 600; color: var(--ink); cursor: pointer; }

        .form-section-title {
            grid-column: 1 / -1;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 12px; font-weight: 800; color: var(--muted);
            text-transform: uppercase; letter-spacing: 1.2px;
            margin: 14px 0 2px;
            display: flex; align-items: center; gap: 8px;
        }
        .form-section-title:first-of-type { margin-top: 0; }
        .form-section-title::after { content: ""; flex: 1; height: 1px; background: var(--line); }

        .btn-primary {
            background: linear-gradient(135deg, var(--teal), #0d3f50); color: #fff;
            border: none; padding: 14px 28px; border-radius: 12px;
            font-size: 15px; font-weight: 800; cursor: pointer; margin-top: 20px;
            font-family: inherit;
            box-shadow: 0 10px 22px rgba(20,86,107,.35);
            transition: transform .2s, box-shadow .2s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(20,86,107,.45); }
        .btn-primary svg { width: 17px; height: 17px; }

        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 18px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .alert svg { width: 18px; height: 18px; }
        .alert-success { background: #d1e7dd; color: #0f5132; border: 1px solid #a3cfbb; }
        .alert-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid #f4c4c6; }

        @media (max-width: 620px) {
            .form-grid { grid-template-columns: 1fr; }
            .quick-action { padding: 18px 16px; min-height: 84px; }
            .quick-action .qa-icon { width: 50px; height: 50px; border-radius: 13px; }
            .quick-action .qa-icon svg { width: 22px; height: 22px; }
            .quick-action b { font-size: 15px; }
            .carousel-nav { width: 40px; height: 40px; }
            .carousel-nav.prev { left: 8px; }
            .carousel-nav.next { right: 8px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .train { transform: translateX(520px); }
        }
    </style>
</head>
<body> <!-- ============ SHARED SVG DEFS (Seal) ============ -->
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
        <a class="brand" href="citizen_dashboard.php">
            <span class="logo" role="img" aria-label="Barangay Bigaan seal"><svg viewBox="0 0 200 200" aria-hidden="true"><use href="#seal"/></svg></span>
            <span><b>E-Barangay</b><small>Citizen Service Portal</small></span>
        </a>
        <div class="nav-links">
            <a href="citizen_dashboard.php?view=dashboard" class="nav-item <?= $view_mode === 'dashboard' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                Dashboard
            </a>
            <a href="modules/documents.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>
                Documents
            </a>
            <a href="modules/complaints.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M5 8l7-5 7 5M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/></svg>
                Complaints
            </a>
            <a href="modules/treasury.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="13" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                Treasury
            </a>
            <a href="modules/facilities.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg>
                Facilities
            </a>
            <a href="modules/events.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Events
            </a>
            <a href="citizen_dashboard.php?view=edit_profile" class="nav-item <?= $view_mode === 'edit_profile' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Edit Profile
            </a>
            <span class="role-badge">Resident</span>
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
                <p>Barangay Citizen Service Portal</p>
            </div>
            <div class="welcome-date">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Ngayon ay <?= date('F j, Y') ?>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?>">
                <?php if ($msg_type === 'success'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="9 12 12 15 16 10"/></svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php endif; ?>
                <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($view_mode === 'edit_profile' || !$is_profile_completed): ?>
            <!-- PROFILE FORM VIEW -->
            <div class="profile-panel" style="margin-top:0;">
                <div class="profile-panel-head">
                    <h2>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Complete / Edit My Profile
                    </h2>
                </div>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-grid">
                        <div class="form-section-title">Personal Information</div>
                        <div class="form-group"><label>First Name</label><input type="text" name="first_name" value="<?= e($resident_info['first_name'] ?? '') ?>" required></div>
                        <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" value="<?= e($resident_info['middle_name'] ?? '') ?>"></div>
                        <div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="<?= e($resident_info['last_name'] ?? '') ?>" required></div>
                        <div class="form-group"><label>Suffix (Jr., Sr., III, etc.)</label><input type="text" name="suffix" value="<?= e($resident_info['suffix'] ?? '') ?>"></div>
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
                                <?php foreach (['Single','Married','Widowed','Separated','Annulled'] as $cs): ?>
                                    <option value="<?= $cs ?>" <?= ($resident_info['civil_status'] ?? '') === $cs ? 'selected' : '' ?>><?= $cs ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Occupation</label><input type="text" name="occupation" value="<?= e($resident_info['occupation'] ?? '') ?>"></div>

                        <div class="form-section-title">Contact & Address</div>
                        <div class="form-group"><label>Contact Number</label><input type="text" name="contact_number" value="<?= e($resident_info['contact_number'] ?? '') ?>" placeholder="09XX XXX XXXX"></div>
                        <div class="form-group"><label>Email Address</label><input type="email" name="email" value="<?= e($resident_info['email'] ?? '') ?>" placeholder="you@example.com"></div>
                        <div class="form-group">
                            <label>Purok / Zone</label>
                            <select name="purok_id" required>
                                <option value="">-- Select Purok --</option>
                                <?php foreach ($puroks as $p): ?>
                                    <option value="<?= $p['purok_id'] ?>" <?= ($resident_info['purok_id'] ?? '') == $p['purok_id'] ? 'selected' : '' ?>><?= e($p['purok_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group full"><label>Full Address</label><input type="text" name="address" value="<?= e($resident_info['address'] ?? '') ?>" required placeholder="House no., street, purok"></div>

                        <div class="form-section-title">Civic Information</div>
                        <div class="form-group">
                            <label>Voter Status</label>
                            <select name="voter_status">
                                <?php
                                    $current_voter = $resident_info['voter_status'] ?? 'Registered';
                                    foreach (['Registered', 'Not Registered', 'New Voter'] as $vs):
                                ?>
                                    <option value="<?= $vs ?>" <?= $current_voter === $vs ? 'selected' : '' ?>><?= $vs ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Special Classification</label><input type="text" name="special_classification" value="<?= e($resident_info['special_classification'] ?? '') ?>" placeholder="e.g., IP, 4Ps, OFW"></div>

                        <div class="form-section-title">Household & Sectoral Flags</div>
                        <label class="checkbox-row" style="grid-column:1/-1;">
                            <input type="checkbox" name="is_head_of_family" value="1" <?= !empty($resident_info['is_head_of_family']) ? 'checked' : '' ?>>
                            <span>I am the Head of the Family</span>
                        </label>
                        <label class="checkbox-row" style="grid-column:1/-1;">
                            <input type="checkbox" name="is_senior" value="1" <?= !empty($resident_info['is_senior']) ? 'checked' : '' ?>>
                            <span>Senior Citizen (60 years old and above)</span>
                        </label>
                        <label class="checkbox-row" style="grid-column:1/-1;">
                            <input type="checkbox" name="is_pwd" value="1" <?= !empty($resident_info['is_pwd']) ? 'checked' : '' ?>>
                            <span>Person with Disability (PWD)</span>
                        </label>
                        <div class="form-group full"><label>PWD ID Number (kung PWD)</label><input type="text" name="pwd_id_number" value="<?= e($resident_info['pwd_id_number'] ?? '') ?>" placeholder="PWD-XXXX-XXXX"></div>
                        <label class="checkbox-row" style="grid-column:1/-1;">
                            <input type="checkbox" name="is_solo_parent" value="1" <?= !empty($resident_info['is_solo_parent']) ? 'checked' : '' ?>>
                            <span>Solo Parent</span>
                        </label>
                        <div class="form-group full"><label>Solo Parent ID Number (kung Solo Parent)</label><input type="text" name="solo_parent_id_number" value="<?= e($resident_info['solo_parent_id_number'] ?? '') ?>" placeholder="SP-XXXX-XXXX"></div>
                        <label class="checkbox-row" style="grid-column:1/-1;">
                            <input type="checkbox" name="is_youth" value="1" <?= !empty($resident_info['is_youth']) ? 'checked' : '' ?>>
                            <span>Youth (15-30 years old)</span>
                        </label>
                    </div>
                    <button type="submit" class="btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Profile
                    </button>
                </form>
            </div>

        <?php else: ?>

            <!-- ============================================
                 1. ANNOUNCEMENTS (hiwalay sa Events)
                 ============================================ -->
            <div class="section-head">
                <h2>
                    <span class="head-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h3l4 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M16 8a4 4 0 0 1 0 8"/></svg>
                    </span>
                    Official Announcements
                </h2>
                <a href="modules/announcements.php" class="section-link">
                    Tingnan ang Lahat
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>

            <section class="announcement-panel">
                <div class="announcement-panel-header">
                    <h3>
                        <span class="bell" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        </span>
                        Latest Announcements
                    </h3>
                    <a href="modules/announcements.php" class="view-all">
                        Tingnan ang Lahat
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
                <div class="announcement-grid">
                    <?php if (empty($announcements)): ?>
                        <div class="announcement-empty">Walang opisyal na anunsyo sa ngayon.</div>
                    <?php else: ?>
                        <?php foreach ($announcements as $anc): ?>
                            <div class="announcement-item">
                                <div class="icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h3l4 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M16 8a4 4 0 0 1 0 8"/></svg>
                                </div>
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
                 1B. UPCOMING EVENTS (hiwalay)
                 ============================================ -->
            <div class="section-head">
                <h2>
                    <span class="head-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </span>
                    Upcoming Events
                </h2>
                <a href="modules/events.php" class="section-link">
                    Tingnan ang Lahat
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>

            <?php if (empty($upcoming_events)): ?>
                <div class="events-empty">Walang naka-schedule na event sa ngayon.</div>
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
                                <?php if (!empty($ev['location'])): ?>
                                    <span class="event-location">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        <?= e($ev['location']) ?>
                                    </span>
                                <?php endif; ?>
                                <p class="event-desc"><?= nl2br(e(mb_substr($ev['description'], 0, 140))) ?><?= mb_strlen($ev['description']) > 140 ? '...' : '' ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <!-- ============================================
                 2. AUTO-SLIDING COMMUNITY FEED
                 ============================================ -->
            <div class="section-head">
                <h2>
                    <span class="head-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </span>
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
                 3. QUICK NAVIGATION
                 ============================================ -->
            <div class="section-head">
                <h2>
                    <span class="head-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    </span>
                    Quick Navigation
                </h2>
            </div>

            <section class="quick-actions-panel">
                <div class="quick-actions-intro">
                    <h2>Citizen Online Services</h2>
                    <p>Pindutin ang kahon para mag-request ng serbisyo.</p>
                </div>
                <div class="quick-actions-grid">
                    <a href="modules/documents.php" class="quick-action">
                        <span class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></span>
                        <span class="qa-text"><b>Document Requests</b><span>Request Indigency & Clearance</span></span>
                        <span class="qa-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                    </a>
                    <a href="modules/complaints.php" class="quick-action">
                        <span class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M5 8l7-5 7 5M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/></svg></span>
                        <span class="qa-text"><b>Complaints & Blotter</b><span>Report Citizen Incidents</span></span>
                        <span class="qa-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                    </a>
                    <a href="modules/treasury.php" class="quick-action">
                        <span class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="13" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><circle cx="17" cy="15" r="1.2"/></svg></span>
                        <span class="qa-text"><b>Treasury & Revenue</b><span>Transparency Financial Logs</span></span>
                        <span class="qa-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                    </a>
                    <a href="modules/facilities.php" class="quick-action">
                        <span class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg></span>
                        <span class="qa-text"><b>Facilities Reservation</b><span>Reserve Barangay Hall / Gym</span></span>
                        <span class="qa-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                    </a>
                    <a href="modules/events.php" class="quick-action">
                        <span class="qa-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                        <span class="qa-text"><b>Brgy Events</b><span>Upcoming Activities and Events</span></span>
                        <span class="qa-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                    </a>
                </div>
            </section>

            <!-- ============================================
                 4. PERSONAL PROFILE RECORD
                 ============================================ -->
            <div class="section-head">
                <h2>
                    <span class="head-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    Personal Profile Record
                </h2>
                <a href="citizen_dashboard.php?view=edit_profile" class="section-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    Edit Profile
                </a>
            </div>

            <section class="profile-panel" style="margin-top:0;">
                <div class="profile-section-title">Personal Information</div>
                <div class="profile-grid">
                    <div class="profile-field"><span class="pf-label">First Name</span><span class="pf-value"><?= e($resident_info['first_name'] ?? '—') ?></span></div>
                    <div class="profile-field"><span class="pf-label">Middle Name</span><span class="pf-value <?= empty($resident_info['middle_name']) ? 'muted' : '' ?>"><?= e($resident_info['middle_name'] ?? 'Walang nakalagay') ?></span></div>
                    <div class="profile-field"><span class="pf-label">Last Name</span><span class="pf-value"><?= e($resident_info['last_name'] ?? '—') ?></span></div>
                    <div class="profile-field"><span class="pf-label">Suffix</span><span class="pf-value <?= empty($resident_info['suffix']) ? 'muted' : '' ?>"><?= e($resident_info['suffix'] ?? 'Wala') ?></span></div>
                    <div class="profile-field"><span class="pf-label">Birthdate</span><span class="pf-value"><?= !empty($resident_info['birthdate']) ? date('F j, Y', strtotime($resident_info['birthdate'])) : '—' ?></span></div>
                    <div class="profile-field"><span class="pf-label">Gender</span><span class="pf-value"><?= e($resident_info['gender'] ?? '—') ?></span></div>
                    <div class="profile-field"><span class="pf-label">Civil Status</span><span class="pf-value"><?= e($resident_info['civil_status'] ?? '—') ?></span></div>
                    <div class="profile-field"><span class="pf-label">Occupation</span><span class="pf-value <?= empty($resident_info['occupation']) ? 'muted' : '' ?>"><?= e($resident_info['occupation'] ?? 'Walang nakalagay') ?></span></div>
                </div>

                <div class="profile-section-title">Contact & Address</div>
                <div class="profile-grid">
                    <div class="profile-field"><span class="pf-label">Contact Number</span><span class="pf-value <?= empty($resident_info['contact_number']) ? 'muted' : '' ?>"><?= e($resident_info['contact_number'] ?? 'Walang nakalagay') ?></span></div>
                    <div class="profile-field"><span class="pf-label">Email</span><span class="pf-value <?= empty($resident_info['email']) ? 'muted' : '' ?>"><?= e($resident_info['email'] ?? 'Walang nakalagay') ?></span></div>
                    <div class="profile-field"><span class="pf-label">Purok</span><span class="pf-value"><?= e($resident_info['purok_name'] ?? 'Hindi pa naka-assign') ?></span></div>
                    <div class="profile-field"><span class="pf-label">Purok ID</span><span class="pf-value"><?= e($resident_info['purok_id'] ?? '—') ?></span></div>
                    <div class="profile-field wide"><span class="pf-label">Full Address</span><span class="pf-value"><?= e($resident_info['address'] ?? '—') ?></span></div>
                </div>

                <div class="profile-section-title">Civic Information</div>
                <div class="profile-grid">
                    <div class="profile-field"><span class="pf-label">Voter Status</span><span class="pf-value <?= empty($resident_info['voter_status']) ? 'muted' : '' ?>"><?= e($resident_info['voter_status'] ?? 'Walang nakalagay') ?></span></div>
                    <div class="profile-field wide"><span class="pf-label">Special Classification</span><span class="pf-value <?= empty($resident_info['special_classification']) ? 'muted' : '' ?>"><?= e($resident_info['special_classification'] ?? 'Walang nakalagay') ?></span></div>
                </div>

                <div class="profile-section-title">Household & Sectoral Flags</div>
                <div class="profile-grid">
                    <div class="profile-field"><span class="pf-label">Head of Family</span><span class="pf-badge <?= !empty($resident_info['is_head_of_family']) ? 'yes' : 'no' ?>"><?= yesno($resident_info['is_head_of_family'] ?? 0) ?></span></div>
                    <div class="profile-field"><span class="pf-label">Senior Citizen</span><span class="pf-badge <?= !empty($resident_info['is_senior']) ? 'yes' : 'no' ?>"><?= yesno($resident_info['is_senior'] ?? 0) ?></span></div>
                    <div class="profile-field"><span class="pf-label">Person with Disability</span><span class="pf-badge <?= !empty($resident_info['is_pwd']) ? 'yes' : 'no' ?>"><?= yesno($resident_info['is_pwd'] ?? 0) ?></span></div>
                    <div class="profile-field"><span class="pf-label">PWD ID Number</span><span class="pf-value <?= empty($resident_info['pwd_id_number']) ? 'muted' : '' ?>"><?= e($resident_info['pwd_id_number'] ?? 'Walang nakalagay') ?></span></div>
                    <div class="profile-field"><span class="pf-label">Solo Parent</span><span class="pf-badge <?= !empty($resident_info['is_solo_parent']) ? 'yes' : 'no' ?>"><?= yesno($resident_info['is_solo_parent'] ?? 0) ?></span></div>
                    <div class="profile-field"><span class="pf-label">Solo Parent ID Number</span><span class="pf-value <?= empty($resident_info['solo_parent_id_number']) ? 'muted' : '' ?>"><?= e($resident_info['solo_parent_id_number'] ?? 'Walang nakalagay') ?></span></div>
                    <div class="profile-field"><span class="pf-label">Youth</span><span class="pf-badge <?= !empty($resident_info['is_youth']) ? 'yes' : 'no' ?>"><?= yesno($resident_info['is_youth'] ?? 0) ?></span></div>
                </div>
            </section>

        <?php endif; ?>
    </main>
</div>
<script>
(function () {
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
            if (toggleLink) {
                toggleLink.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21"/></svg> Play Slideshow';
            }
        } else {
            if (pauseIcon) pauseIcon.style.display = 'block';
            if (playIcon) playIcon.style.display = 'none';
            restartProgress();
            restartTimer();
            if (toggleLink) {
                toggleLink.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg> Pause Slideshow';
            }
        }
    }

    if (prevBtn) prevBtn.addEventListener('click', function () { goTo(index - 1, true); });
    if (nextBtn) nextBtn.addEventListener('click', function () { goTo(index + 1, true); });
    if (pauseBtn) pauseBtn.addEventListener('click', function () { setPaused(!isPaused); });
    if (toggleLink) toggleLink.addEventListener('click', function (e) { e.preventDefault(); setPaused(!isPaused); });

    carousel.addEventListener('mouseenter', function () {
        if (!isPaused) {
            if (timer) clearTimeout(timer);
            if (progress) progress.classList.remove('animating');
        }
    });
    carousel.addEventListener('mouseleave', function () {
        if (!isPaused) {
            restartProgress();
            restartTimer();
        }
    });

    var startX = 0, startY = 0, isSwiping = false;
    carousel.addEventListener('touchstart', function (e) {
        var t = e.touches[0];
        startX = t.clientX; startY = t.clientY; isSwiping = true;
    }, { passive: true });
    carousel.addEventListener('touchend', function (e) {
        if (!isSwiping) return;
        isSwiping = false;
        var t = e.changedTouches[0];
        var dx = t.clientX - startX;
        var dy = t.clientY - startY;
        if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
            goTo(dx < 0 ? index + 1 : index - 1, true);
        }
    }, { passive: true });

    carousel.setAttribute('tabindex', '0');
    carousel.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(index - 1, true); }
        if (e.key === 'ArrowRight') { e.preventDefault(); goTo(index + 1, true); }
        if (e.key === ' ') { e.preventDefault(); setPaused(!isPaused); }
    });

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        setPaused(true);
    } else {
        goTo(0, false);
        restartTimer();
    }
})();
</script>

</body>
</html>