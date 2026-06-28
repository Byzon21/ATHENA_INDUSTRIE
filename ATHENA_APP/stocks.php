<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Récupération des statistiques
$stats = $pdo->query("SELECT 
    COUNT(*) AS total_produits,
    SUM(quantite_disponible) AS total_stock,
    SUM(quantite_disponible * prix_vente) AS valeur_stock,
    SUM(CASE WHEN quantite_disponible <= seuil_alerte THEN 1 ELSE 0 END) AS nb_alertes
FROM stocks WHERE actif = TRUE")->fetch();

// Récupération des produits actifs
$produits = $pdo->query("SELECT * FROM stocks 
                        WHERE actif = TRUE 
                        ORDER BY quantite_disponible ASC")->fetchAll();

// Récupération de l'historique
$historique = [];
try {
    $stmtH = $pdo->query("SELECT h.*, s.nom_produit FROM stock_history h 
                          JOIN stocks s ON h.produit_id = s.id 
                          ORDER BY h.date_action DESC LIMIT 10");
    $historique = $stmtH->fetchAll();
} catch (PDOException $e) {}

// Traitement : suppression (archivage) d'un produit
if (isset($_GET['archive']) && $_SESSION['role'] === 'admin') {
    $id = (int)$_GET['archive'];
    $pdo->prepare("UPDATE stocks SET actif = FALSE WHERE id = ?")->execute([$id]);
    header("Location: stocks.php?msg=Produit archivé avec succès.");
    exit();
}

// Traitement : mise à jour rapide (prix ou seuil)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $nouveau_prix = (float)$_POST['edit_prix'];
    $nouveau_seuil = (int)$_POST['edit_seuil'];
    $pdo->prepare("UPDATE stocks SET prix_vente = ?, seuil_alerte = ? WHERE id = ?")
        ->execute([$nouveau_prix, $nouveau_seuil, $edit_id]);
    header("Location: stocks.php?msg=Produit modifié avec succès.");
    exit();
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaire - Athena Industrie</title>
    <link rel="stylesheet" href="css/athena.css">
    <style>
        body {
            background: var(--bg);
            margin: 0;
            padding: 30px 15px;
            display: flex;
            justify-content: center;
        }
        .page-wrapper {
            width: 100%;
            max-width: 920px;
        }

        /* ============================================
           STATISTIQUES
        ============================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 18px 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            text-align: center;
            transition: var(--transition);
        }
        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        .stat-card .stat-icon {
            font-size: 24px;
            margin-bottom: 6px;
        }
        .stat-card .stat-value {
            font-size: 26px;
            font-weight: 800;
            color: var(--secondary);
            line-height: 1.2;
        }
        .stat-card .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-top: 4px;
        }
        .stat-card.alert-stat .stat-value {
            color: var(--danger);
        }
        .stat-card.primary-stat .stat-value {
            color: var(--primary);
        }

        /* ============================================
           LISTE DES PRODUITS
        ============================================ */
        .stock-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .stock-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            border-left: 6px solid var(--primary);
            transition: var(--transition);
            gap: 15px;
        }
        .stock-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateX(4px);
        }
        .stock-card.critical {
            border-left-color: var(--danger);
            background: #fffbfb;
        }
        .stock-card.critical:hover {
            background: #fff5f5;
        }

        .product-info h3 {
            margin: 0 0 5px 0;
            font-size: 17px;
            font-weight: 700;
            color: var(--secondary);
        }
        .product-meta {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .qty-section {
            text-align: right;
            flex-shrink: 0;
            min-width: 100px;
        }
        .qty-number {
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
        }
        .qty-number.ok { color: var(--primary); }
        .qty-number.alert { color: var(--danger); }
        .qty-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 700;
            margin-top: 3px;
            display: block;
        }

        /* ============================================
           BOUTONS D'ACTION SUR LES CARTES
        ============================================ */
        .card-actions {
            display: flex;
            gap: 6px;
            margin-top: 6px;
        }
        .card-actions .btn-icon {
            background: var(--primary-bg);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            padding: 4px 9px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font);
            transition: var(--transition);
            color: var(--text-secondary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .card-actions .btn-icon:hover {
            background: var(--border);
            box-shadow: var(--shadow-sm);
        }
        .card-actions .btn-icon.danger:hover {
            background: #fef2f2;
            color: var(--danger);
            border-color: #fecaca;
        }
        .card-actions .btn-icon.primary:hover {
            background: #f0fdf4;
            color: var(--primary);
            border-color: #bbf7d0;
        }

        /* ============================================
           BARRE DE RECHERCHE
        ============================================ */
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
        }
        .search-bar input {
            flex: 1;
        }

        /* ============================================
           MODALE D'ÉDITION
        ============================================ */
        .edit-modal .modal-body {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .edit-modal .form-row-inline {
            display: flex;
            gap: 12px;
        }
        .edit-modal .form-row-inline .form-group {
            flex: 1;
        }

        /* ============================================
           HISTORIQUE
        ============================================ */
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
        .histo-table tbody tr:hover {
            background: #f8fafc;
        }

        /* ============================================
           RESPONSIVE
        ============================================ */
        @media (max-width: 640px) {
            .stock-card {
                flex-direction: column;
                align-items: flex-start;
            }
            .qty-section {
                text-align: left;
                width: 100%;
            }
            .product-meta {
                flex-wrap: wrap;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">

    <!-- ============================================
         EN-TÊTE
    ============================================ -->
    <div class="page-header">
        <h2>
            <span>📦</span> Inventaire
        </h2>
        <div class="header-actions">
            <a href="index.php" class="btn btn-ghost">🏠 Dashboard</a>
        </div>
    </div>

    <!-- Message flash -->
    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- ============================================
         STATISTIQUES
    ============================================ -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?= (int)$stats['total_produits'] ?></div>
            <div class="stat-label">Produits actifs</div>
        </div>
        <div class="stat-card primary-stat">
            <div class="stat-icon">📊</div>
            <div class="stat-value"><?= (int)$stats['total_stock'] ?></div>
            <div class="stat-label">Unités en stock</div>
        </div>
        <div class="stat-card alert-stat">
            <div class="stat-icon">⚠️</div>
            <div class="stat-value"><?= (int)$stats['nb_alertes'] ?></div>
            <div class="stat-label">Alertes stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value"><?= number_format((float)$stats['valeur_stock'], 0) ?> $</div>
            <div class="stat-label">Valeur totale</div>
        </div>
    </div>

    <!-- ============================================
         BOUTON LIVRAISON + RECHERCHE
    ============================================ -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="search-bar">
            <input type="text" class="form-control" id="searchInput" 
                   placeholder="🔍 Rechercher un produit..." oninput="filterProducts()">
            <a href="update_stock.php" class="btn btn-primary" style="white-space:nowrap;">
                📦 + Livraison
            </a>
        </div>
    <?php else: ?>
        <div class="search-bar">
            <input type="text" class="form-control" id="searchInput" 
                   placeholder="🔍 Rechercher un produit..." oninput="filterProducts()">
        </div>
    <?php endif; ?>

    <!-- ============================================
         LISTE DES PRODUITS
    ============================================ -->
    <div class="stock-list" id="stockList">
        <?php if (empty($produits)): ?>
            <div class="card" style="text-align:center;padding:40px;">
                <div style="font-size:40px;margin-bottom:10px;">📭</div>
                <p style="color:var(--text-muted);">Aucun produit dans l'inventaire.</p>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="update_stock.php" class="btn btn-primary">+ Ajouter un produit</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($produits as $p): 
            $is_critical = ($p['quantite_disponible'] <= $p['seuil_alerte']);
        ?>
            <div class="stock-card <?= $is_critical ? 'critical' : '' ?>" 
                 data-name="<?= strtolower(htmlspecialchars($p['nom_produit'])) ?>">
                
                <div class="product-info">
                    <h3><?= htmlspecialchars($p['nom_produit']); ?></h3>
                    <div class="product-meta">
                        <span>💰 <strong><?= number_format($p['prix_vente'], 2); ?> $</strong></span>
                        <span>🔔 Seuil : <?= (int)$p['seuil_alerte']; ?> u</span>
                        <?php if ($is_critical): ?>
                            <span class="badge badge-danger">⚠️ Alerte Stock</span>
                        <?php else: ?>
                            <span class="badge badge-success">✅ En Stock</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="card-actions">
                        <button class="btn-icon primary" onclick="openEditModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nom_produit'], ENT_QUOTES) ?>', <?= $p['prix_vente'] ?>, <?= (int)$p['seuil_alerte'] ?>)">
                            ✏️ Modifier
                        </button>
                        <a href="stocks.php?archive=<?= $p['id'] ?>" class="btn-icon danger" 
                           onclick="return confirm('Archiver « <?= htmlspecialchars($p['nom_produit'], ENT_QUOTES) ?> » ?\n\nLe produit sera masqué mais les données historiques restent.')">
                            🗑️ Archiver
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="qty-section">
                    <span class="qty-number <?= $is_critical ? 'alert' : 'ok' ?>">
                        <?= (int)$p['quantite_disponible']; ?>
                    </span>
                    <span class="qty-label">Disponibles</span>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <!-- ============================================
         HISTORIQUE
    ============================================ -->
    <?php if (!empty($historique)): ?>
        <div style="margin-top: 40px; padding-bottom: 20px;">
            <h3 style="font-size: 16px; margin-bottom: 15px; color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
                🕐 Traçabilité des mouvements
            </h3>
            <div class="table-wrapper">
                <table class="histo-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th style="text-align:center;">Mouvement</th>
                            <th style="text-align:center;">Par</th>
                            <th style="text-align:right;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historique as $h): 
                            $is_sale = ($h['quantite'] < 0);
                        ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($h['nom_produit']) ?></strong>
                                    <br><small style="color: var(--text-muted);">
                                        <?= $is_sale ? 'Vente (facture)' : 'Réapprovisionnement' ?>
                                    </small>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($is_sale): ?>
                                        <span class="badge badge-danger"><?= $h['quantite'] ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-success">+<?= $h['quantite'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center; font-weight:600; color: var(--info);">
                                    <?= htmlspecialchars($h['execute_par'] ?? '—') ?>
                                </td>
                                <td style="text-align:right; color: var(--text-muted); font-size:12px;">
                                    <?= date('d/m/Y H:i', strtotime($h['date_action'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- ============================================
     MODALE D'ÉDITION (prix + seuil)
============================================ -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box edit-modal">
        <div class="modal-header">
            <h3>✏️ Modifier le produit</h3>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>
        <form method="POST" action="stocks.php">
            <div class="modal-body">
                <p id="editProductName" style="font-weight:700;font-size:16px;color:var(--secondary);margin:0;"></p>
                <input type="hidden" name="edit_id" id="editId">
                <div class="form-row-inline">
                    <div class="form-group">
                        <label class="form-label">Prix de vente ($)</label>
                        <input type="number" step="0.01" name="edit_prix" id="editPrix" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Seuil d'alerte</label>
                        <input type="number" name="edit_seuil" id="editSeuil" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px;">
                <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
// =============================================================
// RECHERCHE EN TEMPS RÉEL
// =============================================================
function filterProducts() {
    const query = document.getElementById('searchInput').value.toLowerCase().trim();
    document.querySelectorAll('.stock-card').forEach(card => {
        const name = card.dataset.name || '';
        card.style.display = (!query || name.includes(query)) ? 'flex' : 'none';
    });
}

// =============================================================
// MODALE D'ÉDITION
// =============================================================
function openEditModal(id, name, prix, seuil) {
    document.getElementById('editId').value = id;
    document.getElementById('editProductName').textContent = '📦 ' + name;
    document.getElementById('editPrix').value = prix;
    document.getElementById('editSeuil').value = seuil;
    document.getElementById('editModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}

// Fermeture en cliquant sur l'overlay
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// =============================================================
// FLASH MESSAGE AUTO-DISPARITION
// =============================================================
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
