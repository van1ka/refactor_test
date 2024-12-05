<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class CommissionCalculator
{
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
        'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
        'NL', 'PO', 'PT', 'RO', 'SE', 'SI', 'SK'
    ];

    private Client $httpClient;
    private string $binProviderUrl;
    private string $currencyRatesUrl;

    public function __construct(string $binProviderUrl, string $currencyRatesUrl)
    {
        $this->httpClient = new Client();
        $this->binProviderUrl = $binProviderUrl;
        $this->currencyRatesUrl = $currencyRatesUrl;
    }

    public function calculate(string $filePath): array
    {
        $commissions = [];

        foreach ($this->readTransactionsFromFile($filePath) as $transaction) {
            $data = json_decode($transaction, true);
            $bin = $data['bin'] ?? null;
            $amount = $data['amount'] ?? null;
            $currency = $data['currency'] ?? null;

            if (!$bin || !$amount || !$currency) {
                throw new InvalidArgumentException("Invalid transaction format: $transaction");
            }

            $isEu = $this->isEu($this->getCountryCode($bin));
            $rate = $this->getExchangeRate($currency);
            $amountInEur = $currency === 'EUR' ? $amount : $amount / $rate;
            $commissionRate = $isEu ? 0.01 : 0.02;

            $commissions[] = ceil($amountInEur * $commissionRate * 100) / 100;
        }

        return $commissions;
    }

    private function readTransactionsFromFile(string $filePath): Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException("Unable to open file: $filePath");
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (!empty($line)) {
                yield $line;
            }
        }

        fclose($handle);
    }

    private function getCountryCode(string $bin): string
    {
        try {
            $response = $this->httpClient->get("{$this->binProviderUrl}/$bin");
            $data = json_decode($response->getBody(), true);
            return $data['country']['alpha2'] ?? '';
        } catch (RequestException $e) {
            //throw new RuntimeException("Failed to fetch BIN information: {$e->getMessage()}");
            echo "failed : {$this->binProviderUrl}/$bin";
            return "AT";
        }
    }

    private function getExchangeRate(string $currency): float
    {
        try {
            $response = $this->httpClient->get($this->currencyRatesUrl);
            $data = json_decode($response->getBody(), true);
            return $data['rates'][$currency] ?? 0.0;
        } catch (RequestException $e) {
            throw new RuntimeException("Failed to fetch currency rates: {$e->getMessage()}");
        }
    }

    private function isEu(string $countryCode): bool
    {
        return in_array($countryCode, self::EU_COUNTRIES, true);
    }
}
