<?php
session_start();
require_once 'db.php';
require_once 'dompdf/vendor/autoload.php';

use Dompdf\Dompdf;

if (!isset($_SESSION['user_id'])) {
    die("Accès refusé");
}

$stmt = $pdo->query("SELECT * FROM clients ORDER BY nom_prenom ASC");
$clients = $stmt->fetchAll();

// Préparation du logo
$logoPath = dirname(__DIR__) . '/IMG/athena.jpg';
$logoData = base64_encode(@file_get_contents($logoPath));
$logoSrc = 'data:image/jpeg;base64,' . $logoData;

$html = '
<style>
    body { font-family: sans-serif; font-size: 11px; color: #333; }
    .header-table { width: 100%; border: none; margin-bottom: 20px; }
    h1 { color: #b8860b; margin: 0; text-transform: uppercase; letter-spacing: 1px; }
    .date { color: #666; font-size: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th { background-color: #b8860b; color: white; padding: 10px; border: 1px solid #966d00; text-align: left; text-transform: uppercase; font-size: 10px; }
    td { padding: 8px; border: 1px solid #eee; }
    tr:nth-child(even) { background-color: #fcf8ee; }
    .footer { margin-top: 20px; text-align: right; font-size: 9px; font-style: italic; color: #999; }
</style>
<table class="header-table">
    <tr>
        <td style="border:none; width: 80px;">
            <img src="' . $logoSrc . '" width="60">
        </td>
        <td style="border:none; vertical-align: middle;">
            <h1>Annuaire Complet des Clients</h1>
            <div class="date">Athena Industrie - Généré le ' . date('d/m/Y H:i') . '</div>
        </td>
    </tr>
</table>
<table>
    <thead>
        <tr>
            <th>Nom & Prénom</th>
            <th>Téléphone</th>
            <th>Ville</th>
            <th>Âge</th>
            <th>Poids (kg)</th>
            <th>Objectif (kg)</th>
            <th>Créé par</th>
        </tr>
    </thead>
    <tbody>';

foreach ($clients as $c) {
    $html .= '
        <tr>
            <td>' . htmlspecialchars($c['nom_prenom']) . '</td>
            <td>' . htmlspecialchars($c['telephone'] ?? '-') . '</td>
            <td>' . htmlspecialchars($c['ville'] ?? '-') . '</td>
            <td align="center">' . ($c['age'] ?? '-') . '</td>
            <td align="center">' . ($c['poids_actuel'] ?? '-') . '</td>
            <td align="center">' . ($c['poids_objectif'] ?? '-') . '</td>
            <td>' . htmlspecialchars($c['cree_par']) . '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>
<div class="footer">Athena Industrie - Système de Gestion Interne</div>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

ob_end_clean();
$dompdf->stream("Liste_Clients_Athena_" . date('d-m-Y') . ".pdf", ["Attachment" => false]);