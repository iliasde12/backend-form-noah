<?php
// backend/api/save-intake.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/mailer.php';
$mailer = new Mailer();

// Error reporting (uitzetten in productie)
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Alleen POST toestaan
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Alleen POST requests toegestaan'
    ]);
    exit;
}

function buildCalendlyUrl($cleanData): string
{
    $baseUrl = " https://calendly.com/its-noahcpt/1-1-intake";
    $params = [
        'name' => $cleanData['voornaam'] . ' ' . $cleanData['achternaam'],
        'email' => $cleanData['email'],
        'phone' => $cleanData['telefoon'] ?? ''
    ];

    return $baseUrl . '?' . http_build_query($params);
}

// Haal JSON data op
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check of JSON parsing gelukt is
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Ongeldige JSON data'
    ]);
    exit;
}

// Validatie functie
function validateIntakeData($data): array
{
    $errors = [];

    // Verplichte velden
    $required = [
        'voornaam' => 'Voornaam',
        'achternaam' => 'Achternaam',
        'email' => 'Email',
        'leeftijd' => 'Leeftijd',
        'lengte' => 'Lengte',
        'gewicht' => 'Gewicht',
        'beroep' => 'Beroep',
        'blessures' => 'Blessures',
        'struggle' => 'Struggle',
        'trainFrequentie' => 'Train frequentie',
        'uiteten' => 'Uit eten frequentie',
        'voedingAanpak' => 'Voeding aanpak',
        'doelen' => 'Doelen',
        'importance' => 'Belangrijkheid doelen',
        'actie' => 'Actie ondernemen',
        'startNu' => 'Nu starten'
    ];

    foreach ($required as $field => $label) {
        if (empty($data[$field]) && $data[$field] !== '0') {
            $errors[] = "$label is verplicht";
        }
    }

    // Email validatie
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Ongeldig email adres';
    }

    // Telefoonnummer (optioneel, maar als ingevuld moet het valide zijn)
    if (!empty($data['telefoon']) && !preg_match('/^[\d\s\+\-\(\)]+$/', $data['telefoon'])) {
        $errors[] = 'Ongeldig telefoonnummer';
    }

    // Leeftijd check
    if (!empty($data['leeftijd'])) {
        $leeftijd = (int)$data['leeftijd'];
        if ($leeftijd < 16 || $leeftijd > 100) {
            $errors[] = 'Leeftijd moet tussen 16 en 100 zijn';
        }
    }

    // Importance check (0-10)
    if (isset($data['importance'])) {
        $importance = (int)$data['importance'];
        if ($importance < 0 || $importance > 10) {
            $errors[] = 'Belangrijkheid moet tussen 0 en 10 zijn';
        }
    }

    return $errors;
}

// Valideer data
$errors = validateIntakeData($data);

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Validatie fouten',
        'errors' => $errors
    ]);
    exit;
}

// Sanitize data
function sanitizeData($data): array
{
    $clean = [];
    foreach ($data as $key => $value) {
        if (is_string($value)) {
            $clean[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        } else {
            $clean[$key] = $value;
        }
    }
    return $clean;
}
//data cleanen



$cleanData = sanitizeData($data);

try {
        $result = saveDb($cleanData);
        if($result){
            $calendlyUrl = buildCalendlyUrl($cleanData);
            $mailer->sendIntakeToAdmin($cleanData);
            $mailer->sendConfirmationToClient($cleanData);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Intake succesvol opgeslagen',
                'redirect_url' => $calendlyUrl
            ]);
        }

        /*else{
            echo json_encode([
                'success' => false,
                'message' => 'er is iets fout gegaan',
            ]);
        }*/

} catch (Exception $e) {
    // Error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}

exit; 
