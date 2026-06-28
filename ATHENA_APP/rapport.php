<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 1. Gestion des filtres de période
$period = $_GET['period'] ?? 'all';
$whereClause = "";
$titleLabel = "Global";

switch ($period) {
    case 'day':
        $whereClause = "WHERE date_enregistrement >= date_trunc('day', now())";
        $titleLabel = "Journalier";
        break;
    case 'week':
        $whereClause = "WHERE date_enregistrement >= date_trunc('week', now())";
        $titleLabel = "Hebdomadaire";
        break;
    case 'month':
        $whereClause = "WHERE date_enregistrement >= date_trunc('month', now())";
        $titleLabel = "Mensuel";
        break;
    case 'quarter':
        $whereClause = "WHERE date_enregistrement >= date_trunc('quarter', now())";
        $titleLabel = "Trimestriel";
        break;
    case 'year':
        $whereClause = "WHERE date_enregistrement >= date_trunc('year', now())";
        $titleLabel = "Annuel";
        break;
    default:
        $whereClause = "";
        $titleLabel = "Global";
        break;
}

// 2. Calcul du Chiffre d'Affaires (CA) filtré
$totalCA = $pdo->query("SELECT SUM(prix_cure) FROM clients $whereClause")->fetchColumn();

// 3. Ventes par utilisateur filtrées
$ventesParPoste = $pdo->query("SELECT cree_par, COUNT(*) as nb_ventes, SUM(prix_cure) as total FROM clients $whereClause GROUP BY cree_par ORDER BY total DESC")->fetchAll();

// 4. Transactions détaillées de la période
$whereClauseC = str_replace('date_enregistrement', 'c.date_enregistrement', $whereClause);
$queryTransactions = "
    SELECT 
        c.id, c.nom_prenom, c.prix_cure as total, c.cree_par, c.date_enregistrement,
        STRING_AGG(s.nom_produit, ', ') as cures,
        COALESCE(SUM(s.prix_vente), 0) as total_produits
    FROM clients c
    LEFT JOIN client_produits cp ON c.id = cp.client_id
    LEFT JOIN stocks s ON cp.produit_id = s.id
    $whereClauseC
    GROUP BY c.id, c.nom_prenom, c.prix_cure, c.cree_par, c.date_enregistrement
    ORDER BY c.date_enregistrement DESC
