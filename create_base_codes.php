<?php
if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

$insert_sql = glob('../../insert_sql/tenant/codes/*.sql');

// create new file
$file = fopen('codes.sql', 'w');
fclose($file);

// insert initial data
$out = fopen('codes.sql', 'a');
foreach ($insert_sql as $r) {
    $in = fopen($r, 'r');
    $content = fread($in, filesize($r));
    fwrite($out, $content);
    fclose($in);
}
fclose($out)
?>
