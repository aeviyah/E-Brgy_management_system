<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

/* Already signed in? Skip the form. */
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'Resident' ? 'citizen_dashboard.php' : 'dashboard.php'));
    exit();
}

/* CSRF token */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error         = '';
$username      = '';
$selected_type = 'Resident';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username      = trim($_POST['username'] ?? '');
    $password      = trim($_POST['password'] ?? '');
    $selected_type = ($_POST['login_role'] ?? 'Resident') === 'Official' ? 'Official' : 'Resident';
    $token         = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $token)) {
        $error = "Your session expired. Please try signing in again.";
    } elseif ($username === '' || $password === '') {
        $error = "Please enter your username and password.";
    } else {
        if ($selected_type === 'Resident') {
            $stmt = $conn->prepare("SELECT user_id, username, password, role, position, status FROM users WHERE username = ? AND role = 'Resident'");
        } else {
            $stmt = $conn->prepare("SELECT user_id, username, password, role, position, status FROM users WHERE username = ? AND role != 'Resident'");
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        $valid = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $valid = true;
            } elseif (hash_equals((string)$user['password'], $password)) {
                /* Legacy plain-text password: accept once, then upgrade it to a secure hash. */
                $valid = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $up = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $up->bind_param("ss", $newHash, $user['user_id']);
                $up->execute();
                $up->close();
            }
        }

        if (!$valid) {
            $error = "Wrong username, password, or portal. Check that you picked the right portal above.";
        } elseif ($user['status'] !== 'Active') {
            $error = "Your account is inactive. Please contact your barangay administrator.";
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['position'] = $user['position'];
            $_SESSION['csrf']     = bin2hex(random_bytes(32));

            header('Location: ' . ($user['role'] === 'Resident' ? 'citizen_dashboard.php' : 'dashboard.php'));
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · E-Barangay Bigaan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --night: #0c2733;
            --teal: #14566b;
            --palm: #2f8a57;
            --gold: #f2b84b;
            --gold-deep: #c98a1c;
            --bark: #5a3a28;
            --paper: #fffdf7;
            --ink: #16262e;
            --muted: #5f717a;
            --line: #d9e1e4;
            --danger: #a4262c;
            --danger-bg: #fdecec;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 84px; }
        body { min-height: 100vh; }
        body {
            font-family: 'Figtree', system-ui, sans-serif;
            color: #fff;
            background: var(--night);
            overflow-x: clip;
        }
        :focus-visible { outline: 3px solid var(--gold); outline-offset: 2px; }

        /* ============ ANIMATED SCENE ============ */
        .scene {
            position: fixed; inset: 0; z-index: 0;
            background: linear-gradient(180deg, #0c2733 0%, #14566b 38%, #e9a85c 78%, #f7cf86 100%);
        }
        .scene svg { position: absolute; inset: 0; width: 100%; height: 100%; }
        .scene::after { /* readability veil on the text side */
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(90deg, rgba(8,24,33,.72) 0%, rgba(8,24,33,.35) 45%, rgba(8,24,33,0) 75%);
        }
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

        /* ============ LAYOUT ============ */
        .page { position: relative; z-index: 2; min-height: 100%; display: flex; flex-direction: column; }

        .nav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px clamp(20px, 5vw, 64px);
            position: sticky; top: 0; z-index: 50;
            background: rgba(8,24,33,.55); backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,.08);
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
        .brand .logo { width: 52px; height: 52px; flex: none; box-shadow: 0 0 0 3px rgba(255,255,255,.95), 0 6px 16px rgba(0,0,0,.35); }
        .brand b { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: 18px; letter-spacing: .2px; line-height: 1.1; display: block; }
        .brand small { font-size: 12px; color: #cfe3ea; }
        .nav-links { display: flex; align-items: center; gap: 6px; list-style: none; }
        .nav-links a {
            color: #e8f2f5; text-decoration: none; font-weight: 600; font-size: 14px;
            padding: 9px 14px; border-radius: 999px; transition: background .2s;
        }
        .nav-links a:hover { background: rgba(255,255,255,.14); }
        .nav-links a.active:not(.cta) { background: rgba(255,255,255,.2); color: #fff; }
        .nav-links a.cta { background: var(--gold); color: #3a2605; }
        .nav-links a.cta:hover { background: #ffc965; }

        .hero {
            min-height: calc(100vh - 72px); width: 100%; max-width: 1240px; margin: 0 auto;
            padding: 20px clamp(20px, 5vw, 48px) 60px;
            display: grid; grid-template-columns: 1.1fr .9fr; gap: clamp(28px, 5vw, 80px);
            align-items: center;
        }

        /* ---- Left ---- */
        .seal-wrap { position: relative; width: 168px; height: 168px; margin-bottom: 32px; animation: floaty 7s ease-in-out infinite; }
        @keyframes floaty { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        .seal-wrap .logo { position: relative; width: 100%; height: 100%; box-shadow: 0 0 0 5px rgba(255,255,255,.96), 0 0 0 6px rgba(201,138,28,.9), 0 16px 40px rgba(0,0,0,.5); }
        .seal-wrap::before {
            content: ""; position: absolute; inset: -16px; border-radius: 50%;
            border: 2px dashed rgba(242,184,75,.85);
            animation: spin 40s linear infinite;
        }
        .seal-wrap::after {
            content: ""; position: absolute; inset: -34px; border-radius: 50%;
            background: radial-gradient(circle, rgba(242,184,75,.35), transparent 65%);
            z-index: -1; animation: breathe 5s ease-in-out infinite;
        }
        .place { font-weight: 600; font-size: 15px; color: #ffe3a6; margin-bottom: 10px; }
        h1 {
            font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800;
            font-size: clamp(38px, 5.2vw, 66px); line-height: 1.04; letter-spacing: -1.2px;
            max-width: 12ch; text-shadow: 0 3px 24px rgba(0,0,0,.35);
        }
        .lede { margin-top: 20px; max-width: 46ch; font-size: 17px; line-height: 1.65; color: #e3eef2; }

        .perks { margin-top: 32px; display: grid; gap: 12px; max-width: 460px; }
        .perk {
            display: flex; gap: 14px; align-items: center; padding: 13px 16px;
            background: rgba(10,30,42,.82); border: 1px solid rgba(255,255,255,.14);
            border-radius: 14px; backdrop-filter: blur(8px);
            transition: transform .25s ease, border-color .25s ease;
        }
        .perk:hover { transform: translateX(6px); border-color: var(--gold); }
        .perk svg { flex: none; width: 40px; height: 40px; padding: 9px; border-radius: 11px; background: rgba(242,184,75,.18); color: var(--gold); }
        .perk b { display: block; font-size: 15px; }
        .perk span { font-size: 13.5px; color: #c3d6dd; }

        /* ---- Card ---- */
        .card {
            background: rgba(255,253,247,.97); color: var(--ink);
            border-radius: 26px; padding: 36px 34px 30px;
            box-shadow: 0 30px 70px rgba(4,16,24,.5), 0 0 0 1px rgba(255,255,255,.5) inset;
            position: relative; max-width: 460px; width: 100%; justify-self: end;
        }
        .card::before { /* gold rail along the top, nod to the railway in the seal */
            content: ""; position: absolute; top: 0; left: 34px; right: 34px; height: 5px;
            border-radius: 0 0 6px 6px;
            background: repeating-linear-gradient(90deg, var(--gold) 0 14px, transparent 14px 20px);
        }
        .card h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 30px; font-weight: 800; letter-spacing: -.5px; }
        .card .sub { margin: 4px 0 22px; color: var(--muted); font-size: 14.5px; min-height: 22px; }

        .seg {
            position: relative; display: grid; grid-template-columns: 1fr 1fr;
            background: #eaf0f2; border-radius: 14px; padding: 4px; margin-bottom: 20px;
        }
        .seg input { position: absolute; opacity: 0; pointer-events: none; }
        .seg label {
            position: relative; z-index: 1; text-align: center; padding: 11px 6px;
            font-weight: 700; font-size: 14px; color: var(--muted); cursor: pointer;
            transition: color .25s;
        }
        .seg input:checked + label { color: #fff; }
        .seg input:focus-visible + label { outline: 3px solid var(--gold); outline-offset: 1px; border-radius: 10px; }
        .seg .thumb {
            position: absolute; top: 4px; bottom: 4px; left: 4px; width: calc(50% - 4px);
            background: linear-gradient(135deg, var(--teal), #0d3f50); border-radius: 11px;
            box-shadow: 0 6px 14px rgba(20,86,107,.35);
            transition: transform .35s cubic-bezier(.3,1.3,.5,1);
        }
        #r-official:checked ~ .thumb { transform: translateX(100%); }

        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 13.5px; font-weight: 700; margin-bottom: 7px; color: #2b3e47; }
        .input { position: relative; }
        .input > svg.lead {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            width: 19px; height: 19px; color: #8a9aa2; pointer-events: none; transition: color .2s;
        }
        .input input {
            width: 100%; padding: 14px 46px 14px 44px; font: inherit; font-size: 15px; color: var(--ink);
            background: #f5f8f9; border: 1.5px solid var(--line); border-radius: 12px; outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .input input:focus { border-color: var(--teal); background: #fff; box-shadow: 0 0 0 4px rgba(20,86,107,.14); }
        .input:focus-within > svg.lead { color: var(--teal); }
        .eye {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            width: 38px; height: 38px; border: 0; background: transparent; border-radius: 10px;
            display: grid; place-items: center; cursor: pointer; color: #6c7f88;
        }
        .eye:hover { background: #e6edf0; }
        .eye svg { width: 20px; height: 20px; }
        .caps { display: none; margin-top: 7px; font-size: 12.5px; font-weight: 600; color: var(--gold-deep); }
        .caps.on { display: block; }

        .btn {
            position: relative; width: 100%; margin-top: 6px; padding: 15px; border: 0; border-radius: 13px;
            font: inherit; font-weight: 800; font-size: 16px; color: #fff; cursor: pointer; overflow: hidden;
            background: linear-gradient(135deg, var(--palm), #1f6e41);
            box-shadow: 0 10px 22px rgba(47,138,87,.38);
            transition: transform .2s, box-shadow .2s;
        }
        .btn::after { /* light sweep */
            content: ""; position: absolute; top: 0; bottom: 0; width: 60px; left: -90px;
            background: linear-gradient(100deg, transparent, rgba(255,255,255,.35), transparent);
            transform: skewX(-20deg); animation: sweep 4.5s ease-in-out infinite;
        }
        @keyframes sweep { 0%, 55% { left: -90px; } 100% { left: 120%; } }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 14px 26px rgba(47,138,87,.45); }
        .btn:active { transform: translateY(0); }
        .btn .spin { display: none; width: 18px; height: 18px; border: 3px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; margin: 0 auto; }
        .btn.loading span { display: none; }
        .btn.loading .spin { display: block; }
        .btn.loading { pointer-events: none; opacity: .9; }

        .alert {
            display: flex; gap: 10px; align-items: flex-start;
            background: var(--danger-bg); color: var(--danger); border: 1px solid #f4c4c6;
            padding: 12px 14px; border-radius: 12px; font-size: 14px; font-weight: 600; line-height: 1.45;
            margin-bottom: 18px; animation: shake .5s;
        }
        .alert svg { flex: none; width: 20px; height: 20px; margin-top: 1px; }
        @keyframes shake { 15%{transform:translateX(-7px)} 30%{transform:translateX(6px)} 45%{transform:translateX(-5px)} 60%{transform:translateX(4px)} 75%{transform:translateX(-2px)} }

        .foot { margin-top: 20px; text-align: center; font-size: 14px; color: var(--muted); }
        .foot a { color: var(--teal); font-weight: 700; text-decoration: none; }
        .foot a:hover { text-decoration: underline; }


        /* ---- Extra sections ---- */
        .section { width: 100%; max-width: 1240px; margin: 0 auto; padding: 70px clamp(20px, 5vw, 48px); }
        .section .panel {
            background: rgba(9,28,39,.82); border: 1px solid rgba(255,255,255,.14);
            border-radius: 26px; padding: clamp(24px, 4vw, 46px); backdrop-filter: blur(14px);
            box-shadow: 0 24px 60px rgba(0,0,0,.35);
        }
        .section h3 { font-family: 'Bricolage Grotesque', sans-serif; font-size: clamp(28px, 3.6vw, 40px); font-weight: 800; letter-spacing: -.8px; }
        .section .intro-text { margin-top: 10px; color: #c9dbe2; max-width: 60ch; line-height: 1.65; }
        .svc-grid { margin-top: 28px; display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; }
        .svc {
            background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 18px;
            padding: 22px; display: flex; flex-direction: column; gap: 8px;
            transition: transform .25s ease, border-color .25s ease, background .25s ease;
        }
        .svc:hover { transform: translateY(-5px); border-color: var(--gold); background: rgba(255,255,255,.1); }
        .svc svg { width: 44px; height: 44px; padding: 10px; border-radius: 12px; background: rgba(242,184,75,.18); color: var(--gold); }
        .svc b { font-size: 17px; margin-top: 6px; }
        .svc span { color: #bcd0d8; font-size: 14.5px; line-height: 1.55; flex: 1; }
        .svc a { margin-top: 10px; color: var(--gold); font-weight: 700; font-size: 14px; text-decoration: none; }
        .svc a:hover { text-decoration: underline; }
        .about-grid { display: grid; grid-template-columns: 220px 1fr; gap: clamp(22px, 4vw, 48px); align-items: center; }
        .about-grid .logo { width: 220px; height: 220px; box-shadow: 0 14px 40px rgba(0,0,0,.5), 0 0 0 4px rgba(242,184,75,.5); }
        .about-points { margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
        .about-points div { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 14px; padding: 14px 16px; }
        .about-points b { display: block; color: var(--gold); margin-bottom: 3px; }
        .about-points span { font-size: 14px; color: #c3d6dd; }
        .site-foot { text-align: center; padding: 26px 20px 40px; font-size: 13.5px; color: #cfe0e6; }
        @media (max-width: 760px) { .about-grid { grid-template-columns: 1fr; justify-items: start; } .about-grid .logo { width: 140px; height: 140px; } }

        /* ---- One page-load sequence ---- */
        .intro { opacity: 0; transform: translateY(18px); animation: rise .8s cubic-bezier(.2,.8,.2,1) forwards; }
        .i1 { animation-delay: .1s; } .i2 { animation-delay: .25s; } .i3 { animation-delay: .4s; } .i4 { animation-delay: .55s; } .i5 { animation-delay: .7s; }
        @keyframes rise { to { opacity: 1; transform: none; } }

        /* ---- Responsive ---- */
        @media (max-width: 920px) {
            .hero { grid-template-columns: 1fr; padding-top: 6px; }
            .card { justify-self: center; }
            .perks { display: none; }
            .seal-wrap { width: 112px; height: 112px; margin-bottom: 26px; }
            h1 { max-width: 16ch; }
            .scene::after { background: linear-gradient(180deg, rgba(8,24,33,.55), rgba(8,24,33,.2)); }
        }
        @media (max-width: 560px) {
            .nav { flex-wrap: wrap; gap: 8px; }
            .brand small { display: none; }
            .nav-links a { padding: 8px 10px; font-size: 13px; }
            .card { padding: 30px 22px 24px; border-radius: 22px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .intro { opacity: 1; transform: none; }
            .train { transform: translateX(520px); }
        }
    </style>
</head>
<body>
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
<path d="M44,128 C44,118 45,110 46,102" fill="none" stroke="#6b4426" stroke-width="2.0" stroke-linecap="round"/><g transform="translate(46 102) scale(0.42)"><g transform="rotate(-172)" fill="#2f7d3f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-140)" fill="#4aa457"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-108)" fill="#2a6f38"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-72)" fill="#56b45f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-40)" fill="#2f7d3f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-10)" fill="#4aa457"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(20)" fill="#2a6f38"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g></g><path d="M83,128 C83,122 84,117 84,112" fill="none" stroke="#6b4426" stroke-width="1.8" stroke-linecap="round"/><g transform="translate(84 112) scale(0.36)"><g transform="rotate(-172)" fill="#2f7d3f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-140)" fill="#4aa457"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-108)" fill="#2a6f38"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-72)" fill="#56b45f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-40)" fill="#2f7d3f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-10)" fill="#4aa457"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(20)" fill="#2a6f38"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g></g>
<path d="M103.4,168.8 L102.4,167.5 L101.6,166.3 L100.8,165.0 L100.1,163.8 L99.4,162.5 L98.9,161.2 L98.4,160.0 L97.9,158.6 L97.5,157.3 L97.2,155.9 L97.0,154.4 L96.7,152.9 L96.6,151.4 L96.5,149.8 L96.5,148.2 L96.5,146.4 L96.6,144.7 L96.7,142.9 L96.9,141.0 L97.2,139.1 L97.4,137.2 L97.8,135.2 L98.1,133.2 L98.6,131.1 L99.0,129.0 L99.5,126.8 L100.0,124.6 L100.5,122.4 L101.1,120.1 L101.6,117.8 L102.2,115.4 L102.7,113.0 L103.3,110.6 L103.9,108.1 L104.4,105.6 L105.0,103.1 L105.5,100.5 L106.0,97.9 L106.5,95.2 L106.9,92.5 L107.3,89.8 L107.7,87.1 L108.0,84.3 L108.3,81.4 L108.5,78.6 L108.7,75.7 L108.8,72.7 L108.9,69.8 L108.8,66.7 L108.7,63.7 L108.6,60.6 L108.3,57.5 L108.0,54.3 L107.5,51.2 L107.0,47.9 L106.4,44.7 L105.7,41.4 L104.9,38.0 L103.9,34.6 L102.9,31.2 L97.1,32.8 L97.8,36.1 L98.3,39.4 L98.7,42.6 L99.1,45.8 L99.3,48.9 L99.5,51.9 L99.5,54.9 L99.5,57.8 L99.4,60.7 L99.2,63.6 L99.0,66.4 L98.7,69.1 L98.3,71.8 L97.9,74.5 L97.4,77.1 L96.9,79.7 L96.3,82.3 L95.6,84.8 L95.0,87.2 L94.3,89.7 L93.5,92.1 L92.7,94.5 L91.9,96.8 L91.1,99.2 L90.3,101.5 L89.4,103.8 L88.5,106.0 L87.7,108.3 L86.8,110.5 L85.9,112.7 L85.0,115.0 L84.1,117.1 L83.2,119.3 L82.4,121.5 L81.5,123.7 L80.7,125.9 L79.9,128.1 L79.1,130.2 L78.4,132.4 L77.7,134.6 L77.0,136.9 L76.4,139.1 L75.8,141.3 L75.3,143.6 L74.8,145.9 L74.5,148.2 L74.2,150.6 L73.9,153.0 L73.8,155.4 L73.8,157.8 L73.9,160.3 L74.1,162.8 L74.4,165.4 L74.9,167.9 L75.5,170.5 L76.2,173.0 L77.1,175.6 L78.1,178.1 L79.3,180.7 L80.6,183.2Z" fill="#f7eddc"/><path d="M101.5,164.2 L75.8,175.1" stroke="#b5633a" stroke-width="2.98" stroke-linecap="round"/><path d="M98.2,156.0 L72.9,158.3" stroke="#b5633a" stroke-width="2.74" stroke-linecap="round"/><path d="M97.3,146.6 L74.4,143.5" stroke="#b5633a" stroke-width="2.51" stroke-linecap="round"/><path d="M98.5,135.8 L78.2,130.5" stroke="#b5633a" stroke-width="2.30" stroke-linecap="round"/><path d="M100.8,124.2 L82.8,118.5" stroke="#b5633a" stroke-width="2.10" stroke-linecap="round"/><path d="M103.6,112.2 L87.4,107.2" stroke="#b5633a" stroke-width="1.91" stroke-linecap="round"/><path d="M106.1,100.2 L91.5,96.3" stroke="#b5633a" stroke-width="1.74" stroke-linecap="round"/><path d="M108.0,88.4 L94.8,85.8" stroke="#b5633a" stroke-width="1.58" stroke-linecap="round"/><path d="M109.1,77.1 L97.2,75.7" stroke="#b5633a" stroke-width="1.43" stroke-linecap="round"/><path d="M109.2,66.5 L98.6,66.1" stroke="#b5633a" stroke-width="1.30" stroke-linecap="round"/><path d="M108.6,56.7 L99.2,57.1" stroke="#b5633a" stroke-width="1.18" stroke-linecap="round"/><path d="M107.4,48.1 L99.0,49.1" stroke="#b5633a" stroke-width="1.09" stroke-linecap="round"/><path d="M105.8,40.8 L98.4,42.2" stroke="#b5633a" stroke-width="1.00" stroke-linecap="round"/><path d="M104.4,35.3 L97.6,36.8" stroke="#b5633a" stroke-width="0.94" stroke-linecap="round"/><path d="M103.3,31.8 L97.0,33.4" stroke="#b5633a" stroke-width="0.91" stroke-linecap="round"/><path d="M99.1,171.5 L98.0,170.0 L97.1,168.5 L96.3,167.0 L95.5,165.5 L94.9,164.0 L94.3,162.5 L93.8,161.0 L93.4,159.4 L93.0,157.9 L92.8,156.2 L92.6,154.6 L92.4,152.9 L92.3,151.2 L92.3,149.5 L92.4,147.7 L92.5,145.9 L92.6,144.1 L92.9,142.2 L93.1,140.2 L93.4,138.3 L93.8,136.3 L94.2,134.3 L94.7,132.2 L95.2,130.1 L95.7,128.0 L96.2,125.8 L96.8,123.6 L97.4,121.4 L98.0,119.1 L98.6,116.8 L99.3,114.5 L99.9,112.1 L100.5,109.7 L101.1,107.3 L101.7,104.8 L102.3,102.3 L102.9,99.8 L103.5,97.2 L104.0,94.6 L104.5,92.0 L105.0,89.3 L105.4,86.6 L105.8,83.9 L106.1,81.1 L106.4,78.3 L106.7,75.4 L106.8,72.6 L106.9,69.6 L107.0,66.7 L106.9,63.7 L106.8,60.6 L106.6,57.6 L106.4,54.4 L106.0,51.3 L105.6,48.1 L105.0,44.9 L104.4,41.6 L103.6,38.3 L102.8,34.9 L101.8,31.5" fill="none" stroke="#8a8f98" stroke-width="1.6" stroke-linecap="round"/><path d="M84.9,180.5 L83.7,178.2 L82.6,175.9 L81.6,173.6 L80.7,171.3 L80.0,168.9 L79.4,166.6 L79.0,164.3 L78.6,162.0 L78.4,159.7 L78.3,157.5 L78.2,155.2 L78.3,153.0 L78.4,150.7 L78.6,148.5 L78.9,146.3 L79.3,144.2 L79.7,142.0 L80.2,139.8 L80.8,137.6 L81.4,135.5 L82.0,133.3 L82.7,131.2 L83.4,129.0 L84.1,126.9 L84.9,124.7 L85.6,122.5 L86.4,120.3 L87.2,118.1 L88.1,115.9 L88.9,113.7 L89.7,111.5 L90.5,109.2 L91.4,106.9 L92.2,104.6 L93.0,102.3 L93.7,99.9 L94.5,97.5 L95.3,95.1 L96.0,92.7 L96.7,90.2 L97.3,87.7 L97.9,85.2 L98.5,82.6 L99.0,80.0 L99.5,77.4 L99.9,74.7 L100.3,72.0 L100.6,69.2 L100.9,66.4 L101.0,63.6 L101.2,60.7 L101.2,57.8 L101.1,54.8 L101.0,51.8 L100.8,48.7 L100.5,45.5 L100.1,42.4 L99.5,39.1 L98.9,35.8 L98.2,32.5" fill="none" stroke="#8a8f98" stroke-width="1.6" stroke-linecap="round"/>
<path d="M57,160 C61,132 56,102 66,74" fill="none" stroke="#6b4426" stroke-width="4.6" stroke-linecap="round"/><g transform="translate(66 74) scale(1.0)"><g transform="rotate(-172)" fill="#2f7d3f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-140)" fill="#4aa457"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-108)" fill="#2a6f38"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-72)" fill="#56b45f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-40)" fill="#2f7d3f"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(-10)" fill="#4aa457"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g><g transform="rotate(20)" fill="#2a6f38"><path d="M0 0 C7 -9 22 -10 34 2 C22 -3 9 -2 0 0Z"/></g></g><path d="M55.6,158 C59.5,132 54.6,102 64.4,76" fill="none" stroke="#8d6038" stroke-width="1.2" stroke-dasharray="2 4" stroke-linecap="round"/><circle cx="62" cy="79" r="3.7" fill="#7c4f2c"/><circle cx="70" cy="80" r="3.7" fill="#8a5a33"/><circle cx="66" cy="85" r="3.5" fill="#6a4125"/>
<g transform="translate(56 140) rotate(-16) scale(.88)"><ellipse rx="15" ry="11" fill="#7b4a2b"/><ellipse rx="15" ry="11" fill="none" stroke="#5b3520" stroke-width="1.2"/><ellipse cx="1" cy="-1" rx="11" ry="7.6" fill="#fdf0e2"/><ellipse cx="3" cy="-2" rx="6" ry="3.6" fill="#f3cfcf"/></g><g transform="translate(79 147) rotate(14) scale(.88)"><ellipse rx="15" ry="11" fill="#7b4a2b"/><ellipse rx="15" ry="11" fill="none" stroke="#5b3520" stroke-width="1.2"/><ellipse cx="1" cy="-1" rx="11" ry="7.6" fill="#fdf0e2"/><ellipse cx="3" cy="-2" rx="6" ry="3.6" fill="#f3cfcf"/></g>
<ellipse cx="142" cy="80" rx="25" ry="27" fill="#16341f" opacity=".75"/><path d="M141,106 C141,96 140,90 141,84" stroke="#2c6b36" stroke-width="2.2" fill="none" stroke-linecap="round"/><g transform="translate(131 88) rotate(-22) scale(.62)"><path d="M0,-18 C10,-20 22,-8 18,6 C14,18 4,22 0,26 C-4,22 -14,18 -18,6 C-22,-8 -10,-20 0,-18Z" fill="#3f9a4c"/><path d="M0,-14 L0,22" stroke="#9ad39b" stroke-width="1.6"/></g><g transform="translate(143 72) rotate(10) scale(.9)"><path d="M0,-18 C10,-20 22,-8 18,6 C14,18 4,22 0,26 C-4,22 -14,18 -18,6 C-22,-8 -10,-20 0,-18Z" fill="#2f8340"/><path d="M0,-15 L0,23 M0,6 L-9,-4 M0,6 L9,-4 M0,14 L-8,7 M0,14 L8,7" stroke="#a6dca4" stroke-width="1.3" fill="none" stroke-linecap="round"/></g>
<path d="M130,136 L118,110" stroke="#c58a2a" stroke-width="1.7" stroke-linecap="round"/><ellipse cx="115.6" cy="110.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-53 115.6 110.0)"/><ellipse cx="120.4" cy="110.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(3 120.4 110.0)"/><ellipse cx="116.0" cy="110.9" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-53 116.0 110.9)"/><ellipse cx="120.8" cy="110.9" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(3 120.8 110.9)"/><ellipse cx="116.4" cy="111.7" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-53 116.4 111.7)"/><ellipse cx="121.2" cy="111.7" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(3 121.2 111.7)"/><ellipse cx="116.8" cy="112.6" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-53 116.8 112.6)"/><ellipse cx="121.6" cy="112.6" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(3 121.6 112.6)"/><ellipse cx="117.2" cy="113.5" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-53 117.2 113.5)"/><ellipse cx="122.0" cy="113.5" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(3 122.0 113.5)"/><ellipse cx="117.6" cy="114.3" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-53 117.6 114.3)"/><ellipse cx="122.4" cy="114.3" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(3 122.4 114.3)"/><ellipse cx="118" cy="108" rx="1.5" ry="3.3" fill="#e8b94d"/><path d="M136,136 L133,105" stroke="#c58a2a" stroke-width="1.7" stroke-linecap="round"/><ellipse cx="130.6" cy="105.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-34 130.6 105.0)"/><ellipse cx="135.4" cy="105.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(22 135.4 105.0)"/><ellipse cx="130.7" cy="106.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-34 130.7 106.0)"/><ellipse cx="135.5" cy="106.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(22 135.5 106.0)"/><ellipse cx="130.8" cy="107.1" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-34 130.8 107.1)"/><ellipse cx="135.6" cy="107.1" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(22 135.6 107.1)"/><ellipse cx="130.9" cy="108.1" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-34 130.9 108.1)"/><ellipse cx="135.7" cy="108.1" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(22 135.7 108.1)"/><ellipse cx="131.0" cy="109.1" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-34 131.0 109.1)"/><ellipse cx="135.8" cy="109.1" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(22 135.8 109.1)"/><ellipse cx="131.1" cy="110.2" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-34 131.1 110.2)"/><ellipse cx="135.9" cy="110.2" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(22 135.9 110.2)"/><ellipse cx="133" cy="103" rx="1.5" ry="3.3" fill="#e8b94d"/><path d="M143,136 L149,107" stroke="#c58a2a" stroke-width="1.7" stroke-linecap="round"/><ellipse cx="146.6" cy="107.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-16 146.6 107.0)"/><ellipse cx="151.4" cy="107.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(40 151.4 107.0)"/><ellipse cx="146.4" cy="108.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-16 146.4 108.0)"/><ellipse cx="151.2" cy="108.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(40 151.2 108.0)"/><ellipse cx="146.2" cy="108.9" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-16 146.2 108.9)"/><ellipse cx="151.0" cy="108.9" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(40 151.0 108.9)"/><ellipse cx="146.0" cy="109.9" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-16 146.0 109.9)"/><ellipse cx="150.8" cy="109.9" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(40 150.8 109.9)"/><ellipse cx="145.8" cy="110.9" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-16 145.8 110.9)"/><ellipse cx="150.6" cy="110.9" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(40 150.6 110.9)"/><ellipse cx="145.6" cy="111.8" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-16 145.6 111.8)"/><ellipse cx="150.4" cy="111.8" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(40 150.4 111.8)"/><ellipse cx="149" cy="105" rx="1.5" ry="3.3" fill="#e8b94d"/><path d="M150,136 L161,116" stroke="#c58a2a" stroke-width="1.7" stroke-linecap="round"/><ellipse cx="158.6" cy="116.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(1 158.6 116.0)"/><ellipse cx="163.4" cy="116.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(57 163.4 116.0)"/><ellipse cx="158.2" cy="116.7" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(1 158.2 116.7)"/><ellipse cx="163.0" cy="116.7" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(57 163.0 116.7)"/><ellipse cx="157.9" cy="117.3" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(1 157.9 117.3)"/><ellipse cx="162.7" cy="117.3" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(57 162.7 117.3)"/><ellipse cx="157.5" cy="118.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(1 157.5 118.0)"/><ellipse cx="162.3" cy="118.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(57 162.3 118.0)"/><ellipse cx="157.1" cy="118.7" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(1 157.1 118.7)"/><ellipse cx="161.9" cy="118.7" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(57 161.9 118.7)"/><ellipse cx="156.8" cy="119.3" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(1 156.8 119.3)"/><ellipse cx="161.6" cy="119.3" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(57 161.6 119.3)"/><ellipse cx="161" cy="114" rx="1.5" ry="3.3" fill="#e8b94d"/><path d="M124,138 L108,121" stroke="#c58a2a" stroke-width="1.7" stroke-linecap="round"/><ellipse cx="105.6" cy="121.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-71 105.6 121.0)"/><ellipse cx="110.4" cy="121.0" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-15 110.4 121.0)"/><ellipse cx="106.1" cy="121.6" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-71 106.1 121.6)"/><ellipse cx="110.9" cy="121.6" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-15 110.9 121.6)"/><ellipse cx="106.7" cy="122.1" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-71 106.7 122.1)"/><ellipse cx="111.5" cy="122.1" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-15 111.5 122.1)"/><ellipse cx="107.2" cy="122.7" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-71 107.2 122.7)"/><ellipse cx="112.0" cy="122.7" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-15 112.0 122.7)"/><ellipse cx="107.7" cy="123.3" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-71 107.7 123.3)"/><ellipse cx="112.5" cy="123.3" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-15 112.5 123.3)"/><ellipse cx="108.3" cy="123.8" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-71 108.3 123.8)"/><ellipse cx="113.1" cy="123.8" rx="1.5" ry="3.2" fill="#eabd4f" transform="rotate(-15 113.1 123.8)"/><ellipse cx="108" cy="119" rx="1.5" ry="3.3" fill="#e8b94d"/>
<g transform="translate(140 138) scale(.95)"><path d="M-17,16 C-21,2 -13,-8 0,-8 C13,-8 21,2 17,16 Z" fill="#d9a05b"/><path d="M-17,16 C-21,2 -13,-8 0,-8 C-6,2 -10,10 -17,16Z" fill="#e9b872" opacity=".7"/><ellipse cx="0" cy="-8" rx="13" ry="4.6" fill="#c28542"/><ellipse cx="0" cy="-9" rx="11.5" ry="3.8" fill="#eec45f"/><circle cx="-4" cy="-9.5" r=".9" fill="#c28a2c"/><circle cx="1" cy="-8.5" r=".9" fill="#c28a2c"/><circle cx="5" cy="-10" r=".9" fill="#c28a2c"/><circle cx="-1" cy="-10.6" r=".8" fill="#d5a23a"/><path d="M-10,-4 C-4,-1 4,-1 10,-4" stroke="#b4783a" stroke-width="1.2" fill="none"/></g>
</g>
<text font-family="'Bricolage Grotesque','Arial Black','Impact',sans-serif" font-weight="800" fill="#4a2f1f" font-size="19"><textPath href="#sl-top" startOffset="50%" text-anchor="middle" textLength="206" lengthAdjust="spacing">BARANGAY BIGAAN</textPath></text>
<text font-family="'Bricolage Grotesque','Arial Black','Impact',sans-serif" font-weight="800" fill="#4a2f1f" font-size="16"><textPath href="#sl-bot" startOffset="50%" text-anchor="middle" textLength="226" lengthAdjust="spacing">CALAUAG, QUEZON</textPath></text>
<circle cx="16.5" cy="100" r="3.2" fill="#5a3a28"/><circle cx="183.5" cy="100" r="3.2" fill="#5a3a28"/>
<circle cx="100" cy="100" r="99" fill="url(#sl-gloss)"/>
</symbol></svg>

