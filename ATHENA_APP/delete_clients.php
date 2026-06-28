<?php
session_start();
require_once 'db.php';

// 1. Sécurité : Seul l'admin peut supprimer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: list_clients.php?msg=Refusé : Accès restreint");
    exit();
}

// 2. Vérification de l'ID
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);

    try {
        // 3. Exécution de la suppression
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$id]);

        // Redirection avec succès
        header("Location: list_clients.php?msg=Client supprimé avec succès");
        exit();
    } catch (PDOException $e) {
        // En cas d'erreur (ex: contrainte de clé étrangère)
        header("Location: list_clients.php?msg=Erreur lors de la suppression");
        exit();
    }
} else {
    header("Location: list_clients.php");
    exit();
}