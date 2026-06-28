<?php
session_start();
require_once 'db.php';

$produits_dispo = $pdo->query("SELECT * FROM stocks WHERE actif = TRUE ORDER BY nom_produit ASC")->fetchAll();
$custom_fields  = $pdo->query("SELECT * FROM form_fields WHERE actif = TRUE ORDER BY ordre ASC, id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau Client — Athena Industrie</title>
    <link rel="stylesheet" href="css/athena.css">
    <style>
        /* Styles supplémentaires spécifiques au formulaire */
        .form-wrapper {
            max-width: 820px;
            margin: 0 auto;
            padding: 20px 0;
        }
        .cure-card { cursor: pointer; }
        .cure-card:has(input:checked) {
            background: #ffe58f !important;
            border-color: var(--accent) !important;
        }
        .range-value-display {
            font-weight: 700;
            color: var(--primary);
            font-size: 18px;
        }
        .form-section-header .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            font-size: 12px;
            font-weight: 700;
            margin-right: 6px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="form-wrapper">

        <!-- En-tête -->
        <div class="page-header">
            <h2>
                <span>📋</span> Nouveau Client
            </h2>
            <div class="header-actions">
                <a href="index.php" class="btn btn-ghost">← Retour</a>
            </div>
        </div>

        <!-- Message de succès/erreur (flash) -->
        <div id="flashMsg" style="display:none;"></div>

        <form action="process_add.php" method="POST" id="clientForm" class="card" style="padding:30px;">

            <!-- ============================================================
                 SECTION 1 – IDENTITÉ & CONTACT
            ============================================================ -->
            <div class="form-section open" data-section="1">
                <div class="form-section-header" onclick="toggleSection(this)">
                    <h3><span class="step-number">1</span>👤 Identité &amp; Contact</h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="form-section-body">
                    <div class="form-group">
                        <label class="form-label">Nom &amp; Prénom *</label>
                        <input type="text" name="nom_prenom" class="form-control" placeholder="Nom complet" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Sexe</label>
                            <select name="sexe" class="form-control">
                                <option value="F">Femme</option>
                                <option value="M">Homme</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Âge</label>
                            <input type="number" name="age" class="form-control" placeholder="Ex: 30">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" name="telephone" class="form-control" placeholder="+243 XXX XXX XXX">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Ville</label>
                            <input type="text" name="ville" class="form-control" placeholder="Ex: Kinshasa">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================
                 SECTION 2 – MENSURATIONS & OBJECTIFS
            ============================================================ -->
            <div class="form-section open" data-section="2">
                <div class="form-section-header" onclick="toggleSection(this)">
                    <h3><span class="step-number">2</span>📏 Mensurations &amp; Objectifs</h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="form-section-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Taille (cm)</label>
                            <input type="number" name="taille" class="form-control" placeholder="170">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Poids actuel (kg)</label>
                            <input type="number" step="0.1" name="poids" class="form-control" placeholder="75.0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Objectif (kg)</label>
                            <input type="number" step="0.1" name="objectif" class="form-control" placeholder="65.0">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================
                 SECTION 3 – HYGIÈNE DE VIE
            ============================================================ -->
            <div class="form-section" data-section="3">
                <div class="form-section-header" onclick="toggleSection(this)">
                    <h3><span class="step-number">3</span>🥗 Hygiène de Vie</h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="form-section-body">
                    <div class="form-group">
                        <label class="form-label">Habitudes alimentaires</label>
                        <textarea name="alimentation" class="form-control" rows="2" placeholder="Décrivez le régime habituel..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Activité physique</label>
                        <div class="card" style="padding:18px;text-align:center;">
                            <input type="range" name="activite" min="0" max="10" step="1" value="5" id="rangeInput" style="width:100%;accent-color:var(--primary);">
                            <div style="margin-top:8px;">
                                Note : <span class="range-value-display" id="rangeValue">5</span> / 10
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================
                 SECTION 4 – SANTÉ & ANTÉCÉDENTS
            ============================================================ -->
            <div class="form-section" data-section="4">
                <div class="form-section-header" onclick="toggleSection(this)">
                    <h3><span class="step-number">4</span>🏥 Santé &amp; Antécédents</h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="form-section-body">
                    <div class="form-group">
                        <label class="form-label">Antécédents médicaux</label>
                        <textarea name="antecedents_medicaux" class="form-control" rows="2" placeholder="Hypertension, diabète..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Allergies / Intolérances</label>
                        <textarea name="allergies_intolerances" class="form-control" rows="2" placeholder="Alimentaires ou médicamenteuses..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Opérations chirurgicales</label>
                        <textarea name="operations_chirurgicales" class="form-control" rows="2" placeholder="Détails et dates..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nombre d'accouchements</label>
                            <input type="number" name="nombre_accouchements" class="form-control" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Allaitement en cours ?</label>
                            <select name="allaitement" class="form-control">
                                <option value="Non">Non</option>
                                <option value="Oui">Oui</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================
                 SECTION 5 – CHAMPS PERSONNALISÉS
            ============================================================ -->
            <div id="customFieldsSection" class="form-section" data-section="5" style="<?= empty($custom_fields) ? 'display:none;' : '' ?>">
                <div class="form-section-header" onclick="toggleSection(this)">
                    <h3><span class="step-number">5</span>🧩 Informations Complémentaires</h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="form-section-body" id="customFieldsContainer">
                    <?php foreach ($custom_fields as $cf): ?>
                    <div class="form-group custom-field-row" data-field-id="<?= $cf['id'] ?>">
                        <label class="form-label"><?= htmlspecialchars($cf['label']) ?></label>
                        <?php if ($cf['field_type'] === 'textarea'): ?>
                        <textarea name="custom_field_<?= $cf['id'] ?>" class="form-control" rows="2" placeholder="<?= htmlspecialchars($cf['placeholder'] ?? '') ?>"></textarea>
                        <?php elseif ($cf['field_type'] === 'select' && !empty($cf['options_json'])): ?>
                        <?php $opts = json_decode($cf['options_json'], true) ?? []; ?>
                        <select name="custom_field_<?= $cf['id'] ?>" class="form-control">
                            <option value="">-- Choisir --</option>
                            <?php foreach ($opts as $o): ?>
                            <option value="<?= htmlspecialchars($o) ?>"><?= htmlspecialchars($o) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php elseif ($cf['field_type'] === 'date'): ?>
                        <input type="date" name="custom_field_<?= $cf['id'] ?>" class="form-control">
                        <?php elseif ($cf['field_type'] === 'number'): ?>
                        <input type="number" step="any" name="custom_field_<?= $cf['id'] ?>" class="form-control" placeholder="<?= htmlspecialchars($cf['placeholder'] ?? '') ?>">
                        <?php else: ?>
                        <input type="text" name="custom_field_<?= $cf['id'] ?>" class="form-control" placeholder="<?= htmlspecialchars($cf['placeholder'] ?? '') ?>">
                        <?php endif; ?>
                        <button type="button" class="remove-field-inline" title="Masquer ce champ" onclick="removeFieldFromForm(this, <?= $cf['id'] ?>)">✕</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ============================================================
                 SECTION 6 – TARIFICATION & CURES
            ============================================================ -->
            <div class="form-section open" data-section="6">
                <div class="form-section-header" onclick="toggleSection(this)">
                    <h3><span class="step-number">6</span>💰 Tarification &amp; Cures</h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="form-section-body">
                    <div class="form-group">
                        <label class="form-label">Frais de consultation</label>
                        <select name="frais_consultation" class="form-control">
                            <option value="30">30 $</option>
                            <option value="15">15 $</option>
                            <option value="5">5 $</option>
                            <option value="0">Gratuit (0 $)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cures &amp; Services</label>
                        <div class="cure-grid" id="curesGrid">
                            <?php if (empty($produits_dispo)): ?>
                            <div style="grid-column:1/-1;color:var(--text-muted);font-style:italic;text-align:center;padding:15px;">Aucune cure disponible. Créez-en une depuis le gestionnaire.</div>
                            <?php else: ?>
                            <?php foreach ($produits_dispo as $p): ?>
                            <label class="cure-card">
                                <input type="checkbox" name="produit_ids[]" value="<?= $p['id'] ?>">
                                <div class="cure-name"><?= htmlspecialchars($p['nom_produit']) ?></div>
                                <div class="cure-price"><?= number_format($p['prix_vente'], 2) ?> $</div>
                            </label>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top:12px;text-align:right;">
                            <button type="button" class="btn btn-ghost" id="toggleCureManager" style="font-size:13px;">
                                ⚙️ Gérer les cures &amp; tarifs
                            </button>
                        </div>

                        <!-- Panneau de gestion des cures -->
                        <div id="cureManagerPanel" style="display:none;background:var(--bg);border:1px dashed var(--border);border-radius:var(--radius-lg);padding:16px;margin-top:12px;">
                            <h4 style="font-size:14px;color:var(--secondary);margin:0 0 12px 0;">Gestion des Cures</h4>
                            <div id="managerCureList" style="display:flex;flex-direction:column;gap:7px;margin-bottom:12px;max-height:150px;overflow-y:auto;"></div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <input type="text" id="newCureNom" placeholder="Nom de la cure" style="flex:2;min-width:140px;padding:9px 12px;font-size:13px;border-radius:var(--radius-sm);border:1px solid var(--border);font-family:var(--font);">
                                <input type="number" step="0.01" id="newCurePrix" placeholder="Prix ($)" style="width:80px;padding:9px 12px;font-size:13px;border-radius:var(--radius-sm);border:1px solid var(--border);font-family:var(--font);">
                                <button type="button" class="btn btn-primary" id="btnAddCure" style="padding:9px 14px;font-size:13px;">➕ Ajouter</button>
                            </div>
                            <div id="cureManagerMsg" style="margin-top:8px;font-size:12px;display:none;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bouton de soumission -->
            <div style="margin-top:25px;">
                <button type="submit" class="btn btn-primary" style="width:100%;padding:16px;font-size:16px;" id="submitBtn">
                    ✅ Enregistrer le Client
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     BOUTON FLOTTANT – Gérer les rubriques
============================================================ -->
<button class="fab" id="openFieldManager" title="Gérer les rubriques du formulaire">
    🧩 Rubriques
</button>

<!-- ============================================================
     MODAL – Gestionnaire de champs personnalisés
============================================================ -->
<div class="modal-overlay" id="fieldManagerModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>🧩 Gestion des Rubriques</h3>
            <button class="modal-close" id="closeFieldManager">✕</button>
        </div>
        <div class="modal-body">
            <p>Ajoutez ou supprimez des rubriques personnalisées dans le formulaire. Les données existantes des clients ne sont pas affectées.</p>
            <div id="fieldListContainer"></div>
            <div class="add-field-form">
                <h4>➕ Ajouter une rubrique</h4>
                <input type="text" id="newFieldLabel" placeholder="Libellé (ex: Groupe sanguin)">
                <select id="newFieldType">
                    <option value="text">Texte court</option>
                    <option value="textarea">Zone de texte</option>
                    <option value="number">Nombre</option>
                    <option value="date">Date</option>
                    <option value="select">Liste de choix</option>
                </select>
                <input type="text" id="newFieldPlaceholder" placeholder="Indice (ex: A+, B+, AB…)">
                <div class="options-row" id="optionsRow">
                    <input type="text" id="newFieldOptions" placeholder="Options séparées par des virgules (ex: Oui,Non,Peut-être)">
                </div>
                <button type="button" class="btn-add-field" id="btnAddField">Créer la rubrique</button>
            </div>
            <div id="fieldManagerMsg" style="display:none;"></div>
        </div>
    </div>
</div>

<!-- ============================================================
     TOAST NOTIFICATION
============================================================ -->
<div class="toast" id="toast"></div>

<script>
// =====================================================================
// SECTIONS ACCORDÉON
// =====================================================================
function toggleSection(header) {
    const section = header.closest('.form-section');
    section.classList.toggle('open');
}

// =====================================================================
// ANIMATION D'ENTRÉE
// =====================================================================
document.addEventListener('DOMContentLoaded', () => {
    // Ouvrir automatiquement la première section si toutes sont fermées
    document.querySelectorAll('.form-section').forEach(sec => sec.classList.add('open'));
});

// =====================================================================
// RANGE SLIDER
// =====================================================================
const range = document.getElementById('rangeInput');
const rangeVal = document.getElementById('rangeValue');
if (range) range.addEventListener('input', e => { rangeVal.textContent = e.target.value; });

// =====================================================================
// VALIDATION
// =====================================================================
document.getElementById('clientForm').addEventListener('submit', function(e) {
    const checkboxes = document.querySelectorAll('input[name="produit_ids[]"]');
    let checked = false;
    checkboxes.forEach(cb => { if (cb.checked) checked = true; });
    if (!checked) {
        e.preventDefault();
        showToast('Veuillez sélectionner au moins une cure ou un service.', 'error');
    }
});

// =====================================================================
// TOAST
// =====================================================================
function showToast(msg, type = 'info') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 4000);
}

