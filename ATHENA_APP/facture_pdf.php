<?php
session_start();
require_once 'db.php';
require_once 'dompdf/vendor/autoload.php';
use Dompdf\Dompdf;

if (!isset($_GET['id'])) die("ID client manquant.");

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$_GET['id']]);
$c = $stmt->fetch();

if (!$c) die("Client introuvable.");

// Récupérer tous les produits associés à ce client
$stmtProds = $pdo->prepare("
    SELECT s.* 
    FROM client_produits cp
    JOIN stocks s ON cp.produit_id = s.id
    WHERE cp.client_id = ?
");
$stmtProds->execute([$c['id']]);
$client_products = $stmtProds->fetchAll();

// Compatibilité : si aucun produit dans la table de liaison, récupérer le produit_id direct
if (empty($client_products) && $c['produit_id']) {
    $stmtFallback = $pdo->prepare("SELECT * FROM stocks WHERE id = ?");
    $stmtFallback->execute([$c['produit_id']]);
    $fallbackProd = $stmtFallback->fetch();
    if ($fallbackProd) {
        $client_products = [$fallbackProd];
    }
}

// --- SYSTÈME DE MISE À JOUR DU STOCK COHÉRENT ---
if (!empty($client_products) && !$c['stock_deduit']) {
    try {
        $pdo->beginTransaction();
        
        $all_deducted = true;
        foreach ($client_products as $p) {
            $updStock = $pdo->prepare("UPDATE stocks SET quantite_disponible = quantite_disponible - 1 WHERE id = ? AND quantite_disponible > 0");
            $updStock->execute([$p['id']]);
            if ($updStock->rowCount() == 0) {
                $all_deducted = false;
                break;
            }
        }

        if ($all_deducted) {
            // Justification dans l'historique pour chaque produit
            foreach ($client_products as $p) {
                $histMsg = "Vente (Facture n°" . date('Y') . "-" . $c['id'] . ") - Client: " . $c['nom_prenom'];
                $stmtHist = $pdo->prepare("INSERT INTO stock_history (produit_id, quantite, execute_par, date_action) VALUES (?, -1, ?, NOW())");
                $stmtHist->execute([$p['id'], $_SESSION['username']]);
            }

            // Marquer le client comme "stock déduit"
            $pdo->prepare("UPDATE clients SET stock_deduit = TRUE WHERE id = ?")->execute([$c['id']]);
            
            $pdo->commit();
            $c['stock_deduit'] = true; // Mise à jour locale pour la session actuelle
        } else {
            $pdo->rollBack(); // Pas assez de stock
        }
    } catch (Exception $e) { 
        if ($pdo->inTransaction()) {
            $pdo->rollBack(); 
        }
    }
}

// Simulation d'un numéro de facture (Ex: FAC-2024-001)
$num_facture = "FAC-" . date('Y') . "-" . str_pad($c['id'], 3, '0', STR_PAD_LEFT);

// Préparation du logo
$logoPath = dirname(__DIR__) . '/IMG/athena.jpg';
$logoData = base64_encode(@file_get_contents($logoPath));
$logoSrc = 'data:image/jpeg;base64,' . $logoData;

$html = '
<style>
    body { font-family: sans-serif; color: #333; }
    .invoice-box { padding: 30px; font-size: 14px; line-height: 24px; }
    .header { display: flex; justify-content: space-between; margin-bottom: 40px; }
    .title { color: #b8860b; font-size: 35px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
    .company-info { float: left; }
    .client-info { float: right; text-align: right; margin-top: 20px; }
    table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
    table th { background: #b8860b; color: white; padding: 10px; border: 1px solid #966d00; text-transform: uppercase; font-size: 12px; }
    table td { padding: 10px; border: 1px solid #ddd; }
    .total { font-size: 22px; font-weight: bold; color: #b8860b; text-align: right; border-top: 2px solid #b8860b; padding-top: 10px; margin-top: 10px; }
</style>

<div class="invoice-box">
    <table cellpadding="0" cellspacing="0">
        <tr class="top">
            <td colspan="2" style="border:none;">
                <table>
                    <tr>
                        <td style="border:none; width: 60%;">
                            <img src="' . $logoSrc . '" width="70"><br>
                            <span class="title" style="font-size: 24px;">ATHENA</span><br>
                            Expertise Bien-être & Santé
                        </td>
                        <td style="border:none; text-align: right;">
                            Facture n° : ' . $num_facture . '<br>
                            Date : ' . date('d/m/Y') . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr class="information">
            <td colspan="2" style="border:none; padding-top: 40px; padding-bottom: 40px;">
                <table>
                    <tr>
                        <td style="border:none;">
                            <strong>Émetteur :</strong><br>
                            Athena Management<br>
                            Contact : ' . htmlspecialchars($_SESSION['username'] ?? 'Admin') . '
                        </td>
                        <td style="border:none; text-align: right;">
                            <strong>Client :</strong><br>
                            ' . htmlspecialchars($c['nom_prenom']) . '<br>
                            ' . htmlspecialchars($c['telephone'] ?? '') . '<br>
                            ' . htmlspecialchars($c['ville'] ?? '') . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Montant</th>
            </tr>
        </thead>
        <tbody>';
        
        $sum_prods = 0;
        foreach ($client_products as $p) {
            $sum_prods += $p['prix_vente'];
            $html .= '
            <tr>
                <td>Cure / Produit : ' . htmlspecialchars($p['nom_produit']) . '</td>
                <td style="text-align: right;">' . number_format($p['prix_vente'], 2) . ' $</td>
            </tr>';
        }
        
        // Calculer les frais de consultation
        $frais_consultation = $c['prix_cure'] - $sum_prods;
        if ($frais_consultation > 0.01) {
            $html .= '
            <tr>
                <td>Frais de consultation</td>
                <td style="text-align: right;">' . number_format($frais_consultation, 2) . ' $</td>
            </tr>';
        }
        
        $html .= '
        </tbody>
    </table>

    <br>
    <div class="total">Total : ' . number_format($c['prix_cure'], 2) . ' $</div>
    
    <div style="margin-top: 100px; font-size: 11px; text-align: center; color: #999;">
        Merci de votre confiance. Cette facture est générée automatiquement.
    </div>
</div>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// --- Début : Code pour sauvegarder la facture sur le serveur ---
$output = $dompdf->output();

// Définir le répertoire de sauvegarde des factures
$save_dir = __DIR__ . '/factures_archive/';

// Créer le répertoire s'il n'existe pas
if (!is_dir($save_dir)) {
    mkdir($save_dir, 0777, true); // Les permissions 0777 sont larges, ajustez pour la production (ex: 0755)
}

// Générer un nom de fichier unique pour la facture sauvegardée
$save_filename = "Facture_" . str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9_ -]/', '', $c['nom_prenom'])) . "_" . $c['id'] . "_" . date('Ymd_His') . ".pdf";
file_put_contents($save_dir . $save_filename, $output);
// --- Fin : Code pour sauvegarder la facture sur le serveur ---

ob_end_clean();
$dompdf->stream("Facture_" . $c['nom_prenom'] . ".pdf", ["Attachment" => false]);