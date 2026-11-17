<?php
// JSON API for the weight monitor (single user, no login).
// On Hostinger, config.php lives one folder above public_html so Git deploys never touch it.
// Falls back to a config.php next to this file.
$configFile = file_exists(__DIR__ . '/../config.php') ? __DIR__ . '/../config.php' : __DIR__ . '/config.php';
if (!file_exists($configFile)) { http_response_code(500); header('Content-Type: application/json'); exit('{"error":"config.php is missing on the server"}'); }
$config = require $configFile;

header('Content-Type: application/json');

function reply($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

try {
    $db = new PDO($config['dsn'], $config['db_user'], $config['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // Weights are stored in kg; the unit is only a display preference in the browser.
    $db->exec('CREATE TABLE IF NOT EXISTS entries (date CHAR(10) PRIMARY KEY, kg DOUBLE NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS goal (id INTEGER PRIMARY KEY, kg DOUBLE NOT NULL, date CHAR(10) NOT NULL)');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $entries = array_map(fn($e) => ['date' => $e['date'], 'kg' => (float)$e['kg']],
            $db->query('SELECT date, kg FROM entries ORDER BY date')->fetchAll(PDO::FETCH_ASSOC));
        $goal = $db->query('SELECT kg, date FROM goal WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        reply(['entries' => $entries, 'goal' => $goal ? ['kg' => (float)$goal['kg'], 'date' => $goal['date']] : null]);
    }

    // Requiring a JSON body blocks cross-site form posts (CSRF): browsers can't send it cross-origin without CORS.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        reply(['error' => 'Expected a JSON POST'], 400);
    }
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) reply(['error' => 'Invalid JSON'], 400);
    $action = $in['action'] ?? '';

    $validDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
    $validKg = fn($kg) => is_int($kg) || is_float($kg) ? $kg > 0 && $kg < 1000 : false;
    $require = fn($ok) => $ok || reply(['error' => 'Invalid date or weight'], 422);

    switch ($action) {
        case 'save_entry':
            $require($validDate($in['date'] ?? null) && $validKg($in['kg'] ?? null));
            $db->prepare('REPLACE INTO entries (date, kg) VALUES (?, ?)')->execute([$in['date'], $in['kg']]);
            break;
        case 'delete_entry':
            $require($validDate($in['date'] ?? null));
            $db->prepare('DELETE FROM entries WHERE date = ?')->execute([$in['date']]);
            break;
        case 'set_goal':
            $require($validDate($in['date'] ?? null) && $validKg($in['kg'] ?? null));
            $db->prepare('REPLACE INTO goal (id, kg, date) VALUES (1, ?, ?)')->execute([$in['kg'], $in['date']]);
            break;
        case 'clear_goal':
            $db->exec('DELETE FROM goal');
            break;
        case 'import': // replaces everything with a backup file's contents
            $entries = $in['entries'] ?? null;
            $goal = $in['goal'] ?? null;
            $require(is_array($entries) && array_reduce($entries, fn($ok, $e) => $ok && $validDate($e['date'] ?? null) && $validKg($e['kg'] ?? null), true));
            $require($goal === null || ($validDate($goal['date'] ?? null) && $validKg($goal['kg'] ?? null)));
            $db->beginTransaction();
            $db->exec('DELETE FROM entries');
            $db->exec('DELETE FROM goal');
            $ins = $db->prepare('REPLACE INTO entries (date, kg) VALUES (?, ?)');
            foreach ($entries as $e) $ins->execute([$e['date'], $e['kg']]);
            if ($goal) $db->prepare('REPLACE INTO goal (id, kg, date) VALUES (1, ?, ?)')->execute([$goal['kg'], $goal['date']]);
            $db->commit();
            break;
        default:
            reply(['error' => 'Unknown action'], 400);
    }
    reply(['ok' => true]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    reply(['error' => 'Database error'], 500);
}
