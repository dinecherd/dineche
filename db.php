<?php
$host = '127.0.0.1';
$db   = 'ctf_prisma';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Note: To allow init_db to create the database, we might need a connection without DB name initially.
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // If the database doesn't exist, this will fail. That's fine for init_db.php.
    if (strpos($e->getMessage(), 'Unknown database') === false) {
         throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}
?>
