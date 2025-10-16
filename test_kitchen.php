<?php
// test_kitchen.php - pro testování funkcí kuchyně
require 'db.php';

echo "<h2>Test funkcí kuchyně</h2>";

// 1. Test vyloučených položek
echo "<h3>1. Test vyloučených položek</h3>";
$stmt = $db->query("SELECT COUNT(*) as excluded_count FROM excluded_products");
$excludedCount = $stmt->fetchColumn();
echo "<p>Počet vyloučených produktů: $excludedCount</p>";

// 2. Test aktivních objednávek
echo "<h3>2. Test aktivních objednávek</h3>";
$sqlActive = "SELECT oi.*, o.created, o.table_name, o.delivery_service, o.delivery_note
              FROM order_items oi
              JOIN orders o ON oi.order_id = o.id
              WHERE oi.kitchen_status IN ('new','in-progress','reordered')
                AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))
              ORDER BY o.created ASC";
$stmt = $db->query($sqlActive);
$activeItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Počet aktivních položek: " . count($activeItems) . "</p>";

// 3. Test dokončených objednávek
echo "<h3>3. Test dokončených objednávek</h3>";
$sqlCompleted = "SELECT oi.*, o.created, o.table_name, o.delivery_service, o.delivery_note
                 FROM order_items oi
                 JOIN orders o ON oi.order_id = o.id
                 WHERE oi.kitchen_status = 'completed'
                   AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))
                   AND oi.last_updated >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                 ORDER BY o.created ASC";
$stmt = $db->query($sqlCompleted);
$completedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Počet dokončených položek (posledních 30 min): " . count($completedItems) . "</p>";

// 4. Test předaných objednávek
echo "<h3>4. Test předaných objednávek</h3>";
$sqlPassed = "SELECT COUNT(*) as passed_count FROM order_items WHERE kitchen_status = 'passed'";
$stmt = $db->query($sqlPassed);
$passedCount = $stmt->fetchColumn();
echo "<p>Počet předaných položek: $passedCount</p>";

// 5. Ukázka dat
if (!empty($activeItems)) {
    echo "<h3>5. Ukázka aktivních položek</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Název</th><th>Stav</th><th>Objednávka</th><th>Product ID</th></tr>";
    foreach (array_slice($activeItems, 0, 5) as $item) {
        echo "<tr>";
        echo "<td>{$item['id']}</td>";
        echo "<td>{$item['name']}</td>";
        echo "<td>{$item['kitchen_status']}</td>";
        echo "<td>{$item['order_id']}</td>";
        echo "<td>" . ($item['product_id'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 6. Test AJAX endpointu
echo "<h3>6. Test AJAX endpointu</h3>";
echo "<button onclick='testAjax()'>Test AJAX načítání</button>";
echo "<div id='ajaxResult'></div>";

echo "<script>
function testAjax() {
    fetch('kitchen.php?ajax=1')
        .then(response => response.json())
        .then(data => {
            document.getElementById('ajaxResult').innerHTML = 
                '<p>AJAX test úspěšný!</p>' +
                '<p>Aktivní objednávky: ' + Object.keys(data.active).length + '</p>' +
                '<p>Dokončené objednávky: ' + Object.keys(data.completed).length + '</p>' +
                '<p>Timestamp: ' + data.timestamp + '</p>';
        })
        .catch(error => {
            document.getElementById('ajaxResult').innerHTML = '<p style=\"color: red;\">AJAX test neúspěšný: ' + error + '</p>';
        });
}
</script>";

echo "<hr>";
echo "<p><a href='kitchen.php'>← Zpět do kuchyně</a></p>";
?>
