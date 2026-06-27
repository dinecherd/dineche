<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOC - Prisma CTF Live Command Center</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Share Tech Mono', monospace;
        }

        body {
            background-color: #050a10;
            color: #00e5ff;
            height: 100vh;
            overflow: hidden;
            background-image: 
                linear-gradient(rgba(0, 229, 255, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 229, 255, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: backgroundPan 20s linear infinite;
        }

        @keyframes backgroundPan {
            from { background-position: 0 0; }
            to { background-position: -40px -40px; }
        }

        @keyframes shake {
            0% { transform: translate(1px, 1px) rotate(0deg); }
            10% { transform: translate(-1px, -2px) rotate(-1deg); }
            20% { transform: translate(-3px, 0px) rotate(1deg); }
            30% { transform: translate(3px, 2px) rotate(0deg); }
            40% { transform: translate(1px, -1px) rotate(1deg); }
            50% { transform: translate(-1px, 2px) rotate(-1deg); }
            60% { transform: translate(-3px, 1px) rotate(0deg); }
            70% { transform: translate(3px, 1px) rotate(-1deg); }
            80% { transform: translate(-1px, -1px) rotate(1deg); }
            90% { transform: translate(1px, 2px) rotate(0deg); }
            100% { transform: translate(1px, -2px) rotate(-1deg); }
        }

        .global-shake {
            animation: shake 0.5s infinite;
            filter: hue-rotate(-50deg) saturate(200%);
        }

        .header {
            text-align: center;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.8);
            border-bottom: 2px solid #00e5ff;
            box-shadow: 0 0 20px rgba(0, 229, 255, 0.4);
            position: relative;
            z-index: 10;
        }

        .header h1 {
            font-size: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 5px;
            text-shadow: 0 0 10px #00e5ff;
        }
        
        .header a {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #b338ff;
            text-decoration: none;
            border: 1px solid #b338ff;
            padding: 5px 15px;
            border-radius: 4px;
            transition: 0.3s;
        }
        .header a:hover {
            background: #b338ff;
            color: #fff;
            box-shadow: 0 0 15px #b338ff;
        }

        .layout {
            display: flex;
            height: calc(100vh - 80px);
            padding: 2rem;
            gap: 2rem;
        }

        .panel {
            background: rgba(0, 10, 20, 0.85);
            border: 1px solid #00e5ff;
            border-radius: 8px;
            box-shadow: inset 0 0 20px rgba(0, 229, 255, 0.1), 0 0 15px rgba(0, 229, 255, 0.2);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .panel::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 2px;
            background: #00e5ff;
            box-shadow: 0 0 15px #00e5ff;
        }

        .panel-left {
            flex: 1;
        }

        .panel-right {
            flex: 1.5;
        }

        h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            border-bottom: 1px solid rgba(0, 229, 255, 0.3);
            padding-bottom: 0.5rem;
        }

        /* Leaderboard Styles */
        .leaderboard-list {
            list-style: none;
            overflow-y: auto;
        }

        .player-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin-bottom: 0.8rem;
            background: rgba(0, 229, 255, 0.05);
            border-left: 4px solid #00e5ff;
            transition: all 0.5s ease;
        }

        .player-row:hover {
            background: rgba(0, 229, 255, 0.15);
            transform: translateX(10px);
        }

        .player-rank {
            font-size: 1.5rem;
            font-weight: bold;
            width: 40px;
        }
        
        .rank-1 { color: #ffd700; border-color: #ffd700; text-shadow: 0 0 10px #ffd700; }
        .rank-2 { color: #c0c0c0; border-color: #c0c0c0; text-shadow: 0 0 10px #c0c0c0; }
        .rank-3 { color: #cd7f32; border-color: #cd7f32; text-shadow: 0 0 10px #cd7f32; }

        .player-name {
            flex: 1;
            font-size: 1.2rem;
            text-transform: uppercase;
        }

        .player-score {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fff;
        }

        /* Attack Console Styles */
        .attack-console {
            flex: 1;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid #ff3860;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .alert-box {
            padding: 1rem;
            border: 1px solid;
            border-radius: 4px;
            animation: pulse 2s infinite;
        }

        /* Targeted Attack Terminal Style */
        .alert-targeted {
            background: rgba(0, 10, 0, 0.9);
            border: 2px solid #00ff00;
            color: #00ff00;
            box-shadow: inset 0 0 10px rgba(0, 255, 0, 0.3), 0 0 15px rgba(0, 255, 0, 0.4);
            font-family: 'Courier New', Courier, monospace;
            position: relative;
            overflow: hidden;
            text-shadow: 0 0 5px #00ff00;
        }

        .alert-targeted::before {
            content: '';
            position: absolute; top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 255, 0, 0.2), transparent);
            animation: scanline 2s linear infinite;
        }

        @keyframes scanline {
            100% { left: 200%; }
        }

        /* Matrix Overlay for Global Attack */
        #matrix-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(5, 0, 0, 0.95);
            z-index: 9999;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            color: #ff3860;
        }
        #matrix-overlay.hidden { display: none; }

        .glitch-text {
            font-size: 6rem;
            font-weight: bold;
            text-shadow: 0 0 20px #ff3860, -2px -2px 0px #00ffff;
            animation: intenseGlitch 0.1s infinite;
        }
        @keyframes intenseGlitch {
            0% { transform: translate(0) }
            20% { transform: translate(-10px, 10px) }
            40% { transform: translate(-10px, -10px) }
            60% { transform: translate(10px, 10px) }
            80% { transform: translate(10px, -10px) }
            100% { transform: translate(0) }
        }
        .matrix-sub {
            font-size: 2rem;
            margin-top: 2rem;
            letter-spacing: 15px;
            animation: flash 1s infinite;
            text-align: center;
        }

        .alert-global {
            background: rgba(179, 56, 255, 0.1);
            border-color: #b338ff;
            color: #b338ff;
            box-shadow: 0 0 20px rgba(179, 56, 255, 0.6);
            animation: fastPulse 0.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 0.8; box-shadow: 0 0 10px inset; }
            50% { opacity: 1; box-shadow: 0 0 20px inset; }
            100% { opacity: 0.8; box-shadow: 0 0 10px inset; }
        }

        @keyframes fastPulse {
            0% { opacity: 0.5; }
            50% { opacity: 1; }
            100% { opacity: 0.5; }
        }

        .alert-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
        }

        .alert-body {
            font-size: 1rem;
        }

        .radar {
            position: absolute;
            right: 10px;
            top: 10px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 1px solid rgba(0, 229, 255, 0.5);
            overflow: hidden;
        }
        .radar::after {
            content: '';
            position: absolute;
            width: 50%;
            height: 50%;
            background: linear-gradient(45deg, rgba(0, 229, 255, 0.8), transparent);
            top: 0; left: 50%;
            transform-origin: bottom left;
            animation: radarSpin 2s linear infinite;
        }
        @keyframes radarSpin {
            100% { transform: rotate(360deg); }
        }
        
        .no-activity {
            text-align: center;
            color: rgba(0, 229, 255, 0.3);
            font-style: italic;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div id="matrix-overlay" class="hidden">
        <div class="glitch-text">☢️ SYSTEM OVERRIDE ☢️</div>
        <div class="matrix-sub">
            CRITICAL FAILURE<br>ALL NODES FROZEN BY <span id="global-attacker"></span><br><br>
            <span id="global-timer" style="font-size: 4rem; color: #fff; text-shadow: 0 0 15px #fff;"></span>
        </div>
    </div>

    <div class="header">
        <a href="admin.php">◀ Retour Admin</a>
        <h1>SOC - LIVE COMMAND CENTER</h1>
    </div>

    <div class="layout">
        <!-- SCOREBOARD -->
        <div class="panel panel-left">
            <h2>🏆 LIVE SCOREBOARD</h2>
            <ul class="leaderboard-list" id="live-scores">
                <p class="no-activity">Chargement des données...</p>
            </ul>
        </div>

        <!-- ATTACK CONSOLE -->
        <div class="panel panel-right">
            <h2>🚨 CYBER WARFARE CONSOLE</h2>
            <div class="radar"></div>
            <div class="attack-console" id="live-attacks">
                <p class="no-activity">Analyse du trafic réseau... Aucun gel détecté.</p>
            </div>
        </div>
    </div>

    <script>
        let localGlobalTimer = null;
        let currentGlobalSeconds = 0;

        function escapeHtml(unsafe) {
            if(!unsafe) return '';
            return unsafe
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }

        function updateDashboard() {
            fetch('api_admin_live.php')
                .then(response => response.json())
                .then(data => {
                    // Update Scores
                    const scoresContainer = document.getElementById('live-scores');
                    if (data.top_users.length === 0) {
                        scoresContainer.innerHTML = '<p class="no-activity">Aucun joueur enregistré.</p>';
                    } else {
                        let scoreHtml = '';
                        data.top_users.forEach((user, index) => {
                            let rank = index + 1;
                            let rankClass = '';
                            let rankIcon = rank;
                            
                            if (rank === 1) { rankClass = 'rank-1'; rankIcon = '🥇'; }
                            else if (rank === 2) { rankClass = 'rank-2'; rankIcon = '🥈'; }
                            else if (rank === 3) { rankClass = 'rank-3'; rankIcon = '🥉'; }
                            
                            scoreHtml += `
                                <li class="player-row ${rankClass}">
                                    <span class="player-rank">${rankIcon}</span>
                                    <span class="player-name">${escapeHtml(user.username)}</span>
                                    <span class="player-score">${user.score_total} PTS</span>
                                </li>
                            `;
                        });
                        scoresContainer.innerHTML = scoreHtml;
                    }

                    // Update Attacks
                    const attacksContainer = document.getElementById('live-attacks');
                    if (data.freezes.length === 0) {
                        attacksContainer.innerHTML = '<p class="no-activity">Analyse du trafic réseau... Aucun gel détecté.</p>';
                        document.body.classList.remove('global-shake');
                        const matrixOverlay = document.getElementById('matrix-overlay');
                        if (matrixOverlay) matrixOverlay.classList.add('hidden');
                        if (localGlobalTimer) {
                            clearInterval(localGlobalTimer);
                            localGlobalTimer = null;
                        }
                    } else {
                        let attackHtml = '';
                        let hasGlobal = false;
                        let globalAttackerName = '';
                        let globalFreezeSeconds = 0;

                        data.freezes.forEach(freeze => {
                            let isGlobal = freeze.target_user_id === null;
                            let attackerName = escapeHtml(freeze.attacker_name).toUpperCase();
                            
                            if (isGlobal) {
                                hasGlobal = true;
                                globalAttackerName = attackerName;
                                globalFreezeSeconds = freeze.seconds_left;
                                attackHtml += `
                                    <div class="alert-box alert-global">
                                        <div class="alert-title">
                                            <span>☢️ MASSIVE BREACH</span>
                                            <span>${freeze.seconds_left}s</span>
                                        </div>
                                        <div class="alert-body">
                                            <strong>${attackerName}</strong> HAS FROZEN THE ENTIRE NETWORK!
                                        </div>
                                    </div>
                                `;
                            } else {
                                let targetName = escapeHtml(freeze.target_name).toUpperCase();
                                attackHtml += `
                                    <div class="alert-box alert-targeted">
                                        <div style="font-weight: bold; margin-bottom: 5px;">> EXECUTING PAYLOAD...</div>
                                        <div style="color: #fff; margin-bottom: 5px;">> TARGET LOCK : [${targetName}]</div>
                                        <div>> FREEZE SEQUENCE INITIATED BY [${attackerName}]</div>
                                        <div style="text-align: right; margin-top: 10px; font-weight: bold;">T-MINUS ${freeze.seconds_left}s</div>
                                    </div>
                                `;
                            }
                        });
                        attacksContainer.innerHTML = attackHtml;

                        const matrixOverlay = document.getElementById('matrix-overlay');
                        const globalAttackerSpan = document.getElementById('global-attacker');
                        const globalTimerSpan = document.getElementById('global-timer');

                        // Trigger visual shake and matrix overlay if global attack is active
                        if (hasGlobal) {
                            document.body.classList.add('global-shake');
                            matrixOverlay.classList.remove('hidden');
                            globalAttackerSpan.textContent = globalAttackerName;
                            
                            // Synchronize our local counter with the server
                            currentGlobalSeconds = globalFreezeSeconds;
                            globalTimerSpan.textContent = "T-MINUS " + currentGlobalSeconds + "s";
                            
                            if (!localGlobalTimer) {
                                localGlobalTimer = setInterval(() => {
                                    if (currentGlobalSeconds > 0) {
                                        currentGlobalSeconds--;
                                        globalTimerSpan.textContent = "T-MINUS " + currentGlobalSeconds + "s";
                                    }
                                }, 1000);
                            }
                        } else {
                            document.body.classList.remove('global-shake');
                            matrixOverlay.classList.add('hidden');
                            if (localGlobalTimer) {
                                clearInterval(localGlobalTimer);
                                localGlobalTimer = null;
                            }
                        }
                    }
                })
                .catch(error => console.error('Erreur API Live:', error));
        }

        // Poll every 2 seconds for intense live feeling
        setInterval(updateDashboard, 2000);
        updateDashboard();
    </script>
</body>
</html>
