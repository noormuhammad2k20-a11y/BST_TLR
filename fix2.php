<?php
$c = file_get_contents('resources/views/customers/index.blade.php');
$c = str_replace('\`', '`', $c);
file_put_contents('resources/views/customers/index.blade.php', $c);
echo "Fixed backticks";
