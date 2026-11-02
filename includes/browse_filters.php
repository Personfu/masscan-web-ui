<?php
/** Build SQL structure independently of untrusted filter values. */
function buildBrowseFilters($filter, $driver)
{
    $where = ' FROM data WHERE 1 = 1';
    $params = array();
    if (!empty($filter['ip'])) {
        list($start, $end) = getStartAndEndIps($filter['ip']);
        $where .= ' AND ip >= :start_ip AND ip <= :end_ip';
        $params[':start_ip'] = $start;
        $params[':end_ip'] = $end;
    }
    if (!empty($filter['port']) && (int) $filter['port'] > 0 && (int) $filter['port'] <= 65535) {
        $where .= ' AND port_id = :port';
        $params[':port'] = (int) $filter['port'];
    }
    foreach (array('protocol', 'state', 'service') as $column) {
        if (isset($filter[$column]) && $filter[$column] !== '') {
            $where .= ' AND '.$column.' = :'.$column;
            $params[':'.$column] = $filter[$column];
        }
    }
    foreach (array('banner', 'text') as $field) {
        if (!isset($filter[$field]) || $filter[$field] === '') continue;
        if ($field === 'banner' && !empty($filter['exact-match'])) {
            $like = $driver === 'mysql' ? ' LIKE BINARY ' : ' LIKE ';
            $where .= ' AND (banner'.$like.':banner_value OR title'.$like.':title_value)';
            $params[':banner_value'] = '%'.$filter[$field].'%';
            $params[':title_value'] = '%'.$filter[$field].'%';
        } elseif ($driver === 'pgsql') {
            $where .= " AND searchtext @@ plainto_tsquery('english', :".$field.'_search)';
            $params[':'.$field.'_search'] = $filter[$field];
        } elseif ($driver === 'mysql') {
            $where .= ' AND (MATCH(title, banner) AGAINST (:'.$field.'_search IN NATURAL LANGUAGE MODE)';
            $params[':'.$field.'_search'] = $filter[$field];
            if ($field === 'text') {
                $where .= ' OR service = :text_service OR protocol = :text_protocol';
                $params[':text_service'] = $filter[$field];
                $params[':text_protocol'] = $filter[$field];
                if (ctype_digit((string) $filter[$field])) {
                    $where .= ' OR port_id = :text_port';
                    $params[':text_port'] = (int) $filter[$field];
                }
            }
            $where .= ')';
        } else {
            // SQLite is used by the isolated regression test fixture.
            $where .= ' AND (banner LIKE :'.$field.'_banner OR title LIKE :'.$field.'_title)';
            $params[':'.$field.'_banner'] = '%'.$filter[$field].'%';
            $params[':'.$field.'_title'] = '%'.$filter[$field].'%';
        }
    }
    return array($where, $params);
}
