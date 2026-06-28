<?php
/**
 * ajax_fields.php
 * Endpoint AJAX pour gérer les champs dynamiques du formulaire client.
 * Actions : add, delete, list
 */
session_start();
require_once 'db.php';

// Sécurité : connexion requise
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Non autorisé. Veuillez vous connecter.']);
    exit();
}

header('Content-Type: application/json');

// ----- Action GET : lister les champs actifs -----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'list') {
        try {
            $fields = $pdo->query("SELECT * FROM form_fields WHERE actif = TRUE ORDER BY ordre ASC, id ASC")->fetchAll();
            echo json_encode(['success' => true, 'fields' => $fields]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }
    http_response_code(400);
    echo json_encode(['error' => 'Action GET inconnue.']);
    exit();
}

// ----- Actions POST -----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée.']);
    exit();
}

$action = $_POST['action'] ?? '';

// --- Ajouter un nouveau champ ---
if ($action === 'add') {
    $label       = trim($_POST['label'] ?? '');
    $field_type  = trim($_POST['field_type'] ?? 'text');
    $placeholder = trim($_POST['placeholder'] ?? '');
    $options_raw = trim($_POST['options_json'] ?? '');

    $allowed_types = ['text', 'number', 'textarea', 'select', 'date'];
    if (empty($label)) {
        http_response_code(400);
        echo json_encode(['error' => 'Le libellé ne peut pas être vide.']);
        exit();
    }
    if (!in_array($field_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Type de champ invalide.']);
        exit();
    }

    // Validate options JSON for select
    $options_json = null;
    if ($field_type === 'select' && !empty($options_raw)) {
        $decoded = json_decode($options_raw, true);
        if (!is_array($decoded)) {
            http_response_code(400);
            echo json_encode(['error' => 'Les options du champ "Liste" doivent être un tableau JSON valide.']);
            exit();
        }
        $options_json = $options_raw;
    }

    try {
        // Get max order
        $max_ordre = (int) $pdo->query("SELECT COALESCE(MAX(ordre), 0) FROM form_fields WHERE actif = TRUE")->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO form_fields (label, field_type, options_json, placeholder, actif, ordre)
            VALUES (?, ?, ?, ?, TRUE, ?)
        ");
        $stmt->execute([$label, $field_type, $options_json, $placeholder, $max_ordre + 1]);
        $new_id = $pdo->lastInsertId();

        echo json_encode([
            'success'      => true,
            'id'           => $new_id,
            'label'        => $label,
            'field_type'   => $field_type,
            'options_json' => $options_json,
            'placeholder'  => $placeholder,
            'ordre'        => $max_ordre + 1
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur base de données : ' . $e->getMessage()]);
    }
    exit();
}

// --- Désactiver (soft delete) un champ ---
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Identifiant invalide.']);
        exit();
    }
    try {
        $pdo->prepare("UPDATE form_fields SET actif = FALSE WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur base de données : ' . $e->getMessage()]);
    }
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Action non reconnue : ' . htmlspecialchars($action)]);
exit();
