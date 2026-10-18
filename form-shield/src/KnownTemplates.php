<?php

declare(strict_types=1);

class KnownTemplates
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function check(string $message): array
    {

        $normalizedMessage = $this->normalizeText($message);



        $stmt = $this->pdo->query(
            'SELECT
                id,
                name,
                pattern
             FROM known_templates
             WHERE enabled = 1
             ORDER BY id ASC'
        );

        $templates = $stmt->fetchAll();


        foreach ($templates as $template) {

            $pattern = (string) $template['pattern'];

            $normalizedPattern =
                $this->normalizeText($pattern);


            if ($normalizedPattern === '') {
                continue;
            }

            if (
                str_contains(
                    $normalizedMessage,
                    $normalizedPattern
                )
            ) {
                return [
                    'isSpam' => true,
                    'silent' => false,
                    'reason' => 'known_template',
                    'template_id' => (int) $template['id'],
                    'template_name' =>
                        (string) $template['name']
                ];
            }
        }


        return [
            'isSpam' => false,
            'silent' => false,
            'reason' => null,
            'template_id' => null,
            'template_name' => null
        ];
    }
    private function normalizeText(string $text): string
    {

        $text = mb_strtolower(
            $text,
            'UTF-8'
        );


        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}