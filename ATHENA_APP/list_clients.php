<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$search = $_GET['q'] ?? '';

// Requête optimisée avec recherche sur Nom, Créateur ou Ville
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE nom_prenom ILIKE ? OR cree_par ILIKE ? OR ville ILIKE ? ORDER BY id DESC");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY id DESC");
}

$clients = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Clients - Athena</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #b8860b;
            --secondary: #2c3e50;
            --accent: #d4a017;
            --danger: #e74c3c;
            --bg-gradient: linear-gradient(135deg, #1a1a1a 0%, #2c3e50 100%);
            --glass: rgba(255, 255, 255, 0.95);
            --text-dark: #1e293b;
            --text-light: #64748b;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-gradient); color: var(--text-dark); margin: 0; min-height: 100vh; }
        .container { max-width: 1000px; margin: 40px auto; padding: 40px; background: var(--glass); backdrop-filter: blur(10px); border-radius: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }

        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        header h2 { font-weight: 800; font-size: 26px; margin: 0; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; }
        
        .btn-back { background: white; padding: 10px; border-radius: 12px; text-decoration: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: 0.3s; font-size: 20px; }
        .btn-back:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); }

        .search-box { background: white; padding: 8px; border-radius: 16px; display: flex; gap: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); margin-bottom: 30px; border: 1px solid rgba(0,0,0,0.05); }
        .search-box input { flex: 1; border: none; padding: 12px; font-size: 16px; font-family: inherit; outline: none; }
        .search-box button { background: var(--secondary); color: white; border: none; padding: 0 20px; border-radius: 12px; cursor: pointer; font-weight: 600; transition: 0.3s; }
        .search-box button:hover { background: var(--primary); }

        .client-card {
            background: white; border-radius: 20px; padding: 20px; margin-bottom: 15px;
            display: flex; justify-content: space-between; align-items: center;
            border-left: 6px solid var(--primary); transition: 0.3s;
        }
        .client-card:hover { transform: translateX(5px); box-shadow: 0 10px 25px rgba(184, 134, 11, 0.15); }

        .client-info h3 { margin: 0 0 5px 0; font-size: 18px; color: var(--text-dark); }
        .client-meta { font-size: 13px; color: var(--text-light); display: flex; flex-wrap: wrap; gap: 12px; }
        .badge-city { background: #fcf8ee; padding: 2px 8px; border-radius: 6px; font-weight: 600; color: var(--primary); }

        .actions { display: flex; gap: 8px; }
        .btn-action { 
            padding: 10px 15px; border-radius: 10px; border: none; font-size: 13px; font-weight: 700; cursor: pointer; 
            text-decoration: none; display: flex; align-items: center; gap: 5px; transition: 0.2s;
        }
        .btn-visualize { background: var(--secondary); color: white; }
        .btn-pdf { background: #fcf8ee; color: var(--primary); border: 1px solid var(--primary); }
        .btn-action:hover { transform: translateY(-2px); filter: brightness(1.1); }

        /* Modal */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); justify-content: center; align-items: center;
        }
        .modal-content {
            background: white; width: 95%; max-width: 600px; border-radius: 28px; padding: 30px;
            max-height: 90vh; overflow-y: auto; position: relative; animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-header { border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; }
        .modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-body p { margin: 8px 0; font-size: 14px; border-bottom: 1px solid #f8f9fa; padding-bottom: 5px; }
        .modal-body strong { color: var(--text-light); font-weight: 600; display: block; font-size: 11px; text-transform: uppercase; margin-bottom: 2px; }

        .modal-footer { margin-top: 25px; display: flex; gap: 10px; }
        .btn-edit { background: var(--accent); color: var(--secondary); flex: 1; justify-content: center; }
        .btn-delete { background: #fee2e2; color: var(--danger); flex: 1; justify-content: center; }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 24px; cursor: pointer; color: var(--text-light); }

        @media (max-width: 600px) {
            .client-card { flex-direction: column; align-items: flex-start; gap: 15px; }
            .actions { width: 100%; }
            .modal-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="../IMG/athena.jpg" alt="Logo" style="width: 50px; border-radius: 8px; border: 1px solid var(--primary);">
            <h2>Annuaire Clients</h2>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="export_clients_pdf.php" class="btn-action" style="background: var(--danger); color: white;">📄 Liste PDF</a>
            <a href="index.php" class="btn-back">🏠</a>
        </div>
    </header>

    <form method="GET" class="search-box">
        <input type="text" name="q" placeholder="Nom, ville ou auteur..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Filtrer</button>
    </form>

    <div class="client-grid">
        <?php foreach ($clients as $c): ?>
            <div class="client-card">
                <div class="client-info">
                    <h3><?= htmlspecialchars($c['nom_prenom']) ?></h3>
                    <div class="client-meta">
                        <span>📱 <?= htmlspecialchars($c['telephone'] ?? 'N/A') ?></span>
                        <span class="badge-city">📍 <?= htmlspecialchars($c['ville'] ?? 'N/A') ?></span>
                        <span>👤 <?= htmlspecialchars($c['cree_par']) ?></span>
                    </div>
                </div>
                
                <div class="actions">
                    <button class="btn-action btn-visualize" onclick='showDetails(<?= json_encode($c) ?>)'>
                        👁️ Détails
                    </button>
                                        <a href="add_suivi.php?client_id=<?= $c['id'] ?>" class="btn-action" style="background: var(--primary); color: white;">
                        📈 Suivi
                    </a>
                    <a href="historique_suivi.php?client_id=<?= $c['id'] ?>" class="btn-action" style="background: #f0f7ff; color: #2563eb; border: 1px solid #93c5fd;">
                        📂 Historique
                    </a>
                    <a href="fiche_client.php?id=<?= $c['id'] ?>" class="btn-action btn-pdf">📄 PDF</a>
                    <a href="facture_pdf.php?id=<?= $c['id'] ?>" class="btn-action" style="background: #fef3c7; color: var(--primary); border: 1px solid var(--primary);">
                        💰 Facture
                    </a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="delete_clients.php?id=<?= $c['id'] ?>" 
                           class="btn-action" 
                           style="background: #fee2e2; color: var(--danger);"
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement le client : <?= addslashes($c['nom_prenom']) ?> ? Cette action est irréversible.')">
                            🗑️ Supprimer
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="clientModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <div class="modal-header">
            <h2 id="m_nom" style="margin:0; color:var(--primary);">Nom Client</h2>
            <div id="m_contact" style="color:var(--text-light); font-size: 14px; margin-top: 5px;"></div>
        </div>
        
        <div class="modal-body">
            <div class="modal-grid">
                <div>
                    <p><strong>Sexe / Âge</strong> <span id="m_sexe_age"></span></p>
                    <p><strong>Ville</strong> <span id="m_ville"></span></p>
                    <p><strong>Taille (cm)</strong> <span id="m_taille"></span></p>
                    <p><strong>Poids Actuel</strong> <span id="m_poids"></span> kg</p>
                </div>
                <div>
                    <p><strong>Objectif</strong> <span id="m_objectif"></span> kg</p>
                    <p><strong>Activité Physique</strong> <span id="m_activite"></span> / 10</p>
                    <p><strong>Allaitement</strong> <span id="m_allaitement"></span></p>
                    <p><strong>Prix Cure</strong> <span id="m_prix" style="font-weight:bold; color:var(--primary);"></span> $</p>
                </div>
            </div>
            
            <div style="margin-top:15px; background:#f8fafc; padding:15px; border-radius:12px;">
                <strong>Santé & Antécédents</strong>
                <p style="border:none; font-style:italic;" id="m_sante"></p>
            </div>
        </div>
        
        <div class="modal-footer">
            <a href="#" id="editLink" class="btn-action btn-edit">✏️ Modifier</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="#" id="deleteLink" class="btn-action btn-delete" onclick="return confirm('Supprimer ce client ?')">🗑️ Supprimer</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('clientModal');

    function showDetails(c) {
        document.getElementById('m_nom').innerText = c.nom_prenom;
        document.getElementById('m_contact').innerText = "📞 " + (c.telephone || 'N/A') + " | 📍 " + (c.ville || 'N/A');
        
        document.getElementById('m_sexe_age').innerText = (c.sexe || '-') + " | " + (c.age || '0') + " ans";
        document.getElementById('m_ville').innerText = c.ville || 'Non renseignée';
        document.getElementById('m_taille').innerText = c.taille_cm || '-';
        document.getElementById('m_poids').innerText = c.poids_actuel || '-';
        document.getElementById('m_objectif').innerText = c.poids_objectif || '-';
        document.getElementById('m_activite').innerText = c.activite_physique || '0';
        document.getElementById('m_allaitement').innerText = c.allaitement || 'Non spécifié';
        document.getElementById('m_prix').innerText = c.prix_cure || '0';
        
        // Regroupement des infos santé
        let sante = "";
        if(c.antecedents_medicaux) sante += "Médicaux: " + c.antecedents_medicaux + "\n";
        if(c.allergies_intolerances) sante += "Allergies: " + c.allergies_intolerances + "\n";
        if(c.operations_chirurgicales) sante += "Chirurgie: " + c.operations_chirurgicales;
        document.getElementById('m_sante').innerText = sante || "Aucun antécédent signalé.";

        document.getElementById('editLink').href = "edit_client.php?id=" + c.id;

        const del = document.getElementById('deleteLink');
        if(del) del.href = "delete_clients.php?id=" + c.id;

        modal.style.display = "flex";
    }

    function closeModal() { modal.style.display = "none"; }
    window.onclick = (e) => { if (e.target == modal) closeModal(); }
</script>

</body>
</html>