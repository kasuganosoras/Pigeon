<?php
error_reporting(E_ALL);
define("ROOT", str_replace("\\", "/", __DIR__));
if(!file_exists(ROOT . "/pigeon/config.php")) {
	echo "请先在命令行下执行 install.php 进行安装。";
	exit;
}
include(ROOT . "/pigeon/loader.php");