// =====================================================================
// GESTIONNAIRE DE CURES
// =====================================================================
const toggleBtn   = document.getElementById('toggleCureManager');
const curePanel   = document.getElementById('cureManagerPanel');
const btnAddCure  = document.getElementById('btnAddCure');
const newCureNom  = document.getElementById('newCureNom');
const newCurePrix = document.getElementById('newCurePrix');
const cureMsgDiv  = document.getElementById('cureManagerMsg');
const cureListDiv = document.getElementById('managerCureList');
const curesGrid   = document.getElementById('curesGrid');

let availableCures = <?= json_encode($produits_dispo) ?>;
const checkedCureIds = new Set();

function updateCheckedState() {
    document.querySelectorAll('input[name="produit_ids[]"]').forEach(cb => {
        if (cb.checked) checkedCureIds.add(parseInt(cb.value));
        else checkedCureIds.delete(parseInt(cb.value));
    });
}
document.addEventListener('change', e => {
    if (e.target && e.target.name === 'produit_ids[]') updateCheckedState();
});
updateCheckedState();

toggleBtn.addEventListener('click', () => {
    const open = curePanel.style.display === 'none';
    curePanel.style.display = open ? 'block' : 'none';
    if (open) renderCureManagerList();
});

function renderCuresGrid() {
    curesGrid.innerHTML = '';
    if (!availableCures.length) {
        curesGrid.innerHTML = '<div style="grid-column:1/-1;color:var(--text-muted);font-style:italic;text-align:center;padding:15px;">Aucune cure disponible.</div>';
        return;
    }
    availableCures.forEach(p => {
        const lbl = document.createElement('label');
        lbl.className = 'cure-card';
        lbl.innerHTML = `
            <input type="checkbox" name="produit_ids[]" value="${p.id}" ${checkedCureIds.has(parseInt(p.id)) ? 'checked' : ''}>
            <div class="cure-name">${escHtml(p.nom_produit)}</div>
            <div class="cure-price">${parseFloat(p.prix_vente).toFixed(2)} $</div>`;
        curesGrid.appendChild(lbl);
    });
    updateCheckedState();
}

