<?php
// db.php – připojení k databázi
$host = 'localhost';
$dbname = 'monitor';
$username = 'monitor';
$password = 'Iv_a32y50';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Chyba připojení k databázi: " . $e->getMessage());
}
?>
