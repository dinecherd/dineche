<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Clean up expired freezes
$now = date('Y-m-d H:i:s');
$pdo->prepare("DELETE FROM active_freezes WHERE expires_at <= ?")->execute([$now]);

// Check if user is frozen (target_user_id is the user OR target_user_id is NULL for a global freeze) AND the user is NOT the attacker.
$stmt = $pdo->prepare("
    SELECT f.expires_at, u.username as attacker_name 
    FROM active_freezes f
    JOIN users u ON f.attacker_id = u.id
    WHERE (f.target_user_id = ? OR f.target_user_id IS NULL) 
    AND f.attacker_id != ? 
    AND f.expires_at > ?
    ORDER BY f.expires_at DESC LIMIT 1
");
$stmt->execute([$user_id, $user_id, $now]);
$freeze = $stmt->fetch();

$is_frozen = false;
$seconds_left = 0;

if ($freeze) {
    $expires_at = strtotime($freeze['expires_at']);
    $now = time();
    if ($expires_at > $now) {
        $is_frozen = true;
        $seconds_left = $expires_at - $now;
        $attacker_name = $freeze['attacker_name'];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'frozen' => $is_frozen,
    'seconds_left' => $seconds_left,
    'attacker_name' => $attacker_name ?? ''
]);
?>
