<?php

session_start();

$config = require __DIR__ . '/../config/config.php';

$error = '';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}


try {

    $db = $config['database'];

    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}",
        $db['username'],
        $db['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

} catch (PDOException $e) {

    http_response_code(500);
    exit('Database connection failed.');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'Please enter your username and password.';

    } else {


        $stmt = $pdo->prepare(
            'SELECT id, username, password_hash
             FROM admins
             WHERE username = :username
             LIMIT 1'
        );

        $stmt->execute([
            'username' => $username
        ]);

        $admin = $stmt->fetch();


        if ($admin && password_verify($password, $admin['password_hash'])) {

            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            header('Location: index.php');
            exit;

        } else {

            $error = 'Invalid username or password.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Login</title>
    <link rel="stylesheet" href="admin.css">

</head>

<body class="login-page">

<div class="container">

    <div class="login-card">

        <h1>Admin Login</h1>

        <?php if ($error !== ''): ?>

            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>


        <form
            action="login.php"
            method="POST"
            autocomplete="on"
        >

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    maxlength="50"
                    autocomplete="username"
                    placeholder="Username"
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Password"
                >

            </div>


            <button type="submit">
                Login
            </button>

        </form>

    </div>

</div>

</body>

</html>
