<?php
session_start();
require_once 'db.php';
require_once 'dompdf/vendor/autoload.php';

use Dompdf\Dompdf;

if (!isset($_GET['id'])) die("ID client manquant.");

$id = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) die("Client introuvable.");

// Historique de progression
$stmtH = $pdo->prepare("SELECT * FROM suivi_progression WHERE client_id = ? ORDER BY date_consultation DESC, id DESC");
$stmtH->execute([$id]);
$historique = $stmtH->fetchAll();

// ============================================================
// GRAPHIQUE SVG GÉNÉRÉ LOCALEMENT (sans dépendance externe)
// ============================================================
$graphLabels = [];
$graphWeights = [];
$graphTailles = [];
$stmtG = $pdo->prepare("SELECT date_consultation, poids_mesure, tour_taille FROM suivi_progression WHERE client_id = ? ORDER BY date_consultation ASC, id ASC");
$stmtG->execute([$id]);
foreach ($stmtG->fetchAll() as $dg) {
    $graphLabels[]  = date('d/m', strtotime($dg['date_consultation']));
    $graphWeights[] = (float)$dg['poids_mesure'];
    $graphTailles[] = $dg['tour_taille'] ? (float)$dg['tour_taille'] : null;
}

// Nettoyer les nulls en bout de tableau
while (!empty($graphTailles) && end($graphTailles) === null) {
    array_pop($graphTailles);
}

// Générer un graphique SVG inline
function generateSvgChart($labels, $weights, $tailles) {
    $nb = count($labels);
    if ($nb === 0) return '<p style="text-align:center;color:#999;">Aucune donnée de suivi</p>';
    
    $W = 500; $H = 200;
    $pad = [40, 45, 30, 20]; // left, top, right, bottom
    $gw = $W - $pad[0] - $pad[2];
    $gh = $H - $pad[1] - $pad[3];
    
    // Trouver les min/max pour les échelles
    $w_all = array_filter($weights);
    $t_all = array_filter($tailles);
    $all_w = count($w_all) ? $w_all : [0, 100];
    $all_t = count($t_all) ? $t_all : [0, 100];
    $min_w = min($all_w); $max_w = max($all_w);
    $min_t = min($all_t); $max_t = max($all_t);
    $range_w = ($max_w - $min_w) ?: 10;
    $range_t = ($max_t - $min_t) ?: 10;
    $min_w = floor(($min_w - $range_w * 0.1) / 5) * 5;
    $max_w = ceil(($max_w + $range_w * 0.1) / 5) * 5;
    $min_t = floor(($min_t - $range_t * 0.1) / 5) * 5;
    $max_t = ceil(($max_t + $range_t * 0.1) / 5) * 5;
    if ($min_w >= $max_w) $max_w = $min_w + 10;
    if ($min_t >= $max_t) $max_t = $min_t + 10;
    
    $x_step = $nb > 1 ? $gw / ($nb - 1) : $gw / 2;
    
    $lines_w = '';
    $lines_t = '';
    $circles = '';
    $labels_svg = '';
    
    foreach ($weights as $i => $w) {
        $x = $pad[0] + ($nb > 1 ? $i * $x_step : $gw / 2);
        $y = $pad[1] + $gh - (($w - $min_w) / ($max_w - $min_w)) * $gh;
        $y = max($pad[1], min($pad[1] + $gh, $y));
        
        $lines_w .= ($i > 0 ? " L$x,$y" : " M$x,$y");
        
        $circles .= "<circle cx='$x' cy='$y' r='3' fill='#b8860b' stroke='white' stroke-width='1.5'/>";
        $labels_svg .= "<text x='$x' y='" . ($pad[1] + $gh + 15) . "' text-anchor='middle' font-size='8' fill='#666' transform='rotate(-30,$x," . ($pad[1] + $gh + 15) . ")'>" . htmlspecialchars($labels[$i]) . "</text>";
        
        // Valeur du poids au-dessus du point
        $labels_svg .= "<text x='$x' y='" . ($y - 8) . "' text-anchor='middle' font-size='7' fill='#b8860b' font-weight='bold'>" . number_format($w, 1) . "</text>";
    }
    
    // Courbe tour de taille
    $has_taille = false;
    foreach ($tailles as $i => $t) {
        if ($t === null) continue;
        $has_taille = true;
        $x = $pad[0] + ($nb > 1 ? $i * $x_step : $gw / 2);
        $y = $pad[1] + $gh - (($t - $min_t) / ($max_t - $min_t)) * $gh;
        $y = max($pad[1], min($pad[1] + $gh, $y));
        $lines_t .= ($lines_t === '' ? " M$x,$y" : " L$x,$y");
        $circles .= "<circle cx='$x' cy='$y' r='3' fill='#e74c3c' stroke='white' stroke-width='1.5'/>";
    }
    
    // Grille horizontale
    $grid = '';
    for ($i = 0; $i <= 5; $i++) {
        $y = $pad[1] + $gh * (1 - $i / 5);
        $val_w = $min_w + ($max_w - $min_w) * $i / 5;
        $grid .= "<line x1='{$pad[0]}' y1='$y' x2='" . ($W - $pad[2]) . "' y2='$y' stroke='#eee' stroke-width='0.5'/>";
        $grid .= "<text x='" . ($pad[0] - 5) . "' y='" . ($y + 3) . "' text-anchor='end' font-size='8' fill='#999'>" . round($val_w, 1) . "</text>";
    }
    
    // Légende
    $legend = "<rect x='" . ($W - 120) . "' y='5' width='115' height='35' fill='white' fill-opacity='0.9' rx='4'/>";
    $legend .= "<line x1='" . ($W - 110) . "' y1='15' x2='" . ($W - 85) . "' y2='15' stroke='#b8860b' stroke-width='2'/>";
    $legend .= "<text x='" . ($W - 80) . "' y='18' font-size='9' fill='#333'>Poids (kg)</text>";
    if ($has_taille) {
        $legend .= "<line x1='" . ($W - 110) . "' y1='30' x2='" . ($W - 85) . "' y2='30' stroke='#e74c3c' stroke-width='2' stroke-dasharray='4,3'/>";
        $legend .= "<text x='" . ($W - 80) . "' y='33' font-size='9' fill='#333'>Tour taille (cm)</text>";
    }
    
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='$W' height='$H' viewBox='0 0 $W $H' style='max-width:100%;'>
        <rect width='$W' height='$H' fill='white'/>
        $grid
        <path d='$lines_w' fill='none' stroke='#b8860b' stroke-width='2'/>
        " . ($has_taille && $lines_t ? "<path d='$lines_t' fill='none' stroke='#e74c3c' stroke-width='2' stroke-dasharray='5,4'/>" : '') . "
        $circles
        $labels_svg
        $legend
    </svg>";
    
    return $svg;
}

