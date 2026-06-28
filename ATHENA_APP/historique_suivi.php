<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['client_id'])) {
    header("Location: list_clients.php");
    exit();
}

$client_id = (int)$_GET['client_id'];

// Infos client
$stmtC = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmtC->execute([$client_id]);
$client = $stmtC->fetch();

if (!$client) {
    header("Location: list_clients.php");
    exit();
}

// Suppression d'une entrée de suivi (admin uniquement)
if (isset($_GET['delete_id']) && $_SESSION['role'] === 'admin') {
    $delete_id = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM suivi_progression WHERE id = ? AND client_id = ?")
        ->execute([$delete_id, $client_id]);
    header("Location: historique_suivi.php?client_id=$client_id&msg=Entrée supprimée.");
    exit();
}

// Récupération de TOUT l'historique
$stmtH = $pdo->prepare("SELECT * FROM suivi_progression WHERE client_id = ? ORDER BY date_consultation DESC, id DESC");
$stmtH->execute([$client_id]);
$historique = $stmtH->fetchAll();

// Statistiques
$stmtStats = $pdo->prepare("SELECT 
    MIN(poids_mesure) AS poids_min,
    MAX(poids_mesure) AS poids_max,
    MIN(imc) AS imc_min,
    MAX(imc) AS imc_max,
    COUNT(*) AS total_consultations
FROM suivi_progression WHERE client_id = ?");
$stmtStats->execute([$client_id]);
$stats = $stmtStats->fetch();

// Évolution entre première et dernière mesure
$stmtFirst = $pdo->prepare("SELECT poids_mesure, tour_taille, date_consultation FROM suivi_progression WHERE client_id = ? ORDER BY date_consultation ASC, id ASC LIMIT 1");
$stmtFirst->execute([$client_id]);
$first = $stmtFirst->fetch();

$last = $historique[0] ?? null;
$evolution_poids = null;
$evolution_taille = null;
if ($first && $last) {
    $evolution_poids = ($last['poids_mesure'] ?? 0) - ($first['poids_mesure'] ?? 0);
    $evolution_taille = ($last['tour_taille'] ?? 0) - ($first['tour_taille'] ?? 0);
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique - <?= htmlspecialchars($client['nom_prenom']) ?> — Athena Industrie</title>
    <link rel="stylesheet" href="css/athena.css">
    <style>
        body {
            background: var(--bg);
            padding: 30px 15px;
            margin: 0;
            display: flex;
            justify-content: center;
        }
        .page-wrapper { width: 100%; max-width: 960px; }

        /* En-tête client */
        .client-header {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .client-header h2 { margin: 0; font-size: 20px; color: var(--secondary); }
        .client-info { font-size: 13px; color: var(--text-muted); }
        .client-actions { display: flex; gap: 8px; }

        /* Cartes de stats */
        .stats-evolution {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .stat-evo-card {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 16px;
            text-align: center;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
        }
        .stat-evo-card .stat-icon { font-size: 22px; margin-bottom: 4px; }
        .stat-evo-card .stat-value {
            font-size: 22px;
            font-weight: 800;
            color: var(--secondary);
        }
        .stat-evo-card .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }
        .stat-evo-card .stat-diff {
            font-size: 14px;
            font-weight: 700;
            margin-top: 4px;
        }
        .stat-evo-card .stat-diff.positive { color: var(--danger); }
        .stat-evo-card .stat-diff.negative { color: var(--primary); }
        .stat-evo-card .stat-diff.neutral { color: var(--text-muted); }

        /* Tableau */
        .suivi-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .suivi-table th {
            padding: 11px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            background: var(--primary-bg);
            border-bottom: 2px solid var(--border);
        }
        .suivi-table td {
            padding: 11px 10px;
            border-top: 1px solid var(--border-light);
            vertical-align: middle;
        }
        .suivi-table tbody tr:hover {
            background: #f8fafc;
        }
        .suivi-table .difference {
            font-weight: 700;
        }
        .suivi-table .difference.up { color: var(--danger); }
        .suivi-table .difference.down { color: var(--primary); }
        .suivi-table .difference.same { color: var(--text-muted); }

        /* Badge d'évolution */
        .evo-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            font-weight: 700;
        }

        /* Ligne du temps */
        .timeline-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 4px;
        }
        .timeline-dot.first { background: var(--primary); }
        .timeline-dot.latest { background: var(--accent); }

        /* Notes tooltip */
        .notes-preview {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: help;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }
        .empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; }

        @media (max-width: 768px) {
            .suivi-table { font-size: 12px; }
            .suivi-table th, .suivi-table td { padding: 8px 6px; }
            .stats-evolution { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="page-wrapper">

    <!-- En-tête -->
    <div class="page-header">
        <h2>
            <span>📂</span> Historique des Suivis
        </h2>
        <div class="header-actions">
            <a href="add_suivi.php?client_id=<?= $client_id ?>" class="btn btn-primary">➕ Nouveau suivi</a>
            <a href="fiche_client.php?id=<?= $client_id ?>" class="btn btn-ghost">👤 Fiche</a>
        </div>
    </div>

    <!-- Message flash -->
    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Bannière client -->
    <div class="client-header">
        <div>
            <h2>👤 <?= htmlspecialchars($client['nom_prenom']) ?></h2>
            <div class="client-info">
                <?php if ($client['sexe'] === 'F'): ?>Femme<?php else: ?>Homme<?php endif; ?>
                — <?= (int)$client['age'] ?> ans
                — 📞 <?= htmlspecialchars($client['telephone'] ?? 'N/R') ?>
                — Objectif : <?= number_format($client['poids_objectif'] ?? 0, 1) ?> kg
                — Poids actuel : <strong><?= number_format($client['poids_actuel'] ?? 0, 1) ?> kg</strong>
            </div>
        </div>
        <div class="client-actions">
            <a href="add_suivi.php?client_id=<?= $client_id ?>" class="btn btn-primary" style="padding:8px 16px;font-size:13px;">
                ➕ Suivi
            </a>
            <a href="list_clients.php" class="btn btn-ghost" style="padding:8px 16px;font-size:13px;">
                ← Clients
            </a>
        </div>
    </div>

    <?php if (empty($historique)): ?>
        <!-- État vide -->
        <div class="card empty-state">
            <div class="empty-icon">📭</div>
            <h3 style="color:var(--secondary);">Aucun suivi enregistré</h3>
            <p style="color:var(--text-muted);">Ce client n'a pas encore de consultation de suivi.</p>
            <a href="add_suivi.php?client_id=<?= $client_id ?>" class="btn btn-primary" style="margin-top:10px;">
                ➕ Enregistrer la première consultation
            </a>
        </div>
    <?php else: ?>

        <!-- Statistiques d'évolution -->
        <div class="stats-evolution">
            <div class="stat-evo-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?= (int)$stats['total_consultations'] ?></div>
                <div class="stat-label">Consultations</div>
            </div>
            <div class="stat-evo-card">
                <div class="stat-icon">⚖️</div>
                <div class="stat-value"><?= number_format($stats['poids_min'] ?? 0, 1) ?> kg</div>
                <div class="stat-label">Poids min</div>
                <div class="stat-value" style="font-size:16px;"><?= number_format($stats['poids_max'] ?? 0, 1) ?> kg</div>
                <div class="stat-label">Poids max</div>
            </div>
            <div class="stat-evo-card">
                <div class="stat-icon">🧮</div>
                <div class="stat-value"><?= number_format($stats['imc_min'] ?? 0, 1) ?></div>
                <div class="stat-label">IMC min</div>
                <div class="stat-value" style="font-size:16px;"><?= number_format($stats['imc_max'] ?? 0, 1) ?></div>
                <div class="stat-label">IMC max</div>
            </div>
            <div class="stat-evo-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value">
                    <?php if ($evolution_poids !== null): ?>
                        <?= number_format($evolution_poids, 1) ?> kg
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
                <div class="stat-label">Évolution totale</div>
                <div class="stat-diff <?= $evolution_poids !== null ? ($evolution_poids < 0 ? 'negative' : ($evolution_poids > 0 ? 'positive' : 'neutral')) : 'neutral' ?>">
                    <?php if ($evolution_poids !== null): ?>
                        <?php if ($evolution_poids < 0): ?>⬇️ Perte<?php elseif ($evolution_poids > 0): ?>⬆️ Prise<?php else: ?>➡️ Stable<?php endif; ?>
                    <?php endif; ?>
                    <?php if ($first): ?>
                        <br><small style="font-weight:400;">Depuis le <?= date('d/m/Y', strtotime($first['date_consultation'])) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tableau complet -->
        <div class="table-wrapper">
            <table class="suivi-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>📅 Date</th>
                        <th>⚖️ Poids</th>
                        <th>📐 Taille</th>
                        <th>📐 Tour taille</th>
                        <th>📐 Tour hanches</th>
                        <th>🧮 IMC</th>
                        <th>🔥 Masse gr.</th>
                        <th>💪 Masse musc.</th>
                        <th>❤️ Tension</th>
                        <th>🏃 Activité</th>
                        <th>👤 Par</th>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <th style="text-align:right;">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $prev_poids = null;
                    $prev_taille = null;
                    foreach ($historique as $i => $h): 
                        $row_class = ($i === 0) ? 'style="background:#fffbeb;"' : '';
                    ?>
                    <tr <?= $row_class ?>>
                        <td style="font-weight:600;color:var(--text-muted);">
                            <?= $i === 0 ? '🔵' : ($i === count($historique)-1 ? '🟢' : '') ?>
                            <?= count($historique) - $i ?>
                        </td>
                        <td>
                            <strong><?= date('d/m/Y', strtotime($h['date_consultation'])) ?></strong>
                            <?php if ($i === 0): ?>
                                <br><small style="color:var(--accent);font-weight:600;">Dernier</small>
                            <?php elseif ($i === count($historique)-1): ?>
                                <br><small style="color:var(--primary);font-weight:600;">Premier</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= number_format($h['poids_mesure'], 1) ?></strong> kg
                            <?php if ($prev_poids !== null && $h['poids_mesure'] !== null): 
                                $diff = $h['poids_mesure'] - $prev_poids; ?>
                                <br><span class="difference <?= $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'same') ?>">
                                    <?= $diff > 0 ? '+' : '' ?><?= number_format($diff, 1) ?> kg
                                </span>
                            <?php endif; $prev_poids = $h['poids_mesure']; ?>
                        </td>
                        <td><?= (int)$h['taille_mesure'] ?: '—' ?> cm</td>
                        <td><?= $h['tour_taille'] ? number_format($h['tour_taille'], 1) . ' cm' : '—' ?></td>
                        <td><?= $h['tour_hanches'] ? number_format($h['tour_hanches'], 1) . ' cm' : '—' ?></td>
                        <td><strong><?= number_format($h['imc'], 1) ?></strong></td>
                        <td><?= $h['masse_graisseuse'] ? number_format($h['masse_graisseuse'], 1) . '%' : '—' ?></td>
                        <td><?= $h['masse_musculaire'] ? number_format($h['masse_musculaire'], 1) . '%' : '—' ?></td>
                        <td><?= htmlspecialchars($h['tension_arterielle'] ?? '—') ?></td>
                        <td style="text-align:center;"><?= number_format($h['note_activite'], 0) ?>/10</td>
                        <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($h['enregistre_par'] ?? '—') ?></td>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <td style="text-align:right;">
                            <a href="historique_suivi.php?client_id=<?= $client_id ?>&delete_id=<?= $h['id'] ?>" 
                               class="btn btn-danger" style="padding:4px 10px;font-size:11px;"
                               onclick="return confirm('Supprimer cette entrée de suivi du <?= date('d/m/Y', strtotime($h['date_consultation'])) ?> ?')">
                                🗑️
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Légende -->
        <div style="margin-top:16px;font-size:12px;color:var(--text-muted);display:flex;gap:20px;flex-wrap:wrap;">
            <span>🔵 <strong>Dernière consultation</strong></span>
            <span>🟢 <strong>Première consultation</strong></span>
            <span>⬆️ <span style="color:var(--danger);">Prise de poids</span></span>
            <span>⬇️ <span style="color:var(--primary);">Perte de poids</span></span>
            <span>➡️ Stable</span>
        </div>

    <?php endif; ?>

    <!-- Liens rapides -->
    <div style="display:flex;justify-content:center;gap:12px;margin-top:30px;padding-bottom:30px;flex-wrap:wrap;">
        <a href="add_suivi.php?client_id=<?= $client_id ?>" class="btn btn-primary">➕ Nouvelle consultation</a>
        <a href="fiche_client.php?id=<?= $client_id ?>" class="btn btn-ghost">👤 Voir la fiche client</a>
        <a href="list_clients.php" class="btn btn-ghost">← Liste des clients</a>
    </div>

</div>

<script>
// Flash message auto-disparition
document.addEventListener('DOMContentLoaded', () => {
    const alert = document.querySelector('.alert');
    if (alert) {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    }
});
</script>

</body>
</html>
