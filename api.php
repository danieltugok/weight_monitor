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
    $db->exec('CREATE TABLE IF NOT EXISTS goals (date CHAR(10) PRIMARY KEY, kg DOUBLE NOT NULL)');
    // shortcut: one-time move from the old single-goal table; delete this block once the live site has loaded once.
    try { $db->query('SELECT 1 FROM goal LIMIT 1'); $oldGoalTable = true; } catch (PDOException $e) { $oldGoalTable = false; }
    if ($oldGoalTable) {
        $db->exec('REPLACE INTO goals (date, kg) SELECT date, kg FROM goal');
        $db->exec('DROP TABLE goal');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $rows = fn($table) => array_map(fn($r) => ['date' => $r['date'], 'kg' => (float)$r['kg']],
            $db->query("SELECT date, kg FROM $table ORDER BY date")->fetchAll(PDO::FETCH_ASSOC));
        reply(['entries' => $rows('entries'), 'goals' => $rows('goals')]);
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
    $validList = fn($list) => is_array($list) && array_reduce($list, fn($ok, $x) => $ok && $validDate($x['date'] ?? null) && $validKg($x['kg'] ?? null), true);

    switch ($action) {
        case 'save_entry':
            $require($validDate($in['date'] ?? null) && $validKg($in['kg'] ?? null));
            $db->prepare('REPLACE INTO entries (date, kg) VALUES (?, ?)')->execute([$in['date'], $in['kg']]);
            break;
        case 'delete_entry':
            $require($validDate($in['date'] ?? null));
            $db->prepare('DELETE FROM entries WHERE date = ?')->execute([$in['date']]);
            break;
        case 'save_goal': // one goal per date
            $require($validDate($in['date'] ?? null) && $validKg($in['kg'] ?? null));
            $db->prepare('REPLACE INTO goals (date, kg) VALUES (?, ?)')->execute([$in['date'], $in['kg']]);
            break;
        case 'delete_goal':
            $require($validDate($in['date'] ?? null));
            $db->prepare('DELETE FROM goals WHERE date = ?')->execute([$in['date']]);
            break;
        case 'import': // replaces everything with a backup file's contents
            $require($validList($in['entries'] ?? null) && $validList($in['goals'] ?? null));
            $db->beginTransaction();
            foreach (['entries', 'goals'] as $table) {
                $db->exec("DELETE FROM $table");
                $ins = $db->prepare("REPLACE INTO $table (date, kg) VALUES (?, ?)");
                foreach ($in[$table] as $x) $ins->execute([$x['date'], $x['kg']]);
            }
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
