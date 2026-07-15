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
// GRAPHIQUE D'ÉVOLUTION — Généré en HTML/CSS (compatible Dompdf)
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

/**
 * Génère un graphique d'évolution visuel en HTML/CSS pur
 * Compatible Dompdf à 100% (pas de SVG, pas de JS)
 */
function generateEvolutionChart($labels, $weights, $tailles) {
    $nb = count($labels);
    if ($nb === 0) {
        return '<p style="text-align:center;color:#999;font-size:13px;">Aucune donnée de suivi disponible.</p>';
    }
    
    if ($nb === 1) {
        $w = $weights[0] ?? 0;
        $t = $tailles[0] ?? null;
        $html = '<div style="text-align:center;padding:15px;">';
        $html .= '<div style="font-size:24px;font-weight:bold;color:#b8860b;">' . number_format($w, 1) . ' kg</div>';
        $html .= '<div style="font-size:12px;color:#999;">Poids actuel &mdash; <strong>' . htmlspecialchars($labels[0]) . '</strong></div>';
        if ($t) {
            $html .= '<div style="font-size:18px;font-weight:bold;color:#e74c3c;margin-top:8px;">' . number_format($t, 1) . ' cm</div>';
            $html .= '<div style="font-size:12px;color:#999;">Tour de taille</div>';
        }
        $html .= '<div style="margin-top:10px;font-size:12px;color:#999;">Ajoutez plus de consultations pour voir la courbe d\'évolution.</div>';
        $html .= '</div>';
        return $html;
    }
    
    // Trouver les bornes
    $min_w = min($weights);
    $max_w = max($weights);
    $range_w = ($max_w - $min_w) ?: 1;
    $marge = $range_w * 0.15;
    $baseline_w = max(0, $min_w - $marge);
    $top_w = $max_w + $marge;
    
    $has_tailles = count(array_filter($tailles)) > 0;
    if ($has_tailles) {
        $t_filtres = array_filter($tailles);
        $min_t = min($t_filtres);
        $max_t = max($t_filtres);
        $range_t = ($max_t - $min_t) ?: 1;
        $marge_t = $range_t * 0.15;
        $baseline_t = max(0, $min_t - $marge_t);
        $top_t = $max_t + $marge_t;
    }
    
    $chart_height = 160;
    $left_margin = 35;
    $point_spacing = $nb > 1 ? min(60, (420 - $left_margin) / ($nb - 1)) : 0;
    $total_width = max($left_margin + ($nb - 1) * $point_spacing + 40, 300);
    
    // Génération du graphique en HTML/CSS
    $html = '<div style="position:relative;width:' . $total_width . 'px;height:' . ($chart_height + 35) . 'px;margin:0 auto;border-left:1px solid #ddd;border-bottom:1px solid #ddd;">';
    
    // Grille horizontale (3 lignes)
    for ($i = 0; $i <= 3; $i++) {
        $y_pos = $chart_height - ($chart_height * $i / 3);
        $val_w = $baseline_w + ($top_w - $baseline_w) * $i / 3;
        $html .= '<div style="position:absolute;left:' . ($left_margin - 2) . 'px;top:' . round($y_pos) . 'px;width:' . ($total_width - $left_margin) . 'px;height:1px;background:#f0f0f0;"></div>';
        $html .= '<div style="position:absolute;left:0px;top:' . round($y_pos - 6) . 'px;width:' . ($left_margin - 5) . 'px;text-align:right;font-size:9px;color:#999;">' . number_format($val_w, 1) . '</div>';
    }
    
    // Points de poids
    $points_html = '';
    $labels_html = '';
    
    foreach ($weights as $i => $w) {
        $x = $left_margin + $i * $point_spacing;
        $ratio = ($w - $baseline_w) / ($top_w - $baseline_w);
        $y = $chart_height - ($ratio * $chart_height);
        $y = max(5, min($chart_height - 5, $y));
        
        // Cercle
        $points_html .= '<div style="position:absolute;left:' . round($x - 5) . 'px;top:' . round($y - 5) . 'px;width:10px;height:10px;border-radius:50%;background:#b8860b;border:2px solid white;"></div>';
        
        // Valeur au-dessus
        $points_html .= '<div style="position:absolute;left:' . round($x - 18) . 'px;top:' . round($y - 18) . 'px;width:36px;text-align:center;font-size:8px;font-weight:bold;color:#b8860b;">' . number_format($w, 1) . '</div>';
        
        // Ligne de connexion entre points
        if ($i > 0) {
            $prev_w = $weights[$i - 1];
            $prev_ratio = ($prev_w - $baseline_w) / ($top_w - $baseline_w);
            $prev_y = $chart_height - ($prev_ratio * $chart_height);
            $prev_y = max(5, min($chart_height - 5, $prev_y));
            $prev_x = $left_margin + ($i - 1) * $point_spacing;
            
            // On dessine la ligne entre les deux points
            $points_html .= '<div style="position:absolute;left:' . round($prev_x) . 'px;top:' . round(min($prev_y, $y)) . 'px;width:' . round(abs($x - $prev_x)) . 'px;height:' . round(abs($y - $prev_y) + 1) . 'px;overflow:hidden;">';
            if ($prev_y < $y) {
                // Montant
                $points_html .= '<div style="position:absolute;left:0;top:0;width:1px;height:100%;background:#b8860b;"></div>';
                $points_html .= '<div style="position:absolute;left:0;top:' . round($y - $prev_y) . 'px;width:100%;height:1px;background:#b8860b;"></div>';
            } else if ($prev_y > $y) {
                // Descendant
                $points_html .= '<div style="position:absolute;left:0;top:0;width:1px;height:100%;background:#b8860b;"></div>';
                $points_html .= '<div style="position:absolute;left:0;top:0;width:100%;height:1px;background:#b8860b;"></div>';
            } else {
                // Horizontal
                $points_html .= '<div style="position:absolute;left:0;top:50%;width:100%;height:1px;background:#b8860b;"></div>';
            }
            $points_html .= '</div>';
        }
        
        // Label date
        $labels_html .= '<div style="position:absolute;left:' . round($x - 20) . 'px;top:' . ($chart_height + 5) . 'px;width:40px;text-align:center;font-size:7px;color:#666;">' . htmlspecialchars($labels[$i]) . '</div>';
    }
    
    // Points tour de taille (si disponibles)
    if ($has_tailles) {
        foreach ($tailles as $i => $t) {
            if ($t === null) continue;
            $x = $left_margin + $i * $point_spacing;
            $ratio = ($t - $baseline_t) / ($top_t - $baseline_t);
            $y = $chart_height - ($ratio * $chart_height);
            $y = max(5, min($chart_height - 5, $y));
            
            // Cercle rouge
            $points_html .= '<div style="position:absolute;left:' . round($x - 5) . 'px;top:' . round($y - 5) . 'px;width:10px;height:10px;border-radius:50%;background:#e74c3c;border:2px solid white;"></div>';
            
            // Connexion avec le précédent non-null
            for ($j = $i - 1; $j >= 0; $j--) {
                if ($tailles[$j] !== null) {
                    $prev_t = $tailles[$j];
                    $prev_x = $left_margin + $j * $point_spacing;
                    $prev_ratio = ($prev_t - $baseline_t) / ($top_t - $baseline_t);
                    $prev_y = $chart_height - ($prev_ratio * $chart_height);
                    $prev_y = max(5, min($chart_height - 5, $prev_y));
                    
                    $points_html .= '<div style="position:absolute;left:' . round($prev_x) . 'px;top:' . round(min($prev_y, $y)) . 'px;width:' . round(abs($x - $prev_x)) . 'px;height:' . round(abs($y - $prev_y) + 1) . 'px;overflow:hidden;opacity:0.6;">';
                    if ($prev_y < $y) {
                        $points_html .= '<div style="position:absolute;left:0;top:0;width:1px;height:100%;background:#e74c3c;"></div>';
                        $points_html .= '<div style="position:absolute;left:0;top:' . round($y - $prev_y) . 'px;width:100%;height:1px;background:#e74c3c;"></div>';
                    } else if ($prev_y > $y) {
                        $points_html .= '<div style="position:absolute;left:0;top:0;width:1px;height:100%;background:#e74c3c;"></div>';
                        $points_html .= '<div style="position:absolute;left:0;top:0;width:100%;height:1px;background:#e74c3c;"></div>';
                    } else {
                        $points_html .= '<div style="position:absolute;left:0;top:50%;width:100%;height:1px;background:#e74c3c;"></div>';
                    }
                    $points_html .= '</div>';
                    break;
                }
            }
        }
    }
    
    $html .= $points_html;
    $html .= $labels_html;
    $html .= '</div>';
    
    // Légende
    $html .= '<div style="text-align:center;margin-top:15px;font-size:11px;color:#333;">';
    $html .= '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#b8860b;vertical-align:middle;margin-right:4px;"></span> <strong>Poids (kg)</strong>';
    if ($has_tailles) {
        $html .= ' &nbsp;&nbsp;&nbsp; ';
        $html .= '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#e74c3c;vertical-align:middle;margin-right:4px;"></span> <strong>Tour de taille (cm)</strong>';
    }
    $html .= '</div>';
    
    return $html;
}

