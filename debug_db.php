<?php
// debug_db.php - pro analýzu databáze
require 'db.php';

echo "<h2>Analýza databáze</h2>";

// 1. Počet položek v order_items
$stmt = $db->query("SELECT COUNT(*) as total FROM order_items");
$total = $stmt->fetchColumn();
echo "<p>Celkem položek v order_items: $total</p>";

// 2. Počet položek s product_id
$stmt = $db->query("SELECT COUNT(*) as with_product_id FROM order_items WHERE product_id IS NOT NULL");
$withProductId = $stmt->fetchColumn();
echo "<p>Položek s product_id: $withProductId</p>";

// 3. Počet položek bez product_id
$stmt = $db->query("SELECT COUNT(*) as without_product_id FROM order_items WHERE product_id IS NULL");
$withoutProductId = $stmt->fetchColumn();
echo "<p>Položek bez product_id: $withoutProductId</p>";

// 4. Počet vyloučených produktů
$stmt = $db->query("SELECT COUNT(*) as excluded_count FROM excluded_products");
$excludedCount = $stmt->fetchColumn();
echo "<p>Počet vyloučených produktů: $excludedCount</p>";

// 5. Ukázka položek s product_id
echo "<h3>Ukázka položek s product_id:</h3>";
$stmt = $db->query("SELECT id, name, product_id, kitchen_status FROM order_items WHERE product_id IS NOT NULL LIMIT 10");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1'><tr><th>ID</th><th>Název</th><th>Product ID</th><th>Status</th></tr>";
foreach ($items as $item) {
    echo "<tr><td>{$item['id']}</td><td>{$item['name']}</td><td>{$item['product_id']}</td><td>{$item['kitchen_status']}</td></tr>";
}
echo "</table>";

// 6. Ukázka položek bez product_id
echo "<h3>Ukázka položek bez product_id:</h3>";
$stmt = $db->query("SELECT id, name, product_id, kitchen_status FROM order_items WHERE product_id IS NULL LIMIT 10");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1'><tr><th>ID</th><th>Název</th><th>Product ID</th><th>Status</th></tr>";
foreach ($items as $item) {
    echo "<tr><td>{$item['id']}</td><td>{$item['name']}</td><td>" . ($item['product_id'] ?? 'NULL') . "</td><td>{$item['kitchen_status']}</td></tr>";
}
echo "</table>";

// 7. Kontrola, zda se některé aktivní položky shodují s vyloučenými
echo "<h3>Aktivní položky které by měly být vyloučené:</h3>";
$stmt = $db->query("
    SELECT oi.id, oi.name, oi.product_id, oi.kitchen_status 
    FROM order_items oi 
    WHERE oi.kitchen_status IN ('new','in-progress','reordered') 
    AND oi.product_id IS NOT NULL 
    AND oi.product_id IN (SELECT product_id FROM excluded_products)
    LIMIT 10
");
$problematicItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($problematicItems)) {
    echo "<p>Žádné problematické položky nenalezeny.</p>";
} else {
    echo "<table border='1'><tr><th>ID</th><th>Název</th><th>Product ID</th><th>Status</th></tr>";
    foreach ($problematicItems as $item) {
        echo "<tr><td>{$item['id']}</td><td>{$item['name']}</td><td>{$item['product_id']}</td><td>{$item['kitchen_status']}</td></tr>";
    }
    echo "</table>";
}

// 8. Ukázka vyloučených produktů
echo "<h3>Ukázka vyloučených produktů:</h3>";
$stmt = $db->query("SELECT product_id FROM excluded_products LIMIT 10");
$excluded = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<ul>";
foreach ($excluded as $pid) {
    echo "<li>$pid</li>";
}
echo "</ul>";
?>
