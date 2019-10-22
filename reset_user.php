<?php
if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

require_once 'db_conn.php';

$opt = getopt('u:');

// drop user
$query = "DROP USER '{$opt['u']}'@'localhost';";
$query.= "DROP USER '{$opt['u']}'@'%';";
mysqli_multi_query($link, $query) or die(mysqli_error($link));
