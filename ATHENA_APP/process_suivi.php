<?php
session_start();
require_once 'db.php';

// Sécurité : Vérifier si l'utilisateur est bien connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $client_id = (int)$_POST['client_id'];
    $poids     = !empty($_POST['poids']) ? (float)$_POST['poids'] : null;
    $taille    = !empty($_POST['taille']) ? (int)$_POST['taille'] : null;
    $tour_taille   = !empty($_POST['tour_taille']) ? (float)$_POST['tour_taille'] : null;
    $tour_hanches  = !empty($_POST['tour_hanches']) ? (float)$_POST['tour_hanches'] : null;
    $imc           = !empty($_POST['imc']) ? (float)$_POST['imc'] : null;
    $masse_graisseuse = !empty($_POST['masse_graisseuse']) ? (float)$_POST['masse_graisseuse'] : null;
    $masse_musculaire  = !empty($_POST['masse_musculaire']) ? (float)$_POST['masse_musculaire'] : null;
    $tension       = trim($_POST['tension'] ?? '');
    $activite      = !empty($_POST['activite']) ? (float)$_POST['activite'] : 0;
    $alimentation  = $_POST['alimentation'] ?? '';
    $notes_suivi   = $_POST['notes_suivi'] ?? '';
    $date_consult  = $_POST['date_consultation'] ?? date('Y-m-d');
    $auteur        = $_SESSION['username'];

    try {
        $pdo->beginTransaction();

        // 1. Mettre à jour la fiche principale du client avec les dernières mesures
        $stmtUpdate = $pdo->prepare("UPDATE clients SET 
            poids_actuel = ?, 
            taille_cm = COALESCE(?, taille_cm),
            activite_physique = ? 
            WHERE id = ?");
        $stmtUpdate->execute([$poids, $taille, $activite, $client_id]);

        // 2. Enregistrer l'entrée complète dans l'historique de progression
        $sqlHist = "INSERT INTO suivi_progression 
            (client_id, poids_mesure, taille_mesure, tour_taille, tour_hanches, 
             imc, masse_graisseuse, masse_musculaire, tension_arterielle,
             note_activite, notes_alimentation, notes_suivi,
             date_consultation, enregistre_par)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $pdo->prepare($sqlHist)->execute([
            $client_id, $poids, $taille, $tour_taille, $tour_hanches,
            $imc, $masse_graisseuse, $masse_musculaire, 
            $tension ?: null,
            $activite, $alimentation, $notes_suivi,
            $date_consult, $auteur
        ]);

        $pdo->commit();
        header("Location: historique_suivi.php?client_id=$client_id&msg=success");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur lors de l'enregistrement du suivi : " . $e->getMessage());
    }
} else {
    header("Location: list_clients.php");
    exit();
}
