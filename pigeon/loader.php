<?php
SESSION_START();
// 加载函数库
include(ROOT . "/pigeon/function.php");
include(ROOT . "/pigeon/parsedown.php");
// 实例化 Pigeon
$pigeon = new Pigeon();
// 生成 SESSION ID
if(!isset($_SESSION['seid'])) {
	$_SESSION['seid'] = $pigeon->guid();
}
// 判断传入参数 s
if(isset($_GET['s'])) {
	switch($_GET['s']) {
		case 'timeline':
			if(isset($_GET['page']) && preg_match("/^[0-9]{0,6}$/", $_GET['page'])) {
				$pigeon->before = null;
				$pigeon->search = null;
				if(isset($_GET['time']) && preg_match("/^[0-9\:\- ]+$/", $_GET['time'])) {
					$beforeTime = strtotime($_GET['time']);
					$pigeon->before = $beforeTime ? $beforeTime : null;
				}
				if(isset($_GET['search']) && $_GET['search'] !== '') {
					$pigeon->search = mysqli_real_escape_string($pigeon->conn, $_GET['search']);
				}
				$pigeon->isLogin = (isset($_SESSION['user']) && $_SESSION['user'] !== '');
				$pigeon->isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1');
				if(isset($_GET['user']) && preg_match("/^[A-Za-z0-9\_\-]{0,32}$/", $_GET['user'])) {
					$pigeon->getTimeline($_GET['user'], true, Intval($_GET['page']));
				} else {
					$pigeon->getTimeline(null, true, Intval($_GET['page']));
				}
			}
			break;
		case 'login':
			$error = "";
			$alert = "danger";
			if(isset($_POST['username']) && isset($_POST['password'])) {
				if(!isset($_POST['seid']) || $_POST['seid'] !== $_SESSION['seid']) {
					$pigeon->Exception("CSRF 验证失败，请尝试重新登录。");
				}
				if($pigeon->config['recaptcha_key'] !== '') {
					if(!isset($_POST['g-recaptcha-response']) || !$pigeon->recaptcha_verify($_POST['g-recaptcha-response'])) {
						$error = "Recaptcha 验证失败。";
					}
				}
				$username = mysqli_real_escape_string($pigeon->conn, $_POST['username']);
				$login_ip = mysqli_real_escape_string($pigeon->conn, $_SERVER['REMOTE_ADDR']);
				$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `username`='{$username}'"));
				if($rs) {
					if($rs['status'] !== '200') {
						switch($rs['status']) {
							case "401":
								$error = "您需要先验证邮箱才能登陆，<a href='?s=resendmail&user={$rs['username']}'>点击重新发送邮件</a>。";
								break;
							case "403":
								$error = "您的账号已被封禁。";
								break;
							default:
								$error = "您的账号为异常状态，请联系管理员。";
						}
					} else {
						if(password_verify($_POST['password'], $rs['password'])) {
							if($error == '') {
								mysqli_query($pigeon->conn, "UPDATE `users` SET `latest_ip`='{$login_ip}', `latest_time`='" . time() . "' WHERE `id`='{$rs['id']}'");
								$_SESSION['user'] = $rs['username'];
								$_SESSION['email'] = $rs['email'];
								$_SESSION['token'] = $rs['token'];
								?>
								<html>
									<head>
										<title>跳转中...</title>
									</head>
									<body>
										<script>window.location='?';</script>
									</body>
								</html>
								<?
								exit;
							}
						} else {
							$error = "用户名或密码错误。";
						}
					}
				} else {
					$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `email`='{$username}'"));
					if($rs) {
						if($rs['status'] !== '200') {
							switch($rs['status']) {
								case "401":
									$error = "您需要先验证邮箱才能登陆，<a href='?s=resendmail&user={$rs['username']}'>点击重新发送邮件</a>。";
									break;
								case "403":
									$error = "您的账号已被封禁。";
									break;
								default:
									$error = "您的账号为异常状态，请联系管理员。";
							}
						} else {
							if(password_verify($_POST['password'], $rs['password'])) {
								if($error == '') {
									mysqli_query($pigeon->conn, "UPDATE `users` SET `latest_ip`='{$login_ip}', `latest_time`='" . time() . "' WHERE `id`='{$rs['id']}'");
									$_SESSION['user'] = $rs['username'];
									$_SESSION['email'] = $rs['email'];
									$_SESSION['token'] = $rs['token'];
									?>
									<html>
										<head>
											<title>跳转中...</title>
										</head>
										<body>
											<script>window.location='?';</script>
										</body>
									</html>
									<?
									exit;
								}
							} else {
								$error = "用户名或密码错误。";
							}
						}
					} else {
						$error = "用户名或密码错误。";
					}
				}
			}
			$pigeon->getTemplate("header");
			$pigeon->getTemplate("login");
			$pigeon->getTemplate("footer");
			break;
		case 'register':
			$error = "";
			$alert = "danger";
			if(!$pigeon->config['enable_registe']) {
				$error = "抱歉，本站暂不开放注册。";
			}
			if(isset($_POST['username']) && isset($_POST['password']) && isset($_POST['email'])) {
				if(!isset($_POST['seid']) || $_POST['seid'] !== $_SESSION['seid']) {
					$pigeon->Exception("CSRF 验证失败，请尝试重新登录。");
				}
				if(!preg_match("/^[A-Za-z0-9\_\-]+$/", $_POST['username'])) {
					$error = "用户名不合法，只允许 <code>A-Z a-z 0-9 _ -</code>";
				}
				if(mb_strlen($_POST['password']) < 5 || mb_strlen($_POST['password']) > 32) {
					$error = "密码最少为 5 个字符，最大为 32 个字符。";
				}
				if(!preg_match("/^[a-zA-Z0-9]+([-_.][a-zA-Z0-9]+)*@([a-zA-Z0-9]+[-.])+([a-z]{2,5})$/ims", $_POST['email'])) {
					$error = "邮箱格式不正确。";
				}
				if($pigeon->config['recaptcha_key'] !== '') {
					if(!isset($_POST['g-recaptcha-response']) || !$pigeon->recaptcha_verify($_POST['g-recaptcha-response'])) {
						$error = "Recaptcha 验证失败。";
					}
				}
				$username = mysqli_real_escape_string($pigeon->conn, $_POST['username']);
				$password = password_hash($_POST['password'], PASSWORD_BCRYPT);
				$email = mysqli_real_escape_string($pigeon->conn, $_POST['email']);
				$registe_ip = mysqli_real_escape_string($pigeon->conn, $_SERVER['REMOTE_ADDR']);
				$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `username`='{$username}'"));
				$token = md5(sha1($username . $password . $email . mt_rand(0, 99999999) . time()));
				if($rs) {
					$error = "此用户名已被注册。";
				}
				$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `email`='{$email}'"));
				if($rs) {
					$error = "此邮箱已被注册。";
				}
				if($error == '') {
					$ust = '200';
					$needVerify = '';
					if($pigeon->config['smtp']['enable']) {
						$ust = '401';
						$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
						$siteurl = "{$http_type}{$_SERVER['HTTP_HOST']}/?s=checkmail&token={$token}";
						$pigeon->sendMail($email, "验证您的 {$pigeon->config['sitename']} 账号", "<p>您好，感谢您注册 {$pigeon->config['sitename']}。</p><p>请点击以下链接验证您的账号：</p><p><a href='{$siteurl}'>{$siteurl}</a></p><p>如果以上链接无法点击，请复制到浏览器地址栏中打开。</p><p>如果您没有注册本站账号，请忽略此邮件。</p>");
						$needVerify = "系统已发送一封邮件到您的邮箱，请点击邮件中的链接完成验证。";
					}
					mysqli_query($pigeon->conn, "INSERT INTO `users` (
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
						'user',
						'{$registe_ip}',
						'" . time() . "',
						NULL,
						NULL,
						'{$ust}',
						'{$token}'
					)");
					$alert = "success";
					$error = "账号注册成功！{$needVerify}";
				}
			}
			$pigeon->getTemplate("header");
			$pigeon->getTemplate("register");
			$pigeon->getTemplate("footer");
			break;
		case "logout":
			if(!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				echo "<script>window.location='?';</script>";
				exit;
			}
			unset($_SESSION['user']);
			unset($_SESSION['email']);
			unset($_SESSION['token']);
			unset($_SESSION['seid']);
			?>
			<html>
				<head>
					<title>跳转中...</title>
				</head>
				<body>
					<script>window.location='?';</script>
				</body>
			</html>
			<?
			break;
		case "newpost":
			if(isset($_POST['content']) && isset($_POST['ispublic'])) {
				$apiUser = false;
				if(!isset($_SESSION['user'])) {
					if(isset($_GET['token']) && preg_match("/^[A-Za-z0-9]{32}$/", $_GET['token'])) {
						$token = mysqli_real_escape_string($pigeon->conn, $_GET['token']);
						$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `token`='{$token}'"));
						if($rs) {
							$_SESSION['user'] = $rs['username'];
							$_SESSION['email'] = $rs['email'];
							$apiUser = true;
						} else {
							$pigeon->Exception("Permission denied");
						}
					} else {
						$pigeon->Exception("请先登录。");
					}
				}
				if(!$apiUser) {
					if(!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
						$pigeon->Exception("CSRF 验证失败，请尝试重新登录。");
					}
				}
				if($_POST['ispublic'] !== '0' && $_POST['ispublic'] !== '1' && $_POST['ispublic'] !== '2') {
					$pigeon->Exception("Bad Request");
				}
				$content = mysqli_real_escape_string($pigeon->conn, $_POST['content']);
				$textlen = mb_strlen($content);
				if($textlen < 1 || $textlen > 1000000) {
					$pigeon->Exception("最少输入 1 个字符，最大输入 100 万个字符，当前已输入：{$textlen}。");
				}
				mysqli_query($pigeon->conn, "INSERT INTO `posts` (`id`, `content`, `author`, `time`, `public`) VALUES (NULL, '{$content}', '{$_SESSION['user']}', '" . time() . "', '{$_POST['ispublic']}')");
				echo "Successful";
			}
			break;
		case "deletepost":
			if(isset($_GET['id']) && preg_match("/^[0-9]{0,10}$/", $_GET['id'])) {
				$apiUser = false;
				if(!isset($_SESSION['user'])) {
					if(isset($_GET['token']) && preg_match("/^[A-Za-z0-9]{32}$/", $_GET['token'])) {
						$token = mysqli_real_escape_string($pigeon->conn, $_GET['token']);
						$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `token`='{$token}'"));
						if($rs) {
							$_SESSION['user'] = $rs['username'];
							$_SESSION['email'] = $rs['email'];
							$apiUser = true;
						} else {
							$pigeon->Exception("Permission denied");
						}
					} else {
						$pigeon->Exception("请先登录。");
					}
				}
				if(!$apiUser) {
					if(!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
						$pigeon->Exception("CSRF 验证失败，请尝试重新登录。");
					}
				}
				if(!$pigeon->isAdmin($_SESSION['user'])) {
					$pigeon->Exception("请求被拒绝。");
				}
				$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `posts` WHERE `id`='{$_GET['id']}'"));
				if($rs) {
					mysqli_query($pigeon->conn, "DELETE FROM `posts` WHERE `id`='{$_GET['id']}'");
					echo "Successful";
				} else {
					$pigeon->Exception("内容不存在。");
				}
			}
			break;
		case "changepublic":
			if(isset($_GET['id']) && preg_match("/^[0-9]{0,10}$/", $_GET['id']) && isset($_GET['newstatus']) && preg_match("/^[0-9]{1}$/", $_GET['newstatus'])) {
				$apiUser = false;
				if(!isset($_SESSION['user'])) {
					if(isset($_GET['token']) && preg_match("/^[A-Za-z0-9]{32}$/", $_GET['token'])) {
						$token = mysqli_real_escape_string($pigeon->conn, $_GET['token']);
						$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `token`='{$token}'"));
						if($rs) {
							$_SESSION['user'] = $rs['username'];
							$_SESSION['email'] = $rs['email'];
							$apiUser = true;
						} else {
							$pigeon->Exception("Permission denied");
						}
					} else {
						$pigeon->Exception("请先登录。");
					}
				}
				if(!$apiUser) {
					if(!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
						$pigeon->Exception("CSRF 验证失败，请尝试重新登录。");
					}
				}
				if(!$pigeon->isAdmin($_SESSION['user'])) {
					$pigeon->Exception("请求被拒绝。");
				}
				if($_GET['newstatus'] !== "0" && $_GET['newstatus'] !== "1" && $_GET['newstatus'] !== "2") {
					$pigeon->Exception("请求被拒绝。");
				}
				$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `posts` WHERE `id`='{$_GET['id']}'"));
				if($rs) {
					mysqli_query($pigeon->conn, "UPDATE `posts` SET `public`='{$_GET['newstatus']}' WHERE `id`='{$_GET['id']}'");
					echo "Successful";
				} else {
					$pigeon->Exception("内容不存在。");
				}
			}
			break;
		case "resendmail":
			if(isset($_GET['user']) && preg_match("/^[A-Za-z0-9\_\-]{0,32}$/", $_GET['user'])) {
				$alert = "danger";
				$error = "";
				$username = mysqli_real_escape_string($pigeon->conn, $_GET['user']);
				$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `username`='{$username}'"));
				if($rs) {
					if($rs['status'] !== '401') {
						$error = "此账号已经通过验证。";
					} else {
						$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
						$siteurl = "{$http_type}{$_SERVER['HTTP_HOST']}/?s=checkmail&token={$rs['token']}";
						$pigeon->sendMail($rs['email'], "验证您的 {$pigeon->config['sitename']} 账号", "<p>您好，感谢您注册 {$pigeon->config['sitename']}。</p><p>请点击以下链接验证您的账号：</p><p><a href='{$siteurl}'>{$siteurl}</a></p><p>如果以上链接无法点击，请复制到浏览器地址栏中打开。</p><p>如果您没有注册本站账号，请忽略此邮件。</p>");
						$error = "系统已发送一封邮件到您的邮箱，请点击邮件中的链接完成验证。";
						$alert = "success";
					}
				} else {
					$error = "此账号不存在。";
				}
				$pigeon->getTemplate("header");
				$pigeon->getTemplate("login");
				$pigeon->getTemplate("footer");
			}
			break;
		case "checkmail":
			if(isset($_GET['token']) && preg_match("/^[A-Za-z0-9]{32}$/", $_GET['token'])) {
				$alert = "danger";
				$error = "";
				$token = mysqli_real_escape_string($pigeon->conn, $_GET['token']);
				$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `token`='{$token}'"));
				if($rs) {
					if($rs['status'] !== '401') {
						$error = "无效的验证链接。";
					} else {
						mysqli_query($pigeon->conn, "UPDATE `users` SET `status`='200' WHERE `id`='{$rs['id']}'");
						$error = "验证成功，您可以登录了。";
						$alert = "success";
					}
				} else {
					$error = "无效的验证链接。";
				}
				$pigeon->getTemplate("header");
				$pigeon->getTemplate("login");
				$pigeon->getTemplate("footer");
			}
			break;
		case "msg":
			if(isset($_GET['id']) && preg_match("/^[0-9]{0,10}$/", $_GET['id'])) {
				$message = $pigeon->getMessageById($_GET['id']);
				$pigeon->isAjax = false;
				$pigeon->isLogin = (isset($_SESSION['user']) && $_SESSION['user'] !== '');
				if($message) {
					$pigeon->getTemplate("header");
					echo $message;
					$pigeon->getTemplate("footer");
				} else {
					$pigeon->getTemplate("header");
					echo "<h3>404 Not found</h3>";
					echo "<p>未找到指定的消息内容，该消息已被删除或者您暂时没有权限查看。</p>";
					$pigeon->getTemplate("footer");
				}
			}
			break;
		case "getmsg":
			if(!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				$pigeon->Exception("CSRF 验证失败，请尝试重新登录。");
			}
			if(isset($_GET['id']) && preg_match("/^[0-9]{0,10}$/", $_GET['id'])) {
				$message = $pigeon->getRawMessageById($_GET['id']);
				$pigeon->isAjax = false;
				$pigeon->isLogin = (isset($_SESSION['user']) && $_SESSION['user'] !== '');
				if($message) {
					echo json_encode(Array(
						'content' => $message['content'],
						'public' => $message['public'],
						'author' => $message['author'],
						'time' => $message['time']
					));
				} else {
					$pigeon->Exception("未找到指定的消息内容，该消息已被删除或者您暂时没有权限查看。");
				}
			}
			break;
		case "editpost":
			if(isset($_GET['id']) && preg_match("/^[0-9]{1,10}$/", $_GET['id'])) {
				if(!isset($_SESSION['user'])) {
					if(isset($_GET['token']) && preg_match("/^[A-Za-z0-9]{32}$/", $_GET['token'])) {
						$token = mysqli_real_escape_string($pigeon->conn, $_GET['token']);
						$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `token`='{$token}'"));
						if($rs) {
							$_SESSION['user'] = $rs['user'];
							$_SESSION['email'] = $rs['email'];
						} else {
							$pigeon->Exception("Permission denied");
						}
					}
					$pigeon->Exception("请先登录。");
				}
				if($_POST['ispublic'] !== '0' && $_POST['ispublic'] !== '1' && $_POST['ispublic'] !== '2') {
					$pigeon->Exception("Bad Request");
				}
				$id = mysqli_real_escape_string($pigeon->conn, $_GET['id']);
				$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `posts` WHERE `id`='{$id}'"));
				if($rs) {
					if($rs['author'] !== $_SESSION['user'] && !$pigeon->isAdmin($_SESSION['user'])) {
						$pigeon->Exception("未找到指定的消息内容，该消息已被删除或者您暂时没有权限查看。");
					}
					$content = mysqli_real_escape_string($pigeon->conn, $_POST['content']);
					$public = mysqli_real_escape_string($pigeon->conn, $_POST['ispublic']);
					$textlen = mb_strlen($content);
					if($textlen < 1 || $textlen > 1000000) {
						$pigeon->Exception("最少输入 1 个字符，最大输入 100 万个字符，当前已输入：{$textlen}。");
					}
					mysqli_query($pigeon->conn, "UPDATE `posts` SET `content`='{$content}',`public`='{$public}' WHERE `id`='{$id}'");
					echo "Successful";
				} else {
					$pigeon->Exception("未找到指定的消息内容，该消息已被删除或者您暂时没有权限查看。");
				}
			}
			break;
	}
} else {
	// 默认首页
	$pigeon->before = null;
	$pigeon->search = null;
	if(isset($_GET['time']) && preg_match("/^[0-9\:\- ]+$/", $_GET['time'])) {
		$beforeTime = strtotime($_GET['time']);
		$pigeon->before = $beforeTime ? $beforeTime : null;
	}
	if(isset($_GET['search']) && $_GET['search'] !== '') {
		$pigeon->search = mysqli_real_escape_string($pigeon->conn, $_GET['search']);
	}
	$pigeon->isAjax = false;
	$pigeon->isLogin = (isset($_SESSION['user']) && $_SESSION['user'] !== '');
	$pigeon->getTemplate("header");
	if(isset($_GET['user']) && preg_match("/^[A-Za-z0-9\_\-]{0,32}$/", $_GET['user'])) {
		$pigeon->getTimeline($_GET['user'], true, 1);
	} else {
		$pigeon->getTimeline(null, true, 1);
	}
	$pigeon->getTemplate("footer");
}
