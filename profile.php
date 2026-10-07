<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];

    if (!empty($old_pass) && !empty($new_pass)) {
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (password_verify($old_pass, $user['password']) || $old_pass === $user['password']) {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update->bind_param("si", $hashed, $user_id);
            if ($update->execute()) {
                $message = "Matagumpay na nabago ang iyong password!";
            } else {
                $error = "Nagkaroon ng error sa pag-update.";
            }
        } else {
            $error = "Maling lumang password!";
        }
    } else {
        $error = "Paki-punan ang lahat ng fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - E-Barangay</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-header">
        <div class="brand">🏠 E-Barangay Portal</div>
        <div class="user-profile">
            <a href="<?= ($_SESSION['role'] === 'Resident') ? 'citizen_dashboard.php' : 'dashboard.php'; ?>" style="color: white; text-decoration: none; font-weight: bold; margin-right: 15px;">← Bumalik sa Dashboard</a>
            <a href="logout.php" class="btn-logout-sm">Logout</a>
        </div>
    </div>

    <div style="max-width: 600px; margin: 40px auto; padding: 0 20px;">
        <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0; color: #0f172a;">👤 Aking Profile at Seguridad</h2>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 25px;">Username: <strong><?= htmlspecialchars($_SESSION['username']); ?></strong> | Role: <strong><?= htmlspecialchars($_SESSION['role']); ?></strong></p>

            <?php if (!empty($message)): ?>
                <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;"><?= $message; ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;"><?= $error; ?></div>
            <?php endif; ?>

            <form action="profile.php" method="POST">
                <h3 style="font-size: 16px; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; margin-bottom: 15px;">🔒 Magpalit ng Password</h3>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px;">Kasalukuyang Password (Old Password) *</label>
                    <input type="password" name="old_password" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px;">Bagong Password (New Password) *</label>
                    <input type="password" name="new_password" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
                </div>

                <button type="submit" style="width: 100%; background: #2563eb; color: white; padding: 12px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">I-save ang Bagong Password</button>
            </form>
        </div>
    </div>
</body>
</html>