<!-- ============ ANIMATED SCENE: Bigaan's coconuts, palay and the railway ============ -->
<div class="scene" aria-hidden="true">
    <svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMax slice">
        <defs>
            <radialGradient id="sunG"><stop offset="0" stop-color="#fff3c4"/><stop offset=".45" stop-color="#ffd272"/><stop offset="1" stop-color="#ffd272" stop-opacity="0"/></radialGradient>
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
                    <g class="frond-wave" style="animation-delay:-3s"><path d="M0 0 C60 -70 150 -60 215 10 C150 -22 70 -24 0 0Z" fill="#2f8a57" transform="rotate(-235)"/></g>
                    <circle cx="-6" cy="14" r="11" fill="#6b4a2b"/><circle cx="12" cy="18" r="11" fill="#7c5833"/><circle cx="2" cy="28" r="10" fill="#5e4025"/>
                </g>
            </g>
        </defs>

        <!-- sun + rays -->
        <g class="sun-glow"><circle cx="520" cy="560" r="330" fill="url(#sunG)"/></g>
        <g class="rays" opacity=".16" fill="#fff0b8">
            <polygon points="520,560 480,-400 560,-400"/><polygon points="520,560 480,1520 560,1520"/>
            <polygon points="520,560 -400,520 -400,600"/><polygon points="520,560 1440,520 1440,600"/>
            <polygon points="520,560 -300,-300 -230,-370"/><polygon points="520,560 1340,1420 1270,1490"/>
            <polygon points="520,560 -300,1420 -230,1490"/><polygon points="520,560 1340,-300 1270,-370"/>
        </g>
        <circle cx="520" cy="560" r="78" fill="#fff6d0"/>

        <!-- clouds -->
        <g class="cloud c1" fill="#fff" opacity=".55"><ellipse cx="0" cy="170" rx="120" ry="24"/><ellipse cx="-60" cy="155" rx="70" ry="22"/><ellipse cx="60" cy="152" rx="60" ry="20"/></g>
        <g class="cloud c2" fill="#fff" opacity=".4"><ellipse cx="0" cy="300" rx="150" ry="28"/><ellipse cx="70" cy="284" rx="80" ry="24"/></g>
        <g class="cloud c3" fill="#ffe9c0" opacity=".5"><ellipse cx="0" cy="420" rx="110" ry="20"/><ellipse cx="-50" cy="406" rx="60" ry="18"/></g>

        <!-- birds -->
        <g class="bird" fill="none" stroke="#10303d" stroke-width="3" stroke-linecap="round" opacity=".7"><path d="M0 260 q10 -12 20 0 q10 -12 20 0"/><path d="M60 290 q8 -9 16 0 q8 -9 16 0"/></g>
        <g class="bird b2" fill="none" stroke="#10303d" stroke-width="3" stroke-linecap="round" opacity=".6"><path d="M0 220 q9 -10 18 0 q9 -10 18 0"/></g>

        <!-- mountains & hills -->
        <path d="M0 640 L170 470 L300 570 L470 400 L690 600 L860 480 L1060 620 L1250 450 L1430 590 L1600 500 V760 H0Z" fill="#1c6a72" opacity=".9"/>
        <path d="M0 690 Q220 560 460 650 T900 640 T1340 620 T1600 660 V780 H0Z" fill="#287d56"/>

        <!-- barangay hall on the hill -->
        <g transform="translate(850 560)">
            <line x1="62" y1="-16" x2="62" y2="-86" stroke="#2b1c14" stroke-width="3"/>
            <path d="M62 -86 h34 l-8 8 l8 8 h-34Z" fill="#e0453c"/>
            <path d="M-8 28 L62 -18 L132 28Z" fill="#a62f35"/>
            <rect x="4" y="28" width="116" height="48" fill="#f6ecd4"/>
            <rect x="14" y="40" width="20" height="20" fill="#5fa8c8"/><rect x="90" y="40" width="20" height="20" fill="#5fa8c8"/>
            <rect x="50" y="42" width="24" height="34" fill="#1a3a68"/>
        </g>

        <!-- palay field -->
        <path d="M0 740 Q400 700 800 716 T1600 700 V820 H0Z" fill="url(#field)"/>
        <path d="M0 740 Q400 700 800 716 T1600 700 V820 H0Z" fill="url(#rows)"/>

        <!-- railway -->
        <rect x="0" y="796" width="1600" height="104" fill="#2d6d44"/>
        <line x1="0" y1="824" x2="1600" y2="824" stroke="#6b4630" stroke-width="16" stroke-dasharray="7 22"/>
        <line x1="0" y1="818" x2="1600" y2="818" stroke="#c9ccd1" stroke-width="3.5"/>
        <line x1="0" y1="830" x2="1600" y2="830" stroke="#c9ccd1" stroke-width="3.5"/>

        <!-- train -->
        <g class="train">
            <g transform="translate(0 746)">
                <!-- engine -->
                <rect x="360" y="12" width="120" height="58" rx="6" fill="#f2b84b"/>
                <rect x="372" y="-14" width="70" height="30" rx="4" fill="#12303f"/>
                <rect x="380" y="-6" width="24" height="16" fill="#9fd8ee"/><rect x="410" y="-6" width="24" height="16" fill="#9fd8ee"/>
                <rect x="448" y="-6" width="14" height="22" fill="#12303f"/>
                <rect x="360" y="50" width="120" height="8" fill="#c0392b"/>
                <circle cx="486" cy="40" r="7" fill="#fff6c4"/>
                <g><circle class="puff" cx="455" cy="-12" r="14" fill="#fff" opacity=".7"/><circle class="puff" cx="455" cy="-12" r="14" fill="#fff" opacity=".7"/><circle class="puff" cx="455" cy="-12" r="14" fill="#fff" opacity=".7"/></g>
                <!-- cars -->
                <g fill="#fffdf7">
                    <rect x="230" y="14" width="120" height="56" rx="6"/><rect x="100" y="14" width="120" height="56" rx="6"/><rect x="-30" y="14" width="120" height="56" rx="6"/>
                </g>
                <g fill="#5fa8c8">
                    <rect x="244" y="26" width="20" height="16" rx="2"/><rect x="274" y="26" width="20" height="16" rx="2"/><rect x="304" y="26" width="20" height="16" rx="2"/>
                    <rect x="114" y="26" width="20" height="16" rx="2"/><rect x="144" y="26" width="20" height="16" rx="2"/><rect x="174" y="26" width="20" height="16" rx="2"/>
                    <rect x="-16" y="26" width="20" height="16" rx="2"/><rect x="14" y="26" width="20" height="16" rx="2"/><rect x="44" y="26" width="20" height="16" rx="2"/>
                </g>
                <g fill="#2f8a57"><rect x="230" y="52" width="120" height="8"/><rect x="100" y="52" width="120" height="8"/><rect x="-30" y="52" width="120" height="8"/></g>
                <rect x="-30" y="66" width="510" height="8" fill="#12303f"/>
                <!-- wheels -->
                <g fill="#12303f" stroke="#c9ccd1" stroke-width="2">
                    <circle class="wheel" cx="0" cy="78" r="9"/><circle class="wheel" cx="60" cy="78" r="9"/><circle class="wheel" cx="130" cy="78" r="9"/><circle class="wheel" cx="190" cy="78" r="9"/>
                    <circle class="wheel" cx="260" cy="78" r="9"/><circle class="wheel" cx="320" cy="78" r="9"/><circle class="wheel" cx="390" cy="78" r="11"/><circle class="wheel" cx="450" cy="78" r="11"/>
                </g>
            </g>
        </g>

        <!-- foreground palms -->
        <g transform="translate(150 900) scale(1.25)"><g class="palm-sway"><use href="#palm"/></g></g>
        <g transform="translate(310 900) scale(.8)"><g class="palm-sway slow"><use href="#palm"/></g></g>
        <g transform="translate(1470 900) scale(1.1)"><g class="palm-sway slow"><use href="#palm"/></g></g>
        <g transform="translate(1340 900) scale(.7)"><g class="palm-sway"><use href="#palm"/></g></g>
    </svg>
