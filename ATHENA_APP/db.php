<?php
$host = "localhost";
$port = "5432"; 
$db   = "ATHENA_INDUSTRIE";
$user = "postgres";
$pass = "8671";

try {
    // 1. On définit d'abord la chaîne de connexion (DSN)
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";

    // 2. On crée la connexion PDO avec les bonnes options pour PostgreSQL
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Optionnel : Forcer l'encodage UTF-8 pour PostgreSQL
    $pdo->exec("SET client_encoding TO 'UTF8'");

} catch (PDOException $e) {
    // Si la connexion échoue, on affiche l'erreur proprement
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>