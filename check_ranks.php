<?php
// idcard/check_ranks.php
require_once 'env_loader.php';

// Force 127.0.0.1 if localhost fails in CLI
$host = '127.0.0.1'; // Or try localhost if that's what Apache is using, but sometimes CLI socket differs.
$dbname = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $ranks = $conn->query("SELECT * FROM idcard_ranks")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($ranks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
