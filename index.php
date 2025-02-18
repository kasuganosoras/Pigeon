<?php
error_reporting(E_ALL);
define("ROOT", str_replace("\\", "/", __DIR__));

if(!file_exists(ROOT . "/pigeon/config.php")) {
	echo "<script>window.location.href = 'install.php';</script>";
	exit;
}

include(ROOT . "/pigeon/loader.php");
