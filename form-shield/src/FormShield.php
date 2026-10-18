<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/Rules.php';
require_once __DIR__ . '/KnownTemplates.php';
require_once __DIR__ . '/GeoIp.php';
require_once __DIR__ . '/PhoneChecker.php';
require_once __DIR__ . '/IpProtection.php';
require_once __DIR__ . '/AiClassifier.php';



class FormShieldResult
{
    public bool $isSpam;

    public bool $silent;

    public ?int $httpHint;

    public string $reason;

    public int $score;

    public string $status;

    public ?string $country;

    public ?string $ipEncrypted;

    public array $reasons;

    public array $phone;

    public ?int $templateId;

    public ?string $templateName;

    public ?string $aiDecision;

    public function __construct(
        bool $isSpam = false,
        bool $silent = false,
        ?int $httpHint = null,
        string $reason = 'none',
        int $score = 0,
        string $status = 'ok',
        ?string $country = null,
        ?string $ipEncrypted = null,
        array $reasons = [],
        array $phone = [],
        ?int $templateId = null,
        ?string $templateName = null,
        ?string $aiDecision = null
    ) {
        $this->isSpam = $isSpam;
        $this->silent = $silent;
        $this->httpHint = $httpHint;
        $this->reason = $reason;
        $this->score = $score;
        $this->status = $status;
        $this->country = $country;
        $this->ipEncrypted = $ipEncrypted;
        $this->reasons = $reasons;
        $this->phone = $phone;
        $this->templateId = $templateId;
        $this->templateName = $templateName;
        $this->aiDecision = $aiDecision;
    }
}


class FormShield
{
    private PDO $pdo;

    private array $config;

    private RateLimiter $rateLimiter;

    private Rules $rules;

    private KnownTemplates $knownTemplates;

    private GeoIp $geoIp;

    private PhoneChecker $phoneChecker;

    private IpProtection $ipProtection;

    private mixed $aiClassifier;


    public function __construct(
        PDO $pdo,
        array $config,
        ?object $aiClassifier = null
    ) {

        $this->pdo = $pdo;

        $this->config = $config;

        
        
        $ipHashSecret =
            $config['security']['ip_hash_secret']
            ?? '';
        
        if ($ipHashSecret === '') {
            throw new RuntimeException(
                'IP hash secret is not configured.'
            );
        }
        
        $ipEncryptionSecret = $config['security']['ip_encryption_key'] ?? '';
        if ($ipEncryptionSecret === '') {
            throw new RuntimeException('IP encryption secret is not configured.');
        }

        $this->ipProtection = new IpProtection($ipEncryptionSecret);
        


        $this->rateLimiter =
            new RateLimiter(
                $pdo,
                $ipHashSecret
            );


        /*
         * Rules
         */

        $this->rules =
            new Rules();


        /*
         * Known templates
         */

        $this->knownTemplates =
            new KnownTemplates(
                $pdo
            );


        /*
         * GeoIP
         */


        $this->geoIp = new GeoIp();


        /*
         * Phone checker
         */

        $homeCountry =
            $config['phone']['home_country']
            ?? 'EE';


        $this->phoneChecker =
            new PhoneChecker(
                $homeCountry
            );


        /*
         * Optional AI
         */

        $this->aiClassifier =
            $aiClassifier;
    }



