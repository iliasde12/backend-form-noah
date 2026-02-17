<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';



function ConnectionDb(): PDO
{
    try {
        // SQLite database bestand locatie (buiten www voor veiligheid)
        $dbPath = dirname(__DIR__, 2) . '/database/noahform.db';

        // Zorg dat de database directory bestaat
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        // Maak PDO connectie met SQLite
        $pdo = new PDO('sqlite:' . $dbPath);

        // Zet error mode naar exceptions
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Enable foreign keys (belangrijk voor SQLite!)
        $pdo->exec('PRAGMA foreign_keys = ON');

        // Create table als die nog niet bestaat
        createTablesIfNotExists($pdo);

        return $pdo;

    } catch (PDOException $e) {
        error_log("Database connectie fout: " . $e->getMessage());
        throw new Exception("Er is een fout opgetreden bij de database connectie: " . $e->getMessage());
    }
}

function createTablesIfNotExists(PDO $pdo): void
{
    // Maak inschrijvingen tabel
    $sql = "CREATE TABLE IF NOT EXISTS inschrijvingen (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        voornaam TEXT NOT NULL,
        achternaam TEXT NOT NULL,
        email TEXT NOT NULL,
        leeftijd INTEGER NOT NULL,
        lengte INTEGER NOT NULL,
        gewicht INTEGER NOT NULL,
        beroep TEXT,
        blessures TEXT,
        struggle TEXT,
        train_frequentie TEXT,
        uiteten TEXT,
        voeding_aanpak TEXT,
        doelen TEXT,
        importance TEXT,
        actie TEXT,
        start_nu TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    $pdo->exec($sql);

    // Voeg hier meer tabellen toe als je die nodig hebt
    // Bijvoorbeeld voor producten, bestellingen, etc.
}

function saveDb(array $cleanData): bool
{
    try {
        $pdo = ConnectionDb();

        $sql = "INSERT INTO inschrijvingen (
            voornaam, 
            achternaam, 
            email, 
            leeftijd, 
            lengte, 
            gewicht, 
            beroep, 
            blessures, 
            struggle, 
            train_frequentie, 
            uiteten, 
            voeding_aanpak, 
            doelen, 
            importance, 
            actie, 
            start_nu,
            created_at
        ) VALUES (
            :voornaam,
            :achternaam,
            :email,
            :leeftijd,
            :lengte,
            :gewicht,
            :beroep,
            :blessures,
            :struggle,
            :trainFrequentie,
            :uiteten,
            :voedingAanpak,
            :doelen,
            :importance,
            :actie,
            :startNu,
            datetime('now')
        )";

        $stmt = $pdo->prepare($sql);

        $result = $stmt->execute([
            ':voornaam' => $cleanData['voornaam'],
            ':achternaam' => $cleanData['achternaam'],
            ':email' => $cleanData['email'],
            ':leeftijd' => $cleanData['leeftijd'],
            ':lengte' => $cleanData['lengte'],
            ':gewicht' => $cleanData['gewicht'],
            ':beroep' => $cleanData['beroep'],
            ':blessures' => $cleanData['blessures'],
            ':struggle' => $cleanData['struggle'],
            ':trainFrequentie' => $cleanData['trainFrequentie'],
            ':uiteten' => $cleanData['uiteten'],
            ':voedingAanpak' => $cleanData['voedingAanpak'],
            ':doelen' => $cleanData['doelen'],
            ':importance' => $cleanData['importance'],
            ':actie' => $cleanData['actie'],
            ':startNu' => $cleanData['startNu']
        ]);

        return $result;

    } catch (PDOException $e) {
        error_log("Database save fout: " . $e->getMessage());
        return false;
    }
}

// Extra helper functies voor SQLite

function getLastInsertId(PDO $pdo): int
{
    return (int) $pdo->lastInsertId();
}

function getAllRecords(string $table): array
{
    try {
        $pdo = ConnectionDb();
        $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database query fout: " . $e->getMessage());
        return [];
    }
}

function getRecordById(string $table, int $id): ?array
{
    try {
        $pdo = ConnectionDb();
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Database query fout: " . $e->getMessage());
        return null;
    }
}

function deleteRecord(string $table, int $id): bool
{
    try {
        $pdo = ConnectionDb();
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    } catch (PDOException $e) {
        error_log("Database delete fout: " . $e->getMessage());
        return false;
    }
}

function updateRecord(string $table, int $id, array $data): bool
{
    try {
        $pdo = ConnectionDb();

        // Build SET clause
        $setClause = [];
        foreach (array_keys($data) as $key) {
            $setClause[] = "{$key} = :{$key}";
        }
        $setClauseString = implode(', ', $setClause);

        $sql = "UPDATE {$table} SET {$setClauseString} WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        // Add id to data
        $data[':id'] = $id;

        return $stmt->execute($data);

    } catch (PDOException $e) {
        error_log("Database update fout: " . $e->getMessage());
        return false;
    }
}