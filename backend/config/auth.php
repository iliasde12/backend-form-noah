<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function getAuthToken() {
    $headers = getallheaders();

    // Check Authorization header
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];

        // Format: "Bearer <token>"
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return $matches[1];
        }
    }

    // Check als token in query string zit
    if (isset($_GET['token'])) {
        return $_GET['token'];
    }

    return null;
}

function verifyToken($token) {
    $secret_key = $_ENV['JWT_SECRET'] ?? 'jouw-super-geheime-sleutel-hier';

    try {
        $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        return null;
    }
}

function requireAuth() {
    $token = getAuthToken();

    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token vereist']);
        exit;
    }

    $decoded = verifyToken($token);

    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Ongeldige of verlopen token']);
        exit;
    }

    return $decoded;
}

function getUserFromToken() {
    $token = getAuthToken();

    if (!$token) {
        return null;
    }

    $decoded = verifyToken($token);

    return $decoded ? $decoded->data : null;
}