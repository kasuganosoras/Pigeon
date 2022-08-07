<?php
if (php_sapi_name() !== "cli") {
	exit("请通过命令行执行 install.php");
}
if (!file_exists("pigeon/config-template.php")) {
	exit("配置文件模板不存在，请检查程序是否完整。\n");
}

function PrintLn($text)
{
	echo $text . "\n";
}

while (!$db_host) {
	echo "请输入数据库地址 (localhost)> ";
	$input = trim(fgets(STDIN));
	$input = empty($input) ? "localhost" : $input;
	// if match hostname
	if (!preg_match("/^[a-zA-Z0-9_\-\.]+$/", $input)) {
		PrintLn("数据库地址错误，示例：localhost、127.0.0.1");
	} else {
		$db_host = $input;
	}
}

while (!$db_port) {
	echo "请输入数据库端口 (3306)> ";
	$input = trim(fgets(STDIN));
	$input = empty($input) ? "3306" : $input;
	if (!preg_match("/^[0-9]{1,5}$/", $input)) {
		PrintLn("数据库端口错误，示例：3306");
	} else {
		$db_port = $input;
	}
}

while (!$db_user) {
	echo "请输入数据库账号 (root)> ";
	$input = trim(fgets(STDIN));
	$input = empty($input) ? "root" : $input;
	// if match mysql username
	if (!preg_match("/^[a-zA-Z0-9_\-\.]+$/", $input)) {
		PrintLn("数据库账号错误，示例：root、blog");
	} else {
		$db_user = $input;
	}
}

while (!$db_pass) {
	echo "请输入数据库密码 (root)> ";
	$input = trim(fgets(STDIN));
	$input = empty($input) ? "root" : $input;
	$db_pass = $input;
}

while (!$db_name) {
	echo "请输入数据库名称 (pigeon)> ";
	$input = trim(fgets(STDIN));
	$input = empty($input) ? "pigeon" : $input;
	if (!preg_match("/^[a-zA-Z0-9_\-\.]+$/", $input)) {
		PrintLn("数据库名称错误，示例：pigeon、blog");
	} else {
		$db_name = $input;
	}
}

echo "是否启用注册功能 (y/n)> ";
$enable_registe = trim(fgets(STDIN));
$enable_registe = empty($enable_registe) ? "y" : strtolower($enable_registe);

$enable_smtp = 'false';
$smtp_host = "";
$smtp_port = 25;
$smtp_user = "";
$smtp_pass = "";
$smtp_name = "";
$smtp_mail = "";

