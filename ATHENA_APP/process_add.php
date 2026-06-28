<?php
session_start();
require_once 'db.php';

// Sécurité : Vérifier si l'utilisateur est bien connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Données de base du formulaire
    $nom          = $_POST['nom_prenom'];
    $sexe         = $_POST['sexe'];
    $age          = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $telephone    = $_POST['telephone'];
    $ville        = $_POST['ville'];
    $taille       = !empty($_POST['taille']) ? intval($_POST['taille']) : null;
    $poids        = !empty($_POST['poids']) ? floatval($_POST['poids']) : null;
    $objectif     = !empty($_POST['objectif']) ? floatval($_POST['objectif']) : null;
    $alimentation = $_POST['alimentation'] ?? '';
    $activite     = !empty($_POST['activite']) ? floatval($_POST['activite']) : 0;
    $ant_med      = $_POST['antecedents_medicaux'] ?? '';
    $allergies    = $_POST['allergies_intolerances'] ?? '';
    $operations   = $_POST['operations_chirurgicales'] ?? '';
    $accouchements= intval($_POST['nombre_accouchements'] ?? 0);
    $allaitement  = $_POST['allaitement'] ?? 'Non';
    $produit_ids  = !empty($_POST['produit_ids']) ? array_map('intval', $_POST['produit_ids']) : [];
    $frais_consult= (float)($_POST['frais_consultation'] ?? 0);

    // 2. Calcul du prix total
    $prix_total_produits = 0;
    if (!empty($produit_ids)) {
        $placeholders = implode(',', array_fill(0, count($produit_ids), '?'));
        $stmtP = $pdo->prepare("SELECT SUM(prix_vente) FROM stocks WHERE id IN ($placeholders)");
        $stmtP->execute($produit_ids);
        $prix_total_produits = (float)$stmtP->fetchColumn();
    }
    $prix_total       = $prix_total_produits + $frais_consult;
    $produit_id_primary = !empty($produit_ids) ? $produit_ids[0] : null;
    $auteur = $_SESSION['username'];

    // 3. Récupérer tous les champs dynamiques actifs
    $active_fields = $pdo->query("SELECT id FROM form_fields WHERE actif = TRUE")->fetchAll();

    try {
        $pdo->beginTransaction();

        // 4. Insertion principale du client
        $sql = "INSERT INTO clients (
                    nom_prenom, sexe, age, telephone, ville,
                    taille_cm, poids_actuel, poids_objectif,
                    alimentation, activite_physique, antecedents_medicaux,
                    allergies_intolerances, operations_chirurgicales,
                    nombre_accouchements, allaitement, prix_cure, cree_par, produit_id
                )
                VALUES (
                    :nom, :sexe, :age, :tel, :ville,
                    :taille, :poids, :objectif,
                    :alim, :activite, :ant_med,
                    :allergies, :operations,
                    :accouchements, :allaitement, :prix, :auteur, :prod_id
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nom'           => $nom,
            ':sexe'          => $sexe,
            ':age'           => $age,
            ':tel'           => $telephone,
            ':ville'         => $ville,
            ':taille'        => $taille,
            ':poids'         => $poids,
            ':objectif'      => $objectif,
            ':alim'          => $alimentation,
            ':activite'      => $activite,
            ':ant_med'       => $ant_med,
            ':allergies'     => $allergies,
            ':operations'    => $operations,
            ':accouchements' => $accouchements,
            ':allaitement'   => $allaitement,
            ':prix'          => $prix_total,
            ':auteur'        => $auteur,
            ':prod_id'       => $produit_id_primary
        ]);

        $new_client_id = $pdo->lastInsertId();

        // 5. Liaisons client-produits
        if (!empty($produit_ids)) {
            $stmtCP = $pdo->prepare("INSERT INTO client_produits (client_id, produit_id) VALUES (?, ?)");
            foreach ($produit_ids as $pid) {
                $stmtCP->execute([$new_client_id, $pid]);
            }
        }

        // 6. Sauvegarde des champs dynamiques personnalisés
        if (!empty($active_fields)) {
            $stmtCV = $pdo->prepare("
                INSERT INTO client_custom_values (client_id, field_id, valeur)
                VALUES (?, ?, ?)
                ON CONFLICT (client_id, field_id) DO UPDATE SET valeur = EXCLUDED.valeur
            ");
            foreach ($active_fields as $field) {
                $field_key = 'custom_field_' . $field['id'];
                if (isset($_POST[$field_key]) && $_POST[$field_key] !== '') {
                    $stmtCV->execute([$new_client_id, $field['id'], $_POST[$field_key]]);
                }
            }
        }

        // 7. Initialisation de l'historique de progression
        // Calcul de l'IMC si on a taille et poids
        $imc_initial = null;
        if ($taille > 0 && $poids > 0) {
            $imc_initial = round($poids / pow(($taille / 100), 2), 1);
        }
        $sqlHist = "INSERT INTO suivi_progression 
            (client_id, poids_mesure, taille_mesure, imc, note_activite, notes_alimentation, enregistre_par, date_consultation)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_DATE)";
        $pdo->prepare($sqlHist)->execute([$new_client_id, $poids, $taille, $imc_initial, $activite, $alimentation, $auteur]);

        $pdo->commit();

        header("Location: list_clients.php?msg=success");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}
?>