$chartHtmlInline = generateEvolutionChart($graphLabels, $graphWeights, $graphTailles);

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
        <td class="value">' . ($c['sexe'] == 'F' ? 'Femme' : 'Homme') . ' &mdash; ' . ($c['age'] ?? 'N/R') . ' ans</td>
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
            <td>' . ($c['taille_cm'] ?? '&mdash;') . ' cm</td>
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

<div style="page-break-before:auto; page-break-inside:avoid; margin-top:30px;">
    <div class="section-title">Courbe d Evolution</div>
    <div style="text-align:center; padding:20px 10px; border:1px solid #ccc; background:#fafafa;">
        ' . $chartHtmlInline . '
        <p style="font-size:11px; color:#7f8c8d; margin-top:8px;">
            Evolution sur ' . count($graphLabels) . ' consultation(s)
        </p>
    </div>
</div>

<div style="page-break-before:auto;">
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
            <td style="padding:7px;text-align:center;">' . ($h['tour_taille'] ? number_format($h['tour_taille'], 1) . ' cm' : '&mdash;') . '</td>
            <td style="padding:7px;text-align:center;">' . ($h['imc'] ? number_format($h['imc'], 1) : '&mdash;') . '</td>
            <td style="padding:7px;text-align:center;">' . number_format($h['note_activite'], 0) . '/10</td>
            <td style="padding:7px;">' . htmlspecialchars(mb_substr($h['notes_suivi'] ?? ($h['notes_alimentation'] ?? '&mdash;'), 0, 60)) . '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>

<div style="margin-top:20px; padding:12px; background:#e8f6ef; border-radius:6px; border-left:4px solid #b8860b;">
    <b style="color:#b8860b;">Prix Total de la Cure :</b> <strong>' . number_format($c['prix_cure'], 2) . ' $</strong>
</div>

<div class="footer">
    Fiche générée par <strong>' . htmlspecialchars($c['cree_par'] ?? 'Système') . '</strong> &mdash; ' . date('d/m/Y à H:i') . '
</div>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "Fiche_Athena_" . str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9_ -]/', '', $c['nom_prenom'])) . ".pdf";
ob_end_clean();
$dompdf->stream($filename, ["Attachment" => false]);
?>