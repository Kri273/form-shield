<?php

declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/IpProtection.php';
require_once __DIR__ . '/../src/Rules.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function displayMessage(mixed $message): string
{
    return Rules::redactUrls((string) ($message ?? ''));
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
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Database connection failed. Check the private configuration file.');
}

$notice = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $submittedToken)) {
        http_response_code(403);
        $error = 'Security token validation failed. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'clear_suspicious') {
                $pdo->exec('DELETE FROM spam_logs');
                $notice = 'Suspicious messages have been cleared.';
            } elseif ($action === 'clear_all') {
                $pdo->beginTransaction();
                $pdo->exec('DELETE FROM spam_logs');
                $pdo->exec('DELETE FROM submissions');
                $pdo->commit();
                $notice = 'All legitimate and suspicious messages have been cleared.';
            } else {
                $error = 'Unknown action.';
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Form-Shield admin action failed: ' . $exception->getMessage());
            $error = 'The action failed. Check the server error log.';
        }
    }
}

$spamCount = (int) $pdo->query('SELECT COUNT(*) FROM spam_logs')->fetchColumn();
$submissionCount = (int) $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
$totalMessages = $spamCount + $submissionCount;

$spamLogs = $pdo->query(
    'SELECT id, created_at, ip_encrypted, country, email, reason, score, silent, message
     FROM spam_logs ORDER BY created_at DESC LIMIT 50'
)->fetchAll();

$ipProtection = null;
try {
    $secret = (string) ($config['security']['ip_encryption_key'] ?? '');
    if ($secret !== '') {
        $ipProtection = new IpProtection($secret);
    }
} catch (Throwable $exception) {
    error_log('Form-Shield IP decryption is not configured: ' . $exception->getMessage());
}
foreach ($spamLogs as &$spam) {
    $spam['ip'] = null;
    if ($ipProtection instanceof IpProtection && !empty($spam['ip_encrypted'])) {
        try {
            $spam['ip'] = $ipProtection->decrypt((string) $spam['ip_encrypted']);
        } catch (Throwable $exception) {
            $spam['ip'] = null;
        }
    }
}
unset($spam);

$submissions = $pdo->query(
    'SELECT id, name, email, phone, country, message, created_at
     FROM submissions ORDER BY created_at DESC LIMIT 50'
)->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Form-Shield Admin</title>
    <link rel="stylesheet" href="admin.css">
</head>

<body class="dashboard-page">
    <main>
        <header class="topbar">
            <div>
                <h1>Form-Shield</h1>
                <div class="subtle">Signed in as <?= e($_SESSION['admin_username'] ?? 'admin') ?></div>
            </div>
            <nav class="actions"><a class="button secondary" href="known-templates.php">Known templates</a><a class="button secondary" href="logout.php">Log out</a></nav>
        </header>
        <?php if ($notice !== ''): ?><div class="alert success"><?= e($notice) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <section class="stats" aria-label="Message statistics">
            <div class="stat">
                <div class="stat-label">Suspicious messages</div>
                <div class="stat-value"><?= $spamCount ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Legitimate messages</div>
                <div class="stat-value"><?= $submissionCount ?></div>
            </div>
        </section>
        <section class="section">
            <div class="section-head">
                <div>
                    <h2>Recent legitimate messages</h2>
                    <div class="subtle">Showing up to 50 newest messages</div>
                </div>
            </div>
            <?php if (!$submissions): ?><div class="empty">No legitimate messages yet.</div><?php else: ?>
                <div class="message-list">
                    <?php foreach ($submissions as $item): ?>
                        <article class="message">
                            <div class="message-head">
                                <div class="message-title"><?= e($item['name']) ?></div><time class="message-date"><?= e($item['created_at']) ?></time>
                            </div>
                            <div class="meta"><span><?= e($item['email']) ?></span><?php if (!empty($item['phone'])): ?><span>Phone: <?= e($item['phone']) ?></span><?php endif; ?><?php if (!empty($item['country'])): ?><span>Country: <?= e($item['country']) ?></span><?php endif; ?></div>
                            <p class="message-body"><?= e(displayMessage($item['message'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div><?php endif; ?>
        </section>
        <section class="section">
            <div class="section-head">
                <div>
                    <h2>Suspicious messages</h2>
                    <div class="subtle">URLs are shown as [URL] and are not clickable.</div>
                </div>
                <form method="post" onsubmit="return confirm('Permanently delete all suspicious messages? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="clear_suspicious"><button class="button danger" type="submit">Clear suspicious messages</button>
                </form>
            </div>
            <?php if (!$spamLogs): ?><div class="empty">No suspicious messages logged.</div><?php else: ?>
                <div class="message-list">
                    <?php foreach ($spamLogs as $item): ?>
                        <article class="message spam">
                            <div class="message-head">
                                <div class="message-title"><?= e($item['email'] ?: 'Unknown sender') ?></div><time class="message-date"><?= e($item['created_at']) ?></time>
                            </div>
                            <div class="meta"><span class="pill red"><?= e($item['score'] >= 60 ? 'Spam' : 'Suspicious') ?></span><span class="pill">Score: <?= (int) $item['score'] ?></span><span class="pill"><?= e($item['reason']) ?></span><?php if (!empty($item['country'])): ?><span class="pill">Country: <?= e($item['country']) ?></span><?php endif; ?><?php if (!empty($item['ip'])): ?><span class="pill">IP: <?= e($item['ip']) ?></span><?php else: ?><span class="pill">IP unavailable</span><?php endif; ?></div>
                            <p class="message-body"><?= e(displayMessage($item['message'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div><?php endif; ?>
        </section>
        <section class="section">
            <div class="section-head">
                <div>
                    <h2>Danger zone</h2>
                    <div class="subtle">Deletes both legitimate submissions and suspicious logs. It does not delete rate-limit records or admin accounts.</div>
                </div>
                <form method="post" onsubmit="return confirm('Permanently delete ALL legitimate and suspicious messages? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="clear_all"><button class="button danger" type="submit">Clear all messages</button>
                </form>
            </div>
        </section>
    </main>
</body>

</html>