<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Firebase\JWT\JWT;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// JWT configuratie
$secret_key = $_ENV['JWT_SECRET'];
$issuer = $_ENV['JWT_ISSUER'] ?? 'noahform.be';
$audience = $_ENV['JWT_AUDIENCE'] ?? 'noahform-users';
$expiration = (int)($_ENV['JWT_EXPIRATION'] ?? 86400); // 24 uur

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Alleen POST requests toegestaan'
    ]);
    exit;
}


$json = file_get_contents('php://input');
$data = json_decode($json, true);

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email en password vereist']);
    exit;
}

try {
    $db = ConnectionDb();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);

    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    $stmt->close();
    $db->close();

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Ongeldige inloggegevens']);
        exit;
    }

    // Maak JWT token met issuer en audience
    $issued_at = time();
    $expiration_time = $issued_at + $expiration;

    $payload = [
        'iat' => $issued_at,           // Issued at
        'exp' => $expiration_time,     // Expiration
        'iss' => $issuer,              // Issuer (wie heeft token gemaakt)
        'aud' => $audience,            // Audience (voor wie is het bedoeld)
        'data' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'] ?? '',
            'role' => $user['role'] ?? 'user'
        ]
    ];

    $jwt = JWT::encode($payload, $secret_key, 'HS256');

    echo json_encode([
        'success' => true,
        'token' => $jwt,
        //'expiresIn' => $expiration,
        /*'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'] ?? '',
            'role' => $user['role'] ?? 'user'
        ]*/
    ]);

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server fout']);
}