function renderCureManagerList() {
    cureListDiv.innerHTML = '';
    if (!availableCures.length) {
        cureListDiv.innerHTML = '<div style="color:var(--text-muted);font-size:12px;font-style:italic;">Aucune cure active.</div>';
        return;
    }
    availableCures.forEach(p => {
        const d = document.createElement('div');
        d.style.cssText = 'display:flex;justify-content:space-between;align-items:center;background:white;padding:8px 11px;border-radius:8px;border:1px solid var(--border);font-size:12px;';
        d.innerHTML = `
            <span><strong style="color:var(--accent)">${escHtml(p.nom_produit)}</strong> — <span style="color:var(--secondary);font-weight:600;">${parseFloat(p.prix_vente).toFixed(2)} $</span></span>
            <button type="button" data-id="${p.id}" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:2px 5px;font-size:14px;" title="Désactiver">🗑️</button>`;
        d.querySelector('button').addEventListener('click', function() {
            if (confirm('Désactiver cette cure ?')) deleteCure(this.dataset.id);
        });
        cureListDiv.appendChild(d);
    });
}

function showCureMsg(text, err) {
    cureMsgDiv.textContent = text;
    cureMsgDiv.style.cssText = `margin-top:8px;font-size:12px;display:block;color:${err ? 'var(--danger)' : 'var(--success)'};`;
    setTimeout(() => { cureMsgDiv.style.display = 'none'; }, 4000);
}