    public function check(
        array $post,
        array $server
    ): FormShieldResult {



        $name =
            trim((string) ($post['name'] ?? ''));

        $email =
            trim((string) ($post['email'] ?? ''));

        $phone =
            trim(
                (string) (
                    $post['phone']
                    ?? $post['number']
                    ?? ''
                )
            );

        $message =
            trim((string) ($post['message'] ?? ''));



        $ip =
            trim(
                (string) (
                    $server['REMOTE_ADDR']
                    ?? ''
                )
            );

$ipEncrypted = null;

if ($ip !== '') {
    $ipEncrypted =
        $this->ipProtection->encrypt($ip);
}



$country = null;

if ($ip !== '') {
    $country =
        $this->geoIp->country($ip);
}


        $honeypot =
            trim(
                (string) (
                    $post['website']
                    ?? ''
                )
            );


        if ($honeypot !== '') {

            return new FormShieldResult(

                isSpam: true,

                silent: true,

                httpHint: null,

                reason: 'honeypot',

                score: 100,

                status: 'spam',

                country: $country,

                ipEncrypted: $ipEncrypted,

                reasons: ['honeypot'],

                phone: [],

                templateId: null,

                templateName: null,

                aiDecision: null
            );
        }



        if ($name === '') {

            return new FormShieldResult(
                isSpam: false,
                silent: false,
                httpHint: 400,
                reason: 'invalid_input',
                score: 0,
                status: 'invalid',
                reasons: ['missing_name']
            );
        }


        if (
            $email === ''
            || filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {

            return new FormShieldResult(
                isSpam: false,
                silent: false,
                httpHint: 400,
                reason: 'invalid_input',
                score: 0,
                status: 'invalid',
                reasons: ['invalid_email']
            );
        }


        if ($message === '') {

            return new FormShieldResult(
                isSpam: false,
                silent: false,
                httpHint: 400,
                reason: 'invalid_input',
                score: 0,
                status: 'invalid',
                reasons: ['missing_message']
            );
        }



        if ($ip !== '') {

            $rateResult =
                $this->rateLimiter->check(
                    $ip
                );


            if ($rateResult['isSpam']) {



                $result = new FormShieldResult(
                    isSpam: true,
                    silent: true,
                    httpHint: 429,
                    reason: 'rules',
                    score: 100,
                    status: 'spam',
                    country: $country,
                    ipEncrypted: $ipEncrypted,
                    reasons: ['rate_limit'],
                    phone: [],
                    templateId: null,
                    templateName: null,
                    aiDecision: null
                );


                /*
                 * Log rate-limit spam.
                 */

                $this->logSpam(
                    $ip,
                    $email,
                    $message,
                    $result
                );


                return $result;
            }
        }



        $ruleResult =
            $this->rules->check(
                $message
            );


        $score =
            (int) $ruleResult['score'];

        $reasons =
            $ruleResult['reasons'];




if ($ruleResult['isSpam']) {

    $result = new FormShieldResult(

        isSpam: true,

        silent: true,

        httpHint: null,

        reason: implode(',', $reasons) !== '' ? implode(',', $reasons) : 'rules',

        score: $score,

        status: $ruleResult['status'],

        country: $country,

        ipEncrypted: $ipEncrypted,

        reasons: $reasons,

        phone: [],

        templateId: null,

        templateName: null,

        aiDecision: null
    );


            /*
             * Log the spam.
             */

            $this->logSpam(
                $ip,
                $email,
                $message,
                $result
            );


            return $result;
        }



        $templateResult =
            $this->knownTemplates->check(
                $message
            );


        if ($templateResult['isSpam']) {

            $result = new FormShieldResult(

                isSpam: true,


                silent: false,

                httpHint: null,

                reason: 'known_template',

                score: max(
                    $score,
                    60
                ),

                status: 'spam',

                country: $country,

                ipEncrypted: $ipEncrypted,

                reasons: [
                    ...$reasons,
                    'known_template'
                ],

                phone: [],

                templateId:
                    $templateResult['template_id'],

                templateName:
                    $templateResult['template_name'],

                aiDecision: null
            );



            $this->logSpam(
                $ip,
                $email,
                $message,
                $result
            );


            return $result;
        }




        $phoneResult =
            $this->phoneChecker->check(
                $phone
            );


        /*
        |--------------------------------------------------------------------------
        | 7. AI
        |--------------------------------------------------------------------------
        |
        | AI is only used when the result is ambiguous.
        |
        | Current Rules:
        *
        * 0-29  = OK
        * 30-59 = suspicious
        * 60+   = spam
        *
        * We only call AI for 30-59.
        |
        */

        $aiDecision = null;


        if (
            $score >= 30
            && $score <= 59
            && $this->aiClassifier !== null
        ) {

            try {

                $aiResult =
                    $this->aiClassifier->classify(
                        $name,
                        $email,
                        $message
                    );


                /*
                 * We expect the classifier to return
                 * something like:
                 *
                 * [
                 *     'decision' => 'legitimate'
                 * ]
                 *
                 * or:
                 *
                 * [
                 *     'decision' => 'spam'
                 * ]
                 */

                if (array_key_exists('is_spam', $aiResult)) {
                    $aiDecision = $aiResult['is_spam'] ? 'spam' : 'legitimate';
                } else {
                    $aiDecision = $aiResult['decision'] ?? null;
                }


            } catch (Throwable $e) {

                /*
                 * AI failure must NOT break
                 * the contact form.
                 *
                 * If AI is unavailable, continue
                 * using the deterministic signals.
                 */

                $aiDecision = null;
            }
        }


        if ($aiDecision === 'spam') {

            $result = new FormShieldResult(

                isSpam: true,

                silent: true,

                httpHint: null,

                reason: 'ai',

                score: max(
                    $score,
                    60
                ),

                status: 'spam',

                country: $country,

                ipEncrypted: $ipEncrypted,

                reasons: [
                    ...$reasons,
                    'ai_spam'
                ],

                phone: $phoneResult,

                templateId: null,

                templateName: null,

                aiDecision: 'spam'
            );


            $this->logSpam(
                $ip,
                $email,
                $message,
                $result
            );


            return $result;
        }


        if ($aiDecision === 'legitimate') {

            return new FormShieldResult(
                isSpam: false,
                silent: false,
                httpHint: null,
                reason: 'ai_legitimate',
                score: $score,
                status: 'ok',
                country: $country,
                ipEncrypted: $ipEncrypted,
                reasons: $reasons,
                phone: $phoneResult,
                templateId: null,
                templateName: null,
                aiDecision: 'legitimate'
            );
        }

        // If no AI decision is available, quarantine ambiguous messages for review.
        if ($score >= 30) {
            $result = new FormShieldResult(
                isSpam: true,
                silent: true,
                httpHint: null,
                reason: $reasons !== [] ? implode(',', $reasons) : 'suspicious_score',
                score: $score,
                status: 'suspicious',
                country: $country,
                ipEncrypted: $ipEncrypted,
                reasons: $reasons,
                phone: $phoneResult,
                templateId: null,
                templateName: null,
                aiDecision: $aiDecision
            );
            $this->logSpam($ip, $email, $message, $result);
            return $result;
        }

        return new FormShieldResult(

            isSpam: false,

            silent: false,

            httpHint: null,

            reason: 'none',

            score: $score,

            status:
                $score >= 30
                    ? 'suspicious'
                    : 'ok',

            country: $country,

            ipEncrypted: $ipEncrypted,

            reasons: $reasons,

            phone: $phoneResult,

            templateId: null,

            templateName: null,

            aiDecision: null
        );
    }



    private function logSpam(
        string $ip,
        string $email,
        string $message,
        FormShieldResult $result
    ): void {


        $ipHashSecret =
            $this->config['security']['ip_hash_secret']
            ?? '';


        $ipHash =
            $ip !== ''
                ? hash_hmac(
                    'sha256',
                    $ip,
                    $ipHashSecret
                )
                : hash_hmac(
                    'sha256',
                    'unknown',
                    $ipHashSecret
                );


        $reason =
            $result->reason;



        $reason =
            mb_substr(
                $reason,
                0,
                100
            );

            
        $stmt = $this->pdo->prepare(
            'INSERT INTO spam_logs
            (
                ip_hash,
                ip_encrypted,
                country,
                email,
                reason,
                score,
                silent,
                message
            )
            VALUES
            (
                :ip_hash,
                :ip_encrypted,
                :country,
                :email,
                :reason,
                :score,
                :silent,
                :message
            )'
        );


        $stmt->execute([
            'ip_hash' => $ipHash,

            'ip_encrypted' =>
                 $result->ipEncrypted,

            'country' =>
                $result->country,

            'email' =>
                $email !== ''
                    ? $email
                    : null,

            'reason' =>
                $reason,

            'score' =>
                $result->score,

            'silent' =>
                $result->silent ? 1 : 0,

            'message' =>
                $message !== ''
                    ? $message
                    : null
        ]);
    }
}
