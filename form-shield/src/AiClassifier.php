<?php

declare(strict_types=1);

class AiClassifier
{
    private string $apiKey;
    private string $model;

    private string $endpoint =
        'https://api.openai.com/v1/responses';


    public function __construct(
        string $apiKey,
        string $model
    ) {
        $this->apiKey = trim($apiKey);
        $this->model = trim($model);
    }


    public function isEnabled(): bool
    {
        return $this->apiKey !== '';
    }



    public function classify(
        string $name,
        string $email,
        string $message
    ): array {


        if (!$this->isEnabled()) {

            return [
                'available' => false,
                'is_spam' => false,
                'confidence' => 0.0,
                'reason' => 'ai_disabled',
            ];
        }


        $prompt = $this->buildPrompt(
            $name,
            $email,
            $message
        );


        $payload = [

            'model' => $this->model,

            'input' => [

                [
                    'role' => 'system',

                    'content' => [

                        [
                            'type' => 'input_text',

                            'text' =>
                                'You are a conservative spam classifier for a small business contact form.'
                        ]
                    ]
                ],

                [
                    'role' => 'user',

                    'content' => [

                        [
                            'type' => 'input_text',

                            'text' => $prompt
                        ]
                    ]
                ]
            ],

            'text' => [

                'format' => [

                    'type' => 'json_schema',

                    'name' =>
                        'spam_classification',

                    'strict' => true,

                    'schema' => [

                        'type' => 'object',

                        'properties' => [

                            'is_spam' => [
                                'type' => 'boolean'
                            ],

                            'confidence' => [
                                'type' => 'number'
                            ],

                            'reason' => [
                                'type' => 'string'
                            ],
                        ],

                        'required' => [
                            'is_spam',
                            'confidence',
                            'reason'
                        ],

                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];


        $response =
            $this->sendRequest($payload);



        if ($response === null) {

            return [
                'available' => false,
                'is_spam' => false,
                'confidence' => 0.0,
                'reason' => 'ai_error',
            ];
        }


        $output =
            $this->extractOutputText($response);


        if ($output === null) {

            return [
                'available' => false,
                'is_spam' => false,
                'confidence' => 0.0,
                'reason' => 'invalid_ai_response',
            ];
        }


        $decoded =
            json_decode(
                $output,
                true
            );


        if (!is_array($decoded)) {

            return [
                'available' => false,
                'is_spam' => false,
                'confidence' => 0.0,
                'reason' => 'invalid_ai_json',
            ];
        }


        $isSpam =
            (bool) (
                $decoded['is_spam'] ?? false
            );


        $confidence =
            (float) (
                $decoded['confidence'] ?? 0
            );



        $confidence =
            max(
                0.0,
                min(1.0, $confidence)
            );


        $reason =
            trim(
                (string) (
                    $decoded['reason'] ?? ''
                )
            );


        return [

            'available' => true,

            'is_spam' => $isSpam,

            'confidence' =>
                $confidence,

            'reason' =>
                $reason,
        ];
    }




    private function buildPrompt(
        string $name,
        string $email,
        string $message
    ): string {

        return <<<PROMPT
Classify this contact form submission as either legitimate or spam.

SPAM includes things such as:
- unsolicited SEO offers
- backlink offers
- guest post offers
- website ranking services
- unsolicited marketing
- mass promotional messages
- suspicious automated messages
- obvious spam templates

LEGITIMATE messages can include:
- customer questions
- requests about products or services
- messages written in English
- customers from foreign countries
- questions about serving patients or customers from another country
- short or imperfectly written messages

Important rules:

1. English is NOT a spam signal by itself.

2. A foreign country is NOT a spam signal by itself.

3. A customer asking whether a business serves people from another
   country can be completely legitimate.

4. Do not classify a message as spam merely because it contains a URL.

5. Consider the overall intent of the message.

6. SEO, backlink, ranking and unsolicited marketing offers are strong
   spam signals.

7. Be conservative. If the message could reasonably be a genuine
   customer inquiry, prefer legitimate.

Name:
{$name}

Email:
{$email}

Message:
{$message}
PROMPT;
    }



    private function sendRequest(
        array $payload
    ): ?array {

        $json =
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );


        if ($json === false) {
            return null;
        }


        $ch =
            curl_init(
                $this->endpoint
            );


        if ($ch === false) {
            return null;
        }


        curl_setopt_array(
            $ch,
            [

                CURLOPT_POST =>
                    true,

                CURLOPT_RETURNTRANSFER =>
                    true,

                CURLOPT_CONNECTTIMEOUT =>
                    3,

                CURLOPT_TIMEOUT =>
                    8,

                CURLOPT_HTTPHEADER => [

                    'Content-Type: application/json',

                    'Authorization: Bearer ' .
                        $this->apiKey,
                ],

                CURLOPT_POSTFIELDS =>
                    $json,
            ]
        );


        $body =
            curl_exec($ch);


        $httpCode =
            (int) curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );


        $curlError =
            curl_error($ch);


        curl_close($ch);


        if ($body === false) {
            return null;
        }


        if ($curlError !== '') {
            return null;
        }


        if (
            $httpCode < 200 ||
            $httpCode >= 300
        ) {
            return null;
        }


        $decoded =
            json_decode(
                $body,
                true
            );


        if (!is_array($decoded)) {
            return null;
        }


        return $decoded;
    }


    private function extractOutputText(
        array $response
    ): ?string {

        if (
            isset($response['output_text']) &&
            is_string(
                $response['output_text']
            )
        ) {

            return $response['output_text'];
        }


        if (
            !isset($response['output']) ||
            !is_array($response['output'])
        ) {
            return null;
        }


        foreach (
            $response['output']
            as $outputItem
        ) {

            if (
                !isset(
                    $outputItem['content']
                ) ||
                !is_array(
                    $outputItem['content']
                )
            ) {
                continue;
            }


            foreach (
                $outputItem['content']
                as $contentItem
            ) {

                if (
                    isset(
                        $contentItem['text']
                    ) &&
                    is_string(
                        $contentItem['text']
                    )
                ) {

                    return
                        $contentItem['text'];
                }
            }
        }


        return null;
    }
}