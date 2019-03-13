<?php
if(php_sapi_name() !== "cli") {
	exit("请通过命令行执行 install.php");
}
if(!file_exists("pigeon/config-template.php")) {
	exit("配置文件模板不存在，请检查程序是否完整。\n");
}
echo "请输入数据库地址 (localhost)> ";
$db_host = trim(fgets(STDIN));
$db_host = empty($db_host) ? "localhost" : $db_host;
echo "请输入数据库端口 (3306)> ";
$db_port = trim(fgets(STDIN));
$db_port = empty($db_port) ? "3306" : $db_port;
echo "请输入数据库账号 (root)> ";
$db_user = trim(fgets(STDIN));
$db_user = empty($db_user) ? "root" : $db_user;
echo "请输入数据库密码 (root)> ";
$db_pass = trim(fgets(STDIN));
$db_pass = empty($db_pass) ? "root" : $db_pass;
echo "请输入数据库名称 (pigeon)> ";
$db_name = trim(fgets(STDIN));
$db_name = empty($db_name) ? "pigeon" : $db_name;
echo "是否启用注册功能 (y/n)> ";
$enable_registe = trim(fgets(STDIN));
$enable_registe = empty($enable_registe) ? "y" : strtolower($enable_registe);
if($enable_registe == "y") {
	$enable_registe = 'true';
	echo "是否启用注册邮箱验证 (y/n)> ";
	$enable_smtp = trim(fgets(STDIN));
	$enable_smtp = empty($enable_smtp) ? "y" : strtolower($enable_smtp);
	if($enable_smtp == "y") {
		$enable_smtp = 'true';
		echo "请输入 SMTP 地址 (localhost)> ";
		$smtp_host = trim(fgets(STDIN));
		$smtp_host = empty($smtp_host) ? "localhost" : $smtp_host;
		echo "请输入 SMTP 端口 (25)> ";
		$smtp_port = trim(fgets(STDIN));
		$smtp_port = empty($smtp_port) ? "25" : $smtp_port;
		echo "请输入 SMTP 账号 (noreply@example.com)> ";
		$smtp_user = trim(fgets(STDIN));
		$smtp_user = empty($smtp_user) ? "root" : $smtp_user;
		echo "请输入 SMTP 密码 (123456789)> ";
		$smtp_pass = trim(fgets(STDIN));
		$smtp_pass = empty($smtp_pass) ? "123456789" : $smtp_pass;
		echo "请输入 SMTP 邮箱 (noreply@example.com)> ";
		$smtp_mail = trim(fgets(STDIN));
		$smtp_mail = empty($smtp_mail) ? "noreply@example.com" : $smtp_mail;
	} else {
		$enable_smtp = 'false';
		$smtp_host = "";
		$smtp_port = 25;
		$smtp_user = "";
		$smtp_pass = "";
		$smtp_name = "";
	}
} else {
	$enable_registe = 'false';
}
echo "请输入站点名称 (Pigeon)> ";
$sitename = trim(fgets(STDIN));
$sitename = empty($sitename) ? "Pigeon" : $sitename;
echo "请输入站点介绍 (咕咕咕，咕咕咕咕咕？)> ";
$description = trim(fgets(STDIN));
$description = empty($description) ? "咕咕咕，咕咕咕咕咕？" : $description;
echo "是否启用谷歌验证码 (y/n)> ";
$enable_recaptcha = trim(fgets(STDIN));
$enable_recaptcha = empty($enable_recaptcha) ? "y" : strtolower($enable_recaptcha);
if($enable_recaptcha == "y") {
	$enable_recaptcha = 'true';
	echo "请输入前端验证 Key> ";
	$recaptcha_key = trim(fgets(STDIN));
	$recaptcha_key = empty($recaptcha_key) ? "" : $recaptcha_key;
	echo "请输入后端验证 Key> ";
	$recaptcha_key_post = trim(fgets(STDIN));
	$recaptcha_key_post = empty($recaptcha_key_post) ? "" : $recaptcha_key_post;
} else {
	$enable_recaptcha = 'false';
	$recaptcha_key = '';
	$recaptcha_key_post = '';
}
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
if(!$conn) {
	exit("无法连接到数据库主机！错误：" . mysqli_error($conn));
} else {
	mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0;") or die("安装出错，请反馈给作者。\n错误代码：" . mysqli_error($conn) . "\n");
	mysqli_query($conn, "DROP TABLE IF EXISTS `posts`;") or die("安装出错，请反馈给作者。\n错误代码：" . mysqli_error($conn) . "\n");
	mysqli_query($conn, "CREATE TABLE `posts` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `time` bigint(32) NOT NULL,
  `public` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;") or die("安装出错，请反馈给作者。\n错误代码：" . mysqli_error($conn) . "\n");
	mysqli_query($conn, "DROP TABLE IF EXISTS `users`;") or die("安装出错，请反馈给作者。\n错误代码：" . mysqli_error($conn) . "\n");
	mysqli_query($conn, "CREATE TABLE `users` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registe_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registe_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latest_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latest_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;") or die("安装出错，请反馈给作者。\n错误代码：" . mysqli_error($conn) . "\n");
	// 字符串转义
	$sitename = str_replace("'", "\\'", $sitename);
	// 写入配置文件
	$template = file_get_contents("pigeon/config-template.php");
	$template = str_replace("{DB_HOST}", $db_host, $template);
	$template = str_replace("{DB_PORT}", $db_port, $template);
	$template = str_replace("{DB_USER}", $db_user, $template);
	$template = str_replace("{DB_PASS}", $db_pass, $template);
	$template = str_replace("{DB_NAME}", $db_name, $template);
	$template = str_replace("{ENABLE_REGISTE}", $enable_registe, $template);
	$template = str_replace("{ENABLE_SMTP}", $enable_smtp, $template);
	$template = str_replace("{SMTP_HOST}", $smtp_host, $template);
	$template = str_replace("{SMTP_PORT}", $smtp_port, $template);
	$template = str_replace("{SMTP_USER}", $smtp_user, $template);
	$template = str_replace("{SMTP_PASS}", $smtp_pass, $template);
	$template = str_replace("{SMTP_NAME}", $smtp_mail, $template);
	$template = str_replace("{SITENAME}", $sitename, $template);
	$template = str_replace("{DESCRIPTION}", $description, $template);
	$template = str_replace("{RECAPTCHA_KEY}", $recaptcha_key, $template);
	$template = str_replace("{RECAPTCHA_KEY_POST}", $recaptcha_key_post, $template);
	file_put_contents("pigeon/config.php", $template);
	echo "安装成功，接下来请配置管理员账号。\n";
	echo "请输入管理员账号 (Admin)> ";
	$admin_user = trim(fgets(STDIN));
	$admin_user = empty($admin_user) ? "Admin" : $admin_user;
	$randpass = substr(md5(sha1(time() . date("Y-m-d H:i:s") . mt_rand(0, 999999999))), 0, 16);
	echo "请输入管理员密码 ({$randpass})> ";
	$admin_pass = trim(fgets(STDIN));
	$admin_pass = empty($admin_pass) ? $randpass : $admin_pass;
	echo "请输入管理员邮箱 (admin@example.com)> ";
	$admin_mail = trim(fgets(STDIN));
	$admin_mail = empty($admin_mail) ? "admin@example.com" : $admin_mail;
	$username = mysqli_real_escape_string($conn, $admin_user);
	$password = password_hash($admin_pass, PASSWORD_BCRYPT);
	$email = mysqli_real_escape_string($conn, $admin_mail);
	$registe_ip = mysqli_real_escape_string($conn, "127.0.0.1");
	$token = md5(sha1($username . $password . $email . mt_rand(0, 99999999) . time()));
	mysqli_query($conn, "INSERT INTO `users` (
		`id`,
		`username`,
		`password`,
		`email`,
		`permission`,
		`registe_ip`,
		`registe_time`,
		`latest_ip`,
		`latest_time`,
		`status`,
		`token`) VALUES (
		NULL,
		'{$username}',
		'{$password}',
		'{$email}',
		'root',
		'{$registe_ip}',
		'" . time() . "',
		NULL,
		NULL,
		'200',
		'{$token}'
	)") or die("添加用户出错，请反馈给作者。\n错误代码：" . mysqli_error($conn) . "\n");
	echo "+==================================================================+\n";
	echo "恭喜您，Pigeon 已经安装完成，您可以访问您的网站并开始使用了。\n";
	echo "如果在使用中遇到任何问题，欢迎通过 Issues 提交或者联系 QQ 204034。\n";
	echo "最后，再次感谢您使用 Pigeon。\n";
}