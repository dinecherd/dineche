<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$msg_type = '';

$FREEZE_SINGLE_COST = 20;
$FREEZE_ALL_COST = 50;
$FREEZE_DURATION_SEC = 30;

// Cleanup expired freezes using PHP time
$now = date('Y-m-d H:i:s');
$pdo->prepare("DELETE FROM active_freezes WHERE expires_at <= ?")->execute([$now]);

// Handle Flag submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_flag') {
    $challenge_id = (int)$_POST['challenge_id'];
    $submitted_flag = trim($_POST['flag']);

    $stmt = $pdo->prepare("SELECT id FROM submissions WHERE user_id = ? AND challenge_id = ?");
    $stmt->execute([$user_id, $challenge_id]);
    
    if ($stmt->fetch()) {
        $message = "Vous avez déjà validé ce flag !";
        $msg_type = "error";
    } else {
        $stmt = $pdo->prepare("SELECT flag_value, points FROM challenges WHERE id = ?");
        $stmt->execute([$challenge_id]);
        $challenge = $stmt->fetch();

        if ($challenge && $challenge['flag_value'] === $submitted_flag) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO submissions (user_id, challenge_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $challenge_id]);
                $stmt = $pdo->prepare("UPDATE users SET score_total = score_total + ? WHERE id = ?");
                $stmt->execute([$challenge['points'], $user_id]);
                $pdo->commit();
                
                $message = "Flag correct ! Vous avez gagné " . $challenge['points'] . " points.";
                $msg_type = "success";
            } catch (\PDOException $e) {
                $pdo->rollBack();
                $message = "Erreur lors de la validation.";
                $msg_type = "error";
            }
        } else {
            $message = "Flag incorrect. Réessayez !";
            $msg_type = "error";
        }
    }
}