btnAddCure.addEventListener('click', () => {
    const nom = newCureNom.value.trim();
    const prix = parseFloat(newCurePrix.value);
    if (!nom) return showCureMsg('Saisir un nom.', true);
    if (isNaN(prix) || prix < 0) return showCureMsg('Prix invalide.', true);
    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('nom_produit', nom);
    fd.append('prix_vente', prix);
    fetch('ajax_cures.php', { method: 'POST', body: fd })
        .then(r => r.ok ? r.json() : r.json().then(e => { throw new Error(e.error); }))
        .then(data => {
            if (data.success) {
                availableCures.push({ id: data.id.toString(), nom_produit: data.nom_produit, prix_vente: data.prix_vente.toString() });
                availableCures.sort((a, b) => a.nom_produit.localeCompare(b.nom_produit));
                checkedCureIds.add(parseInt(data.id));
                renderCuresGrid();
                renderCureManagerList();
                newCureNom.value = '';
                newCurePrix.value = '';
                showCureMsg('Cure ajoutée !');
            }
        }).catch(e => showCureMsg(e.message, true));
});

function deleteCure(id) {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('ajax_cures.php', { method: 'POST', body: fd })
        .then(r => r.ok ? r.json() : r.json().then(e => { throw new Error(e.error); }))
        .then(data => {
            if (data.success) {
                availableCures = availableCures.filter(c => c.id != id);
                checkedCureIds.delete(parseInt(id));
                renderCuresGrid();
                renderCureManagerList();
                showCureMsg('Cure désactivée.');
            }
        }).catch(e => showCureMsg(e.message, true));
}

