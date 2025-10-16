<?php
// update_item.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
require 'db.php';
require_once 'timing_functions.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['error'=>'Invalid JSON input']);
    exit;
}
$newStatus = $input['newStatus'] ?? null;
$trackTiming = $input['trackTiming'] ?? false;

if (!$newStatus) {
    echo json_encode(['error'=>'No newStatus provided']);
    exit;
}
$itemIds = [];
if (isset($input['itemId'])) {
    $itemIds[] = $input['itemId'];
}
if (isset($input['itemIds'])) {
    $itemIds = array_merge($itemIds, $input['itemIds']);
}
if (count($itemIds) === 0) {
    echo json_encode(['error'=>'No itemId(s) given']);
    exit;
}

$in = str_repeat('?,', count($itemIds) - 1) . '?';
$sql = "UPDATE order_items SET kitchen_status = ? WHERE id IN ($in)";
$params = array_merge([$newStatus], $itemIds);
$stmt = $db->prepare($sql);
$result = $stmt->execute($params);

if ($result) {
    // Pokud je zapnuto sledování časů, aktualizujeme timing
    if ($trackTiming) {
        foreach ($itemIds as $itemId) {
            if ($newStatus === 'in-progress') {
                updateItemStarted($db, $itemId);
            } elseif ($newStatus === 'completed') {
                updateItemCompleted($db, $itemId);
            }
        }
    }

    echo json_encode(['success'=>true, 'changed'=>count($itemIds)]);
} else {
    echo json_encode(['error'=>'Update failed']);
}
?>