";
$transactions = $pdo->query($queryTransactions)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport Financier - Athena Industrie</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #27ae60;
            --secondary: #2c3e50;
            --bg: #f4f7fa;
            --white: #ffffff;
            --text-main: #1a202c;
            --text-muted: #718096;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease-out;
        }

        .container.active {
            opacity: 1;
            transform: translateY(0);
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .header-title h2 { font-size: 28px; font-weight: 800; margin: 0; }
        
        .nav-buttons { display: flex; gap: 12px; align-items: center; }

        .btn {
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-home { background: var(--white); color: var(--secondary); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .btn-pdf { background: #e74c3c; color: white; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3); }
        .btn:hover { transform: translateY(-2px); filter: brightness(1.1); }

        /* Filtres de période */
        .period-selector { display: flex; gap: 10px; margin-bottom: 30px; background: white; padding: 10px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); justify-content: center; }
        .period-link { 
            padding: 8px 16px; border-radius: 10px; text-decoration: none; color: var(--text-muted); 
            font-size: 13px; font-weight: 600; transition: 0.3s;
        }
        .period-link.active { background: #b8860b; color: white; }
        .period-link:hover:not(.active) { background: #fcf8ee; color: #b8860b; }

        /* Ajustement CA Card pour le thème Or */
        .ca-card {
            background: linear-gradient(135deg, #b8860b 0%, #8d6708 100%);
            box-shadow: 0 20px 40px rgba(184, 134, 11, 0.2);
        }

        /* Carte CA Hero */
        .ca-card {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 40px;
            border-radius: 24px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: 0 20px 40px rgba(39, 174, 96, 0.2);
            position: relative;
            overflow: hidden;
        }

        .ca-card::before {
            content: '';
            position: absolute;
            top: -50px; right: -50px;
            width: 150px; height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .ca-card span { text-transform: uppercase; font-size: 13px; font-weight: 700; letter-spacing: 2px; opacity: 0.9; }
        .ca-card h1 { font-size: 48px; margin: 10px 0 0 0; font-weight: 800; }

        /* Sections Grid */
        .reports-grid {
            display: grid;
            grid-template-columns: 1fr 2.2fr;
            gap: 30px;
        }

        .section-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        /* Performance Poste */
        .card-list { display: flex; flex-direction: column; gap: 15px; }
        
        .poste-item {
            background: var(--white);
            padding: 20px;
            border-radius: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .poste-info strong { font-size: 16px; color: var(--secondary); }
        .poste-info p { margin: 4px 0 0 0; color: var(--text-muted); font-size: 13px; }
        .poste-amount { font-weight: 800; color: var(--primary); font-size: 18px; }

        /* Tableau Transactions */
        .table-container {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.05);
        }

        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 16px; text-align: left; font-size: 12px; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; }
        td { padding: 16px; border-top: 1px solid #f1f5f9; font-size: 14px; }
        
        .badge-poste {
            background: #edf2f7;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            color: var(--secondary);
        }

        @media (max-width: 850px) {
            .reports-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container" id="reportView">
    <header>
        <div class="header-title">
            <h2>Finances</h2>
        </div>
        <div class="nav-buttons">
            <a href="export_pdf.php?period=<?= $period ?>" class="btn btn-pdf">📄 PDF</a>
            <a href="index.php" class="btn btn-home">🏠 Accueil</a>
        </div>
    </header>

    <!-- Sélecteur de Période -->
    <div class="period-selector">
        <a href="?period=all" class="period-link <?= $period == 'all' ? 'active' : '' ?>">Global</a>
        <a href="?period=day" class="period-link <?= $period == 'day' ? 'active' : '' ?>">Aujourd'hui</a>
        <a href="?period=week" class="period-link <?= $period == 'week' ? 'active' : '' ?>">Hebdo</a>
        <a href="?period=month" class="period-link <?= $period == 'month' ? 'active' : '' ?>">Mensuel</a>
        <a href="?period=quarter" class="period-link <?= $period == 'quarter' ? 'active' : '' ?>">Trimestriel</a>
        <a href="?period=year" class="period-link <?= $period == 'year' ? 'active' : '' ?>">Annuel</a>
    </div>

    <!-- Carte CA Total -->
    <div class="ca-card">
        <span>Recettes Totales (<?= $titleLabel ?>)</span>
        <h1><?php echo number_format($totalCA ?? 0, 2); ?> $</h1>
    </div>

    <div class="reports-grid">
        <!-- Colonne Gauche : Performance -->
        <div>
            <div class="section-title">📊 Par Poste</div>
            <div class="card-list">
                <?php foreach ($ventesParPoste as $v): ?>
                <div class="poste-item">
                    <div class="poste-info">
                        <strong><?php echo htmlspecialchars($v['cree_par']); ?></strong>
                        <p><?php echo $v['nb_ventes']; ?> transactions</p>
                    </div>
                    <div class="poste-amount">+<?php echo number_format($v['total'], 2); ?>$</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Colonne Droite : Historique -->
        <div>
            <div class="section-title">🕒 Transactions de la période</div>
            <div class="table-container" style="overflow-x: auto;">
                <table style="min-width: 600px;">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Cure(s)</th>
                            <th>Consultation</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): 
                            $frais_consultation = $t['total'] - $t['total_produits'];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($t['nom_prenom']); ?></strong></td>
                            <td><span style="font-size: 13px; color: var(--text-muted);"><?php echo htmlspecialchars($t['cures'] ?: '-'); ?></span></td>
                            <td style="color: var(--secondary); font-weight: 600;"><?php echo number_format($frais_consultation, 2); ?>$</td>
                            <td style="color: var(--primary); font-weight: 700;"><?php echo number_format($t['total'], 2); ?>$</td>
                            <td><span style="font-size: 12px; color: var(--text-muted);"><?php echo date('d/m/Y H:i', strtotime($t['date_enregistrement'])); ?></span></td>
                            <td><span class="badge-poste"><?php echo htmlspecialchars($t['cree_par']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            document.getElementById('reportView').classList.add('active');
        }, 100);
    });
</script>

</body>
</html>