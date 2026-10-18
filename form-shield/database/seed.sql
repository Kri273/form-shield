-- Demo data for Form-Shield
-- Run after schema.sql

USE form_shield;

INSERT INTO known_templates (name, pattern, enabled)
VALUES
(
    'Suspicious promotional phrase',
    'buy now|limited offer|free money',
    1
),
(
    'Repeated promotional content',
    'work from home|guaranteed profit',
    1
);

INSERT INTO submissions
    (name, email, phone, country, message)
VALUES
(
    'Alex Example',
    'alex@example.com',
    '+37200000000',
    'EE',
    'Hello, I would like to learn more about your services.'
),
(
    'Jordan Demo',
    'jordan@example.com',
    NULL,
    'US',
    'Please send me information about your product.'
);

INSERT INTO spam_logs
    (ip_hash, country, email, reason, score, silent, message)
VALUES
(
    SHA2('demo-ip-001', 256),
    'US',
    'test-spammer@example.com',
    'suspicious_phrase',
    85,
    0,
    'DEMO: Buy now for a limited offer!'
);

INSERT INTO rate_limits
    (ip_hash, request_count, window_start)
VALUES
(
    SHA2('demo-ip-001', 256),
    3,
    NOW()
);