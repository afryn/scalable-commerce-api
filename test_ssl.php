<?php

$ch = curl_init("https://api.razorpay.com/v1/orders");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CAINFO => 'C:/xampp/php/extras/ssl/cacert.pem',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_VERBOSE => true,
]);

curl_exec($ch);

echo "cURL Error Number: " . curl_errno($ch) . PHP_EOL;
echo "cURL Error: " . curl_error($ch) . PHP_EOL;
echo "HTTP Status: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;

curl_close($ch);
