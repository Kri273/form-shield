<?php

declare(strict_types=1);

/** Deterministic, explainable message scoring. */
class Rules
{
    private const SUSPICIOUS_THRESHOLD = 30;
    private const SPAM_THRESHOLD = 60;

    public function check(string $message): array
    {
        $message = trim($message);
        $score = 0;
        $reasons = [];
        $urlCount = $this->countUrls($message);

        // A single URL is not automatically spam; multiple URLs add more risk.
        if ($urlCount >= 1) {
            $score += 15;
            $reasons[] = 'url';
        }
        if ($urlCount >= 3) {
            $score += 25;
            $reasons[] = 'multiple_urls';
        }
        if ($this->containsSeoSpam($message)) {
            $score += 30;
            $reasons[] = 'seo_spam';
        }
        if ($this->looksTemplateLike($message)) {
            $score += 20;
            $reasons[] = 'template_like';
        }

        if ($score >= self::SPAM_THRESHOLD) {
            $status = 'spam';
        } elseif ($score >= self::SUSPICIOUS_THRESHOLD) {
            $status = 'suspicious';
        } else {
            $status = 'ok';
        }

        return [
            'score' => $score,
            'status' => $status,
            // Suspicious and spam messages are quarantined from normal delivery.
            'isSpam' => $status === 'spam',
            'silent' => true,
            'httpHint' => null,
            'reasons' => $reasons,
            'urlCount' => $urlCount,
        ];
    }

    public static function redactUrls(string $message): string
    {
        return preg_replace(
            "~(?:https?://|www\\.)[^\\s<>\"']+~iu",
            '[URL]',
            $message
        ) ?? $message;
    }

    private function countUrls(string $message): int
    {
        preg_match_all(
            "~(?:https?://|www\\.)[^\\s<>\"']+~iu",
            $message,
            $matches
        );

        return count($matches[0]);
    }

    private function containsSeoSpam(string $message): bool
    {
        $phrases = [
            'improve your google ranking',
            'improve your website ranking',
            'increase your google ranking',
            'boost your google ranking',
            'seo services',
            'seo service',
            'search engine optimization',
            'search engine optimisation',
            'high quality backlinks',
            'quality backlinks',
            'buy backlinks',
            'build backlinks',
            'guest post',
            'guest posting',
            'google ranking',
            'website traffic',
            'increase website traffic',
            'organic traffic',
            'digital marketing agency',
            'online marketing services',
            'we can rank your website',
            'rank your website',
            'first page of google',
        ];
        $normalized = $this->normalizeText($message);
        foreach ($phrases as $phrase) {
            if (str_contains($normalized, $this->normalizeText($phrase))) {
                return true;
            }
        }
        return false;
    }

    private function looksTemplateLike(string $message): bool
    {
        $normalized = $this->normalizeText($message);
        if ((function_exists('mb_strlen') ? mb_strlen($normalized, 'UTF-8') : strlen($normalized)) < 20) {
            return false;
        }
        $phrases = [
            'dear sir',
            'dear madam',
            'dear sir or madam',
            'hello dear',
            'greetings',
            'i am contacting you',
            'i would like to offer',
            'we would like to offer',
            'we are a leading company',
            'our company specializes',
            'our company specialises',
            'we provide professional services',
            'please let me know if you are interested',
            'if you are interested please contact',
            'feel free to contact us',
            'let us know if you are interested',
            'looking forward to hearing from you',
        ];
        foreach ($phrases as $phrase) {
            if (str_contains($normalized, $this->normalizeText($phrase))) {
                return true;
            }
        }
        return false;
    }

    private function normalizeText(string $text): string
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }
}