</div>

<div class="page">
    <nav class="nav intro i1">
        <a class="brand" href="login.php">
            <span class="logo" role="img" aria-label="Barangay Bigaan seal"><svg viewBox="0 0 200 200" aria-hidden="true"><use href="#seal"/></svg></span>
            <span><b>E-Barangay</b><small>Bigaan, Calauag, Quezon</small></span>
        </a>
        <ul class="nav-links">
            <li><a href="#home" data-nav="home">Home</a></li>
            <li><a href="#services" data-nav="services">Public services</a></li>
            <li><a href="#about" data-nav="about">About</a></li>
            <li><a class="cta" href="register.php">Create account</a></li>
        </ul>
    </nav>

    <main class="hero" id="home">
        <!-- LEFT -->
        <section>
            <div class="seal-wrap intro i2"><span class="logo" role="img" aria-label="Seal of Barangay Bigaan, Calauag, Quezon"><svg viewBox="0 0 200 200" aria-hidden="true"><use href="#seal"/></svg></span></div>
            <p class="place intro i2">Barangay Bigaan · Calauag, Quezon</p>
            <h1 class="intro i3">Serbisyo ng Barangay, abot-kamay na.</h1>
            <p class="lede intro i3">Request barangay documents, follow your application, and see what is happening in the community, all from one secure portal.</p>

            <div class="perks intro i4">
                <div class="perk">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                    <div><b>Request documents online</b><span>Clearance, residency and indigency certificates without the queue.</span></div>
                </div>
                <div class="perk">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16 9.5"/></svg>
                    <div><b>Track every request</b><span>See each step from submitted to ready for pickup.</span></div>
                </div>
                <div class="perk">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg>
                    <div><b>Open barangay budget</b><span>Read how public funds and projects are being used.</span></div>
                </div>
            </div>
        </section>

        <!-- RIGHT -->
        <section class="card intro i4" aria-labelledby="signin-title">
            <h2 id="signin-title">Welcome back</h2>
            <p class="sub" id="roleHint">Sign in to request and track your documents.</p>

            <?php if ($error !== ''): ?>
                <div class="alert" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/></svg>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="seg" role="radiogroup" aria-label="Portal">
                    <input type="radio" name="login_role" id="r-resident" value="Resident" <?= $selected_type === 'Resident' ? 'checked' : '' ?>>
                    <label for="r-resident">Resident</label>
                    <input type="radio" name="login_role" id="r-official" value="Official" <?= $selected_type === 'Official' ? 'checked' : '' ?>>
                    <label for="r-official">Official / Admin</label>
                    <span class="thumb" aria-hidden="true"></span>
                </div>

                <div class="field">
                    <label for="username">Username</label>
                    <div class="input">
                        <svg class="lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        <input type="text" id="username" name="username" required autocomplete="username" placeholder="Enter your username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input">
                        <svg class="lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                        <button type="button" class="eye" id="eye" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="caps" id="caps">Caps Lock is on.</div>
                </div>

                <button type="submit" class="btn" id="submitBtn"><span>Sign in</span><i class="spin"></i></button>
            </form>

            <p class="foot">New to the portal? <a href="register.php">Register as a resident</a></p>
        </section>
    </main>

    <section class="section" id="services" aria-labelledby="svc-title">
        <div class="panel">
            <h3 id="svc-title">Public services</h3>
            <p class="intro-text">Services you can request online from Barangay Bigaan. Sign in to your resident account to start a request.</p>
            <div class="svc-grid">
                <div class="svc">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                    <b>Barangay clearance</b><span>For jobs, IDs, and other official transactions.</span>
                    <a href="#home" data-focus>Sign in to request</a>
                </div>
                <div class="svc">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 12 3l9 8"/><path d="M5 10v10h14V10M10 20v-6h4v6"/></svg>
                    <b>Certificate of residency</b><span>Proof that you live in Barangay Bigaan.</span>
                    <a href="#home" data-focus>Sign in to request</a>
                </div>
                <div class="svc">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.6-7 10-7 10z"/></svg>
                    <b>Certificate of indigency</b><span>For assistance programs, scholarships, and medical help.</span>
                    <a href="#home" data-focus>Sign in to request</a>
                </div>
                <div class="svc">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16 9.5"/></svg>
                    <b>Track my request</b><span>Follow each step until your document is ready for pickup.</span>
                    <a href="#home" data-focus>Sign in to track</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="about" aria-labelledby="about-title">
        <div class="panel">
            <div class="about-grid">
                <span class="logo" role="img" aria-label="Seal of Barangay Bigaan"><svg viewBox="0 0 200 200" aria-hidden="true"><use href="#seal"/></svg></span>
                <div>
                    <h3 id="about-title">About Barangay Bigaan</h3>
                    <p class="intro-text">Barangay Bigaan is in Calauag, Quezon. E-Barangay brings its everyday services online, so residents can request documents and follow their applications without waiting in line.</p>
                    <div class="about-points">
                        <div><b>Niyog</b><span>Coconut palms on the barangay seal.</span></div>
                        <div><b>Riles ng tren</b><span>The railway that runs through the seal.</span></div>
                        <div><b>Gabi at palay</b><span>Crops that grow in the community.</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="site-foot">Barangay Bigaan, Calauag, Quezon · E-Barangay System</footer>
