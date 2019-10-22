<?php
require_once '../../env.php';

// connect
$link = mysqli_connect($db['host'], $db['user'], $db['pass'], $db['name']);
// check connection
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}
