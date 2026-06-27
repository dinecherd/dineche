<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stmt_top = $pdo->query("SELECT username, score_total FROM users WHERE is_admin = 0 ORDER BY score_total DESC, created_at ASC LIMIT 5");
$top_users = $stmt_top->fetchAll();

header('Content-Type: application/json');
echo json_encode($top_users);
?>
