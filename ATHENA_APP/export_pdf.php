<?php
session_start();
require_once 'db.php';
require_once 'dompdf/vendor/autoload.php'; // Chemin standardisé

use Dompdf\Dompdf;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Accès refusé");
}

// 1. Gestion des filtres identique au rapport
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

$totalCA = $pdo->query("SELECT SUM(prix_cure) FROM clients $whereClause")->fetchColumn();
$ventesParPoste = $pdo->query("SELECT cree_par, COUNT(*) as nb_ventes, SUM(prix_cure) as total FROM clients $whereClause GROUP BY cree_par ORDER BY total DESC")->fetchAll();

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
// Préparation du logo
$logoPath = dirname(__DIR__) . '/IMG/athena.jpg';
$logoData = base64_encode(@file_get_contents($logoPath));
$logoSrc = 'data:image/jpeg;base64,' . $logoData;

// 2. Construction du HTML pour le PDF
$html = '
<style>
    body { font-family: sans-serif; color: #333; }
    .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #b8860b; padding-bottom: 20px; }
    h1 { color: #b8860b; text-transform: uppercase; letter-spacing: 1px; margin-top: 10px; font-size: 24px; }
    .date { color: #666; font-size: 12px; }
    .ca-box { background: #fcf8ee; border: 1px solid #b8860b; padding: 25px; text-align: center; border-radius: 15px; margin-bottom: 30px; }
    .ca-box h2 { color: #b8860b; margin: 0; font-size: 28px; }
    .ca-box span { text-transform: uppercase; font-size: 11px; color: #7f8c8d; letter-spacing: 1px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background-color: #b8860b; color: white; padding: 12px; border: 1px solid #966d00; text-align: left; text-transform: uppercase; font-size: 11px; }
    td { padding: 10px; border: 1px solid #eee; font-size: 13px; }
    tr:nth-child(even) { background-color: #fcf8ee; }
    h3 { color: #2c3e50; border-left: 5px solid #b8860b; padding-left: 10px; text-transform: uppercase; font-size: 15px; margin-top: 30px; }
</style>

<div class="header">
    <img src="' . $logoSrc . '" width="80"><br>
    <h1>Rapport Financier ' . $titleLabel . '</h1>
    <div class="date">Athena Industrie — État généré le ' . date('d/m/Y à H:i') . '</div>
</div>

<div class="ca-box">
    <span>Chiffre d\'Affaires Total (' . $titleLabel . ')</span>
    <h2>' . number_format($totalCA, 2) . ' $</h2>
</div>

<h3>Répartition par Poste de Travail</h3>
<table>
    <thead>
        <tr>
            <th>Poste / Utilisateur</th>
            <th align="center">Nombre de ventes</th>
            <th align="right">Total encaissé</th>
        </tr>
    </thead>
    <tbody>';

foreach ($ventesParPoste as $v) {
    $html .= '
        <tr>
            <td>' . htmlspecialchars($v['cree_par']) . '</td>
            <td align="center">' . $v['nb_ventes'] . '</td>
            <td align="right">' . number_format($v['total'], 2) . ' $</td>
        </tr>';
}

$html .= '</tbody></table>';

$html .= '<h3>Détail des Transactions</h3>
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Client</th>
            <th>Cure(s)</th>
            <th align="right">Consultation</th>
            <th align="right">Total</th>
            <th align="center">Source</th>
        </tr>
    </thead>
    <tbody>';

foreach ($transactions as $t) {
    $frais_consultation = $t['total'] - $t['total_produits'];
    $html .= '
        <tr>
            <td>' . date('d/m/Y H:i', strtotime($t['date_enregistrement'])) . '</td>
            <td><strong>' . htmlspecialchars($t['nom_prenom']) . '</strong></td>
            <td>' . htmlspecialchars($t['cures'] ?: '-') . '</td>
            <td align="right">' . number_format($frais_consultation, 2) . ' $</td>
            <td align="right" style="color: #b8860b; font-weight: bold;">' . number_format($t['total'], 2) . ' $</td>
            <td align="center">' . htmlspecialchars($t['cree_par']) . '</td>
        </tr>';
}

$html .= '</tbody></table>';

// 3. Initialisation de Dompdf
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nettoyage du tampon de sortie pour éviter la corruption du fichier
ob_end_clean();

// Attachment => false permet d'ouvrir le PDF dans le navigateur au lieu de forcer le téléchargement
$dompdf->stream("Rapport_Athena_" . date('Y-m-d') . ".pdf", ["Attachment" => false]);
?>