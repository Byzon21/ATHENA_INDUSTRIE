<?php
session_start();
require_once 'db.php';

// Sécurité : Seul l'admin peut ajouter du stock
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$msg = "";
// Gestion de la suppression d'un ajout (Annulation) directement ici
if (isset($_GET['delete_history_id']) && $_SESSION['role'] === 'admin') {
    $id_h = (int)$_GET['delete_history_id'];
    try {
        $pdo->beginTransaction();
        $stmtH = $pdo->prepare("SELECT * FROM stock_history WHERE id = ?");
        $stmtH->execute([$id_h]);
        $entry = $stmtH->fetch();

        if ($entry) {
            // On retire la quantité du stock global
            $stmtUpd = $pdo->prepare("UPDATE stocks SET quantite_disponible = quantite_disponible - ? WHERE id = ?");
            $stmtUpd->execute([$entry['quantite'], $entry['produit_id']]);

            // On supprime la ligne d'historique
            $stmtDel = $pdo->prepare("DELETE FROM stock_history WHERE id = ?");
            $stmtDel->execute([$id_h]);
            
            $pdo->commit();
            header("Location: stocks.php?msg=L'ajout a été annulé avec succès.");
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Erreur lors de l'annulation : " . $e->getMessage();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $produit_id = $_POST['produit_id'] ?? null;
    $nouveau_produit_nom = trim($_POST['nouveau_produit_nom'] ?? '');
    $quantite_ajoutee = (int)$_POST['quantite'];

    if ($quantite_ajoutee > 0) {
        try {
            $pdo->beginTransaction();

            // Si c'est un nouveau produit (pas de sélection dans la liste)
            if (empty($produit_id) && !empty($nouveau_produit_nom)) {
                // Créer le nouveau produit dans la table stocks
                $stmtNew = $pdo->prepare("INSERT INTO stocks (nom_produit, quantite_disponible, prix_vente, seuil_alerte, actif) VALUES (?, 0, 0.00, 5, TRUE)");
                $stmtNew->execute([$nouveau_produit_nom]);
                $produit_id = $pdo->lastInsertId();
                $msg = "Nouveau produit « {$nouveau_produit_nom} » créé ! ";
            }

            if ($produit_id) {
                $stmt = $pdo->prepare("UPDATE stocks SET quantite_disponible = quantite_disponible + ? WHERE id = ?");
                $stmt->execute([$quantite_ajoutee, $produit_id]);

                $stmtHist = $pdo->prepare("INSERT INTO stock_history (produit_id, quantite, execute_par) VALUES (?, ?, ?)");
                $stmtHist->execute([$produit_id, $quantite_ajoutee, $_SESSION['username']]);

                $pdo->commit();
                $msg .= "Réapprovisionnement effectué avec succès !";
            } else {
                $msg = "Veuillez sélectionner un produit ou en saisir un nouveau.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $msg = "Erreur lors de la mise à jour.";
        }
    }
}

// Récupération du message via URL si présent
if (isset($_GET['msg'])) $msg = $_GET['msg'];

// On récupère uniquement les produits actifs pour réapprovisionnement
$produits = $pdo->query("SELECT * FROM stocks 
                        WHERE actif = TRUE 
                        ORDER BY nom_produit ASC")->fetchAll();

// Prépare un tableau des stocks critiques pour le JS
$critical_ids = [];
foreach ($produits as $p) {
    if ($p['quantite_disponible'] <= $p['seuil_alerte']) {
        $critical_ids[] = (int)$p['id'];
    }
}
$critical_json = json_encode($critical_ids);

// Récupération des 10 derniers arrivages
$historique = $pdo->query("SELECT h.*, s.nom_produit FROM stock_history h JOIN stocks s ON h.produit_id = s.id ORDER BY h.date_action DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livraison - Athena Industrie</title>
    <link rel="stylesheet" href="css/athena.css">
    <style>
        body {
            background: var(--bg);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 30px 15px;
            margin: 0;
        }
        .page-wrapper { width: 100%; max-width: 540px; }

        /* Sélecteur existant / nouveau */
        .product-choice {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
        }
        .product-choice .choice-btn {
            flex: 1;
            text-align: center;
            padding: 11px 8px;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            border: 2px solid var(--border);
            background: var(--white);
            transition: var(--transition);
            font-family: var(--font);
            line-height: 1.3;
        }
        .product-choice .choice-btn.active-existing {
            border-color: #3498db;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .product-choice .choice-btn.active-new {
            border-color: var(--primary);
            background: #f0fdf4;
            color: #166534;
        }

        #existingProductGroup, #newProductGroup {
            transition: opacity 0.3s ease;
        }
        #newProductGroup { display: none; }
        #newProductGroup.visible { display: block; animation: slideDown 0.3s ease-out; }
        #existingProductGroup.hidden { display: none; }

        /* Alerte de stock critique inline */
        .stock-alert-banner {
            display: none;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            margin-top: 8px;
            font-size: 13px;
            color: var(--danger);
            font-weight: 600;
            animation: slideDown 0.3s ease-out;
        }
        .stock-alert-banner.visible {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Option en alerte dans le select */
        select option.option-critical {
            color: var(--danger);
            font-weight: 700;
        }

        /* Tableau historique */
        .histo-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .histo-table th {
            padding: 11px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            background: var(--primary-bg);
        }
        .histo-table td {
            padding: 11px 12px;
            border-top: 1px solid var(--border-light);
        }
        .histo-table tbody tr:hover { background: #f8fafc; }

        .badge-qty {
            display: inline-block;
            background: #dcfce7;
            color: #166534;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .btn-undo {
            padding: 5px 12px;
            font-size: 12px;
            border-radius: var(--radius-sm);
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fecaca;
            cursor: pointer;
            font-family: var(--font);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-block;
        }
        .btn-undo:hover {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="page-wrapper">
        
        <!-- En-tête -->
        <div class="page-header">
            <h2>
                <span>📦</span> Livraison
            </h2>
            <div class="header-actions">
                <a href="stocks.php" class="btn btn-ghost">← Inventaire</a>
            </div>
        </div>

        <!-- Message flash -->
        <?php if (!empty($msg)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Carte formulaire -->
        <div class="card" style="padding:28px;">

            <!-- Sélecteur : Produit existant OU Nouveau -->
            <div class="product-choice">
                <button type="button" class="choice-btn active-existing" id="btnExisting" onclick="switchMode('existing')">
                    📋 Existant
                </button>
                <button type="button" class="choice-btn" id="btnNew" onclick="switchMode('new')">
                    ✏️ Nouveau
                </button>
            </div>

            <form method="POST">
                <!-- Groupe : Produit existant (liste déroulante) -->
                <div id="existingProductGroup">
                    <div class="form-group">
                        <label class="form-label">Produit à réapprovisionner</label>
                        <select name="produit_id" class="form-control" id="produitSelect" onchange="checkStockAlert()">
                            <option value="" disabled selected>Sélectionnez un article...</option>
                            <?php foreach ($produits as $p): 
                                $is_critical = ($p['quantite_disponible'] <= $p['seuil_alerte']);
                            ?>
                                <option value="<?= $p['id']; ?>" class="<?= $is_critical ? 'option-critical' : '' ?>"
                                        data-stock="<?= (int)$p['quantite_disponible'] ?>"
                                        data-seuil="<?= (int)$p['seuil_alerte'] ?>">
                                    <?= htmlspecialchars($p['nom_produit']); ?> 
                                    (Stock : <?= (int)$p['quantite_disponible']; ?>)<?= $is_critical ? ' ⚠️' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="help-text" style="font-size:12px;color:var(--text-muted);margin-top:5px;display:block;">
                            Choisissez un produit existant dans la liste.
                        </span>
                        <!-- Bannière d'alerte stock critique -->
                        <div class="stock-alert-banner" id="stockAlertBanner">
                            ⚠️ <span id="stockAlertText">Stock critique !</span>
                        </div>
                    </div>
                </div>

                <!-- Groupe : Nouveau produit (saisie libre) -->
                <div id="newProductGroup">
                    <div class="form-group">
                        <label class="form-label">Nom du nouveau produit</label>
                        <input type="text" name="nouveau_produit_nom" class="form-control" 
                               placeholder="Ex: Pack Minceur Premium" id="newProductName" disabled>
                        <span class="help-text" style="font-size:12px;color:var(--text-muted);margin-top:5px;display:block;">
                            Le produit sera créé avec un prix à 0 $, un seuil d'alerte à 5, puis approvisionné.
                        </span>
                    </div>
                </div>

                <!-- Quantité -->
                <div class="form-group">
                    <label class="form-label">Quantité reçue</label>
                    <input type="number" name="quantite" class="form-control" placeholder="Ex: 50" min="1" required>
                    <span class="help-text" style="font-size:12px;color:var(--text-muted);margin-top:5px;display:block;">
                        Cette quantité s'ajoutera au stock actuel du produit.
                    </span>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;padding:15px;font-size:15px;margin-top:8px;">
                    📦 Enregistrer la livraison
                </button>
            </form>
        </div>

        <!-- Historique des arrivages -->
        <?php if (!empty($historique)): ?>
            <div style="margin-top:35px;">
                <h3 style="font-size:16px;margin-bottom:15px;color:var(--text-secondary);display:flex;align-items:center;gap:8px;">
                    🕐 Arrivages récents
                </h3>
                <div class="table-wrapper">
                    <table class="histo-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th style="text-align:center;">Qté</th>
                                <th style="text-align:center;">Par</th>
                                <th style="text-align:center;">Date</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historique as $h): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($h['nom_produit']) ?></strong>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge-qty">+<?= (int)$h['quantite'] ?></span>
                                    </td>
                                    <td style="text-align:center;font-weight:600;color:var(--info);">
                                        <?= htmlspecialchars($h['execute_par'] ?? '—') ?>
                                    </td>
                                    <td style="text-align:center;color:var(--text-muted);font-size:12px;">
                                        <?= date('d/m/Y H:i', strtotime($h['date_action'])) ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="update_stock.php?delete_history_id=<?= $h['id'] ?>" 
                                           class="btn-undo"
                                           onclick="return confirm('Annuler cet arrivage ?\n\nLa quantité sera retirée du stock.')">
                                            🗑️ Annuler
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <script>
        // =====================================================================
        // BASCULE : PRODUIT EXISTANT ↔ NOUVEAU PRODUIT
        // =====================================================================
        function switchMode(mode) {
            const existingGroup = document.getElementById('existingProductGroup');
            const newGroup = document.getElementById('newProductGroup');
            const btnExisting = document.getElementById('btnExisting');
            const btnNew = document.getElementById('btnNew');
            const newProductInput = document.getElementById('newProductName');

            // Réinitialiser l'alerte
            document.getElementById('stockAlertBanner').classList.remove('visible');

            if (mode === 'existing') {
                existingGroup.classList.remove('hidden');
                newGroup.classList.remove('visible');
                btnExisting.className = 'choice-btn active-existing';
                btnNew.className = 'choice-btn';
                newProductInput.disabled = true;
            } else {
                existingGroup.classList.add('hidden');
                newGroup.classList.add('visible');
                btnNew.className = 'choice-btn active-new';
                btnExisting.className = 'choice-btn';
                newProductInput.disabled = false;
                newProductInput.focus();
            }
        }

        // =====================================================================
        // ALERTE STOCK CRITIQUE DANS LE SELECT
        // =====================================================================
        function checkStockAlert() {
            const select = document.getElementById('produitSelect');
            const banner = document.getElementById('stockAlertBanner');
            const text = document.getElementById('stockAlertText');
            const selected = select.options[select.selectedIndex];

            if (selected && selected.dataset) {
                const stock = parseInt(selected.dataset.stock);
                const seuil = parseInt(selected.dataset.seuil);
                if (stock <= seuil) {
                    text.textContent = `Stock critique ! Il ne reste que ${stock} unité(s) de « ${selected.text.split('(')[0].trim()} » (seuil : ${seuil}).`;
                    banner.classList.add('visible');
                } else {
                    banner.classList.remove('visible');
                }
            } else {
                banner.classList.remove('visible');
            }
        }

        // =====================================================================
        // FLASH MESSAGE AUTO-DISPARITION
        // =====================================================================
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
