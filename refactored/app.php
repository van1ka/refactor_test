<?php
require __DIR__ . '/CommissionCalculator.php';


$calculator = new CommissionCalculator(
    'https://lookup.binlist.net',
    'https://api.exchangeratesapi.io/latest'
);


$calculator->calculate(__DIR__.'/../input.txt');