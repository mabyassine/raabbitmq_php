<?php

namespace Worker\ApiFetcher;

use Exception;

class CapitalFetcher
{
    public function fetchCapital(string $countryName): ?string
    {
        $url = "https://restcountries.com/v3.1/name/" . urlencode($countryName);
        $response = file_get_contents($url);
        if ($response === false) {
            throw new Exception("API request failed");
        }

        $data = json_decode($response, true);
        return $data[0]['capital'][0] ?? null;
    }
}
