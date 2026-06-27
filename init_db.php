<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS ctf_prisma");
    echo "<p>Database 'ctf_prisma' created or already exists.</p>";

    // Connect to the new database
    $pdo->exec("USE ctf_prisma");

    // Drop tables if we are re-initializing (useful for dev)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DROP TABLE IF EXISTS active_freezes, submissions, challenges, events, users");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<p>Old tables dropped (if they existed).</p>";

    // Create Users Table
    $pdo->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        is_admin BOOLEAN DEFAULT FALSE,
        score_total INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>Table 'users' created.</p>";

    // Create Events Table
    $pdo->exec("CREATE TABLE events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>Table 'events' created.</p>";

    // Create Challenges Table
    $pdo->exec("CREATE TABLE challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        title VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        flag_value VARCHAR(100) NOT NULL,
        points INT DEFAULT 10,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    )");
    echo "<p>Table 'challenges' created.</p>";

    // Create Submissions Table
    $pdo->exec("CREATE TABLE submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        challenge_id INT NOT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE,
        UNIQUE KEY unique_submission (user_id, challenge_id)
    )");
    echo "<p>Table 'submissions' created.</p>";

    // Create Active Freezes Table
    $pdo->exec("CREATE TABLE active_freezes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attacker_id INT NOT NULL,
        target_user_id INT DEFAULT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (attacker_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "<p>Table 'active_freezes' created.</p>";

    // Create default admin account (password: admin123)
    $admin_user = 'admin';
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)");
    $stmt->execute([$admin_user, $admin_pass, 1]);
    echo "<p>Default admin created. (Username: <strong>admin</strong>, Password: <strong>admin123</strong>)</p>";

    echo "<h3>Initialization successful!</h3>";
    echo "<a href='index.php'>Go to Login Page</a>";

} catch (PDOException $e) {
    die("DB ERROR: " . $e->getMessage());
}
?>
