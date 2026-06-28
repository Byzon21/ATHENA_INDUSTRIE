<?php
session_start();
require_once 'db.php';

// Sécurité : Vérifier si l'utilisateur est bien connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(430); // 403 Forbidden
    echo json_encode(['error' => 'Non autorisé. Veuillez vous connecter.']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée. Utilisez POST.']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $nom = trim($_POST['nom_produit'] ?? '');
    $prix = floatval($_POST['prix_vente'] ?? 0);

    if (empty($nom)) {
        http_response_code(400);
        echo json_encode(['error' => 'Le nom de la cure ne peut pas être vide.']);
        exit();
    }

    if ($prix < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Le prix ne peut pas être négatif.']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO stocks (nom_produit, quantite_disponible, prix_vente, seuil_alerte, actif) VALUES (?, 100, ?, 5, TRUE)");
        $stmt->execute([$nom, $prix]);
        $new_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => $new_id,
            'nom_produit' => $nom,
            'prix_vente' => $prix
        ]);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
        exit();
    }
} elseif ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Identifiant de cure invalide.']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE stocks SET actif = FALSE WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true
        ]);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
        exit();
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Action non reconnue.']);
    exit();
}