$chartSvgInline = generateSvgChart($graphLabels, $graphWeights, $graphTailles);

$ecart = ($c['poids_actuel'] ?? 0) - ($c['poids_objectif'] ?? 0);
$imc_actuel = ($c['taille_cm'] ?? 0) > 0 ? ($c['poids_actuel'] ?? 0) / pow(($c['taille_cm'] / 100), 2) : 0;

// Logo
$logoPath = dirname(__DIR__) . '/IMG/athena.jpg';
$logoData = base64_encode(@file_get_contents($logoPath));
$logoSrc  = 'data:image/jpeg;base64,' . $logoData;

// ---- Champs personnalisés du client ----
$custom_fields_html = '';
try {
    $stmtCF = $pdo->prepare("
        SELECT f.label, f.field_type, v.valeur
        FROM form_fields f
        JOIN client_custom_values v ON f.id = v.field_id
        WHERE v.client_id = ? AND f.actif = TRUE AND v.valeur IS NOT NULL AND v.valeur <> ''
        ORDER BY f.ordre ASC, f.id ASC
    ");
    $stmtCF->execute([$id]);
    $custom_rows = $stmtCF->fetchAll();

    if (!empty($custom_rows)) {
        $custom_fields_html .= '<div class="section-title">Informations Complémentaires</div>';
        $custom_fields_html .= '<table class="info-table">';
        foreach ($custom_rows as $cf) {
            $display_val = $cf['field_type'] === 'textarea'
                ? nl2br(htmlspecialchars($cf['valeur']))
                : htmlspecialchars($cf['valeur']);
            $custom_fields_html .= '
            <tr>
                <td class="label">' . htmlspecialchars($cf['label']) . '</td>
                <td class="value">' . $display_val . '</td>
            </tr>';
        }
        $custom_fields_html .= '</table>';
    }
} catch (Exception $e) {
    // Table absente ou erreur : on ignore silencieusement
}

// ====== GÉNÉRATION HTML DU PDF ======
$html = '
<style>
    body { font-family: "Helvetica", "Arial", sans-serif; color: #333; }
    .header { text-align: center; border-bottom: 3px solid #b8860b; padding-bottom: 10px; margin-bottom: 20px; }
    .header h1 { color: #b8860b; margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
    .header p  { color: #7f8c8d; margin: 4px 0 0 0; font-size: 12px; }
    .section-title { background: #fcf8ee; padding: 8px 10px; font-size: 14px; font-weight: bold; color: #2c3e50; border-left: 5px solid #b8860b; margin-top: 20px; text-transform: uppercase; }
    .info-table { width: 100%; margin-top: 10px; border-collapse: collapse; }
    .info-table td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
    .label { font-weight: bold; color: #7f8c8d; width: 35%; }
    .value { color: #2c3e50; }
    .poids-table { width: 100%; margin-top: 15px; border-collapse: collapse; text-align: center; }
    .poids-table th { background: #b8860b; color: white; padding: 10px; text-transform: uppercase; font-size: 12px; }
    .poids-table td { border: 1px solid #b8860b; padding: 15px; font-size: 18px; font-weight: bold; color: #2c3e50; }
    .measures-summary { width: 100%; margin-top: 12px; border-collapse: collapse; }
    .measures-summary td { padding: 10px; border: 1px solid #b8860b; text-align: center; width: 25%; }
    .measures-summary .ms-label { font-size: 10px; text-transform: uppercase; color: #7f8c8d; font-weight: normal; }
    .measures-summary .ms-value { font-size: 20px; font-weight: 800; color: #2c3e50; }
    .footer { margin-top: 40px; font-size: 11px; text-align: right; border-top: 1px solid #eee; padding-top: 10px; font-style: italic; color: #999; }
</style>

<div class="header">
    <img src="' . $logoSrc . '" width="60" style="margin-bottom:8px;"><br>
    <h1>Fiche de Suivi Athéna</h1>
    <p>Expertise en Transformation Corporelle</p>
</div>

<div class="section-title">Identité &amp; Contact</div>
<table class="info-table">
    <tr>
        <td class="label">Nom &amp; Prénom</td>
        <td class="value"><strong>' . htmlspecialchars($c['nom_prenom']) . '</strong></td>
    </tr>
    <tr>
        <td class="label">Sexe / Âge</td>
        <td class="value">' . ($c['sexe'] == 'F' ? 'Femme' : 'Homme') . ' — ' . ($c['age'] ?? 'N/R') . ' ans</td>
    </tr>
    <tr>
        <td class="label">Téléphone</td>
        <td class="value">' . htmlspecialchars($c['telephone'] ?? 'Non renseigné') . '</td>
    </tr>
    <tr>
        <td class="label">Ville</td>
        <td class="value">' . htmlspecialchars($c['ville'] ?? 'Non renseignée') . '</td>
    </tr>
    <tr>
        <td class="label">Date d\'enregistrement</td>
        <td class="value">' . date('d/m/Y', strtotime($c['date_enregistrement'])) . '</td>
    </tr>
</table>

<div class="section-title">Bilan Morphologique</div>
<table class="poids-table">
    <thead>
        <tr>
            <th>Poids Actuel</th>
            <th>Poids Objectif</th>
            <th>Écart</th>
            <th>Taille</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>' . number_format($c['poids_actuel'] ?? 0, 1) . ' kg</td>
            <td>' . number_format($c['poids_objectif'] ?? 0, 1) . ' kg</td>
            <td style="color:' . ($ecart > 0 ? '#e74c3c' : '#27ae60') . ';">' . number_format($ecart, 1) . ' kg</td>
            <td>' . ($c['taille_cm'] ?? '—') . ' cm</td>
        </tr>
    </tbody>
</table>

<div class="section-title">Indicateurs Corporels</div>
<table class="measures-summary">
    <tr>
        <td>
            <div class="ms-label">IMC</div>
            <div class="ms-value">' . number_format($imc_actuel, 1) . '</div>
        </td>
        <td>
            <div class="ms-label">Activité</div>
            <div class="ms-value">' . ($c['activite_physique'] ?? 0) . '/10</div>
        </td>
        <td>
            <div class="ms-label">Accouchements</div>
            <div class="ms-value">' . ($c['nombre_accouchements'] ?? 0) . '</div>
        </td>
        <td>
            <div class="ms-label">Allaitement</div>
            <div class="ms-value">' . ($c['allaitement'] ?? 'Non') . '</div>
        </td>
    </tr>
</table>

<div class="section-title">Hygiène &amp; Santé</div>
<table class="info-table">
    <tr>
        <td class="label">Alimentation</td>
        <td class="value">' . nl2br(htmlspecialchars($c['alimentation'] ?? 'Non renseignée')) . '</td>
    </tr>
    <tr>
        <td class="label">Antécédents Médicaux</td>
        <td class="value">' . nl2br(htmlspecialchars($c['antecedents_medicaux'] ?? 'Aucun')) . '</td>
    </tr>
    <tr>
        <td class="label">Allergies / Intolérances</td>
        <td class="value">' . nl2br(htmlspecialchars($c['allergies_intolerances'] ?? 'Aucune')) . '</td>
    </tr>
    <tr>
        <td class="label">Opérations Chirurgicales</td>
        <td class="value">' . nl2br(htmlspecialchars($c['operations_chirurgicales'] ?? 'Aucune')) . '</td>
    </tr>
</table>

' . $custom_fields_html . '

<div class="section-title">📈 Courbe d\'Évolution</div>
<div style="text-align:center; margin-top:10px; border:1px solid #eee; padding:10px; border-radius:8px;">
    ' . $chartSvgInline . '
    <p style="font-size:11px; color:#7f8c8d; margin-top:5px;">
        📊 Évolution sur ' . count($graphLabels) . ' consultation(s)
    </p>
</div>

<div class="section-title">Historique des Consultations</div>
<table class="info-table" style="font-size:11px;">
    <thead>
        <tr style="background:#f1f5f9;">
            <th align="left"  style="padding:8px;">Date</th>
            <th align="center" style="padding:8px;">Poids</th>
            <th align="center" style="padding:8px;">Tour taille</th>
            <th align="center" style="padding:8px;">IMC</th>
            <th align="center" style="padding:8px;">Activité</th>
            <th align="left"  style="padding:8px;">Notes</th>
        </tr>
    </thead>
    <tbody>';

$prev_poids = null;
$prev_taille = null;
foreach ($historique as $h) {
    $diff_poids = '';
    if ($prev_poids !== null && $h['poids_mesure'] !== null) {
        $d = $h['poids_mesure'] - $prev_poids;
        $color = $d > 0 ? '#e74c3c' : ($d < 0 ? '#27ae60' : '#7f8c8d');
        $diff_poids = ' <span style="color:' . $color . ';font-weight:700;">(' . ($d > 0 ? '+' : '') . number_format($d, 1) . ')</span>';
    }
    $prev_poids = $h['poids_mesure'];
    
    $html .= '
        <tr>
            <td style="padding:7px;">' . date('d/m/Y', strtotime($h['date_consultation'])) . '</td>
            <td style="padding:7px;text-align:center;"><strong>' . number_format($h['poids_mesure'], 1) . '</strong> kg' . $diff_poids . '</td>
            <td style="padding:7px;text-align:center;">' . ($h['tour_taille'] ? number_format($h['tour_taille'], 1) . ' cm' : '—') . '</td>
            <td style="padding:7px;text-align:center;">' . ($h['imc'] ? number_format($h['imc'], 1) : '—') . '</td>
            <td style="padding:7px;text-align:center;">' . number_format($h['note_activite'], 0) . '/10</td>
            <td style="padding:7px;">' . htmlspecialchars(mb_substr($h['notes_suivi'] ?? ($h['notes_alimentation'] ?? '—'), 0, 60)) . '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>

<div style="margin-top:20px; padding:12px; background:#e8f6ef; border-radius:6px; border-left:4px solid #b8860b;">
    <b style="color:#b8860b;">Prix Total de la Cure :</b> <strong>' . number_format($c['prix_cure'], 2) . ' $</strong>
</div>

<div class="footer">
    Fiche générée par <strong>' . htmlspecialchars($c['cree_par'] ?? 'Système') . '</strong> — ' . date('d/m/Y à H:i') . '
</div>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "Fiche_Athena_" . str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9_ -]/', '', $c['nom_prenom'])) . ".pdf";
ob_end_clean();
$dompdf->stream($filename, ["Attachment" => false]);
?>