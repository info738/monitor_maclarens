<?php
// fetch_orders.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db.php'; // soubor s PDO připojením

// Konfigurace Dotykacky
$cloudId      = '343951305';
$refreshToken = 'd4af932a9d1260132c7b3401f8232d7c';
$accessToken  = '';

// 1) Získání Access Tokenu
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

// 2) Definice časového intervalu (např. dnešní půlnoc -> zítra 03:00)
$todayMidnight = gmdate('Y-m-d\T00:00:00\Z');
$endTime       = strtotime('+1 day 03:00:00');
$tomorrow3am   = gmdate('Y-m-d\TH:i:s\Z', $endTime);
$filter   = urlencode("created|gteq|$todayMidnight;created|lt|$tomorrow3am");

// 3) Načtení všech objednávek pomocí stránkování
$allOrders = fetchAllOrders($cloudId, $accessToken, $filter);
if (empty($allOrders)) {
    die("Žádné objednávky pro zadané období.");
}

foreach ($allOrders as $order) {
    // Získání názvu stolu
    $tableId  = $order['_tableId'] ?? '';
    $tableName = getTableName($cloudId, $accessToken, $tableId);
    
    // Rozpoznání dodací služby dle poznámky
    $rawNote  = $order['note'] ?? '';
    $lowerNote= strtolower($rawNote);
    $deliveryService = '';
    if (strpos($lowerNote, 'foodora') !== false) {
        $deliveryService = 'foodora';
    } elseif (strpos($lowerNote, 'wolt') !== false) {
        $deliveryService = 'wolt';
    } elseif (strpos($lowerNote, 'bolt') !== false) {
        $deliveryService = 'bolt';
    }
    $deliveryNote = $rawNote;
    
    // Uložení objednávky do DB
    saveOrder($order, $tableName, $deliveryService, $deliveryNote);
    
    // Načtení položek objednávky
    $orderItems = fetchAllOrderItems($cloudId, $accessToken, $order['id']);
    foreach ($orderItems as $item) {
        // Uložíme referenci na ID objednávky
        $item['_orderId'] = $order['id'];
        saveOrderItem($item);
        
        // Zpracování podpoložek (customizations)
        if (!empty($item['orderItemCustomizations'])) {
            // Nejprve smažeme staré podpoložky pro tuto položku
            $sql = "DELETE FROM order_item_subitems WHERE order_item_id = :order_item_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':order_item_id' => $item['id']]);
            // Vložíme nové podpoložky
            foreach ($item['orderItemCustomizations'] as $cust) {
                $cName = $cust['name'] ?? '';
                $cQty  = $cust['quantity'] ?? '1';
                $sql = "INSERT INTO order_item_subitems (order_item_id, name, quantity)
                        VALUES (:order_item_id, :name, :quantity)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':order_item_id' => $item['id'],
                    ':name' => $cName,
                    ':quantity' => $cQty
                ]);
            }
        }
    }
}

echo "Objednávky a položky byly úspěšně aktualizovány v databázi.";

/* Pomocné funkce */

function fetchAllOrders($cloudId, $accessToken, $filter) {
    $allOrders = [];
    $page = 1;
    $perPage = 100;
    do {
        $url = "https://api.dotykacka.cz/v2/clouds/$cloudId/orders?filter=$filter&page=$page&perPage=$perPage";
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
        $allOrders = array_merge($allOrders, $json['data']);
        $nextPage = $json['nextPage'] ?? null;
        $page = !empty($nextPage) ? (int)$nextPage : null;
    } while ($page !== null);
    return $allOrders;
}

function fetchAllOrderItems($cloudId, $accessToken, $orderId) {
    $allItems = [];
    $page = 1;
    $perPage = 100;
    $filter = urlencode("_orderId|eq|".$orderId);
    do {
        $url = "https://api.dotykacka.cz/v2/clouds/$cloudId/order-items?filter=$filter&page=$page&perPage=$perPage";
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
        $allItems = array_merge($allItems, $json['data']);
        $nextPage = $json['nextPage'] ?? null;
        $page = !empty($nextPage) ? (int)$nextPage : null;
    } while ($page !== null);
    return $allItems;
}

function getTableName($cloudId, $accessToken, $tableId) {
    if (empty($tableId)) {
        return '';
    }
    $tableUrl = "https://api.dotykacka.cz/v2/clouds/$cloudId/tables/$tableId";
    $ch = curl_init($tableUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Accept: application/json; charset=UTF-8"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $tableData = json_decode($response, true);
    return $tableData['name'] ?? '';
}

function saveOrder($order, $tableName, $deliveryService, $deliveryNote) {
    global $db;
    $sql = "INSERT INTO orders (id, created, note, table_name, delivery_service, delivery_note)
            VALUES (:id, :created, :note, :table_name, :delivery_service, :delivery_note)
            ON DUPLICATE KEY UPDATE
                created = VALUES(created),
                note = VALUES(note),
                table_name = VALUES(table_name),
                delivery_service = VALUES(delivery_service),
                delivery_note = VALUES(delivery_note)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $order['id'],
        ':created' => $order['created'],
        ':note' => $order['note'] ?? '',
        ':table_name' => $tableName,
        ':delivery_service' => $deliveryService,
        ':delivery_note' => $deliveryNote,
    ]);
}

function saveOrderItem($item) {
    global $db;
    // Získáme produktové ID ze správného klíče (v JSON je to _productId)
    $productId = $item['_productId'] ?? null;
    $sql = "INSERT INTO order_items 
              (id, order_id, product_id, name, quantity, kitchen_status, note, shown)
            VALUES 
              (:id, :order_id, :product_id, :name, :quantity, :kitchen_status, :note, 0)
            ON DUPLICATE KEY UPDATE
              product_id     = VALUES(product_id),
              name           = VALUES(name),
              quantity       = VALUES(quantity),
              kitchen_status = VALUES(kitchen_status),
              note           = VALUES(note)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id'             => $item['id'],
        ':order_id'       => $item['_orderId'],
        ':product_id'     => $productId,
        ':name'           => $item['name'],
        ':quantity'       => $item['quantity'],
        ':kitchen_status' => $item['kitchenStatus'] ?? 'new',
        ':note'           => $item['note'] ?? '',
    ]);
}

?>
