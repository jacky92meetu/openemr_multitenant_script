<?php
if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

require_once '../../env.php';
$link = mysqli_connect($db['host'], $db['user'], $db['pass']);

if (mysqli_select_db($link , $db['name'])) {
    // Delete all mysql_user associated with tenant if the db exists
    // get list of current tenant_id
    $query = "SELECT name, tenant_id FROM tenants";
    $result = mysqli_query($link, $query) or die(mysqli_error($link) . ', you have to run db_update.php first or drop your database: ' . $db['name']);
    $tenant_id_list = array();
    while ($r = mysqli_fetch_assoc($result)) {
        $tenant_id_list[] = $r['tenant_id'];
        // delete sites conf
        ShellCommands::removeFolder("../sites/{$r['name']}");
    }
    mysqli_free_result($result);

    // get mysql_user associated with tenant_id
    $query = "SELECT DISTINCT(user) as user FROM mysql.user";
    $result = mysqli_query($link, $query) or die(mysqli_error($link));
    $delete_user_query = '';
    while ($r = mysqli_fetch_assoc($result)) {
        if (in_array($r['user'], $tenant_id_list) && $r['user'] != "root") {
            echo "Deleting tenant: {$r['user']}\n";
            $delete_user_query = "DROP USER '{$r['user']}'@'localhost';";
            $delete_user_query.= "DROP USER '{$r['user']}'@'%';";

            mysqli_multi_query($link, $delete_user_query) or die(mysqli_error($link));
            while (mysqli_next_result($link)) {;} // flush multi_queries
        }
    }

    // drop DB if exists
    echo "Drop DB: {$db['name']}\n";
    $query = "DROP DATABASE {$db['name']};";
    mysqli_query($link, $query) or die(mysqli_error($link));
}

echo "Create DB: {$db['name']}\n";
$query = "CREATE DATABASE {$db['name']} CHARACTER SET utf8 COLLATE utf8_general_ci;";
mysqli_query($link, $query) or die(mysqli_error($link));

echo "Load initial data: multitenant.sql\n";
shell_exec("mysql -u{$db['user']} -p{$db['pass']} --host={$db['host']} --default-character-set=utf8 --database={$db['name']} < multitenant.sql");