// Handle Attack Purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buy_attack') {
    $target_id = $_POST['target_id']; // 'all' or numeric ID
    
    // Check user points
    $stmt = $pdo->prepare("SELECT score_total FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_score = (int)$stmt->fetchColumn();
    
    $cost = ($target_id === 'all') ? $FREEZE_ALL_COST : $FREEZE_SINGLE_COST;
    
    if ($current_score < $cost) {
        $message = "Fonds insuffisants. Il vous faut $cost points.";
        $msg_type = "error";
    } else {
        try {
            $pdo->beginTransaction();
            // Deduct points
            $stmt = $pdo->prepare("UPDATE users SET score_total = score_total - ? WHERE id = ?");
            $stmt->execute([$cost, $user_id]);
            
            // Insert freeze
            $expires = date('Y-m-d H:i:s', time() + $FREEZE_DURATION_SEC);
            if ($target_id === 'all') {
                $stmt = $pdo->prepare("INSERT INTO active_freezes (attacker_id, target_user_id, expires_at) VALUES (?, NULL, ?)");
                $stmt->execute([$user_id, $expires]);
                $message = "Attaque Globale lancée ! Tout le monde est gelé pendant $FREEZE_DURATION_SEC secondes.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO active_freezes (attacker_id, target_user_id, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, (int)$target_id, $expires]);
                $message = "Attaque ciblée lancée ! L'adversaire est gelé pendant $FREEZE_DURATION_SEC secondes.";
            }
            $msg_type = "success";
            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $message = "Erreur système lors de l'attaque.";
            $msg_type = "error";
        }
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT score_total FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch all events and their challenges
$stmt = $pdo->query("SELECT e.id as event_id, e.name as event_name, e.description as event_desc,
                            c.id as challenge_id, c.title, c.description, c.points, s.id as solved
                     FROM events e
                     JOIN challenges c ON e.id = c.event_id
                     LEFT JOIN submissions s ON c.id = s.challenge_id AND s.user_id = " . $pdo->quote($user_id) . "
                     ORDER BY e.created_at DESC, c.points ASC");

$results = $stmt->fetchAll();

// Group by event
$events_data = [];
foreach ($results as $row) {
    $eid = $row['event_id'];
    if (!isset($events_data[$eid])) {
        $events_data[$eid] = [
            'name' => $row['event_name'],
            'desc' => $row['event_desc'],
            'challenges' => []
        ];
    }
    $events_data[$eid]['challenges'][] = [
        'id' => $row['challenge_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'points' => $row['points'],
        'solved' => $row['solved']
    ];
}

// Fetch top 5 users for scoreboard
$stmt_top = $pdo->query("SELECT id, username, score_total FROM users WHERE is_admin = 0 ORDER BY score_total DESC, created_at ASC LIMIT 5");
$top_users = $stmt_top->fetchAll();

// Fetch all other users for attack target list
$stmt_targets = $pdo->prepare("SELECT id, username FROM users WHERE is_admin = 0 AND id != ? ORDER BY username ASC");
$stmt_targets->execute([$user_id]);
$attack_targets = $stmt_targets->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Palo Alto Prisma CTF - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-layout {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .main-content {
            flex: 1;
            min-width: 300px;
        }
        .sidebar {
            width: 350px;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
            }
        }
        
        /* Freeze Overlay CSS */
        #freeze-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(10, 0, 0, 0.95);
            z-index: 9999;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #ff3860;
            text-align: center;
            font-family: monospace;
        }
        .freeze-glitch {
            font-size: 3rem;
            font-weight: bold;
            text-shadow: 0 0 10px #ff3860;
            margin-bottom: 1rem;
            animation: glitch 1s infinite;
        }
        #freeze-timer {
            font-size: 5rem;
            color: #fff;
        }
        @keyframes glitch {
            0% { transform: translate(0) }
            20% { transform: translate(-5px, 5px) }
            40% { transform: translate(-5px, -5px) }
            60% { transform: translate(5px, 5px) }
            80% { transform: translate(5px, -5px) }
            100% { transform: translate(0) }
        }
        
        select.target-select {
            width: 100%;
            padding: 0.5rem;
            background: rgba(0,0,0,0.5);
            color: #fff;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- FREEZE OVERLAY -->
    <div id="freeze-overlay">
        <div class="freeze-glitch">SYSTEM FROZEN BY HACKER</div>
        <p>VOTRE ÉCRAN EST BLOQUÉ. VEUILLEZ PATIENTER.</p>
        <div id="freeze-timer">00</div>
    </div>

    <nav class="nav">
        <a href="dashboard.php" class="nav-brand">Prisma CTF</a>
        <div class="nav-links">
            <span style="color: var(--primary); margin-right: 1rem; font-weight: bold; font-size: 1.1rem;">
                Mes Points: <?php echo $user['score_total']; ?> pts
            </span>
            <a href="dashboard.php" class="active">Challenges</a>
            <?php if ($_SESSION['is_admin']): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="logout.php">Déconnexion (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
        </div>
    </nav>

    <div class="container dashboard-layout">
        
        <!-- Left Side: Challenges -->
        <div class="main-content">
            <h2>Missions Prisma Browser</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">Trouvez les vulnérabilités, configurez PAB et récupérez les flags.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msg_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($events_data)): ?>
                <p style="color: var(--text-muted); text-align: center;">Aucun événement ou flag disponible pour le moment.</p>
            <?php endif; ?>

            <?php foreach ($events_data as $eid => $event): ?>
                <div class="glass-panel" style="margin-bottom: 3rem; padding: 1.5rem; background: rgba(0,0,0,0.4);">
                    <h2 style="color: var(--primary); margin-bottom: 0.5rem;"><?php echo htmlspecialchars($event['name']); ?></h2>
                    <?php if ($event['desc']): ?>
                        <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;"><?php echo htmlspecialchars($event['desc']); ?></p>
                    <?php endif; ?>

                    <div class="grid" style="margin-top: 1rem;">
                        <?php foreach ($event['challenges'] as $c): ?>
                            <div class="challenge-card <?php echo $c['solved'] ? 'challenge-solved' : ''; ?>">
                                <div class="challenge-header">
                                    <h3 class="challenge-title"><?php echo htmlspecialchars($c['title']); ?></h3>
                                    <span class="challenge-points"><?php echo $c['points']; ?> pts</span>
                                </div>
                                
                                <div class="challenge-desc">
                                    <?php echo nl2br(htmlspecialchars($c['description'])); ?>
                                </div>
                                
                                <?php if ($c['solved']): ?>
                                    <div style="text-align: center; color: var(--success); font-weight: bold; padding: 0.5rem; background: rgba(35, 209, 96, 0.1); border-radius: 4px;">
                                        ✓ Flag Validé
                                    </div>
                                <?php else: ?>
                                    <form method="POST" action="" style="margin-top: auto;">
                                        <input type="hidden" name="action" value="submit_flag">
                                        <input type="hidden" name="challenge_id" value="<?php echo $c['id']; ?>">
                                        <div class="form-group" style="margin-bottom: 0.5rem;">
                                            <input type="text" name="flag" placeholder="Format: flag{...}" required>
                                        </div>
                                        <button type="submit" class="btn btn-small" style="width: 100%;">Soumettre</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Right Side: Sidebar -->
        <div class="sidebar">
            
            <!-- ATTACK SHOP -->
            <div class="glass-panel" style="margin-bottom: 2rem; border-color: var(--error);">
                <h3 style="text-align: center; margin-bottom: 1rem; color: var(--error);">☠️ Arsenal Hacker</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; text-align: center;">Dépensez vos points pour geler l'écran de vos adversaires !</p>
                
                <form method="POST" action="" style="margin-bottom: 1rem;">
                    <input type="hidden" name="action" value="buy_attack">
                    <label style="font-size: 0.85rem; color: #fff;">Cible (Coût: <?php echo $FREEZE_SINGLE_COST; ?> pts)</label>
                    <select name="target_id" class="target-select" required>
                        <option value="">-- Choisir un Hacker --</option>
                        <?php foreach($attack_targets as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-small" style="width: 100%; border-color: var(--error); color: var(--error);">Geler le Hacker</button>
                </form>

                <hr style="border: 0; border-top: 1px solid rgba(255,56,96,0.3); margin: 1rem 0;">

                <form method="POST" action="" onsubmit="return confirm('Geler TOUT LE MONDE coûte <?php echo $FREEZE_ALL_COST; ?> points. Confirmer ?');">
                    <input type="hidden" name="action" value="buy_attack">
                    <input type="hidden" name="target_id" value="all">
                    <button type="submit" class="btn btn-small" style="width: 100%; background: rgba(255,56,96,0.1); border-color: var(--error); color: var(--error); font-weight: bold;">☢️ GELER TOUT LE MONDE (<?php echo $FREEZE_ALL_COST; ?> pts)</button>
                </form>
            </div>

            <!-- SCOREBOARD -->
            <div class="glass-panel" style="position: sticky; top: 2rem;">
                <h3 style="text-align: center; margin-bottom: 1.5rem;">🏆 Top Hackers</h3>
                <table style="margin-top: 0;">
                    <tbody id="scoreboard-body">
                        <?php 
                        $rank = 1;
                        foreach ($top_users as $tu): 
                            $rankClass = '';
                            if ($rank == 1) $rankClass = 'rank-1';
                            elseif ($rank == 2) $rankClass = 'rank-2';
                            elseif ($rank == 3) $rankClass = 'rank-3';
                        ?>
                            <tr>
                                <td class="<?php echo $rankClass; ?>" style="width: 40px; padding: 0.8rem 0.5rem;">
                                    <?php 
                                    if ($rank == 1) echo '🥇';
                                    elseif ($rank == 2) echo '🥈';
                                    elseif ($rank == 3) echo '🥉';
                                    else echo '#' . $rank; 
                                    ?>
                                </td>
                                <td style="padding: 0.8rem 0.5rem;">
                                    <strong class="<?php echo $rankClass; ?>"><?php echo htmlspecialchars($tu['username']); ?></strong>
                                </td>
                                <td style="text-align: right; padding: 0.8rem 0.5rem;">
                                    <span class="challenge-points" style="font-size: 0.8rem;"><?php echo $tu['score_total']; ?></span>
                                </td>
                            </tr>
                        <?php 
                        $rank++;
                        endforeach; 
                        ?>
                        <?php if(empty($top_users)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 1rem;">Aucun point attribué.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
    function escapeHtml(unsafe) {
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    function updateScoreboard() {
        fetch('api_scoreboard.php')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('scoreboard-body');
                if (data.length === 0) return;
                
                let html = '';
                data.forEach((user, index) => {
                    let rank = index + 1;
                    let rankClass = '';
                    let rankIcon = '#' + rank;
                    
                    if (rank === 1) { rankClass = 'rank-1'; rankIcon = '🥇'; }
                    else if (rank === 2) { rankClass = 'rank-2'; rankIcon = '🥈'; }
                    else if (rank === 3) { rankClass = 'rank-3'; rankIcon = '🥉'; }
                    
                    let safeUsername = escapeHtml(user.username);
                    let safeScore = escapeHtml(String(user.score_total));

                    html += `
                        <tr>
                            <td class="${rankClass}" style="width: 40px; padding: 0.8rem 0.5rem;">${rankIcon}</td>
                            <td style="padding: 0.8rem 0.5rem;"><strong class="${rankClass}">${safeUsername}</strong></td>
                            <td style="text-align: right; padding: 0.8rem 0.5rem;">
                                <span class="challenge-points" style="font-size: 0.8rem;">${safeScore}</span>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            })
            .catch(error => console.error(error));
    }

    // Freeze logic
    let freezeTimerInterval = null;

    function checkFreezeStatus() {
        fetch('api_status.php')
            .then(response => response.json())
            .then(data => {
                const overlay = document.getElementById('freeze-overlay');
                const timerDisplay = document.getElementById('freeze-timer');
                const glitchText = document.querySelector('.freeze-glitch');

                if (data.frozen && data.seconds_left > 0) {
                    let safeAttackerName = escapeHtml(data.attacker_name).toUpperCase();
                    glitchText.textContent = "SYSTEM FROZEN BY " + safeAttackerName;
                    
                    overlay.style.display = 'flex';
                    let timeLeft = data.seconds_left;
                    timerDisplay.textContent = timeLeft;

                    // Clear existing interval if any
                    if (freezeTimerInterval) clearInterval(freezeTimerInterval);

                    // Start local countdown
                    freezeTimerInterval = setInterval(() => {
                        timeLeft--;
                        if (timeLeft <= 0) {
                            clearInterval(freezeTimerInterval);
                            overlay.style.display = 'none';
                        } else {
                            timerDisplay.textContent = timeLeft;
                        }
                    }, 1000);
                } else {
                    overlay.style.display = 'none';
                    if (freezeTimerInterval) clearInterval(freezeTimerInterval);
                }
            })
            .catch(error => console.error(error));
    }

    // Actualisation du scoreboard toutes les 15s
    setInterval(updateScoreboard, 15000);
    
    // Vérification du gel toutes les 3 secondes pour plus de réactivité
    setInterval(checkFreezeStatus, 3000);
    // Première vérification immédiate
    checkFreezeStatus();
    </script>
</body>
</html>
