<?php
$f1 = 'resources/views/dashboard.blade.php';
if (file_exists($f1)) {
    $c = file_get_contents($f1);
    $c = str_replace('\${', '${', $c);
    $c = str_replace('\`', '`', $c);
    file_put_contents($f1, $c);
}
echo "Fixed dashboard";