// =====================================================================
// GESTIONNAIRE DE CHAMPS DYNAMIQUES
// =====================================================================
let customFields = <?= json_encode($custom_fields) ?>;

const modalOverlay     = document.getElementById('fieldManagerModal');
const openBtn          = document.getElementById('openFieldManager');
const closeBtn         = document.getElementById('closeFieldManager');
const fieldListDiv     = document.getElementById('fieldListContainer');
const btnAddField      = document.getElementById('btnAddField');
const newFieldLabel    = document.getElementById('newFieldLabel');
const newFieldType     = document.getElementById('newFieldType');
const newFieldPh       = document.getElementById('newFieldPlaceholder');
const newFieldOptions  = document.getElementById('newFieldOptions');
const optionsRow       = document.getElementById('optionsRow');
const fieldMsgDiv      = document.getElementById('fieldManagerMsg');
const customSection    = document.getElementById('customFieldsSection');
const customContainer  = document.getElementById('customFieldsContainer');

openBtn.addEventListener('click', () => {
    modalOverlay.classList.add('open');
    renderFieldList();
});
closeBtn.addEventListener('click', () => modalOverlay.classList.remove('open'));
modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) modalOverlay.classList.remove('open'); });

newFieldType.addEventListener('change', () => {
    optionsRow.classList.toggle('visible', newFieldType.value === 'select');
});

function typeBadge(type) {
    const map = { text: ['Texte', 'type-text'], number: ['Nombre', 'type-number'], textarea: ['Mémo', 'type-textarea'], select: ['Liste', 'type-select'], date: ['Date', 'type-date'] };
    const [label, cls] = map[type] || ['?', 'type-text'];
    return `<span class="type-badge ${cls}">${label}</span>`;
}

function renderFieldList() {
    if (!customFields.length) {
        fieldListDiv.innerHTML = '<div class="empty-state"><span class="empty-icon">📋</span><p>Aucune rubrique personnalisée. Créez-en une ci-dessous !</p></div>';
        return;
    }
    fieldListDiv.innerHTML = '';
    customFields.forEach(f => {
        const d = document.createElement('div');
        d.className = 'field-list-item';
        let metaHtml = '';
        if (f.placeholder) metaHtml += `<div class="field-meta">Indice : ${escHtml(f.placeholder)}</div>`;
        if (f.options_json) metaHtml += `<div class="field-meta">Options : ${escHtml(JSON.parse(f.options_json).join(', '))}</div>`;
        d.innerHTML = `
            <div>
                <div style="font-weight:600;color:var(--secondary);">${escHtml(f.label)} ${typeBadge(f.field_type)}</div>
                ${metaHtml}
            </div>
            <button class="btn-del-field" data-id="${f.id}" title="Supprimer">🗑️</button>`;
        d.querySelector('.btn-del-field').addEventListener('click', function() {
            if (confirm(`Supprimer la rubrique "${f.label}" ?`)) deleteField(this.dataset.id);
        });
        fieldListDiv.appendChild(d);
    });
}

