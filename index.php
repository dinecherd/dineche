<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Identifiants incorrects.';
            }
        } catch (\PDOException $e) {
            $error = 'Erreur de base de données. Avez-vous exécuté init_db.php ?';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Palo Alto Prisma CTF - Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container" style="max-width: 500px; margin-top: 5vh;">
        <div class="glass-panel">
            <h1>Prisma CTF</h1>
            <h3 style="text-align: center; color: var(--text-muted); margin-top: -10px;">Exclusive Networks</h3>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn" style="width: 100%;">Se connecter</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem;">
                <p style="color: var(--text-muted);">Demandez à l'administrateur de vous créer un compte pour participer.</p>
            </div>
        </div>
    </div>
</body>
</html>
