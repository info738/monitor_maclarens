<?php
// fetch_products.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

$cloudId      = '343951305';
$refreshToken = 'd4af932a9d1260132c7b3401f8232d7c';
$accessToken  = '';

// 1) Získání Access Tokenu (stejně jako dříve)
$url = "https://api.dotykacka.cz/v2/signin/token";
$data = ['_cloudId' => $cloudId];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json; charset=UTF-8",
    "Accept: application/json; charset=UTF-8",
    "Authorization: User $refreshToken"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);
if ($http_code === 201 && isset($responseData['accessToken'])) {
    $accessToken = $responseData['accessToken'];
} else {
    die("Nepodařilo se získat Access Token. Odezva: $response");
}

// 2) Paging smyčka pro /products
$allProducts = [];
$page = 1;
$perPage = 100;

do {
    $url = "https://api.dotykacka.cz/v2/clouds/$cloudId/products?page=$page&perPage=$perPage";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Accept: application/json; charset=UTF-8"
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($resp, true);
    if (!isset($json['data']) || !is_array($json['data'])) {
        break;
    }
    // Sloučím
    $allProducts = array_merge($allProducts, $json['data']);

    $nextPage = $json['nextPage'] ?? null;
    if (!empty($nextPage)) {
        $page = (int)$nextPage;
    } else {
        $page = null;
    }
} while ($page !== null);

// 3) Uložím do products.json
file_put_contents('products.json', json_encode($allProducts, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

echo "Hotovo! Stáhli jsme ".count($allProducts)." produktů z Dotypos a uložili do products.json\n";
