<?php

session_start();


if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}


$config = require __DIR__ . '/../config/config.php';


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


if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

$csrfToken = $_SESSION['csrf_token'];



function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}




$success = '';
$error = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrfToken, $submittedToken)) {

        http_response_code(403);

        exit('Invalid CSRF token.');
    }



    $action = $_POST['action'] ?? '';



    if ($action === 'add') {

        $name = trim($_POST['name'] ?? '');
        $pattern = trim($_POST['pattern'] ?? '');

        if ($name === '' || $pattern === '') {

            $error = 'Name and pattern are required.';
        } elseif (mb_strlen($name) > 150) {

            $error = 'Name is too long.';
        } else {

            $stmt = $pdo->prepare(
                'INSERT INTO known_templates
                    (name, pattern, enabled)
                 VALUES
                    (:name, :pattern, 1)'
            );

            $stmt->execute([
                'name' => $name,
                'pattern' => $pattern
            ]);

            $success = 'Template added successfully.';
        }
    } elseif ($action === 'edit') {

        $id = filter_input(
            INPUT_POST,
            'id',
            FILTER_VALIDATE_INT
        );

        $name = trim($_POST['name'] ?? '');
        $pattern = trim($_POST['pattern'] ?? '');

        if (!$id) {

            $error = 'Invalid template ID.';
        } elseif ($name === '' || $pattern === '') {

            $error = 'Name and pattern are required.';
        } elseif (mb_strlen($name) > 150) {

            $error = 'Name is too long.';
        } else {

            $stmt = $pdo->prepare(
                'UPDATE known_templates
                 SET
                    name = :name,
                    pattern = :pattern
                 WHERE id = :id'
            );

            $stmt->execute([
                'name' => $name,
                'pattern' => $pattern,
                'id' => $id
            ]);

            $success = 'Template updated successfully.';
        }
    } elseif ($action === 'toggle') {

        $id = filter_input(
            INPUT_POST,
            'id',
            FILTER_VALIDATE_INT
        );

        if (!$id) {

            $error = 'Invalid template ID.';
        } else {


            $stmt = $pdo->prepare(
                'UPDATE known_templates
                 SET enabled = NOT enabled
                 WHERE id = :id'
            );

            $stmt->execute([
                'id' => $id
            ]);

            $success = 'Template status updated.';
        }
    } elseif ($action === 'delete') {

        $id = filter_input(
            INPUT_POST,
            'id',
            FILTER_VALIDATE_INT
        );

        if (!$id) {

            $error = 'Invalid template ID.';
        } else {

            $stmt = $pdo->prepare(
                'DELETE FROM known_templates
                 WHERE id = :id'
            );

            $stmt->execute([
                'id' => $id
            ]);

            $success = 'Template deleted.';
        }
    }
}


$stmt = $pdo->query(
    'SELECT
        id,
        name,
        pattern,
        enabled,
        created_at,
        updated_at
     FROM known_templates
     ORDER BY id DESC'
);

$templates = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Known Templates - Form-Shield</title>

    <link rel="stylesheet" href="admin.css">

</head>

<body class="templates-page">

    <div class="container">

        <div class="header">

            <div>
                <h1 class="page-title">Known Templates</h1>
                <p class="subtitle">Manage reusable patterns used to identify known spam.</p>
            </div>

            <a
                href="index.php"
                class="back">
                < Dashboard
                    </a>

        </div>


        <?php if ($success !== ''): ?>

            <div class="notice success">
                <?= e($success) ?>
            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="notice error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <div class="card">

            <h2>Add New Template</h2>

            <form
                action="known-templates.php"
                method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>">

                <input
                    type="hidden"
                    name="action"
                    value="add">


                <div class="form-group">

                    <label for="name">
                        Template name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        required
                        maxlength="150"
                        placeholder="SEO offer">

                </div>


                <div class="form-group">

                    <label for="pattern">
                        Spam pattern
                    </label>

                    <textarea
                        id="pattern"
                        name="pattern"
                        required
                        placeholder="We can improve your Google ranking..."></textarea>
                    <p class="hint">Enter the phrase or message pattern you want Form-Shield to recognize.</p>

                </div>


                <button type="submit">
                    Add Template
                </button>

            </form>

        </div>


        <div class="card">

            <h2>Existing Templates <span style="color: var(--muted); font-weight: 500;">(<?= count($templates) ?>)</span></h2>


            <?php if (empty($templates)): ?>

                <p class="empty-state">No known templates yet. Add one using the form above.</p>

            <?php else: ?>
                <div class="template-list">
                    <?php foreach ($templates as $template): ?>

                        <div class="template">


                            <div class="template-header">

                                <div class="template-name">

                                    <?= e($template['name']) ?>

                                </div>


                                <?php if ($template['enabled']): ?>

                                    <span class="status enabled">
                                        Enabled
                                    </span>

                                <?php else: ?>

                                    <span class="status disabled">
                                        Disabled
                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="pattern">

                                <?= e(trim((string) $template['pattern'])) ?>

                            </div>


                            <div class="actions">

                                <form
                                    action="known-templates.php"
                                    method="POST">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="toggle">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $template['id'] ?>">

                                    <button
                                        type="submit"
                                        class="toggle">
                                        <?= $template['enabled']
                                            ? 'Disable'
                                            : 'Enable'
                                        ?>
                                    </button>

                                </form>

                                <button
                                    type="button"
                                    onclick="toggleEdit(
                                <?= (int) $template['id'] ?>
                            )">
                                    Edit
                                </button>


                                <form
                                    action="known-templates.php"
                                    method="POST"
                                    onsubmit="return confirm(
                                'Delete this template?'
                            );">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $template['id'] ?>">

                                    <button
                                        type="submit"
                                        class="delete">
                                        Delete
                                    </button>

                                </form>

                            </div>


                            <div
                                class="edit-form"
                                id="edit-<?= (int) $template['id'] ?>">

                                <form
                                    action="known-templates.php"
                                    method="POST">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="edit">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $template['id'] ?>">


                                    <div class="form-group">

                                        <label>
                                            Template name
                                        </label>

                                        <input
                                            type="text"
                                            name="name"
                                            required
                                            maxlength="150"
                                            value="<?= e(
                                                        $template['name']
                                                    ) ?>">

                                    </div>


                                    <div class="form-group">

                                        <label>
                                            Pattern
                                        </label>

                                        <textarea
                                            name="pattern"
                                            required><?= e(
                                                            $template['pattern']
                                                        ) ?></textarea>

                                    </div>


                                    <button type="submit">
                                        Save Changes
                                    </button>

                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

    </div>


    <script>
        function toggleEdit(id) {

            const element = document.getElementById(
                'edit-' + id
            );

            if (element.classList.contains('active')) {

                element.classList.remove('active');

            } else {

                element.classList.add('active');

            }

        }
    </script>

</body>

</html>