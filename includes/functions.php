<?php
require_once __DIR__.'/browse_filters.php';
function pre_var_dump($var)
{
    echo "<pre>";
    var_dump($var);
    echo "</pre>";
}
function getPdo()
{
    try {
        $db = new PDO(DB_DRIVER.":host=".DB_HOST.";dbname=".DB_DATABASE, DB_USERNAME, DB_PASSWORD);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $q = "SELECT 1 FROM data";
            $stmt = $db->query($q);
            $tmp = $stmt->fetch(PDO::FETCH_ASSOC);
            return $db;
        }
        catch (PDOException $pdoe) {
            include DOC_ROOT.'includes/install.php';
            exit();
        }
    }
    catch (PDOException $pdoe) {
        if (substr(php_sapi_name(), 0, 3) == 'cli'):
            echo $pdoe->getMessage();
            die;
        endif;
        if (strpos($pdoe->getMessage(), 'Access denied for user') || strpos($pdoe->getMessage(), ' getaddrinfo failed') || strpos($pdoe->getMessage(), 'not find driver') || strpos($pdoe->getMessage(), 'does not exist')):
            include DOC_ROOT.'includes/setup.php';
        else:
            include DOC_ROOT.'includes/error.php';
        endif;
        exit();
    }
}
function browse($filter, $export = false, $connection = null)
{
    $db = $connection === null ? getPdo() : $connection;
    $records_per_page = isset($filter['rec_per_page']) ? (int) $filter['rec_per_page'] : 10;
    if (!in_array($records_per_page, array(10, 20, 40, 50, 100), true)) $records_per_page = 10;
    $page = isset($filter['page']) ? max(1, (int) $filter['page']) : 1;
    $from = ($page - 1) * $records_per_page;
    $q1 = "SELECT ip AS ipaddress, port_id, protocol, state, reason, service, banner, title";
    $q2 = "SELECT COUNT(*) as total_records";
    list($q, $params) = buildBrowseFilters($filter, DB_DRIVER);
    $q3 = !empty($filter['ip']) ? " ORDER BY ip ASC" : " ORDER BY scanned_ts DESC";
    $q4 = $export ? "" : " LIMIT $records_per_page OFFSET $from";
    try {
        $stmt = $db->prepare($q1 . $q . $q3 . $q4);
        $stmt->execute($params);
    }
    catch(PDOException $ex) {
        error_log("Masscan browse query failed");
        http_response_code(500);
        echo "Unable to load results.";
        die;
    }
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($export) {
        return $data;
    }
    $tmp2 = $db->prepare($q2 . $q);
    $tmp2->execute($params);
    $total = $tmp2->fetch(PDO::FETCH_ASSOC);
    $to = $from + $records_per_page < $total['total_records'] ? $from + $records_per_page : $total['total_records'];
    $pages = $total ['total_records'] > 1 ? ceil($total ['total_records'] / $records_per_page) : 0;
    return array(
        'data' => $data,
        'pagination' => array(
            'page' => $page,
            'pages' => $pages,
            'records' => $total ['total_records'],
            'from' => ++$from,
            'to' => $to)
    );
}

function getStartAndEndIps($ip)
{
    $start_ip   = '';
    $end_ip     = '';
    $ip         = trim($ip, '.');
    $p          = explode('.', trim($ip));
    for ($i = 0; $i < 4; $i++):
        if ($i > 0):
            $start_ip .= '.';
            $end_ip .= '.';
        endif;
        if (isset($p[$i])):
            $start_ip .= $p[$i];
            $end_ip .= $p[$i];
        else:
            $start_ip .= "0";
            $end_ip .= "255";
        endif;
    endfor;
    return array(ip2long($start_ip), ip2long($end_ip));
}