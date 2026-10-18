<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$config = require __DIR__ . '/form-shield/config/config.php';



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


require_once __DIR__ . '/form-shield/src/FormShield.php';
require_once __DIR__ . '/form-shield/src/MailSender.php';

$aiClassifier = null;
if (
    !empty($config['ai']['enabled'])
    && !empty($config['ai']['api_key'])
    && !empty($config['ai']['model'])
) {
    $aiClassifier = new AiClassifier(
        (string) $config['ai']['api_key'],
        (string) $config['ai']['model']
    );
}

$shield = new FormShield(
    $pdo,
    $config,
    $aiClassifier
);



$result = $shield->check(
    $_POST,
    $_SERVER
);

if ($result->httpHint === 400) {
    http_response_code(400);
    exit('Please check the required form fields and try again.');
}

if ($result->isSpam) {

    if ($result->httpHint === 429) {

        http_response_code(429);

        exit('Too many requests. Please try again later.');
    }



    if ($result->silent) {

        echo 'Thank you. Your message has been received.';
        exit;
    }



    http_response_code(400);

    exit('Your message could not be submitted.');
}



$name =
    trim($_POST['name'] ?? '');

$email =
    trim($_POST['email'] ?? '');

$phone =
    trim(
        $_POST['phone']
            ?? ''
    );

$message =
    trim($_POST['message'] ?? '');


$stmt = $pdo->prepare(
    'INSERT INTO submissions
        (
            name,
            email,
            phone,
            ip_encrypted,
            country,
            message
        )
     VALUES
        (
            :name,
            :email,
            :phone,
            :ip_encrypted,
            :country,
            :message
        )'
);

$stmt->execute([
    'name' => $name,
    'email' => $email,
    'phone' => $phone !== ''
        ? $phone
        : null,
    'ip_encrypted' => $result->ipEncrypted,
    'country' => $result->country,
    'message' => $message
]);


$mailConfig = $config['mail'] ?? [];
$mailSent = false;
$mailError = false;

if (!empty($mailConfig['enabled'])) {
    try {
        $sender = new MailSender();
        $sender->send($mailConfig, [
            'subject' => 'New contact form message from ' . $name,
            'body' => "A new contact form message was received.\n\n"
                . "Name: {$name}\n"
                . "Email: {$email}\n"
                . "Phone: " . ($phone !== '' ? $phone : '(not provided)') . "\n\n"
                . "Message:\n{$message}\n",
        ]);
        $mailSent = true;
    } catch (Throwable $e) {
        // Do not expose SMTP details or credentials to visitors.
        error_log('Form-Shield email delivery failed: ' . $e->getMessage());
        $mailError = true;
    }
}

header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>Message received</title><body style="font-family:Arial,sans-serif;max-width:640px;margin:60px auto;padding:24px;line-height:1.6">';
echo '<h1>Thank you</h1><p>Your message has been saved.</p>';
if (!empty($mailConfig['enabled']) && $mailError) {
    echo '<p>Your message was saved, but the email notification could not be sent. Please try again later or contact the site owner.</p>';
}
echo '<p><a href="index.php">Return to the contact form</a></p></body></html>';
