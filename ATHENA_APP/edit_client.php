<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['id']))          { header("Location: list_clients.php"); exit(); }

$client_id = intval($_GET['id']);

// Données actuelles du client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) die("Client introuvable.");

// Produits actuellement associés au client
$stmtCP = $pdo->prepare("SELECT produit_id FROM client_produits WHERE client_id = ?");
$stmtCP->execute([$client_id]);
$selected_produits = $stmtCP->fetchAll(PDO::FETCH_COLUMN);

// Produits/cures disponibles (actifs)
$produits_dispo = $pdo->query("SELECT * FROM stocks WHERE actif = TRUE ORDER BY nom_produit ASC")->fetchAll();

// Champs personnalisés actifs
$custom_fields = $pdo->query("SELECT * FROM form_fields WHERE actif = TRUE ORDER BY ordre ASC, id ASC")->fetchAll();

// Valeurs déjà enregistrées pour ce client
$stmtVals = $pdo->prepare("SELECT field_id, valeur FROM client_custom_values WHERE client_id = ?");
$stmtVals->execute([$client_id]);
$custom_values = $stmtVals->fetchAll(PDO::FETCH_KEY_PAIR); // field_id => valeur
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Client - Athena</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #27ae60;
            --secondary: #2c3e50;
            --accent: #d4a017;
            --danger: #e74c3c;
            --bg-gradient: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            margin: 0;
            padding: 30px 20px;
        }
        .form-container {
            width: 100%;
            max-width: 820px;
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
            margin-top: 10px;
            margin-bottom: 80px;
        }
        header { text-align: center; margin-bottom: 30px; }
        h2 { color: var(--secondary); margin-bottom: 6px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 22px; }
        header p { color: #7f8c8d; font-size: 13px; margin: 0; }
        .section-title {
            border-left: 4px solid var(--primary);
            padding-left: 12px;
            margin: 28px 0 14px 0;
            color: var(--secondary);
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: var(--secondary); }
        input[type="text"], input[type="number"], input[type="tel"], input[type="date"],
        select, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #eef2f7;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.3s;
            outline: none;
            background: #fdfdfe;
        }
        input:focus, textarea:focus, select:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(39,174,96,0.08);
        }
        .flex-row { display: flex; gap: 15px; margin-bottom: 10px; }
        .flex-row > div { flex: 1; }
        .range-container { background: #f8fafc; padding: 14px; border-radius: 12px; text-align: center; }
        input[type="range"] { width: 100%; accent-color: var(--primary); }

        .btn-update {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), #1e8449);
            color: white;
            border: none;
            padding: 17px;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 25px;
            transition: all 0.3s;
        }
        .btn-update:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(39,174,96,0.3); filter: brightness(1.05); }

        .cure-checkbox-card {
            display: block;
            background: #fffbe6;
            border: 2px solid #ffe58f;
            padding: 11px 14px;
            border-radius: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .cure-checkbox-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(212,160,23,0.1); }
        .cure-checkbox-card:has(input:checked) {
            background: #ffe58f !important;
            border-color: var(--accent) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212,160,23,0.2);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }
        .back-link:hover { color: var(--secondary); }

        /* Custom fields */
        .custom-field-row { position: relative; }
        .custom-field-row .remove-field-inline {
            position: absolute; top: 0; right: 0;
            background: none; border: none;
            color: #e0e0e0; font-size: 16px; cursor: pointer;
            padding: 2px 4px; transition: color 0.2s; line-height: 1;
        }
        .custom-field-row:hover .remove-field-inline { color: var(--danger); }

        /* FAB + Modal */
        .fab-manage {
            position: fixed; bottom: 30px; right: 30px;
            background: linear-gradient(135deg, #8e44ad, #6c3483);
            color: white; border: none; border-radius: 50px;
            padding: 13px 20px; font-size: 14px; font-weight: 700;
            font-family: inherit; cursor: pointer;
            box-shadow: 0 8px 25px rgba(142,68,173,0.4);
            z-index: 1000; transition: all 0.3s;
            display: flex; align-items: center; gap: 8px;
        }
        .fab-manage:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(142,68,173,0.5); }

        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 1100;
            backdrop-filter: blur(3px);
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: white; border-radius: 24px; padding: 30px;
            width: 95%; max-width: 520px; max-height: 85vh; overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2);
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9;
        }
        .modal-header h3 { margin: 0; font-size: 17px; color: var(--secondary); }
        .modal-close {
            background: #f1f5f9; border: none; border-radius: 50%;
            width: 32px; height: 32px; cursor: pointer; font-size: 16px; line-height: 1; transition: 0.2s;
        }
        .modal-close:hover { background: #e2e8f0; }
        .field-list-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 14px; background: #f8fafc; border-radius: 10px;
            margin-bottom: 8px; border: 1px solid #e2e8f0; font-size: 13px;
        }
        .field-list-item .field-meta { color: #64748b; font-size: 11px; margin-top: 2px; }
        .btn-del-field {
            background: none; border: none; color: #e0e0e0; cursor: pointer;
            font-size: 16px; padding: 3px 6px; border-radius: 6px; transition: all 0.2s; flex-shrink: 0;
        }
        .btn-del-field:hover { color: var(--danger); background: #fef2f2; }
        .add-field-form {
            background: #f0fdf4; border: 1px dashed #86efac;
            border-radius: 12px; padding: 16px; margin-top: 18px;
        }
        .add-field-form h4 { margin: 0 0 14px 0; font-size: 13px; color: #166534; font-weight: 700; }
        .add-field-form input, .add-field-form select {
            width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid #bbf7d0;
            font-size: 13px; font-family: inherit; background: white; margin-bottom: 10px;
            outline: none; transition: border 0.2s;
        }
        .add-field-form input:focus, .add-field-form select:focus { border-color: var(--primary); }
        .btn-add-field {
            width: 100%; background: var(--primary); color: white; border: none;
            padding: 10px; border-radius: 10px; font-size: 14px; font-weight: 700;
            font-family: inherit; cursor: pointer; transition: all 0.2s;
        }
        .btn-add-field:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .options-row { display: none; }
        .options-row.visible { display: block; }
        .type-badge { font-size: 10px; padding: 2px 7px; border-radius: 10px; font-weight: 700; text-transform: uppercase; }
        .type-text    { background: #e0f2fe; color: #0369a1; }
        .type-number  { background: #dcfce7; color: #166534; }
        .type-textarea{ background: #faf5ff; color: #6b21a8; }
        .type-select  { background: #fff7ed; color: #c2410c; }
        .type-date    { background: #fef9c3; color: #854d0e; }
        .empty-custom { text-align: center; color: #94a3b8; font-size: 13px; font-style: italic; padding: 20px 0; }

        @media (max-width: 600px) {
            .flex-row { flex-direction: column; gap: 0; }
            .form-container { padding: 22px 16px; }
            .fab-manage { bottom: 15px; right: 15px; padding: 11px 15px; font-size: 13px; }
        }
    </style>
</head>
<body>

<div class="form-container">
    <header>
        <h2>Modifier la Fiche Client</h2>
        <p>Mise à jour de <strong><?= htmlspecialchars($client['nom_prenom']) ?></strong></p>
    </header>

    <form action="process_edit.php" method="POST" onsubmit="return validateForm()">
        <input type="hidden" name="id" value="<?= $client['id'] ?>">

        <!-- IDENTITÉ & CONTACT -->
        <div class="section-title">Identité & Contact</div>
        <div class="form-group">
            <label>Nom et Prénom *</label>
            <input type="text" name="nom_prenom" value="<?= htmlspecialchars($client['nom_prenom']) ?>" required>
        </div>
        <div class="flex-row">
            <div class="form-group">
                <label>Sexe</label>
                <select name="sexe">
                    <option value="F" <?= $client['sexe']=='F'?'selected':'' ?>>Femme</option>
                    <option value="M" <?= $client['sexe']=='M'?'selected':'' ?>>Homme</option>
                </select>
            </div>
            <div class="form-group">
                <label>Âge</label>
                <input type="number" name="age" value="<?= htmlspecialchars($client['age']??'') ?>">
            </div>
        </div>
        <div class="flex-row">
            <div class="form-group">
                <label>Téléphone</label>
                <input type="tel" name="telephone" value="<?= htmlspecialchars($client['telephone']??'') ?>">
            </div>
            <div class="form-group">
                <label>Ville</label>
                <input type="text" name="ville" value="<?= htmlspecialchars($client['ville']??'') ?>">
            </div>
        </div>

        <!-- MENSURATIONS -->
        <div class="section-title">Mensurations</div>
        <div class="flex-row">
            <div class="form-group">
                <label>Taille (cm)</label>
                <input type="number" name="taille" value="<?= htmlspecialchars($client['taille_cm']??'') ?>">
            </div>
            <div class="form-group">
                <label>Poids actuel (kg)</label>
                <input type="number" step="0.1" name="poids" value="<?= htmlspecialchars($client['poids_actuel']??'') ?>">
            </div>
            <div class="form-group">
                <label>Poids objectif (kg)</label>
                <input type="number" step="0.1" name="objectif" value="<?= htmlspecialchars($client['poids_objectif']??'') ?>">
            </div>
        </div>

        <!-- HYGIÈNE DE VIE -->
        <div class="section-title">Hygiène de vie</div>
        <div class="form-group">
            <label>Alimentation (Habitudes)</label>
            <textarea name="alimentation" rows="2"><?= htmlspecialchars($client['alimentation']??'') ?></textarea>
        </div>
        <div class="form-group">
            <label>Activité physique (0 à 10)</label>
            <div class="range-container">
                <input type="range" name="activite" min="0" max="10" step="1" id="rangeInput"
                    value="<?= htmlspecialchars($client['activite_physique']??'5') ?>">
                <div style="font-weight:bold; color:var(--primary); margin-top:5px;">
                    Note : <span id="rangeValue"><?= htmlspecialchars($client['activite_physique']??'5') ?></span> / 10
                </div>
            </div>
        </div>

        <!-- SANTÉ & ANTÉCÉDENTS -->
        <div class="section-title">Santé & Antécédents</div>
        <div class="form-group">
            <label>Antécédents médicaux</label>
            <textarea name="antecedents_medicaux" rows="2"><?= htmlspecialchars($client['antecedents_medicaux']??'') ?></textarea>
        </div>
        <div class="form-group">
            <label>Allergies et intolérances</label>
            <textarea name="allergies_intolerances" rows="2"><?= htmlspecialchars($client['allergies_intolerances']??'') ?></textarea>
        </div>
        <div class="form-group">
            <label>Opérations chirurgicales</label>
            <textarea name="operations_chirurgicales" rows="2"><?= htmlspecialchars($client['operations_chirurgicales']??'') ?></textarea>
        </div>
        <div class="flex-row">
            <div class="form-group">
                <label>Nombre d'accouchements</label>
                <input type="number" name="nombre_accouchements" value="<?= htmlspecialchars($client['nombre_accouchements']??'0') ?>">
            </div>
            <div class="form-group">
                <label>Allaitement en cours ?</label>
                <select name="allaitement">
                    <option value="Non" <?= ($client['allaitement']=='Non')?'selected':'' ?>>Non</option>
                    <option value="Oui" <?= ($client['allaitement']=='Oui')?'selected':'' ?>>Oui</option>
                </select>
            </div>
        </div>

        <!-- INFORMATIONS COMPLÉMENTAIRES (champs dynamiques) -->
        <div id="customFieldsSection" style="<?= empty($custom_fields)?'display:none;':'' ?>">
            <div class="section-title" style="border-left-color:#8e44ad;">
                <span>Informations complémentaires</span>
            </div>
            <div id="customFieldsContainer">
                <?php foreach ($custom_fields as $cf):
                    $val = $custom_values[$cf['id']] ?? ''; ?>
                    <div class="form-group custom-field-row" data-field-id="<?= $cf['id'] ?>">
                        <label><?= htmlspecialchars($cf['label']) ?></label>
                        <?php if ($cf['field_type'] === 'textarea'): ?>
                            <textarea name="custom_field_<?= $cf['id'] ?>" rows="2"
                                placeholder="<?= htmlspecialchars($cf['placeholder']??'') ?>"><?= htmlspecialchars($val) ?></textarea>
                        <?php elseif ($cf['field_type'] === 'select' && !empty($cf['options_json'])): ?>
                            <?php $opts = json_decode($cf['options_json'], true) ?? []; ?>
                            <select name="custom_field_<?= $cf['id'] ?>">
                                <option value="">-- Choisir --</option>
                                <?php foreach ($opts as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>" <?= $val===$opt?'selected':'' ?>><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($cf['field_type'] === 'date'): ?>
                            <input type="date" name="custom_field_<?= $cf['id'] ?>" value="<?= htmlspecialchars($val) ?>">
                        <?php elseif ($cf['field_type'] === 'number'): ?>
                            <input type="number" step="any" name="custom_field_<?= $cf['id'] ?>"
                                placeholder="<?= htmlspecialchars($cf['placeholder']??'') ?>"
                                value="<?= htmlspecialchars($val) ?>">
                        <?php else: ?>
                            <input type="text" name="custom_field_<?= $cf['id'] ?>"
                                placeholder="<?= htmlspecialchars($cf['placeholder']??'') ?>"
                                value="<?= htmlspecialchars($val) ?>">
                        <?php endif; ?>
                        <button type="button" class="remove-field-inline"
                            onclick="removeFieldFromForm(this,<?= $cf['id'] ?>)" title="Masquer ce champ">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TARIFICATION -->
        <div class="section-title">Tarification</div>
        <p style="font-size:12px; color:#7f8c8d; margin:-8px 0 12px 0;">
            Prix actuel enregistré : <strong><?= number_format($client['prix_cure'],2) ?> $</strong>
        </p>
        <div class="form-group">
            <label>Frais de consultation</label>
            <select name="frais_consultation" style="background:#f0f7ff;font-weight:bold;color:#3498db;border-color:#d1e9ff;">
                <option value="0">Aucun changement (0 $)</option>
                <option value="30">30 $</option>
                <option value="15">15 $</option>
                <option value="5">5 $</option>
            </select>
        </div>
        <div class="form-group">
            <label>Cures & Services sélectionnés</label>
            <div class="cures-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:11px;margin-top:8px;">
                <?php foreach ($produits_dispo as $p): ?>
                    <label class="cure-checkbox-card">
                        <input type="checkbox" name="produit_ids[]" value="<?= $p['id'] ?>"
                            <?= in_array($p['id'], $selected_produits)?'checked':'' ?>
                            style="margin-right:8px;accent-color:var(--accent);">
                        <span style="font-weight:bold;color:var(--accent);font-size:13px;"><?= htmlspecialchars($p['nom_produit']) ?></span>
                        <div style="font-size:12px;color:#7f8c8d;margin-top:3px;"><?= number_format($p['prix_vente'],2) ?> $</div>
                    </label>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:12px;text-align:right;">
                <button type="button" id="toggleCureManager" style="background:none;border:none;color:var(--primary);font-weight:600;font-size:13px;cursor:pointer;text-decoration:underline;padding:0;">
                    ⚙️ Gérer les tarifs & cures
                </button>
            </div>

            <div id="cureManagerPanel" style="display:none;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:15px;margin-top:13px;">
                <div style="font-weight:700;font-size:13px;color:var(--secondary);margin-bottom:10px;">Gestion des Cures & Tarifs</div>
                <div id="managerCureList" style="margin-bottom:13px;display:flex;flex-direction:column;gap:7px;max-height:150px;overflow-y:auto;"></div>
                <div style="display:flex;gap:9px;flex-wrap:wrap;">
                    <input type="text" id="newCureNom" placeholder="Nom de la nouvelle cure"
                        style="flex:2;min-width:140px;padding:8px 11px;font-size:13px;border-radius:8px;border:1px solid #cbd5e1;">
                    <input type="number" step="0.01" id="newCurePrix" placeholder="Prix ($)"
                        style="width:80px;padding:8px 11px;font-size:13px;border-radius:8px;border:1px solid #cbd5e1;">
                    <button type="button" id="btnAddCure"
                        style="background:var(--primary);color:white;border:none;padding:8px 14px;font-size:13px;font-weight:bold;border-radius:8px;cursor:pointer;">
                        ➕ Ajouter
                    </button>
                </div>
                <div id="cureManagerMsg" style="margin-top:8px;font-size:12px;display:none;"></div>
            </div>
        </div>

        <p style="font-size:11px;color:var(--danger);font-style:italic;">
            * Si vous cochez de nouveaux tarifs, le montant total sera recalculé.
        </p>

        <button type="submit" class="btn-update">💾 Enregistrer les modifications</button>
    </form>

    <a href="list_clients.php" class="back-link">← Annuler et retourner</a>
</div>

<!-- FAB Gestion des rubriques -->
<button class="fab-manage" id="openFieldManager">🧩 Gérer les rubriques</button>

<!-- Modal Gestionnaire de rubriques -->
<div class="modal-overlay" id="fieldManagerModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>🧩 Rubriques du formulaire</h3>
            <button class="modal-close" id="closeFieldManager">✕</button>
        </div>
        <p style="font-size:12px;color:#64748b;margin:0 0 15px 0;">
            Ajoutez ou supprimez des rubriques personnalisées. Les données déjà saisies pour ce client seront conservées.
        </p>
        <div id="fieldListContainer"></div>
        <div class="add-field-form">
            <h4>➕ Ajouter une nouvelle rubrique</h4>
            <input type="text" id="newFieldLabel" placeholder="Libellé (ex: Groupe sanguin)">
            <select id="newFieldType">
                <option value="text">Texte court</option>
                <option value="textarea">Zone de texte (mémo)</option>
                <option value="number">Nombre</option>
                <option value="date">Date</option>
                <option value="select">Liste de choix</option>
            </select>
            <input type="text" id="newFieldPlaceholder" placeholder="Indice (optionnel)">
            <div class="options-row" id="optionsRow">
                <input type="text" id="newFieldOptions" placeholder="Options séparées par virgules (ex: A+, B+, O+)">
            </div>
            <button type="button" class="btn-add-field" id="btnAddField">Créer la rubrique</button>
        </div>
        <div id="fieldManagerMsg"></div>
    </div>
</div>

<script>
// Range slider
const range = document.getElementById('rangeInput');
const output = document.getElementById('rangeValue');
range.addEventListener('input', e => { output.textContent = e.target.value; });

// Validation
function validateForm() {
    const cbs = document.querySelectorAll('input[name="produit_ids[]"]');
    let ok = false;
    cbs.forEach(c => { if (c.checked) ok = true; });
    if (!ok) { alert('Veuillez sélectionner au moins une cure.'); return false; }
    return true;
}

// ---- CURES MANAGER ----
const toggleBtn   = document.getElementById('toggleCureManager');
const curePanel   = document.getElementById('cureManagerPanel');
const btnAddCure  = document.getElementById('btnAddCure');
const newCureNom  = document.getElementById('newCureNom');
const newCurePrix = document.getElementById('newCurePrix');
const cureMsgDiv  = document.getElementById('cureManagerMsg');
const cureListDiv = document.getElementById('managerCureList');
const curesGrid   = document.querySelector('.cures-grid');

let availableCures = <?php echo json_encode($produits_dispo); ?>;
const checkedCureIds = new Set();
<?php foreach ($selected_produits as $pid): ?>
    checkedCureIds.add(<?= intval($pid) ?>);
<?php endforeach; ?>

function updateCheckedState() {
    document.querySelectorAll('input[name="produit_ids[]"]').forEach(cb => {
        if (cb.checked) checkedCureIds.add(parseInt(cb.value));
        else checkedCureIds.delete(parseInt(cb.value));
    });
}
document.addEventListener('change', e => { if (e.target?.name === 'produit_ids[]') updateCheckedState(); });
updateCheckedState();

toggleBtn.addEventListener('click', () => {
    const open = curePanel.style.display === 'none';
    curePanel.style.display = open ? 'block' : 'none';
    if (open) renderCureManagerList();
});

function renderCuresGrid() {
    curesGrid.innerHTML = '';
    if (!availableCures.length) {
        curesGrid.innerHTML = '<div style="color:#7f8c8d;font-size:13px;font-style:italic;grid-column:1/-1">Aucune cure active.</div>';
        return;
    }
    availableCures.forEach(p => {
        const lbl = document.createElement('label');
        lbl.className = 'cure-checkbox-card';
        lbl.innerHTML = `
            <input type="checkbox" name="produit_ids[]" value="${p.id}"
                ${checkedCureIds.has(parseInt(p.id)) ? 'checked' : ''}
                style="margin-right:8px;accent-color:var(--accent);">
            <span style="font-weight:bold;color:var(--accent);font-size:13px;">${escHtml(p.nom_produit)}</span>
            <div style="font-size:12px;color:#7f8c8d;margin-top:3px;">${parseFloat(p.prix_vente).toFixed(2)} $</div>`;
        curesGrid.appendChild(lbl);
    });
    updateCheckedState();
}

function renderCureManagerList() {
    cureListDiv.innerHTML = '';
    if (!availableCures.length) {
        cureListDiv.innerHTML = '<div style="color:#7f8c8d;font-size:12px;font-style:italic;">Aucune cure.</div>';
        return;
    }
    availableCures.forEach(p => {
        const d = document.createElement('div');
        d.style.cssText = 'display:flex;justify-content:space-between;align-items:center;background:white;padding:8px 11px;border-radius:8px;border:1px solid #e2e8f0;font-size:12px;';
        d.innerHTML = `
            <span><strong style="color:var(--accent)">${escHtml(p.nom_produit)}</strong> — <span style="color:var(--secondary);font-weight:600;">${parseFloat(p.prix_vente).toFixed(2)} $</span></span>
            <button type="button" data-id="${p.id}" style="background:none;border:none;color:#e74c3c;cursor:pointer;padding:2px 5px;font-size:14px;">🗑️</button>`;
        d.querySelector('button').addEventListener('click', function () {
            if (confirm('Désactiver cette cure ?')) deleteCure(this.dataset.id);
        });
        cureListDiv.appendChild(d);
    });
}

function showCureMsg(t, err=false) {
    cureMsgDiv.textContent = t;
    cureMsgDiv.style.color = err ? 'var(--danger)' : 'var(--primary)';
    cureMsgDiv.style.display = 'block';
    setTimeout(() => { cureMsgDiv.style.display = 'none'; }, 4000);
}

btnAddCure.addEventListener('click', () => {
    const nom = newCureNom.value.trim(), prix = parseFloat(newCurePrix.value);
    if (!nom) return showCureMsg('Saisir un nom.', true);
    if (isNaN(prix) || prix < 0) return showCureMsg('Prix invalide.', true);
    const fd = new FormData();
    fd.append('action','add'); fd.append('nom_produit',nom); fd.append('prix_vente',prix);
    fetch('ajax_cures.php',{method:'POST',body:fd})
        .then(r => r.ok ? r.json() : r.json().then(e=>{throw new Error(e.error);}))
        .then(d => {
            if (d.success) {
                availableCures.push({id:d.id.toString(),nom_produit:d.nom_produit,prix_vente:d.prix_vente.toString()});
                availableCures.sort((a,b)=>a.nom_produit.localeCompare(b.nom_produit));
                checkedCureIds.add(parseInt(d.id));
                renderCuresGrid(); renderCureManagerList();
                newCureNom.value=''; newCurePrix.value='';
                showCureMsg('Cure ajoutée !');
            }
        }).catch(e=>showCureMsg(e.message,true));
});

function deleteCure(id) {
    const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
    fetch('ajax_cures.php',{method:'POST',body:fd})
        .then(r=>r.ok?r.json():r.json().then(e=>{throw new Error(e.error);}))
        .then(d => {
            if (d.success) {
                availableCures = availableCures.filter(c=>c.id!=id);
                checkedCureIds.delete(parseInt(id));
                renderCuresGrid(); renderCureManagerList();
                showCureMsg('Cure désactivée.');
            }
        }).catch(e=>showCureMsg(e.message,true));
}

// ---- CHAMPS DYNAMIQUES ----
let customFields = <?php echo json_encode($custom_fields); ?>;
const modal         = document.getElementById('fieldManagerModal');
const openBtn       = document.getElementById('openFieldManager');
const closeBtn      = document.getElementById('closeFieldManager');
const fieldListDiv  = document.getElementById('fieldListContainer');
const btnAddField   = document.getElementById('btnAddField');
const newFieldLabel = document.getElementById('newFieldLabel');
const newFieldType  = document.getElementById('newFieldType');
const newFieldPh    = document.getElementById('newFieldPlaceholder');
const newFieldOpts  = document.getElementById('newFieldOptions');
const optionsRow    = document.getElementById('optionsRow');
const fieldMsgDiv   = document.getElementById('fieldManagerMsg');
const custSection   = document.getElementById('customFieldsSection');
const custContainer = document.getElementById('customFieldsContainer');

openBtn.addEventListener('click',  () => { modal.classList.add('open'); renderFieldList(); });
closeBtn.addEventListener('click', () =>   modal.classList.remove('open'));
modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });
newFieldType.addEventListener('change', () => { optionsRow.classList.toggle('visible', newFieldType.value === 'select'); });

function typeBadge(type) {
    const map = { text:['Texte','type-text'], number:['Nombre','type-number'], textarea:['Mémo','type-textarea'], select:['Liste','type-select'], date:['Date','type-date'] };
    const [lbl, cls] = map[type] || ['?','type-text'];
    return `<span class="type-badge ${cls}">${lbl}</span>`;
}

function renderFieldList() {
    if (!customFields.length) {
        fieldListDiv.innerHTML = '<div class="empty-custom">Aucune rubrique personnalisée.<br>Créez-en une ci-dessous !</div>';
        return;
    }
    fieldListDiv.innerHTML = '';
    customFields.forEach(f => {
        const d = document.createElement('div');
        d.className = 'field-list-item';
        d.innerHTML = `
            <div>
                <div style="font-weight:600;color:var(--secondary);">${escHtml(f.label)} ${typeBadge(f.field_type)}</div>
                ${f.placeholder ? `<div class="field-meta">Indice : ${escHtml(f.placeholder)}</div>` : ''}
                ${f.options_json ? `<div class="field-meta">Options : ${escHtml(JSON.parse(f.options_json).join(', '))}</div>` : ''}
            </div>
            <button class="btn-del-field" data-id="${f.id}">🗑️</button>`;
        d.querySelector('.btn-del-field').addEventListener('click', function () {
            if (confirm(`Supprimer la rubrique "${f.label}" ?`)) deleteField(this.dataset.id);
        });
        fieldListDiv.appendChild(d);
    });
}

function showFieldMsg(t, err=false) {
    fieldMsgDiv.textContent = t;
    fieldMsgDiv.style.cssText = `display:block;font-size:12px;text-align:center;margin-top:10px;padding:8px;border-radius:8px;background:${err?'#fef2f2':'#f0fdf4'};color:${err?'var(--danger)':'var(--primary)'};`;
    setTimeout(() => { fieldMsgDiv.style.display = 'none'; }, 4000);
}

function addFieldToForm(field) {
    custSection.style.display = '';
    const wrapper = document.createElement('div');
    wrapper.className = 'form-group custom-field-row';
    wrapper.dataset.fieldId = field.id;
    let inputHtml = '';
    if (field.field_type === 'textarea') {
        inputHtml = `<textarea name="custom_field_${field.id}" rows="2" placeholder="${escHtml(field.placeholder||'')}"></textarea>`;
    } else if (field.field_type === 'select' && field.options_json) {
        const opts = JSON.parse(field.options_json);
        inputHtml = `<select name="custom_field_${field.id}"><option value="">-- Choisir --</option>${opts.map(o=>`<option value="${escHtml(o)}">${escHtml(o)}</option>`).join('')}</select>`;
    } else {
        const t = field.field_type === 'date' ? 'date' : field.field_type === 'number' ? 'number' : 'text';
        inputHtml = `<input type="${t}" name="custom_field_${field.id}" placeholder="${escHtml(field.placeholder||'')}">`;
    }
    wrapper.innerHTML = `
        <label>${escHtml(field.label)}</label>${inputHtml}
        <button type="button" class="remove-field-inline" onclick="removeFieldFromForm(this,${field.id})" title="Masquer">✕</button>`;
    custContainer.appendChild(wrapper);
}

function removeFieldFromForm(btn, id) {
    btn.closest('.custom-field-row')?.remove();
    if (!custContainer.querySelector('.custom-field-row')) custSection.style.display = 'none';
}

btnAddField.addEventListener('click', () => {
    const label = newFieldLabel.value.trim();
    const type  = newFieldType.value;
    const ph    = newFieldPh.value.trim();
    const opts  = newFieldOpts.value.trim();
    if (!label) return showFieldMsg('Le libellé est obligatoire.', true);
    let options_json = null;
    if (type === 'select') {
        if (!opts) return showFieldMsg('Saisir les options pour une liste.', true);
        options_json = JSON.stringify(opts.split(',').map(s=>s.trim()).filter(s=>s));
    }
    const fd = new FormData();
    fd.append('action','add'); fd.append('label',label); fd.append('field_type',type); fd.append('placeholder',ph);
    if (options_json) fd.append('options_json', options_json);
    fetch('ajax_fields.php',{method:'POST',body:fd})
        .then(r=>r.ok?r.json():r.json().then(e=>{throw new Error(e.error);}))
        .then(d => {
            if (d.success) {
                const nf = {id:d.id.toString(),label:d.label,field_type:d.field_type,options_json:d.options_json,placeholder:d.placeholder,ordre:d.ordre};
                customFields.push(nf);
                addFieldToForm(nf);
                renderFieldList();
                newFieldLabel.value=''; newFieldPh.value=''; newFieldOpts.value='';
                newFieldType.value='text'; optionsRow.classList.remove('visible');
                showFieldMsg('Rubrique créée !');
            }
        }).catch(e=>showFieldMsg(e.message,true));
});

function deleteField(id) {
    const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
    fetch('ajax_fields.php',{method:'POST',body:fd})
        .then(r=>r.ok?r.json():r.json().then(e=>{throw new Error(e.error);}))
        .then(d => {
            if (d.success) {
                customFields = customFields.filter(f=>f.id!=id);
                const row = custContainer.querySelector(`.custom-field-row[data-field-id="${id}"]`);
                if (row) row.remove();
                if (!custContainer.querySelector('.custom-field-row')) custSection.style.display = 'none';
                renderFieldList();
                showFieldMsg('Rubrique supprimée.');
            }
        }).catch(e=>showFieldMsg(e.message,true));
}

function escHtml(t) {
    if (!t) return '';
    return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>

</body>
</html>