</div>

<script>
(function () {
    var pw = document.getElementById('password');
    var eye = document.getElementById('eye');
    var caps = document.getElementById('caps');
    var form = document.getElementById('loginForm');
    var btn = document.getElementById('submitBtn');
    var hint = document.getElementById('roleHint');
    var radios = document.querySelectorAll('input[name="login_role"]');

    eye.addEventListener('click', function () {
        var show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        eye.style.color = show ? '#14566b' : '';
    });

    function capsCheck(e) {
        if (e.getModifierState) caps.classList.toggle('on', e.getModifierState('CapsLock'));
    }
    pw.addEventListener('keyup', capsCheck);
    pw.addEventListener('keydown', capsCheck);
    pw.addEventListener('blur', function () { caps.classList.remove('on'); });

    function updateHint() {
        var official = document.getElementById('r-official').checked;
        hint.textContent = official
            ? 'Sign in to manage records, requests and reports.'
            : 'Sign in to request and track your documents.';
    }
    radios.forEach(function (r) { r.addEventListener('change', updateHint); });
    updateHint();


    /* Active nav link while scrolling */
    var links = document.querySelectorAll('[data-nav]');
    var map = {};
    links.forEach(function (a) { map[a.getAttribute('data-nav')] = a; });
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) {
                    links.forEach(function (a) { a.classList.remove('active'); });
                    var a = map[en.target.id]; if (a) a.classList.add('active');
                }
            });
        }, { rootMargin: '-45% 0px -50% 0px' });
        ['home', 'services', 'about'].forEach(function (id) { var el = document.getElementById(id); if (el) io.observe(el); });
    }
    /* "Sign in to request" links: scroll up and focus the username field */
    document.querySelectorAll('[data-focus]').forEach(function (a) {
        a.addEventListener('click', function () { setTimeout(function () { document.getElementById('username').focus({ preventScroll: true }); }, 500); });
    });

    form.addEventListener('submit', function () { btn.classList.add('loading'); });
    window.addEventListener('pageshow', function () { btn.classList.remove('loading'); });
})();
</script>
</body>
</html>