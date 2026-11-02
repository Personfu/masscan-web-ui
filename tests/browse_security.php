<?php
define('DB_DRIVER', getenv('TEST_DRIVER') ?: 'sqlite');
require __DIR__.'/../includes/functions.php';
$db = new PDO(getenv('TEST_DSN') ?: 'sqlite::memory:', getenv('TEST_USER') ?: null, getenv('TEST_PASSWORD') ?: null);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$db->exec('CREATE TABLE data (ip BIGINT, port_id INTEGER, protocol VARCHAR(255), state VARCHAR(255), reason VARCHAR(255), service VARCHAR(255), banner TEXT, title TEXT, scanned_ts VARCHAR(30))');
$insert = $db->prepare('INSERT INTO data VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insert->execute(array(167772161, 443, 'tcp', 'open', 'response', 'https', 'welcome portal', 'safe application', '2026-11-02'));
$insert->execute(array(167772162, 22, 'tcp', 'closed', 'response', 'ssh', 'second server', 'private management', '2026-11-01'));
if (DB_DRIVER === 'pgsql') {
    $db->exec('ALTER TABLE data ADD COLUMN searchtext tsvector');
    $db->exec("UPDATE data SET searchtext = to_tsvector('english', title || ' ' || banner)");
} elseif (DB_DRIVER === 'mysql') {
    $db->exec('ALTER TABLE data ADD FULLTEXT INDEX searchtext (title, banner)');
}
$base = array('ip'=>'', 'port'=>0, 'protocol'=>'', 'state'=>'', 'service'=>'', 'banner'=>'', 'text'=>'', 'exact-match'=>0, 'rec_per_page'=>10, 'page'=>1);
$checks = 0;
function check($ok, $message) {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
check(browse($base, false, $db)['pagination']['records'] == 2, 'Baseline row count');
check(count(browse(array_merge($base, array('service'=>'https')), true, $db)) === 1, 'Legitimate service filter');
check(count(browse(array_merge($base, array('ip'=>'10.0.0.1')), true, $db)) === 1, 'IP filter');
foreach (array('protocol', 'state', 'service', 'banner', 'text') as $field) {
    foreach (array("x' OR 1=1 -- ", 'x" OR 1=1 -- ', "x'; DROP TABLE data; --") as $payload) {
        $filter = array_merge($base, array($field=>$payload, 'exact-match'=>1));
        check(count(browse($filter, true, $db)) === 0, $field.' must treat SQL syntax as data');
        check(browse($filter, false, $db)['pagination']['records'] == 0, $field.' count query must also bind input');
    }
}
check(count(browse(array_merge($base, array('banner'=>'portal', 'exact-match'=>1)), true, $db)) === 1, 'Banner substring search');
check(count(browse(array_merge($base, array('text'=>'welcome')), true, $db)) === 1, 'Full text search');
check(browse($base, false, $db)['pagination']['records'] == 2, 'Injection attempts did not alter the table');
echo $checks.' browse security checks passed for '.DB_DRIVER.PHP_EOL;
