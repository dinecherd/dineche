<?php
session_start();
require_once 'db.php';

// Check admin access
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 1. Fetch Top 10 Users for scoreboard
$stmt_top = $pdo->query("SELECT id, username, score_total FROM users WHERE is_admin = 0 ORDER BY score_total DESC, created_at ASC LIMIT 10");
$top_users = $stmt_top->fetchAll();

// 2. Cleanup expired freezes using PHP time to avoid timezone mismatch
$now = date('Y-m-d H:i:s');
$pdo->prepare("DELETE FROM active_freezes WHERE expires_at <= ?")->execute([$now]);

// 3. Fetch active freezes
$stmt_freezes = $pdo->prepare("
    SELECT 
        f.id,
        u_attacker.username as attacker_name, 
        u_target.username as target_name,
        f.target_user_id,
        f.expires_at,
        TIMESTAMPDIFF(SECOND, ?, f.expires_at) as seconds_left
    FROM active_freezes f
    JOIN users u_attacker ON f.attacker_id = u_attacker.id
    LEFT JOIN users u_target ON f.target_user_id = u_target.id
    WHERE f.expires_at > ?
    ORDER BY f.expires_at DESC
");
$stmt_freezes->execute([$now, $now]);
$active_freezes = $stmt_freezes->fetchAll();

header('Content-Type: application/json');
echo json_encode([
    'top_users' => $top_users,
    'freezes' => $active_freezes
]);
?>
