<?php
SESSION_START();
if (!isset($_SESSION['install'])) {
	$_SESSION['install'] = [];
}
if (!isset($_SESSION['install']['step'])) {
	$_SESSION['install']['step'] = 0;
}
if (!isset($_SESSION['install']['data'])) {
	$_SESSION['install']['data'] = [];
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$_SESSION['install']['data'] = array_merge($_SESSION['install']['data'], $_POST);
}

if (!file_exists('pigeon/config-template.php')) {
	exit('配置文件模板不存在，请检查程序是否完整。');
}

if (file_exists('pigeon/config.php')) {
	exit('Pigeon 已经安装，如需重新安装请删除 pigeon/config.php 文件。');
}

if (!is_writable('pigeon/')) {
	exit('配置文件不可写，请检查程序是否有写入权限。');
}

function JsonPrint($success, $message)
{
	header('Content-Type: application/json');
	echo json_encode(['success' => $success, 'message' => $message]);
	exit();
}

function EscapeConfig($text)
{
	return str_replace("'", "\'", $text);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	Header('Content-Type: application/json');
	if (isset($_POST['action']) && is_string($_POST['action'])) {
		switch($_POST['action']) {
			case 'checkDatabase':
				$checkList = ['db_host', 'db_port', 'db_user', 'db_pass', 'db_name'];
				foreach ($checkList as $key) {
					if (!isset($_POST[$key]) || empty($_POST[$key])) {
						JsonPrint(false, '请填写所有字段。');
					}
				}
				try {
					$conn = new PDO("mysql:host={$_POST['db_host']};port={$_POST['db_port']};dbname={$_POST['db_name']}", $_POST['db_user'], $_POST['db_pass']);
					$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					$_SESSION['install']['step'] = 2;
					$_SESSION['install']['data'] = array_merge($_SESSION['install']['data'], ['db_host' => $_POST['db_host'], 'db_port' => $_POST['db_port'], 'db_user' => $_POST['db_user'], 'db_pass' => $_POST['db_pass'], 'db_name' => $_POST['db_name']]);
					JsonPrint(true, '数据库连接成功。');
				} catch (PDOException $e) {
					$message = $e->getMessage();
					if (strpos($message, 'Unknown database') !== false) {
						JsonPrint(false, '数据库不存在，请先创建数据库。');
					}
					if (strpos($message, 'Access denied') !== false) {
						JsonPrint(false, '数据库账号或密码错误。');
					}
					if (strpos($message, 'Connection refused') !== false) {
						JsonPrint(false, '数据库连接失败，请检查数据库地址和端口。');
					}
					if (strpos($message, 'No such file or directory') !== false) {
						JsonPrint(false, '数据库连接失败，请检查数据库地址和端口。');
					}
					JsonPrint(false, '数据库错误: ' . $e->getMessage());
				}
				break;
			case 'installDatabase':
				$checkList = ['admin_user', 'admin_pass', 'admin_email', 'sitename', 'description', 'enable_rewrite'];
				foreach ($checkList as $key) {
					if (!isset($_POST[$key]) || empty($_POST[$key])) {
						JsonPrint(false, '请填写所有字段。');
					}
				}
				if (strlen($_POST['sitename']) > 32) {
					JsonPrint(false, '站点名称长度不能大于 32 个字符。');
				}
				if (strlen($_POST['description']) > 255) {
					JsonPrint(false, '站点介绍长度不能大于 255 个字符。');
				}
				if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $_POST['admin_user'])) {
					JsonPrint(false, '管理员账号格式错误，只能包含字母、数字、下划线和短横线，长度 1-32。');
				}
				if (!filter_var($_POST['admin_email'], FILTER_VALIDATE_EMAIL)) {
					JsonPrint(false, '管理员邮箱格式错误。');
				}
				if (strlen($_POST['admin_pass']) < 6) {
					JsonPrint(false, '管理员密码长度不能小于 6 个字符。');
				}
				try {
					$db_host = $_SESSION['install']['data']['db_host'];
					$db_port = $_SESSION['install']['data']['db_port'];
					$db_user = $_SESSION['install']['data']['db_user'];
					$db_pass = $_SESSION['install']['data']['db_pass'];
					$db_name = $_SESSION['install']['data']['db_name'];
					$conn = new PDO(sprintf("mysql:host=%s;port=%s;dbname=%s", $db_host, $db_port, $db_name), $db_user, $db_pass);
					$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					// 创建数据表
					$conn->exec("SET FOREIGN_KEY_CHECKS=0;");
					$conn->exec("DROP TABLE IF EXISTS `posts`;");
					$conn->exec("CREATE TABLE `posts` (
						`id` int(10) NOT NULL AUTO_INCREMENT,
						`content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
						`author` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
						`time` bigint(32) NOT NULL,
						`public` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
						PRIMARY KEY (`id`)
					) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
					$conn->exec("DROP TABLE IF EXISTS `users`;");
					$conn->exec("CREATE TABLE `users` (
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
					) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
					// 创建管理员账号
					$admin_user    = $_POST['admin_user'];
					$admin_pass    = $_POST['admin_pass'];
					$admin_email   = $_POST['admin_email'];
					$password_hash = password_hash($admin_pass, PASSWORD_BCRYPT);
					$timestamp     = time();
					$registe_ip    = "127.0.0.1";
					$token         = md5(sha1($admin_user . $admin_pass . $admin_email . mt_rand(0, 99999999) . time()));
					$stmt          = $conn->prepare("INSERT INTO `users` (
						`id`, `username`, `password`, `email`, `permission`, `registe_ip`, `registe_time`, `latest_ip`, `latest_time`, `status`, `token`
					) VALUES (
						NULL, :username, :password, :email, 'root', :registe_ip, :reg_time, NULL, NULL, '200', :token
					)");
					// 绑定参数并执行
					$stmt->bindParam(':username', $admin_user);
					$stmt->bindParam(':password', $password_hash);
					$stmt->bindParam(':email', $admin_email);
					$stmt->bindParam(':registe_ip', $registe_ip);
					$stmt->bindParam(':reg_time', $timestamp);
					$stmt->bindParam(':token', $token);
					$stmt->execute();
					$_SESSION['install']['step'] = 3;
					$_SESSION['install']['data'] = array_merge($_SESSION['install']['data'], $_POST);
					JsonPrint(true, '账户创建成功。');
				} catch (PDOException $e) {
					JsonPrint(false, '数据库错误: ' . $e->getMessage());
				}
				break;
			case 'checkRegister':
				$checkList = ['enable_register', 'enable_smtp'];
				foreach ($checkList as $key) {
					if (!isset($_POST[$key]) || empty($_POST[$key])) {
						JsonPrint(false, '请填写所有字段。');
					}
				}
				if ($_POST['enable_register'] == 'y' && $_POST['enable_smtp'] == 'y') {
					$checkList = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_mail', 'smtp_ssl'];
					foreach ($checkList as $key) {
						if (!isset($_POST[$key]) || empty($_POST[$key])) {
							JsonPrint(false, '请填写所有字段。');
						}
					}
				}
				$_SESSION['install']['step'] = 4;
				$_SESSION['install']['data'] = array_merge($_SESSION['install']['data'], $_POST);
				JsonPrint(true, '注册配置成功。');
				break;
			case 'checkRecaptcha':
				$checkList = ['enable_captcha', 'captcha_key', 'captcha_post_key'];
				if (isset($_POST['enable_captcha']) && $_POST['enable_captcha'] == 'y') {
					foreach ($checkList as $key) {
						if (!isset($_POST[$key]) || empty($_POST[$key])) {
							JsonPrint(false, '请填写所有字段。');
						}
					}
				}
				$_SESSION['install']['step'] = 5;
				$_SESSION['install']['data'] = array_merge($_SESSION['install']['data'], $_POST);
				JsonPrint(true, '验证码配置成功。');
				break;
			case 'installConfig':
				$checkList = ['db_host', 'db_port', 'db_user', 'db_pass', 'db_name', 'enable_register', 'enable_smtp', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_mail', 'smtp_ssl', 'sitename', 'description', 'enable_rewrite', 'captcha_key', 'captcha_post_key'];
				foreach ($checkList as $key) {
					if (!isset($_SESSION['install']['data'][$key])) {
						unset($_SESSION['install']);
						JsonPrint(false, '错误，请重新运行安装程序。');
					}
				}
				$smtpHost = sprintf('%s%s', $_SESSION['install']['data']['smtp_ssl'] == 'y' ? 'ssl://' : '', $_SESSION['install']['data']['smtp_host']);
				$template = file_get_contents('pigeon/config-template.php');
				$template = str_replace('{DB_HOST}', EscapeConfig($_SESSION['install']['data']['db_host']), $template);
				$template = str_replace('{DB_PORT}', EscapeConfig($_SESSION['install']['data']['db_port']), $template);
				$template = str_replace('{DB_USER}', EscapeConfig($_SESSION['install']['data']['db_user']), $template);
				$template = str_replace('{DB_PASS}', EscapeConfig($_SESSION['install']['data']['db_pass']), $template);
				$template = str_replace('{DB_NAME}', EscapeConfig($_SESSION['install']['data']['db_name']), $template);
				$template = str_replace('{ENABLE_REGISTER}', $_SESSION['install']['data']['enable_register'] == 'y' ? 'true' : 'false', $template);
				$template = str_replace('{ENABLE_SMTP}', $_SESSION['install']['data']['enable_smtp'] == 'y' ? 'true' : 'false', $template);
				$template = str_replace('{SMTP_HOST}', EscapeConfig($smtpHost), $template);
				$template = str_replace('{SMTP_PORT}', EscapeConfig($_SESSION['install']['data']['smtp_port']), $template);
				$template = str_replace('{SMTP_USER}', EscapeConfig($_SESSION['install']['data']['smtp_user']), $template);
				$template = str_replace('{SMTP_PASS}', EscapeConfig($_SESSION['install']['data']['smtp_pass']), $template);
				$template = str_replace('{SMTP_NAME}', EscapeConfig($_SESSION['install']['data']['smtp_mail']), $template);
				$template = str_replace('{SITENAME}', EscapeConfig($_SESSION['install']['data']['sitename']), $template);
				$template = str_replace('{DESCRIPTION}', EscapeConfig($_SESSION['install']['data']['description']), $template);
				$template = str_replace('{ENABLE_REWRITE}', $_SESSION['install']['data']['enable_rewrite'] == 'y' ? 'true' : 'false', $template);
				$template = str_replace('{RECAPTCHA_KEY}', EscapeConfig($_SESSION['install']['data']['captcha_key']), $template);
				$template = str_replace('{RECAPTCHA_KEY_POST}', EscapeConfig($_SESSION['install']['data']['captcha_post_key']), $template);
				file_put_contents('pigeon/config.php', $template);
				if (!file_exists('pigeon/config.php')) {
					JsonPrint(false, '配置文件写入失败，请检查程序是否有写入权限。');
				}
				unset($_SESSION['install']);
				JsonPrint(true, '配置文件写入成功。');
				break;
			case 'fetchInstall':
				JsonPrint(true, ['step' => $_SESSION['install']['step'], 'data' => $_SESSION['install']['data']]);
				break;
			default:
				JsonPrint(false, '未知操作。');
		}
	}
	exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Pigeon 安装程序</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		.install-form {
			display: none;
		}

		.install-form.active {
			display: block;
		}

		.allow-check {
			cursor: pointer;
		}

		.allow-check:hover {
			background-color: #f8f9fa;
		}

		.allow-check.active:hover {
			background-color: var(--bs-list-group-active-bg);
		}
	</style>
</head>

<body>
	<div class="container mt-5">
		<h1 class="text-center">Pigeon</h1>
		<div class="row mt-5">
			<div class="col-3">
				<ul class="list-group">
					<li class="list-group-item active" data-step="0">欢迎</li>
					<li class="list-group-item" data-step="1">数据库配置</li>
					<li class="list-group-item" data-step="2">站点信息配置</li>
					<li class="list-group-item" data-step="3">注册配置</li>
					<li class="list-group-item" data-step="4">验证码配置</li>
					<li class="list-group-item" data-step="5">写入配置</li>
					<li class="list-group-item" data-step="6">安装完成</li>
				</ul>
			</div>
			<div class="col-9">
				<!-- 欢迎页面 -->
				<form id="install-step-0" class="install-form active">
					<p>欢迎使用 Pigeon，一款轻量化的留言板程序，接下来将引导您完成安装。</p>
					<p>在开始之前，请确保您已经创建了一个数据库，并且拥有数据库的访问权限。</p>
					<p>有关 Pigeon 的更多信息，请访问 <a href="https://github.com/kasuganosoras/Pigeon" target="_blank">GitHub</a>。</p>
					<div class="text-center">
						<button type="button" class="btn btn-primary w-50 mt-5 set-step" data-set-step="1">下一步</button>
					</div>
				</form>
				<!-- 站点配置页面 -->
				<form id="install-step-1" class="install-form">
					<div class="row">
						<!-- 第一行：数据库地址和端口 -->
						<div class="col-md-4 mb-3">
							<label for="db_host" class="form-label">数据库地址</label>
							<input type="text" class="form-control" id="db_host" value="localhost" required>
						</div>
						<div class="col-md-4 mb-3">
							<label for="db_port" class="form-label">数据库端口</label>
							<input type="text" class="form-control" id="db_port" value="3306" required>
						</div>
						<div class="col-md-4 mb-3">
							<label for="db_name" class="form-label">数据库名称</label>
							<input type="text" class="form-control" id="db_name" value="pigeon" required>
						</div>
					</div>

					<div class="row">
						<!-- 第二行：数据库账号和密码 -->
						<div class="col-md-6 mb-3">
							<label for="db_user" class="form-label">数据库账号</label>
							<input type="text" class="form-control" id="db_user" value="root" required>
						</div>
						<div class="col-md-6 mb-3">
							<label for="db_pass" class="form-label">数据库密码</label>
							<input type="password" class="form-control" id="db_pass" value="root" required>
						</div>
					</div>

					<div class="text-center">
						<button type="button" class="btn btn-primary w-50 mt-5 set-step" data-set-step="2">下一步</button>
					</div>
				</form>

				<form id="install-step-2" class="install-form">
					<div class="row">
						<!-- 第一行：站点名称和站点介绍 -->
						<div class="col-md-4 mb-3">
							<label for="sitename" class="form-label">站点名称</label>
							<input type="text" class="form-control" id="sitename" value="Pigeon" required>
						</div>
						<div class="col-md-4 mb-3">
							<label for="description" class="form-label">站点介绍</label>
							<input type="text" class="form-control" id="description" value="咕咕咕，咕咕咕咕咕？" required>
						</div>
						<div class="col-md-4 mb-3">
							<label for="enable_rewrite" class="form-label">是否启用伪静态</label>
							<select class="form-select" id="enable_rewrite">
								<option value="y">是</option>
								<option value="n">否</option>
							</select>
						</div>
					</div>

					<div class="row">
						<!-- 管理员账号 -->
						<div class="col-md-4 mb-3">
							<label for="admin_user" class="form-label">管理员账号</label>
							<input type="text" class="form-control" id="admin_user" value="Admin" required>
						</div>
						<div class="col-md-4 mb-3">
							<label for="admin_pass" class="form-label">管理员密码</label>
							<input type="password" class="form-control" id="admin_pass" required>
						</div>
						<div class="col-md-4 mb-3">
							<label for="admin_email" class="form-label">管理员邮箱</label>
							<input type="email" class="form-control" id="admin_email" value="admin@example.com" required>
						</div>
					</div>

					<div class="text-center">
						<button type="button" class="btn btn-primary w-50 mt-5 set-step" data-set-step="3">下一步</button>
					</div>
				</form>
				<!-- 注册配置页面 -->
				<form id="install-step-3" class="install-form">
					<div class="row">
						<!-- 第一行：是否启用注册功能 -->
						<div class="col-md-6 mb-3">
							<label for="enable_register" class="form-label">是否启用注册功能</label>
							<select class="form-select" id="enable_register"data-switch="enable_smtp,smtp_host,smtp_port,smtp_user,smtp_pass,smtp_mail,smtp_ssl">
								<option value="y">是</option>
								<option value="n">否</option>
							</select>
						</div>
						<div class="col-md-6 mb-3">
							<label for="enable_smtp" class="form-label">是否启用注册邮箱验证</label>
							<select class="form-select" id="enable_smtp" data-dep="enable_register" data-switch="smtp_host,smtp_port,smtp_user,smtp_pass,smtp_mail,smtp_ssl">
								<option value="y">是</option>
								<option value="n">否</option>
							</select>
						</div>
					</div>
					<div class="row">
						<!-- 第二行：SMTP 配置 -->
						<div class="col-md-6 mb-3">
							<label for="smtp_host" class="form-label">SMTP 地址</label>
							<input type="text" class="form-control" id="smtp_host" value="localhost">
						</div>
						<div class="col-md-6 mb-3">
							<label for="smtp_port" class="form-label">SMTP 端口</label>
							<input type="text" class="form-control" id="smtp_port" value="25">
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 mb-3">
							<label for="smtp_user" class="form-label">SMTP 账号</label>
							<input type="text" class="form-control" id="smtp_user" value="root">
						</div>
						<div class="col-md-6 mb-3">
							<label for="smtp_pass" class="form-label">SMTP 密码</label>
							<input type="password" class="form-control" id="smtp_pass" value="123456789">
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 mb-3">
							<label for="smtp_mail" class="form-label">SMTP 邮箱</label>
							<input type="email" class="form-control" id="smtp_mail" value="noreply@example.com">
						</div>
						<div class="col-md-6 mb-3">
							<label for="smtp_name" class="form-label">SMTP SSL</label>
							<select class="form-select" id="smtp_ssl">
								<option value="y">是</option>
								<option value="n">否</option>
							</select>
						</div>
					</div>
					<div class="text-center">
						<button type="button" class="btn btn-primary w-50 mt-5 set-step" data-set-step="4">下一步</button>
					</div>
				</form>

				<!-- 验证码配置页面 -->
				<form id="install-step-4" class="install-form">
					<div class="row">
						<!-- 第一行：是否启用谷歌验证码 -->
						<div class="col-md-6 mb-3">
							<label for="enable_captcha" class="form-label">是否启用谷歌验证码</label>
							<select class="form-select" id="enable_captcha" data-switch="captcha_key,captcha_post_key">
								<option value="y">是</option>
								<option value="n">否</option>
							</select>
						</div>
						<div class="col-md-6 mb-3">
							<label for="captcha_key" class="form-label">Recaptcha 站点秘钥</label>
							<input type="text" class="form-control" id="captcha_key">
						</div>
						<div class="col-md-6 mb-3">
							<label for="captcha_post_key" class="form-label">Recaptcha 服务器秘钥</label>
							<input type="text" class="form-control" id="captcha_post_key">
						</div>
					</div>
					<div class="text-center">
						<button type="button" class="btn btn-primary w-50 mt-5 set-step" data-set-step="5">下一步</button>
					</div>
				</form>

				<!-- 写入配置页面 -->
				<form id="install-step-5" class="install-form">
					<p>请确认以下配置信息是否正确，点击下一步将写入配置文件。</p>
					<div class="row">
						<ul class="list-group">
							<li class="list-group-item">数据库地址： <span id="pv_db_host"></span></li>
							<li class="list-group-item">数据库端口： <span id="pv_db_port"></span></li>
							<li class="list-group-item">数据库账号： <span id="pv_db_user"></span></li>
							<li class="list-group-item">数据库密码： <span id="pv_db_pass"></span></li>
							<li class="list-group-item">数据库名称： <span id="pv_db_name"></span></li>
							<li class="list-group-item">站点名称： <span id="pv_sitename"></span></li>
							<li class="list-group-item">站点介绍： <span id="pv_description"></span></li>
							<li class="list-group-item">管理员账号： <span id="pv_admin_user"></span></li>
							<li class="list-group-item">管理员邮箱： <span id="pv_admin_email"></span></li>
							<li class="list-group-item">是否启用伪静态： <span id="pv_enable_rewrite"></span></li>
							<li class="list-group-item">是否启用注册功能： <span id="pv_enable_register"></span></li>
							<li class="list-group-item">是否启用注册邮箱验证： <span id="pv_enable_smtp"></span></li>
							<li class="list-group-item">SMTP 地址： <span id="pv_smtp_host"></span></li>
							<li class="list-group-item">SMTP 端口： <span id="pv_smtp_port"></span></li>
							<li class="list-group-item">SMTP 账号： <span id="pv_smtp_user"></span></li>
							<li class="list-group-item">SMTP 密码： <span id="pv_smtp_pass"></span></li>
							<li class="list-group-item">SMTP 邮箱： <span id="pv_smtp_mail"></span></li>
							<li class="list-group-item">SMTP SSL： <span id="pv_smtp_ssl"></span></li>
							<li class="list-group-item">是否启用谷歌验证码： <span id="pv_enable_captcha"></span></li>
							<li class="list-group-item">Recaptcha 站点秘钥： <span id="pv_captcha_key"></span></li>
							<li class="list-group-item">Recaptcha 服务器秘钥： <span id="pv_captcha_post_key"></span></li>
						</ul>
					</div>
					<div class="text-center">
						<button type="button" class="btn btn-primary w-50 mt-5 set-step" data-set-step="6">下一步</button>
					</div>
				</form>

				<!-- 安装完成页面 -->
				<div id="install-step-6" class="install-form">
					<p>安装完成，感谢您使用 Pigeon。</p>
					<p>请删除 install_web.php 文件以确保安全。</p>
					<div class="text-center">
						<button type="button" class="btn btn-primary w-50 mt-5" onclick="location.href = '../'">访问站点</button>
					</div>
				</div>
			</div>

			<!-- 消息显示区域 -->
			<div id="message" class="mt-3"></div>
		</div>
		<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
		<!-- SweetAlert2 弹窗插件 -->
		<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
		<script>
			var stepAction = {};

			function RegisterStepAction(step, action) {
				stepAction[step] = action;
			}

			function FetchInstallData() {
				$.post('?', {
					action: 'fetchInstall'
				}, function(result) {
					if (result.success) {
						$.each(result.message.data, function(key, value) {
							$('#' + key).val(value);
						});
						UpdatePreview();
						$('.install-form').removeClass('active');
						$('#install-step-' + result.message.step).addClass('active');
						$('.list-group-item').removeClass('active');
						$('.list-group-item[data-step="' + result.message.step + '"]').addClass('active');
						for (let i = 0; i < result.message.step; i++) {
							if (i > 1 && i < 5) {
								$('.list-group-item[data-step="' + i + '"]').addClass('allow-check');
							}
						}
						$('.allow-check').on('click', function() {
							const step = $(this).data('step');
							if (stepAction[step]) {
								stepAction[step](function(result) {
									if (result) {
										$('.install-form').removeClass('active');
										$('#install-step-' + step).addClass('active');
										$('.list-group-item').removeClass('active');
										$('.list-group-item[data-step="' + step + '"]').addClass('active');
									}
								});
							}
						});
					}
				}, 'json');
			}

			function UpdatePreview() {
				$('#pv_db_host').text($('#db_host').val());
				$('#pv_db_port').text($('#db_port').val());
				$('#pv_db_user').text($('#db_user').val());
				$('#pv_db_pass').text(('*').repeat($('#db_pass').val().length));
				$('#pv_db_name').text($('#db_name').val());
				$('#pv_sitename').text($('#sitename').val());
				$('#pv_description').text($('#description').val());
				$('#pv_admin_user').text($('#admin_user').val());
				$('#pv_admin_email').text($('#admin_email').val());
				$('#pv_enable_rewrite').text($('#enable_rewrite').val() == 'y' ? '是' : '否');
				$('#pv_enable_register').text($('#enable_register').val() == 'y' ? '是' : '否');
				$('#pv_enable_smtp').text($('#enable_smtp').val() == 'y' ? '是' : '否');
				$('#pv_smtp_host').text($('#smtp_host').val());
				$('#pv_smtp_port').text($('#smtp_port').val());
				$('#pv_smtp_user').text($('#smtp_user').val());
				$('#pv_smtp_pass').text(('*').repeat($('#smtp_pass').val().length));
				$('#pv_smtp_mail').text($('#smtp_mail').val());
				$('#pv_smtp_ssl').text($('#smtp_ssl').val() == 'y' ? '是' : '否');
				$('#pv_enable_captcha').text($('#enable_captcha').val() == 'y' ? '是' : '否');
				$('#pv_captcha_key').text($('#captcha_key').val());
				$('#pv_captcha_post_key').text($('#captcha_post_key').val());
				if ($('#enable_register').val() != 'y') {
					$('#pv_enable_smtp').parent().hide();
					$('#pv_smtp_host').parent().hide();
					$('#pv_smtp_port').parent().hide();
					$('#pv_smtp_user').parent().hide();
					$('#pv_smtp_pass').parent().hide();
					$('#pv_smtp_mail').parent().hide();
					$('#pv_smtp_ssl').parent().hide();
				} else {
					$('#pv_enable_smtp').parent().show();
					if ($('#enable_smtp').val() != 'y') {
						$('#pv_smtp_host').parent().hide();
						$('#pv_smtp_port').parent().hide();
						$('#pv_smtp_user').parent().hide();
						$('#pv_smtp_pass').parent().hide();
						$('#pv_smtp_mail').parent().hide();
						$('#pv_smtp_ssl').parent().hide();
					} else {
						$('#pv_smtp_host').parent().show();
						$('#pv_smtp_port').parent().show();
						$('#pv_smtp_user').parent().show();
						$('#pv_smtp_pass').parent().show();
						$('#pv_smtp_mail').parent().show();
						$('#pv_smtp_ssl').parent().show();
					}
				}
				if ($('#enable_captcha').val() != 'y') {
					$('#pv_captcha_key').parent().hide();
					$('#pv_captcha_post_key').parent().hide();
				} else {
					$('#pv_captcha_key').parent().show();
					$('#pv_captcha_post_key').parent().show();
				}
				UpdateSelects();
			}

			function UpdateSelects() {
				$('.form-select').each(function() {
					const switchData = $(this).data('switch');
					const switchDep = $(this).data('dep');
					if (switchDep) {
						if ($('#' + switchDep).val() != 'y') {
							return;
						}
					}
					if (!switchData) {
						return;
					}
					const switchList = switchData.split(',');
					if ($(this).val() == 'y') {
						$.each(switchList, function(index, value) {
							$('#' + value).parent().show();
						});
					} else {
						$.each(switchList, function(index, value) {
							$('#' + value).parent().hide();
						});
					}
				});
			}

			$(document).ready(function() {
				FetchInstallData();

				$('.form-select').on('change', function() {
					UpdateSelects();
				});

				$('.set-step').on('click', function() {
					const step = $(this).data('set-step');
					if (stepAction[step]) {
						if (stepAction[step](function(result) {
							if (result) {
								$('.install-form').removeClass('active');
								$('#install-step-' + step).addClass('active');
								$('.list-group-item').removeClass('active');
								$('.list-group-item[data-step="' + step + '"]').addClass('active');
							}
						}));
					} else {
						$('.install-form').removeClass('active');
						$('#install-step-' + step).addClass('active');
						$('.list-group-item').removeClass('active');
						$('.list-group-item[data-step="' + step + '"]').addClass('active');
					}
				});

				RegisterStepAction(2, function(cb) {
					$.post('?', {
						action: 'checkDatabase',
						db_host: $('#db_host').val(),
						db_port: $('#db_port').val(),
						db_user: $('#db_user').val(),
						db_pass: $('#db_pass').val(),
						db_name: $('#db_name').val()
					}, function(data) {
						if (!data.success) {
							Swal.fire({
								icon: 'error',
								title: '数据库错误',
								text: data.message
							});
						}
						cb(data.success);
					}, 'json');
				});

				RegisterStepAction(3, function(cb) {
					$.post('?', {
						action: 'installDatabase',
						admin_user: $('#admin_user').val(),
						admin_pass: $('#admin_pass').val(),
						admin_email: $('#admin_email').val(),
						sitename: $('#sitename').val(),
						description: $('#description').val(),
						enable_rewrite: $('#enable_rewrite').val()
					}, function(data) {
						if (!data.success) {
							Swal.fire({
								icon: 'error',
								title: '数据库错误',
								text: data.message
							});
						}
						cb(data.success);
					}, 'json');
				});

				RegisterStepAction(4, function(cb) {
					$.post('?', {
						action: 'checkRegister',
						enable_register: $('#enable_register').val(),
						enable_smtp: $('#enable_smtp').val(),
						smtp_host: $('#smtp_host').val(),
						smtp_port: $('#smtp_port').val(),
						smtp_user: $('#smtp_user').val(),
						smtp_pass: $('#smtp_pass').val(),
						smtp_mail: $('#smtp_mail').val(),
						smtp_ssl: $('#smtp_ssl').val()
					}, function(data) {
						if (!data.success) {
							Swal.fire({
								icon: 'error',
								title: '注册配置错误',
								text: data.message
							});
						}
						cb(data.success);
					}, 'json');
				});

				RegisterStepAction(5, function(cb) {
					$.post('?', {
						action: 'checkRecaptcha',
						enable_captcha: $('#enable_captcha').val(),
						captcha_key: $('#captcha_key').val(),
						captcha_post_key: $('#captcha_post_key').val()
					}, function(data) {
						if (!data.success) {
							Swal.fire({
								icon: 'error',
								title: '验证码配置错误',
								text: data.message
							});
						} else {
							UpdatePreview();
						}
						cb(data.success);
					}, 'json');
				});

				RegisterStepAction(6, function(cb) {
					$.post('?', {
						action: 'installConfig'
					}, function(data) {
						if (!data.success) {
							Swal.fire({
								icon: 'error',
								title: '配置写入错误',
								text: data.message
							});
						}
						cb(data.success);
					}, 'json');
				});
			});
		</script>
</body>

</html>