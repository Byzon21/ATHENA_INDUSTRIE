<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(prix_cure) as ca FROM clients");
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'ca' => 0];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Athena Industrie</title>
    <!-- Google Fonts pour un look moderne -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #b8860b;
            --secondary: #2c3e50;
            --accent: #d4a017;
            --danger: #e74c3c;
            --bg-gradient: linear-gradient(135deg, #1a1a1a 0%, #2c3e50 100%);
            --card-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--secondary);
        }

        .main-container {
            width: 95%;
            max-width: 1100px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 50px;
            box-shadow: var(--card-shadow);
            opacity: 0; /* Pour l'animation JS */
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.2, 1, 0.3, 1);
        }

        .main-container.active {
            opacity: 1;
            transform: translateY(0);
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(0,0,0,0.05);
        }

        header h2 { font-size: 28px; margin: 0; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; }
        .user-badge {
            background: var(--secondary);
            color: white;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 14px;
        }

        /* Grille des statistiques */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .stat-card span.label { font-size: 13px; color: #95a5a6; text-transform: uppercase; letter-spacing: 1px; }
        .stat-card span.value { font-size: 36px; font-weight: 800; display: block; margin-top: 10px; }

        /* Actions Principales */
        .action-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 50px;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
            border-radius: 18px;
            font-weight: 700;
            font-size: 17px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            gap: 12px;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: var(--secondary); color: white; }
        .btn-accent { background: var(--accent); color: var(--secondary); }

        .btn:hover {
            filter: brightness(1.1);
            transform: scale(1.02);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        /* Logistique */
        .logistique-section { margin-top: 20px; }
        .logistique-section h3 { margin-bottom: 25px; font-weight: 700; }

        .log-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .log-card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            text-align: center;
            text-decoration: none;
            color: var(--secondary);
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .log-card:hover {
            border-color: var(--primary);
            background: #f0fff4;
        }

        .log-card .icon { font-size: 40px; margin-bottom: 10px; display: block; }

        .logout {
            display: block;
            text-align: center;
            margin-top: 50px;
            color: var(--danger);
            font-weight: 700;
            text-decoration: none;
            opacity: 0.7;
        }
        .logout:hover { opacity: 1; text-decoration: underline; }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .main-container { padding: 30px 20px; }
            header { flex-direction: column; gap: 15px; text-align: center; }
        }
    </style>
</head>
<body>

<div class="main-container" id="app">
    <header>
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="../IMG/athena.jpg" alt="Logo" style="width: 60px; border-radius: 10px; border: 1px solid var(--primary);">
            <h2>Athena Industrie</h2>
        </div>
        <div class="user-badge">
            👤 <?php echo htmlspecialchars($_SESSION['username']); ?>
        </div>
    </header>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card" style="border-top: 4px solid var(--secondary);">
            <span class="label">Clients Total</span>
            <span class="value" style="color: var(--secondary);"><?php echo $stats['total']; ?></span>
        </div>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="stat-card" style="background: linear-gradient(135deg, #b8860b 0%, #8d6708 100%); border: none;">
            <span class="label" style="color: rgba(255,255,255,0.8);">Chiffre d'Affaires</span>
            <span class="value" style="color: white;"><?php echo number_format($stats['ca'] ?? 0, 2); ?>$</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Actions Rapides -->
    <div class="action-group">
        <a href="add_clients.php" class="btn btn-primary">➕ Nouveau Client</a>
        <a href="list_clients.php" class="btn btn-secondary">📂 Liste des Clients</a>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="rapport.php" class="btn btn-accent">📊 Rapport Financier</a>
        <?php endif; ?>
    </div>

    <!-- Logistique -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="logistique-section">
        <h3>📦 Gestion Logistique</h3>
        <div class="log-grid">
            <a href="stocks.php" class="log-card">
                <span class="icon">📈</span>
                <strong>État des Stocks</strong>
                <p style="font-size: 12px; color: #7f8c8d; margin-top: 5px;">Inventaire en temps réel</p>
            </a>
            <a href="update_stock.php" class="log-card">
                <span class="icon">🚚</span>
                <strong>Nouvel Arrivage</strong>
                <p style="font-size: 12px; color: #7f8c8d; margin-top: 5px;">Réapprovisionner le stock</p>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <a href="logout.php" class="logout">Se déconnecter</a>
</div>

<script>
    // Animation d'entrée progressive
    document.addEventListener('DOMContentLoaded', () => {
        const app = document.getElementById('app');
        setTimeout(() => {
            app.classList.add('active');
        }, 100);
    });

    // Effet d'interaction subtile sur les cartes de logistique
    const cards = document.querySelectorAll('.log-card, .stat-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            // On peut ajouter ici un petit effet sonore ou une vibration pour mobile
            if ('vibrate' in navigator) navigator.vibrate(5);
        });
    });
</script>

</body>
</html>