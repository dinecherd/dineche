<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- Create Event ---
    if ($_POST['action'] === 'create_event') {
        $name = trim($_POST['event_name'] ?? '');
        $desc = trim($_POST['event_desc'] ?? '');
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO events (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $desc]);
                $message = 'Événement créé avec succès !';
                $msg_type = 'success';
            } catch (\PDOException $e) {
                $message = 'Erreur lors de la création de l\'événement.';
                $msg_type = 'error';
            }
        }
    }
    
    // --- Create Challenge/Flag ---
    elseif ($_POST['action'] === 'create_challenge') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $flag_value = trim($_POST['flag_value'] ?? '');
        $points = (int)($_POST['points'] ?? 10);

        if ($event_id > 0 && !empty($title) && !empty($flag_value)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO challenges (event_id, title, description, flag_value, points) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$event_id, $title, $description, $flag_value, $points]);
                $message = 'Jeu de flag ajouté à l\'événement !';
                $msg_type = 'success';
            } catch (\PDOException $e) {
                $message = 'Erreur lors de la création du flag.';
                $msg_type = 'error';
            }
        } else {
            $message = 'Veuillez sélectionner un événement et remplir les champs obligatoires.';
            $msg_type = 'error';
        }
    }
    
    // --- Delete Challenge ---
    elseif ($_POST['action'] === 'delete_challenge') {
        $id = (int)$_POST['challenge_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM challenges WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Challenge supprimé.';
            $msg_type = 'success';
        } catch (\PDOException $e) {
            $message = 'Erreur lors de la suppression.';
            $msg_type = 'error';
        }
    }

    // --- Create User ---
    elseif ($_POST['action'] === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!empty($username) && !empty($password)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $message = 'Ce nom d\'utilisateur existe déjà.';
                    $msg_type = 'error';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                    $stmt->execute([$username, $hashed]);
                    $message = 'Utilisateur créé avec succès !';
                    $msg_type = 'success';
                }
            } catch (\PDOException $e) {
                $message = 'Erreur BDD lors de la création.';
                $msg_type = 'error';
            }
        }
    }
}

// Fetch all events
$events_stmt = $pdo->query("SELECT * FROM events ORDER BY created_at DESC");
$events = $events_stmt->fetchAll();

// Fetch all challenges with event names
$stmt = $pdo->query("SELECT c.*, e.name as event_name FROM challenges c JOIN events e ON c.event_id = e.id ORDER BY e.created_at DESC, c.created_at DESC");
$challenges = $stmt->fetchAll();

// Fetch recent users
$users_stmt = $pdo->query("SELECT username, created_at, score_total FROM users WHERE is_admin=0 ORDER BY created_at DESC LIMIT 10");
$recent_users = $users_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Palo Alto Prisma CTF - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        select {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: #fff;
            font-size: 1rem;
        }
        select option {
            background: var(--bg-color);
            color: #fff;
        }
    </style>
</head>
<body>
    <nav class="nav">
        <a href="dashboard.php" class="nav-brand">Prisma CTF</a>
        <div class="nav-links">
            <a href="dashboard.php">Challenges</a>
            <a href="admin.php" class="active">Admin</a>
            <a href="admin_live.php" target="_blank" style="color: #ff3860; font-weight: bold; text-shadow: 0 0 5px rgba(255,56,96,0.5);">🔴 LIVE DASHBOARD</a>
            <a href="logout.php">Déconnexion</a>
        </div>
    </nav>

    <div class="container">
        <h2>Panneau d'Administration</h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Gestion des événements, flags et utilisateurs.</p>

        <!-- SOC DASHBOARD BUTTON -->
        <div class="glass-panel" style="text-align: center; margin-bottom: 2rem; border-color: #ff3860;">
            <h3 style="color: #ff3860; margin-bottom: 1rem;">Projecteur & Spectateurs</h3>
            <p style="color: var(--text-muted); margin-bottom: 1rem;">Ouvrez l'interface SOC pour la projeter sur grand écran.</p>
            <a href="admin_live.php" target="_blank" class="btn" style="background: rgba(255,56,96,0.1); border-color: #ff3860; color: #ff3860; font-size: 1.2rem; padding: 1rem 2rem;">🔴 LANCER LE SOC LIVE DASHBOARD</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $msg_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="grid" style="margin-top: 0;">
            <!-- CREATE USER -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
                <h3 style="color: var(--secondary);">1. Créer un Utilisateur</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Les inscriptions publiques sont fermées. Créez les comptes ici.</p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_user">
                    <div class="form-group">
                        <label>Nom d'utilisateur</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Mot de passe</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-secondary">Créer Utilisateur</button>
                </form>
            </div>

            <!-- CREATE EVENT -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
                <h3 style="color: var(--primary);">2. Créer un Événement</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Créez un événement (ex: "Semaine PAB") pour regrouper des flags.</p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_event">
                    <div class="form-group">
                        <label>Nom de l'événement</label>
                        <input type="text" name="event_name" required>
                    </div>
                    <div class="form-group">
                        <label>Description (Optionnel)</label>
                        <input type="text" name="event_desc">
                    </div>
                    <button type="submit" class="btn">Créer Événement</button>
                </form>
            </div>
        </div>

        <!-- CREATE CHALLENGE -->
        <div class="glass-panel" style="margin-bottom: 2rem;">
            <h3>3. Créer un Jeu de Flag</h3>
            <?php if(empty($events)): ?>
                <div class="alert alert-error">Vous devez créer au moins un événement avant de créer des flags.</div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_challenge">
                    
                    <div class="grid" style="margin-top: 0;">
                        <div class="form-group">
                            <label>Événement associé</label>
                            <select name="event_id" required>
                                <option value="">-- Choisir un événement --</option>
                                <?php foreach($events as $e): ?>
                                    <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Titre du Flag</label>
                            <input type="text" name="title" required>
                        </div>
                    </div>

                    <div class="grid" style="margin-top: 0;">
                        <div class="form-group">
                            <label>Flag attendu</label>
                            <input type="text" name="flag_value" placeholder="flag{votre_flag}" required>
                        </div>
                        <div class="form-group">
                            <label>Points</label>
                            <input type="number" name="points" value="10" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description (Scénario)</label>
                        <textarea name="description" rows="3" required></textarea>
                    </div>

                    <button type="submit" class="btn">Ajouter le Flag</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- LIST CHALLENGES -->
        <div class="glass-panel">
            <h3>Flags existants</h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Événement</th>
                            <th>Titre</th>
                            <th>Points</th>
                            <th>Flag</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($challenges as $c): ?>
                            <tr>
                                <td><strong style="color: var(--primary);"><?php echo htmlspecialchars($c['event_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['title']); ?></td>
                                <td><span class="challenge-points"><?php echo $c['points']; ?></span></td>
                                <td><code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;"><?php echo htmlspecialchars($c['flag_value']); ?></code></td>
                                <td>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr ?');">
                                        <input type="hidden" name="action" value="delete_challenge">
                                        <input type="hidden" name="challenge_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="btn btn-small" style="color: var(--error); border-color: var(--error);">Suppr.</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($challenges)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted);">Aucun flag créé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