if ($enable_registe == "y") {
	$enable_registe = 'true';
	echo "是否启用注册邮箱验证 (y/n)> ";
	$enable_smtp = trim(fgets(STDIN));
	$enable_smtp = empty($enable_smtp) ? "y" : strtolower($enable_smtp);
	if ($enable_smtp == "y") {
		$enable_smtp = 'true';
		while (empty($smtp_host)) {
			echo "请输入 SMTP 地址 (localhost)> ";
			$input = trim(fgets(STDIN));
			$input = empty($input) ? "localhost" : $input;
			// if match hostname
			if (!preg_match("/^[a-zA-Z0-9_\-\.]+$/", $smtp_host)) {
				PrintLn("SMTP 地址错误，示例：smtp.exmail.com");
			} else {
				$smtp_host = $input;
			}
		}
		while (empty($smtp_port)) {
			echo "请输入 SMTP 端口 (25)> ";
			$input = trim(fgets(STDIN));
			$input = empty($input) ? "25" : $input;
			if (!preg_match("/^[0-9]{1,5}$/", $input)) {
				PrintLn("SMTP 端口错误，示例：25");
			} else {
				$smtp_port = $input;
			}
		}
		while (empty($smtp_user)) {
			echo "请输入 SMTP 账号 (root)> ";
			$input = trim(fgets(STDIN));
			$input = empty($input) ? "root" : $input;
			// if match mysql username
			if (!preg_match("/^[a-zA-Z0-9_\-\.\+\@]+$/", $input)) {
				PrintLn("SMTP 账号错误，示例：email、noreply@example.com");
			} else {
				$smtp_user = $input;
			}
		}
		while (empty($smtp_pass)) {
			echo "请输入 SMTP 密码 (123456)> ";
			$input = trim(fgets(STDIN));
			$input = empty($input) ? "123456" : $input;
			$smtp_pass = $input;
		}
		while (empty($smtp_mail)) {
			echo "请输入 SMTP 发件人邮箱 (noreply@example.com)> ";
			$input = trim(fgets(STDIN));
			$input = empty($input) ? "noreply@example.com" : $input;
			// if match email
			if (!preg_match("/^[a-zA-Z0-9]+([-_.][a-zA-Z0-9]+)*@([a-zA-Z0-9]+[-.])+([a-z]{2,5})$/ims", $input)) {
				PrintLn("SMTP 发件人邮箱错误，示例：noreply@example.com");
			} else {
				$smtp_mail = $input;
			}
		}
	} else {
		$enable_smtp = 'false';
	}
} else {
	$enable_registe = 'false';
	$enable_smtp    = 'false';
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
if ($enable_recaptcha == "y") {
	$enable_recaptcha = 'true';
	echo "请输入前端验证 Key> ";
	$recaptcha_key = trim(fgets(STDIN));
	$recaptcha_key = empty($recaptcha_key) ? "" : $recaptcha_key;
	echo "请输入后端验证 Key> ";
	$recaptcha_key_post = trim(fgets(STDIN));
	$recaptcha_key_post = empty($recaptcha_key_post) ? "" : $recaptcha_key_post;
	if (empty($recaptcha_key) || empty($recaptcha_key_post)) {
		$enable_recaptcha = 'false';
	}
} else {
	$enable_recaptcha   = 'false';
	$recaptcha_key 	    = '';
	$recaptcha_key_post = '';
}
echo "是否启用伪静态 (y/n)> ";
$enable_rewrite = trim(fgets(STDIN));
$enable_rewrite = empty($enable_rewrite) ? "y" : strtolower($enable_rewrite);
if ($enable_rewrite == "y") {
	$enable_rewrite = 'true';
} else {
	$enable_rewrite = 'false';
}

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
if (mysqli_connect_errno()) {
	exit("无法连接到数据库主机！错误：" . mysqli_error($conn));
} else {
	mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0;") or die("安装出错，请反馈给作者。\n错误：" . mysqli_error($conn) . "\n");
	mysqli_query($conn, "DROP TABLE IF EXISTS `posts`;") or die("安装出错，请反馈给作者。\n错误：" . mysqli_error($conn) . "\n");
	mysqli_query($conn, "CREATE TABLE `posts` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `time` bigint(32) NOT NULL,
  `public` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;") or die("安装出错，请反馈给作者。\n错误：" . mysqli_error($conn) . "\n");
	mysqli_query($conn, "DROP TABLE IF EXISTS `users`;") or die("安装出错，请反馈给作者。\n错误：" . mysqli_error($conn) . "\n");
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;") or die("安装出错，请反馈给作者。\n错误：" . mysqli_error($conn) . "\n");

	// 字符串转义
	$sitename = str_replace("'", "\\'", $sitename);
	// 读取配置文件模板并替换
	$template = file_get_contents("pigeon/config-template.php");
	$template = str_replace("{DB_HOST}", $db_host, $template);
	$template = str_replace("{DB_PORT}", $db_port, $template);
	$template = str_replace("{DB_USER}", $db_user, $template);
	$template = str_replace("{DB_PASS}", $db_pass, $template);
	$template = str_replace("{DB_NAME}", $db_name, $template);
	$template = str_replace("{ENABLE_REGISTE}", $enable_registe, $template);
	$template = str_replace("{ENABLE_SMTP}", $enable_smtp, $template);
	$template = str_replace("{ENABLE_REWRITE}", $enable_rewrite, $template);
	$template = str_replace("{SMTP_HOST}", $smtp_host, $template);
	$template = str_replace("{SMTP_PORT}", $smtp_port, $template);
	$template = str_replace("{SMTP_USER}", $smtp_user, $template);
	$template = str_replace("{SMTP_PASS}", $smtp_pass, $template);
	$template = str_replace("{SMTP_NAME}", $smtp_mail, $template);
	$template = str_replace("{SITENAME}", $sitename, $template);
	$template = str_replace("{DESCRIPTION}", $description, $template);
	$template = str_replace("{RECAPTCHA_KEY}", $recaptcha_key, $template);
	$template = str_replace("{RECAPTCHA_KEY_POST}", $recaptcha_key_post, $template);
	// 写入配置文件
	file_put_contents("pigeon/config.php", $template);
	// 配置管理员账户
	echo "安装成功，接下来请配置管理员账号。\n";
	while (!$admin_user) {
		echo "请输入管理员账号 (Admin)> ";
		$input = trim(fgets(STDIN));
		$input = empty($input) ? "Admin" : $input;
		if (preg_match("/^[a-zA-Z0-9\_\-]{1,20}$/", $input)) {
			$admin_user = $input;
		} else {
			PrintLn("账号只能由字母、数字、下划线和减号组成，且长度不能超过 20 个字符。");
		}
	}
	$randpass = substr(md5(sha1(time() . date("Y-m-d H:i:s") . mt_rand(0, 999999999))), 0, 16);
	while (!$admin_pass) {
		echo "请输入管理员密码 ({$randpass})> ";
		$admin_pass = trim(fgets(STDIN));
		$admin_pass = empty($admin_pass) ? $randpass : $admin_pass;
	}
	while (!$admin_email) {
		echo "请输入管理员邮箱 (admin@example.com)> ";
		$input = trim(fgets(STDIN));
		$input = empty($input) ? "admin@example.com" : $input;
		if (preg_match("/^[a-zA-Z0-9]+([-_.][a-zA-Z0-9]+)*@([a-zA-Z0-9]+[-.])+([a-z]{2,5})$/ims", $input)) {
			$admin_email = $input;
		} else {
			PrintLn("邮箱格式不正确。");
		}
	}
	$username   = mysqli_real_escape_string($conn, $admin_user);
	$password   = password_hash($admin_pass, PASSWORD_BCRYPT);
	$email      = mysqli_real_escape_string($conn, $admin_mail);
	$registe_ip = mysqli_real_escape_string($conn, "127.0.0.1");
	$token      = md5(sha1($username . $password . $email . mt_rand(0, 99999999) . time()));
	$reg_time   = time();
	mysqli_query($conn, "INSERT INTO `users` (
			`id`, `username`, `password`, `email`, `permission`, `registe_ip`, `registe_time`, `latest_ip`, `latest_time`, `status`, `token`
		) VALUES (
			NULL, '{$username}', '{$password}', '{$email}', 'root', '{$registe_ip}', '{$reg_time}', NULL, NULL, '200', '{$token}'
		)") or die("添加用户出错，请反馈给作者。\n错误代码：" . mysqli_error($conn) . "\n");
	PrintLn("+==================================================================+");
	PrintLn("恭喜您，Pigeon 已经安装完成，您可以访问您的网站并开始使用了。");
	PrintLn("如果在使用中遇到任何问题，欢迎通过 Issues 提交或者联系 QQ 204034。");
	PrintLn("最后，再次感谢您使用 Pigeon。");
}
