<?php

declare(strict_types=1);

class RateLimiter
{
    private PDO $pdo;

    private string $ipHashSecret;

    private const TEN_MINUTE_LIMIT = 5;
    private const HOUR_LIMIT = 20;

    public function __construct(PDO $pdo, string $ipHashSecret)
    {
        $this->pdo = $pdo;
        $this->ipHashSecret = $ipHashSecret;
    }

    /**
     * Check rate limits for an IP address.
     *
     * Limits:
     *
     * 5 requests / rolling 10 minutes
     * 20 requests / rolling 1 hour
     */
    public function check(string $ip): array
    {
        $ipHash = $this->hashIp($ip);

        $now = new DateTimeImmutable('now');

        /*
         * Store this request first.
         */
        $this->recordRequest(
            $ipHash,
            $now
        );

        $tenMinutesAgo = $now->modify('-10 minutes');

        $count10Minutes = $this->countRequests(
            $ipHash,
            $tenMinutesAgo,
            $now
        );


        $oneHourAgo = $now->modify('-1 hour');

        $count1Hour = $this->countRequests(
            $ipHash,
            $oneHourAgo,
            $now
        );

        /*
         * Check limits.
         */
        $exceeded10Minutes =
            $count10Minutes > self::TEN_MINUTE_LIMIT;

        $exceeded1Hour =
            $count1Hour > self::HOUR_LIMIT;

        if ($exceeded10Minutes || $exceeded1Hour) {
            return [
                'isSpam' => true,
                'silent' => false,
                'httpHint' => 429,
                'reason' => 'rate_limit',
                'count_10m' => $count10Minutes,
                'count_1h' => $count1Hour
            ];
        }

        return [
            'isSpam' => false,
            'silent' => false,
            'httpHint' => null,
            'reason' => null,
            'count_10m' => $count10Minutes,
            'count_1h' => $count1Hour
        ];
    }


    private function hashIp(string $ip): string
    {
        return hash_hmac(
            'sha256',
            $ip,
            $this->ipHashSecret
        );
    }

    private function recordRequest(
        string $ipHash,
        DateTimeImmutable $now
    ): void {

        $bucketStart = $now->setTime(
            (int) $now->format('H'),
            (int) $now->format('i'),
            0
        );

        $bucketString = $bucketStart->format(
            'Y-m-d H:i:s'
        );

        $nowString = $now->format(
            'Y-m-d H:i:s'
        );

        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits
                (
                    ip_hash,
                    request_count,
                    window_start,
                    last_seen
                )
             VALUES
                (
                    :ip_hash,
                    1,
                    :window_start,
                    :last_seen
                )
             ON DUPLICATE KEY UPDATE
                request_count = request_count + 1,
                last_seen = VALUES(last_seen)'
        );

        $stmt->execute([
            'ip_hash' => $ipHash,
            'window_start' => $bucketString,
            'last_seen' => $nowString
        ]);
    }


    private function countRequests(
        string $ipHash,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): int {

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(request_count), 0)
             FROM rate_limits
             WHERE ip_hash = :ip_hash
             AND window_start >= :from_time
             AND window_start <= :to_time'
        );

        $stmt->execute([
            'ip_hash' => $ipHash,
            'from_time' => $from->format('Y-m-d H:i:s'),
            'to_time' => $to->format('Y-m-d H:i:s')
        ]);

        return (int) $stmt->fetchColumn();
    }
}