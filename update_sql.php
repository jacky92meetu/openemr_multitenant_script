<?php
if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

require_once 'db_conn.php';
require_once '../../update_sql/update_version.php';
$update_sql = glob('../../update_sql/*.sql');

$opt = getopt('v:');
$update_data = array();
if (! empty($opt)) {
    if (empty($update_version[$opt['v']])) {
        die("Update version is not registered!\n");
    }
    $update_data = $update_version[$opt['v']];
    echo "Update version: {$opt['v']}\n";
    echo  $update_data['msg'] . "\n";
}

// create new file
$file = fopen('updates.sql', 'w');
fclose($file);

$out = fopen('updates.sql', 'a');

// global exclude
$exclude = $update_version['*']['exclude'];

foreach ($update_sql as $r) {
    // if no version specified
    if (empty($update_data)) {
        if (! in_array(basename($r), $exclude)) {
            $in = fopen($r, 'r');
            $content = fread($in, filesize($r));
            fwrite($out, $content);
            fclose($in);
        }
    } else {
        if (in_array(basename($r), $update_data['sql'])) {
            $in = fopen($r, 'r');
            $content = fread($in, filesize($r));
            fwrite($out, $content);
            fclose($in);
        }
    }
}

if (! empty($update_data['include'])) {
    foreach ($update_data['include'] as $i) {
        $file = '../../' . $i;
        if (file_exists($file)) {
            $in = fopen($file, 'r');
            $content = fread($in, filesize($file));
            fwrite($out, $content);
            fclose($in);
        }
    }
}

fclose($out);

shell_exec("$env_mysql_path --user={$db['user']} --host={$db['host']} --password={$db['pass']} --database={$db['name']} < updates.sql");
// unlink('updates.sql');
?>
