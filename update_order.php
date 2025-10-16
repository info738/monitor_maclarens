<?php
// update_order.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
require 'db.php';
require_once 'timing_functions.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['orderId'])) {
    echo json_encode(['error'=>'Chybí orderId']);
    exit;
}
$orderId = $input['orderId'];
$action = $input['action'] ?? '';
$trackTiming = $input['trackTiming'] ?? false;

if ($action === 'passCompleted') {
    // U objednávky změníme všechny položky se stavem completed na passed
    $sql = "UPDATE order_items SET kitchen_status = 'passed' WHERE order_id = ? AND kitchen_status = 'completed'";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$orderId]);

    if ($result) {
        // Pokud je zapnuto sledování časů, aktualizujeme timing
        if ($trackTiming) {
            updateOrderPassed($db, $orderId);
        }

        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['error'=>'Nepodařilo se aktualizovat objednávku']);
    }
    exit;
}

echo json_encode(['error'=>'Neznámá akce']);
?>
