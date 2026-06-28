<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // 1. Données de base
    $nom           = $_POST['nom_prenom'];
    $sexe          = $_POST['sexe'];
    $age           = !empty($_POST['age'])              ? intval($_POST['age'])              : null;
    $telephone     = $_POST['telephone'];
    $ville         = $_POST['ville'];
    $taille        = !empty($_POST['taille'])            ? intval($_POST['taille'])            : null;
    $poids         = !empty($_POST['poids'])             ? floatval($_POST['poids'])           : null;
    $objectif      = !empty($_POST['objectif'])          ? floatval($_POST['objectif'])        : null;
    $alimentation  = $_POST['alimentation']              ?? '';
    $activite      = !empty($_POST['activite'])          ? floatval($_POST['activite'])        : 0;
    $accouchements = !empty($_POST['nombre_accouchements']) ? intval($_POST['nombre_accouchements']) : 0;
    $allaitement   = $_POST['allaitement']               ?? 'Non';
    $ant_med       = $_POST['antecedents_medicaux']      ?? '';
    $allergies     = $_POST['allergies_intolerances']    ?? '';
    $operations    = $_POST['operations_chirurgicales']  ?? '';
    $produit_ids   = !empty($_POST['produit_ids'])       ? array_map('intval', $_POST['produit_ids']) : [];
    $new_frais     = (float)($_POST['frais_consultation'] ?? 0);

    // 2. Récupérer les champs dynamiques actifs
    $active_fields = $pdo->query("SELECT id FROM form_fields WHERE actif = TRUE")->fetchAll();

    try {
        $pdo->beginTransaction();

        // 3. Recalcul du prix
        $prix_produits = 0;
        if (!empty($produit_ids)) {
            $ph = implode(',', array_fill(0, count($produit_ids), '?'));
            $stmtP = $pdo->prepare("SELECT SUM(prix_vente) FROM stocks WHERE id IN ($ph)");
            $stmtP->execute($produit_ids);
            $prix_produits = (float)$stmtP->fetchColumn();
        }
        $final_price      = $prix_produits + $new_frais;
        $final_produit_id = !empty($produit_ids) ? $produit_ids[0] : null;

        // 4. Mise à jour de la table de liaison client_produits
        $pdo->prepare("DELETE FROM client_produits WHERE client_id = ?")->execute([$id]);
        if (!empty($produit_ids)) {
            $stmtCP = $pdo->prepare("INSERT INTO client_produits (client_id, produit_id) VALUES (?, ?)");
            foreach ($produit_ids as $pid) { $stmtCP->execute([$id, $pid]); }
        }

        // 5. Mise à jour principale du client
        $sql = "UPDATE clients SET
                    nom_prenom              = ?,
                    sexe                    = ?,
                    age                     = ?,
                    telephone               = ?,
                    ville                   = ?,
                    taille_cm               = ?,
                    poids_actuel            = ?,
                    poids_objectif          = ?,
                    alimentation            = ?,
                    activite_physique       = ?,
                    nombre_accouchements    = ?,
                    allaitement             = ?,
                    antecedents_medicaux    = ?,
                    allergies_intolerances  = ?,
                    operations_chirurgicales= ?,
                    prix_cure               = ?,
                    produit_id              = ?
                WHERE id = ?";

        $pdo->prepare($sql)->execute([
            $nom, $sexe, $age, $telephone, $ville,
            $taille, $poids, $objectif,
            $alimentation, $activite,
            $accouchements, $allaitement,
            $ant_med, $allergies, $operations,
            $final_price, $final_produit_id,
            $id
        ]);

        // 6. Mise à jour / insertion des valeurs des champs dynamiques
        if (!empty($active_fields)) {
            $stmtCV = $pdo->prepare("
                INSERT INTO client_custom_values (client_id, field_id, valeur)
                VALUES (?, ?, ?)
                ON CONFLICT (client_id, field_id) DO UPDATE SET valeur = EXCLUDED.valeur
            ");
            foreach ($active_fields as $field) {
                $key = 'custom_field_' . $field['id'];
                // Sauvegarder même si vide (pour écraser une ancienne valeur)
                if (isset($_POST[$key])) {
                    $stmtCV->execute([$id, $field['id'], $_POST[$key]]);
                }
            }
        }

        $pdo->commit();
        header("Location: list_clients.php?msg=success");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "Erreur lors de la mise à jour : " . $e->getMessage();
    }
} else {
    header("Location: list_clients.php");
    exit();
}
?>