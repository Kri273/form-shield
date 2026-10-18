<?php

declare(strict_types=1);

class GeoIp
{
    /**
     * ipwho.is:
     * - HTTPS
     * - Returns ISO country code such as EE, FI, US, DE
     */
    public function country(string $ip): ?string
    {

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
        ) {
            return null;
        }


        $url = 'https://ipwho.is/' . rawurlencode($ip);


        $ch = curl_init($url);

        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);


        $response = curl_exec($ch);

        if ($response === false) {
            curl_close($ch);
            return null;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);


        if ($httpCode < 200 || $httpCode >= 300) {
            return null;
        }


        $data = json_decode($response, true);

        if (!is_array($data)) {
            return null;
        }


        if (($data['success'] ?? false) !== true) {
            return null;
        }

        $countryCode = $data['country_code'] ?? null;

        if (!is_string($countryCode) || $countryCode === '') {
            return null;
        }


        return strtoupper($countryCode);
    }
}