function showFieldMsg(text, err) {
    fieldMsgDiv.textContent = text;
    fieldMsgDiv.style.cssText = `display:block;font-size:12px;text-align:center;margin-top:10px;padding:8px;border-radius:8px;background:${err ? '#fef2f2' : '#f0fdf4'};color:${err ? 'var(--danger)' : 'var(--primary)'};`;
    setTimeout(() => { fieldMsgDiv.style.display = 'none'; }, 4000);
}

function addFieldToForm(field) {
    customSection.style.display = '';
    customSection.classList.add('open');
    const wrapper = document.createElement('div');
    wrapper.className = 'form-group custom-field-row';
    wrapper.dataset.fieldId = field.id;
    let inputHtml = '';
    if (field.field_type === 'textarea') {
        inputHtml = `<textarea name="custom_field_${field.id}" class="form-control" rows="2" placeholder="${escHtml(field.placeholder || '')}"></textarea>`;
    } else if (field.field_type === 'select' && field.options_json) {
        const opts = JSON.parse(field.options_json);
        const optsHtml = opts.map(o => `<option value="${escHtml(o)}">${escHtml(o)}</option>`).join('');
        inputHtml = `<select name="custom_field_${field.id}" class="form-control"><option value="">-- Choisir --</option>${optsHtml}</select>`;
    } else {
        const t = field.field_type === 'date' ? 'date' : (field.field_type === 'number' ? 'number' : 'text');
        inputHtml = `<input type="${t}" name="custom_field_${field.id}" class="form-control" placeholder="${escHtml(field.placeholder || '')}">`;
    }
    wrapper.innerHTML = `
        <label class="form-label">${escHtml(field.label)}</label>
        ${inputHtml}
        <button type="button" class="remove-field-inline" title="Masquer ce champ" onclick="removeFieldFromForm(this, ${field.id})">✕</button>`;
    customContainer.appendChild(wrapper);
}

function removeFieldFromForm(btn, fieldId) {
    const row = btn.closest('.custom-field-row');
    if (row) row.remove();
    if (!customContainer.querySelector('.custom-field-row')) {
        customSection.style.display = 'none';
    }
}

btnAddField.addEventListener('click', () => {
    const label = newFieldLabel.value.trim();
    const type  = newFieldType.value;
    const ph    = newFieldPh.value.trim();
    const opts  = newFieldOptions.value.trim();
    if (!label) return showFieldMsg('Le libellé est obligatoire.', true);
    let options_json = null;
    if (type === 'select') {
        if (!opts) return showFieldMsg('Saisir les options pour une liste.', true);
        options_json = JSON.stringify(opts.split(',').map(s => s.trim()).filter(s => s));
    }
    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('label', label);
    fd.append('field_type', type);
    fd.append('placeholder', ph);
    if (options_json) fd.append('options_json', options_json);
    fetch('ajax_fields.php', { method: 'POST', body: fd })
        .then(r => r.ok ? r.json() : r.json().then(e => { throw new Error(e.error); }))
        .then(data => {
            if (data.success) {
                const f = { id: data.id.toString(), label: data.label, field_type: data.field_type, options_json: data.options_json, placeholder: data.placeholder, ordre: data.ordre };
                customFields.push(f);
                addFieldToForm(f);
                renderFieldList();
                newFieldLabel.value = '';
                newFieldPh.value = '';
                newFieldOptions.value = '';
                newFieldType.value = 'text';
                optionsRow.classList.remove('visible');
                showFieldMsg('Rubrique créée avec succès !');
            }
        }).catch(e => showFieldMsg(e.message, true));
});

function deleteField(id) {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('ajax_fields.php', { method: 'POST', body: fd })
        .then(r => r.ok ? r.json() : r.json().then(e => { throw new Error(e.error); }))
        .then(data => {
            if (data.success) {
                customFields = customFields.filter(f => f.id != id);
                const row = customContainer.querySelector(`.custom-field-row[data-field-id="${id}"]`);
                if (row) row.remove();
                if (!customContainer.querySelector('.custom-field-row')) customSection.style.display = 'none';
                renderFieldList();
                showFieldMsg('Rubrique supprimée.');
            }
        }).catch(e => showFieldMsg(e.message, true));
}

// =====================================================================
// UTILITAIRE
// =====================================================================
function escHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>

</body>
</html>
