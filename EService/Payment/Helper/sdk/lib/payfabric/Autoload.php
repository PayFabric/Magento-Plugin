<?php
// This is an autoloader for the PayFabric! SDK.
// If you are not using a global autoloader do the following
// before any other PayFabric files:
// 
//     require_once "<path>/payfabric/Autoload.php"

function payFabric_Autoload($className) {
    if ($className === "KLogger") {
        $fileName = $className.".php";
    }
    elseif(strtolower(substr($className, 0, 9)) == 'payfabric') {
        $fileName = substr($className, 10).".php";
    }
    !empty($fileName) && require $fileName;
}

spl_autoload_register('payFabric_Autoload');