<?php
if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

$schema_sql = '../../schema_sql/mimscis_schema.sql';
$insert_sql = glob('../../insert_sql/*.sql');

// insert table schema
$out = fopen('multitenant.sql', 'w');
$in = fopen($schema_sql, 'r');
$content = fread($in, filesize($schema_sql));
fwrite($out, $content);
fclose($out);

// insert initial data
$out = fopen('multitenant.sql', 'a');
foreach ($insert_sql as $r) {
    $in = fopen($r, 'r');
    $content = fread($in, filesize($r));
    fwrite($out, $content);
    fclose($in);
}
fclose($out)
?>
