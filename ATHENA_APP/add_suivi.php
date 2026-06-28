<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['client_id'])) {
    header("Location: list_clients.php");
    exit();
}

$client_id = (int)$_GET['client_id'];
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    header("Location: list_clients.php");
    exit();
}

// Récupération du dernier suivi pour pré-remplir et comparer
$stmtLast = $pdo->prepare("SELECT * FROM suivi_progression WHERE client_id = ? ORDER BY date_consultation DESC, id DESC LIMIT 1");
$stmtLast->execute([$client_id]);
$last_suivi = $stmtLast->fetch();

// Nombre total de suivis pour ce client
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM suivi_progression WHERE client_id = ?");
$stmtCount->execute([$client_id]);
$nb_suivis = (int)$stmtCount->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi - <?= htmlspecialchars($client['nom_prenom']) ?> — Athena Industrie</title>
    <link rel="stylesheet" href="css/athena.css">
    <style>
        body {
            background: var(--bg);
            display: flex;
            justify-content: center;
            padding: 30px 15px;
            margin: 0;
        }
        .page-wrapper { width: 100%; max-width: 650px; }

        /* Bannière client */
        .client-banner {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .client-banner h2 {
            margin: 0;
            font-size: 20px;
            color: var(--secondary);
        }
        .client-banner .badge-client {
            font-size: 12px;
            color: var(--text-muted);
        }
        .client-banner .badge-client strong {
            color: var(--secondary);
        }

        /* Stats mini */
        .mini-stats {
            display: flex;
            gap: 12px;
        }
        .mini-stat {
            text-align: center;
            background: var(--primary-bg);
            padding: 6px 14px;
            border-radius: var(--radius-sm);
        }
        .mini-stat .value {
            font-size: 16px;
            font-weight: 800;
            color: var(--primary);
        }
        .mini-stat .label {
            font-size: 10px;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.3px;
        }

        /* Comparaison avec le dernier suivi */
        .comparison-banner {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #166534;
        }
        .comparison-banner .evo {
            font-weight: 700;
        }
        .comparison-banner .evo.up { color: var(--danger); }
        .comparison-banner .evo.down { color: var(--primary); }
        .comparison-banner .evo.stable { color: var(--warning); }

        /* Grille de mesures */
        .measures-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }
        .measure-card {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 16px;
            border: 1px solid var(--border-light);
            transition: var(--transition);
        }
        .measure-card:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(184, 134, 11, 0.1);
        }
        .measure-card label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .measure-card input, .measure-card select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-weight: 600;
            font-family: var(--font);
            outline: none;
            transition: var(--transition);
            box-sizing: border-box;
            background: #fcfdfe;
        }
        .measure-card input:focus, .measure-card select:focus {
            border-color: var(--primary);
            background: white;
        }
        .measure-card .previous {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
            display: block;
        }
        .measure-card .previous .evo-badge {
            font-weight: 700;
        }

        /* Notes */
        .notes-section textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: var(--font);
            font-size: 14px;
            outline: none;
            transition: var(--transition);
            box-sizing: border-box;
            resize: vertical;
        }
        .notes-section textarea:focus {
            border-color: var(--primary);
        }

        /* Alerte si déjà suivi aujourd'hui */
        .alert-warning-custom {
            background: #fef9c3;
            border: 1px solid #fde047;
            color: #854d0e;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 18px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 600px) {
            .client-banner { flex-direction: column; align-items: flex-start; }
            .measures-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<div class="page-wrapper">

    <!-- En-tête -->
    <div class="page-header">
        <h2>
            <span>📈</span> Nouveau Suivi
        </h2>
        <div class="header-actions">
            <a href="historique_suivi.php?client_id=<?= $client_id ?>" class="btn btn-ghost">📋 Historique</a>
            <a href="fiche_client.php?id=<?= $client_id ?>" class="btn btn-ghost">👤 Fiche</a>
        </div>
    </div>

    <!-- Bannière client -->
    <div class="client-banner">
        <div>
            <h2>👤 <?= htmlspecialchars($client['nom_prenom']) ?></h2>
            <div class="badge-client">
                <?php if ($client['sexe'] === 'F'): ?>Femme<?php else: ?>Homme<?php endif; ?>
                — <?= (int)$client['age'] ?> ans
                — 📞 <?= htmlspecialchars($client['telephone'] ?? 'N/R') ?>
                — 🏙️ <?= htmlspecialchars($client['ville'] ?? 'N/R') ?>
            </div>
        </div>
        <div class="mini-stats">
            <div class="mini-stat">
                <div class="value"><?= $nb_suivis ?></div>
                <div class="label">Suivis</div>
            </div>
            <div class="mini-stat">
                <div class="value"><?= number_format($client['poids_actuel'] ?? 0, 1) ?> kg</div>
                <div class="label">Poids actuel</div>
            </div>
            <div class="mini-stat">
                <div class="value"><?= number_format($client['poids_objectif'] ?? 0, 1) ?> kg</div>
                <div class="label">Objectif</div>
            </div>
        </div>
    </div>

    <!-- Comparaison avec le dernier suivi -->
    <?php if ($last_suivi): 
        $diff_poids = ($client['poids_actuel'] ?? 0) - ($last_suivi['poids_mesure'] ?? 0);
        $diff_taille = ($client['taille_cm'] ?? 0) - ($last_suivi['tour_taille'] ?? 0);
    ?>
    <div class="comparison-banner">
        🕐 <strong>Dernière consultation :</strong> 
        <?= date('d/m/Y', strtotime($last_suivi['date_consultation'])) ?>
        — Poids : <?= number_format($last_suivi['poids_mesure'], 1) ?> kg
        <?php if ($diff_poids != 0): ?>
            (<span class="evo <?= $diff_poids > 0 ? 'up' : 'down' ?>">
                <?= $diff_poids > 0 ? '+' : '' ?><?= number_format($diff_poids, 1) ?> kg
            </span>)
        <?php endif; ?>
        <?php if ($last_suivi['tour_taille']): ?>
            — Tour de taille : <?= number_format($last_suivi['tour_taille'], 1) ?> cm
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Formulaire de suivi -->
    <div class="card" style="padding:28px;">
        <form action="process_suivi.php" method="POST" id="suiviForm">
            <input type="hidden" name="client_id" value="<?= $client_id ?>">

            <!-- Ligne 1 : Poids et mensurations principales -->
            <div class="measures-grid">

                <div class="measure-card">
                    <label for="poids">⚖️ Poids (kg) *</label>
                    <input type="number" step="0.1" name="poids" id="poids" 
                           value="<?= $client['poids_actuel'] ?>" required
                           onchange="calculerIMC()">
                    <?php if ($last_suivi): ?>
                    <span class="previous">Précédent : <?= number_format($last_suivi['poids_mesure'], 1) ?> kg</span>
                    <?php endif; ?>
                </div>

                <div class="measure-card">
                    <label for="taille">📏 Taille (cm)</label>
                    <input type="number" name="taille" id="taille" 
                           value="<?= $client['taille_cm'] ?>"
                           onchange="calculerIMC()">
                    <?php if ($last_suivi): ?>
                    <span class="previous">Précédent : <?= (int)$last_suivi['taille_mesure'] ?> cm</span>
                    <?php endif; ?>
                </div>

                <div class="measure-card">
                    <label for="tour_taille">📐 Tour de taille (cm)</label>
                    <input type="number" step="0.1" name="tour_taille" id="tour_taille"
                           value="<?= $last_suivi['tour_taille'] ?? '' ?>">
                    <?php if ($last_suivi && $last_suivi['tour_taille']): ?>
                    <span class="previous">Précédent : <?= number_format($last_suivi['tour_taille'], 1) ?> cm</span>
                    <?php endif; ?>
                </div>

                <div class="measure-card">
                    <label for="tour_hanches">📐 Tour de hanches (cm)</label>
                    <input type="number" step="0.1" name="tour_hanches" id="tour_hanches"
                           value="<?= $last_suivi['tour_hanches'] ?? '' ?>">
                    <?php if ($last_suivi && $last_suivi['tour_hanches']): ?>
                    <span class="previous">Précédent : <?= number_format($last_suivi['tour_hanches'], 1) ?> cm</span>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Ligne 2 : IMC auto, masse grasse, masse musculaire -->
            <div class="measures-grid" style="margin-top:14px;">

                <div class="measure-card" style="background:#f0fdf4;">
                    <label for="imc">🧮 IMC (calculé)</label>
                    <input type="number" step="0.1" name="imc" id="imc" readonly
                           style="background:#f0fdf4;color:#166534;font-weight:800;"
                           placeholder="Auto">
                    <span class="previous">Calculé automatiquement</span>
                </div>

                <div class="measure-card">
                    <label for="masse_graisseuse">🔥 Masse grasse (%)</label>
                    <input type="number" step="0.1" name="masse_graisseuse" id="masse_graisseuse"
                           value="<?= $last_suivi['masse_graisseuse'] ?? '' ?>">
                </div>

                <div class="measure-card">
                    <label for="masse_musculaire">💪 Masse musculaire (%)</label>
                    <input type="number" step="0.1" name="masse_musculaire" id="masse_musculaire"
                           value="<?= $last_suivi['masse_musculaire'] ?? '' ?>">
                </div>

                <div class="measure-card">
                    <label for="tension">❤️ Tension artérielle</label>
                    <input type="text" name="tension" id="tension" placeholder="Ex: 12/8"
                           value="<?= $last_suivi['tension_arterielle'] ?? '' ?>">
                </div>

            </div>

            <!-- Ligne 3 : Activité physique + Date consultation -->
            <div class="measures-grid" style="margin-top:14px;">

                <div class="measure-card">
                    <label for="activite">🏃 Activité physique (/10)</label>
                    <select name="activite" id="activite">
                        <?php for ($i = 0; $i <= 10; $i++): ?>
                        <option value="<?= $i ?>" <?= ($client['activite_physique'] ?? 0) == $i ? 'selected' : '' ?>>
                            <?= $i ?>/10
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="measure-card">
                    <label for="date_consultation">📅 Date de consultation</label>
                    <input type="date" name="date_consultation" id="date_consultation" 
                           value="<?= date('Y-m-d') ?>">
                    <span class="previous">Aujourd'hui par défaut</span>
                </div>

            </div>

            <!-- Notes -->
            <div class="notes-section" style="margin-top:20px;">
                <div class="form-group">
                    <label class="form-label">🥗 Évolution alimentaire</label>
                    <textarea name="alimentation" rows="2" placeholder="Qu'a changé le client dans son alimentation ?"><?= htmlspecialchars($last_suivi['notes_alimentation'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">📝 Notes de suivi</label>
                    <textarea name="notes_suivi" rows="3" placeholder="Observations, ressenti du patient, conseils donnés..."></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;padding:16px;font-size:15px;margin-top:10px;">
                💾 Enregistrer la consultation du <?= date('d/m/Y') ?>
            </button>
        </form>
    </div>

    <!-- Lien d'archivage -->
    <div style="text-align:center;margin-top:18px;">
        <a href="historique_suivi.php?client_id=<?= $client_id ?>" class="btn btn-ghost">
            📂 Voir tout l'historique des suivis
        </a>
    </div>
</div>

<script>
// =====================================================================
// CALCUL AUTOMATIQUE DE L'IMC
// =====================================================================
function calculerIMC() {
    const poids = parseFloat(document.getElementById('poids').value);
    const taille = parseFloat(document.getElementById('taille').value);
    const imcField = document.getElementById('imc');

    if (poids > 0 && taille > 0) {
        const tailleM = taille / 100;
        const imc = poids / (tailleM * tailleM);
        imcField.value = imc.toFixed(1);
    } else {
        imcField.value = '';
    }
}

// =====================================================================
// DATE DU JOUR PAR DÉFAUT
// =====================================================================
document.addEventListener('DOMContentLoaded', () => {
    // La date est déjà pré-remplie via PHP
    calculerIMC();
});
</script>

</body>
</html>
