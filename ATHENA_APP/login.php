<?php
session_start();
require_once 'db.php';

// Votre logique de vérification reste la même
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u");
    $stmt->execute([':u' => $user]);
    $account = $stmt->fetch();

    if ($account && password_verify($pass, $account['password'])) {
        $_SESSION['user_id'] = $account['id'];
        $_SESSION['username'] = $account['username'];
        $_SESSION['role'] = $account['role'];
        header("Location: index.php"); 
        exit();
    } else {
        $error = "Identifiants incorrects";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style2.css">
    <title>Connexion - Startup Minceur</title>
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <h2>Bienvenue</h2>
            <p>Portail de gestion minceur</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required placeholder="ex: poste_1" autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required placeholder="••••••••" autocomplete="current-password">
            </div>

            <button type="submit" class="btn-login">Se connecter</button>
        </form>
    </div>

</body>
</html>