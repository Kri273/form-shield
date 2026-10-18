<?php

declare(strict_types=1);

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class PhoneChecker
{
    private PhoneNumberUtil $phoneUtil;
    private string $homeCountry;

    public function __construct(string $homeCountry = 'EE')
    {
        $this->phoneUtil = PhoneNumberUtil::getInstance();
        $this->homeCountry = strtoupper($homeCountry);
    }

    public function check(string $phone): array
    {
        $phone = trim($phone);

        if ($phone === '') {
            return [
                'valid' => false,
                'possible' => false,
                'country' => null,
                'isHomeCountry' => false,
                'error' => 'empty'
            ];
        }


        try {
            $number = $this->phoneUtil->parse($phone, null);
        } catch (NumberParseException $e) {
            return [
                'valid' => false,
                'possible' => false,
                'country' => null,
                'isHomeCountry' => false,
                'error' => 'invalid_format'
            ];
        }


        $possible = $this->phoneUtil->isPossibleNumber($number);
        $valid = $this->phoneUtil->isValidNumber($number);


        $country = null;

        try {
            $country = $this->phoneUtil->getRegionCodeForNumber($number);
        } catch (Throwable $e) {
            $country = null;
        }

        if ($country !== null) {
            $country = strtoupper($country);
        }


        $isHomeCountry =
            $country !== null &&
            $country === $this->homeCountry;

        return [
            'valid' => $valid,
            'possible' => $possible,
            'country' => $country,
            'isHomeCountry' => $isHomeCountry,
            'error' => null
        ];
    }
}