<?php
if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

require_once 'db_conn.php';
mysqli_set_charset($link, 'utf8');

$longopts = array(
    'flush::'
);
$opt = getopt('d:t:', $longopts);
// d for data
// t for tenant_id

if (empty($opt['d'])) {
    die("Error: data must be specified\nex: -d'drug_form'\n");
}
if (empty($opt['t'])) {
    die("Error: tenant_id must be specified\nex: -t'mims'\n");
}

$title = $opt['d'];
$file_path = 'data/' . $title . '.php';
$tenant_id = $opt['t'];

$query = "SELECT * FROM tenants WHERE tenant_id = '{$tenant_id}'";
$result = mysqli_query($link, $query) or die(mysqli_error($link));
$row = mysqli_fetch_row($result);
if (empty($row)) {
    die("Tenant ID: '{$tenant_id}' is not exists \n");
}

if (file_exists($file_path)) {
    if (! empty($opt['flush'])) {
        // delete drug data
        $query = "DELETE FROM tbase_list_options WHERE list_id = '{$title}' AND tenant_id = '{$tenant_id}'";
        mysqli_query($link, $query) or die(mysqli_error($link));
    }

    // insert/reload new data
    include($file_path); // get file in folder data
    mysqli_query($link, $reload_query) or die(mysqli_error($link)); // execute reload query from file_path
    echo "Reload: '{$title}' data in 'tbase_list_options'\n";
    // Notes:
    // the data inserted will set tenant_id as the root user because of the trigger
    // in that case we need to update the tenant_id
    $query = "UPDATE tbase_list_options SET tenant_id = '{$tenant_id}'";
    $query.= "WHERE list_id = '{$title}' AND tenant_id = '{$db['user']}'";
    mysqli_query($link, $query) or die(mysqli_error($link));
    echo "Update tenant_id: '{$tenant_id}'\n";
} else {
    die("File not found, missing: '{$file_path}' \n");
}
