<?php
/**
 * Database Configuration
 * INVENTORY MANAGEMENT – PDO connection setup
 */

//  DB CONFIG 
// .env bata DB credentials lina (if available), natra default use garne
$host = getenv('DB_HOST') ?: "localhost";   // database host
$db   = getenv('DB_NAME') ?: "groceryflow"; // database name
$user = getenv('DB_USER') ?: "root";        // database username
$pass = getenv('DB_PASS') ?: "";            // database password

try {
    //  PDO CONNECTION 
    // MySQL sanga connect garne using PDO
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4", // connection string
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
            // error aayo vane exception throw garxa (debug easy hunxa)

            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, 
            // result array associative format ma dinxa

            PDO::ATTR_EMULATE_PREPARES => false, 
            // real prepared statement use garxa (security better)
        ]
    );

} catch (PDOException $e) {

    //  ERROR HANDLE 
    // database connect fail bhayo vane JSON response pathaune
    header("Content-Type: application/json");

    http_response_code(500); // server error

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed."
    ]);

    exit(); // script stop garne
}