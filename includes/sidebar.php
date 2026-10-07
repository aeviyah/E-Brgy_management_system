<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Kunin ang kasalukuyang role ng user mula sa Session (Default: 'Resident')
$user_role = $_SESSION['role'] ?? 'Resident';
?>
<aside class="sidebar">
    <nav class="nav-menu">
        <!-- 1. DYNAMIC DASHBOARD LINK -->
        <a href="<?= ($user_role === 'Resident') ? '../citizen_dashboard.php' : '../dashboard.php'; ?>" class="nav-item">📊 Dashboard</a>
        
        <!-- 2. RESIDENT REGISTRY: DAPAT MAKAKAKITA LANG ANG ADMIN O OFFICIALS (ITATAGO SA RESIDENT) -->
        <?php if ($user_role !== 'Resident'): ?>
            <a href="residents.php" class="nav-item">👥 Resident Registry</a>
        <?php endif; ?>

        <!-- 3. DOCUMENT REQUESTS: BUKAS PARA SA LAHAT -->
        <a href="documents.php" class="nav-item">📄 Document Requests</a>

        <!-- 4. COMPLAINTS & BLOTTER: BUKAS PARA SA LAHAT -->
        <a href="complaints.php" class="nav-item">⚖️ Complaints & Blotter</a>
        
        <!-- 5. TREASURY & REVENUE: BUKAS SA LAHAT (FOR TRANSPARENCY VIEW SA RESIDENTS) -->
        <a href="treasury.php" class="nav-item">💳 Treasury & Revenue</a>

        <!-- 6. ANNOUNCEMENTS: BUKAS PARA SA LAHAT -->
        <a href="announcements.php" class="nav-item">📢 Announcements</a>
    </nav>
</aside>