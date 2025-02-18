<?php
error_reporting(E_ALL);
SESSION_START();
define("ROOT", str_replace("\\", "/", __DIR__));
// 延长超时时间
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('max_input_time', 0);

if (!file_exists(ROOT . "/pigeon/config.php")) {
	echo "请先完成安装向导。";
	exit;
}

if (!isset($_SESSION['user']) || $_SESSION['user'] == '') {
	echo "<html><head><title>跳转中...</title></head><body><script>window.location='/';</script></body></html>";
	exit;
}

class PigeonAdmin
{
	public function isAdmin($userName)
	{
		global $pigeon;
		if ($pigeon->conn) {
			$stmt = $pigeon->conn->prepare("SELECT * FROM `users` WHERE `username`=:username");
			$stmt->execute(array(':username' => $userName));
			$rs = $stmt->fetch(PDO::FETCH_ASSOC);
			return $rs ? ($rs['permission'] == 'root' || $rs['permission'] == 'admin') : false;
		} else {
			return false;
		}
	}
	public function isRoot($userName)
	{
		global $pigeon;
		if ($pigeon->conn) {
			$stmt = $pigeon->conn->prepare("SELECT * FROM `users` WHERE `username`=:username");
			$stmt->execute(array(':username' => $userName));
			$rs = $stmt->fetch(PDO::FETCH_ASSOC);
			return $rs ? ($rs['permission'] == 'root') : false;
		} else {
			return false;
		}
	}
	public function isEmpty($str)
	{
		return empty($str) ? "--" : $str;
	}
	public function isZero($str)
	{
		return $str == '0' ? "--" : $str;
	}
	public function accountStatus($status)
	{
		$slist = array(
			'200' => '正常',
			'401' => '未验证',
			'403' => '禁止登录'
		);
		return isset($slist[$status]) ? $slist[$status] : "未知状态";
	}
	public function errorMsg($message)
	{
		Header("Content-Type: text/html", true, 401);
		exit($message);
	}
	public function checkPermission($file)
	{
		if (is_dir($file)) {
			$dir = $file;
			if ($fp = @fopen("{$dir}/.writetest", 'w')) {
				@fclose($fp);
				@unlink("{$dir}/.writetest");
				return true;
			} else {
				return false;
			}
		} else {
			if ($fp = @fopen($file, 'a+')) {
				@fclose($fp);
				return true;
			} else {
				return false;
			}
		}
	}
	public function unzipUpdateFiles($fileName, $unzipPath)
	{
		$zip = new ZipArchive();
		$open = $zip->open($fileName);
		if ($open === true) {
			return $zip->extractTo($unzipPath);
		}
		return false;
	}
}

include(ROOT . "/pigeon/config.php");
include(ROOT . "/pigeon/function.php");
$pigeon = new Pigeon();
$padmin = new PigeonAdmin();
if (!$padmin->isAdmin($_SESSION['user'])) {
	echo "<html><head><title>跳转中...</title></head><body><script>window.location='/';</script></body></html>";
	exit;
}
if (isset($_GET['s'])) {
	switch ($_GET['s']) {
		case "fetchUser":
			if (!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				$padmin->errorMsg("CSRF 验证失败，请尝试重新登录。");
			}
			if (isset($_GET['id']) && preg_match("/^[0-9]{0,10}$/", $_GET['id'])) {
				$stmt = $pigeon->conn->prepare("SELECT * FROM `users` WHERE `id`=:id");
				$stmt->execute(array(':id' => $_GET['id']));
				$rs = $stmt->fetch(PDO::FETCH_ASSOC);
				if ($rs) {
					$user = array(
						'id' => $rs['id'],
						'username' => $rs['username'],
						'email' => $rs['email'],
						'permission' => $rs['permission'],
						'registe_ip' => $padmin->isEmpty($rs['registe_ip']),
						'registe_time' => $rs['registe_time'] == "" ? "--" : date("Y-m-d H:i:s", Intval($rs['registe_time'])),
						'latest_ip' => $padmin->isEmpty($rs['latest_ip']),
						'latest_time' => $rs['latest_time'] == "" ? "--" : date("Y-m-d H:i:s", Intval($rs['latest_time'])),
						'status' => $rs['status']
					);
					echo json_encode($user);
				} else {
					$padmin->errorMsg("User not found");
				}
			}
			break;
		case "fetchUserList":
			if (!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				$padmin->errorMsg("CSRF 验证失败，请尝试重新登录。");
			}
?>
			<tbody>
				<tr>
					<th nowrap class="t-id">ID</th>
					<th nowrap>用户名</th>
					<th nowrap>邮箱</th>
					<th nowrap>权限</th>
					<th nowrap>账号状态</th>
					<th nowrap>操作</th>
				</tr>
				<?php
				$stmt = $pigeon->conn->prepare("SELECT * FROM `users`");
				$stmt->execute();
				$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
				foreach ($rs as $rw) {
					echo "<tr>
					<td class='t-id'>{$rw['id']}</td>
					<td nowrap>{$rw['username']}</td>
					<td nowrap>{$rw['email']}</td>
					<td nowrap>{$rw['permission']}</td>
					<td nowrap>" . $padmin->accountStatus($rw['status']) . "</td>
					<td nowrap><a onclick='fetchUser({$rw['id']})'>[选择]</a></td>
				</tr>
					";
				}
				?>
			</tbody>
<?php
			break;
		case "saveUser":
			if (!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				$padmin->errorMsg("CSRF 验证失败，请尝试重新登录。");
			}
			if (isset($_GET['id']) && preg_match("/^[0-9]{0,10}$/", $_GET['id'])) {
				$stmt = $pigeon->conn->prepare("SELECT * FROM `users` WHERE `id`=:id");
				$stmt->execute(array(':id' => $_GET['id']));
				$rs = $stmt->fetch(PDO::FETCH_ASSOC);
				$uid = $rs ? $rs['id'] : false;
				if ($rs) {
					if (isset($_POST['username']) && isset($_POST['email']) && isset($_POST['permission']) && isset($_POST['status'])) {
						if (!preg_match("/^[A-Za-z0-9\_\-]+$/", $_POST['username'])) {
							$padmin->errorMsg("用户名不合法，只允许 <code>A-Z a-z 0-9 _ -</code>");
						}
						if (!preg_match("/^[a-zA-Z0-9]+([-_.][a-zA-Z0-9]+)*@([a-zA-Z0-9]+[-.])+([a-z]{2,5})$/ims", $_POST['email'])) {
							$padmin->errorMsg("邮箱格式不正确。");
						}
						if ($_POST['permission'] !== 'user' && $_POST['permission'] !== 'admin' && $_POST['permission'] !== 'root') {
							$padmin->errorMsg("权限类型不正确。");
						}
						if ($_POST['status'] !== '200' && $_POST['status'] !== '401' && $_POST['status'] !== '403') {
							$padmin->errorMsg("账号状态不正确。");
						}
						if (isset($_POST['password']) && $_POST['password'] !== '') {
							if (mb_strlen($_POST['password']) < 5 || mb_strlen($_POST['password']) > 32) {
								$padmin->errorMsg("密码最少为 5 个字符，最大为 32 个字符。");
							} else {
								$password = password_hash($_POST['password'], PASSWORD_BCRYPT);
								$passwordSQL = ",`password`=:password";
							}
						} else {
							$passwordSQL = "";
						}
						$stmt = $pigeon->conn->prepare("UPDATE `users` SET `username`=:username,`email`=:email,`permission`=:permission,`status`=:status{$passwordSQL} WHERE `id`=:id");
						$params = array(
							':username' => $_POST['username'],
							':email' => $_POST['email'],
							':permission' => $_POST['permission'],
							':status' => $_POST['status'],
							':id' => $uid
						);
						if ($passwordSQL !== "") {
							$params[':password'] = $password;
						}
						$stmt->execute($params);
						echo "Successful";
					} else {
						$padmin->errorMsg("请将用户信息填写完整");
					}
				} else {
					$padmin->errorMsg("User not found");
				}
			}
			break;
		case "deleteUser":
			if (!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				$padmin->errorMsg("CSRF 验证失败，请尝试重新登录。");
			}
			if (isset($_GET['id']) && preg_match("/^[0-9]{0,10}$/", $_GET['id'])) {
				$stmt = $pigeon->conn->prepare("SELECT * FROM `users` WHERE `id`=:id");
				$stmt->execute(array(':id' => $_GET['id']));
				$rs = $stmt->fetch(PDO::FETCH_ASSOC);
				if ($rs) {
					if ($rs['username'] == $_SESSION['user']) {
						$padmin->errorMsg("无法删除自己的账号");
					}
					if ($rs['permission'] == 'root') {
						$padmin->errorMsg("无法删除超级管理员账号");
					}
					$stmt = $pigeon->conn->prepare("DELETE FROM `users` WHERE `id`=:id");
					$stmt->execute(array(':id' => $rs['id']));
					echo "Successful";
				} else {
					$padmin->errorMsg("User not found");
				}
			}
			break;
		case "checkUpdate":
			if (!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				$padmin->errorMsg("CSRF 验证失败，请尝试重新登录。");
			}
			$update = @file_get_contents("https://cdn.zerodream.net/pigeon/");
			echo $update;
			break;
		case "executeUpdate":
			if (!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				$padmin->errorMsg("CSRF 验证失败，请尝试重新登录。");
			}
			$update = @file_get_contents("https://cdn.zerodream.net/pigeon/");
			$update = json_decode($update, true);
			if (!$update) {
				$padmin->errorMsg("获取更新信息时出错，请稍后重试。");
			}
			if (!$padmin->checkPermission("./")) {
				$padmin->errorMsg("网站目录不可写，请修改权限或手动更新。");
			} elseif (!class_exists("ZipArchive")) {
				$padmin->errorMsg("未检测到 ZipArchive 组件，请先修改 php.ini 启用 php_zip 扩展。");
			} else {
				$file = @file_get_contents($update['download']);
				if (strlen($file) == 0) {
					$padmin->errorMsg("下载的文件长度为 0，请检查网络是否正常。");
				} elseif (file_put_contents('update-temp.zip', $file) === false) {
					$padmin->errorMsg("写入文件时发生错误，请检查目录是否有读写权限。");
				} elseif (md5_file('update-temp.zip') !== $update['md5']) {
					@unlink('update-temp.zip');
					$padmin->errorMsg("文件 MD5 验证失败，请尝试重新更新。");
				} else {
					if ($padmin->unzipUpdateFiles('update-temp.zip', './')) {
						@unlink('update-temp.zip');
						echo "Pigeon 更新成功。";
					} else {
						@unlink('update-temp.zip');
						$padmin->errorMsg("解压文件时发生错误，无法打开文件或解压失败。");
					}
				}
			}
			break;
		default:
			$padmin->errorMsg("Action undefined");
	}
	exit;
}
?>
<html>

<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=11">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.bootcss.com/font-awesome/4.7.0/css/font-awesome.min.css" crossorigin="anonymous">
	<title><?php echo $pigeonConfig['sitename']; ?> - Admin Console</title>
	<script src="https://apps.bdimg.com/libs/jquery/2.1.4/jquery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js" crossorigin="anonymous"></script>
	<style type="text/css">
		* {
			transition-duration: 0.5s;
		}

		.userlist,
		blockquote {
			width: 100%;
			font-size: 14px;
		}

		.userlist tr {
			height: 28px;
		}

		.userlist tr td,
		.userlist tr th {
			padding-left: 4px;
			padding-right: 4px;
		}

		.userlist tr:nth-child(2n+3),
		.userlist tr:hover {
			background-color: #F1F1F1;
		}

		.userlist a {
			cursor: pointer;
		}

		.t-id {
			text-align: center;
			width: 32px;
		}

		#alert_success,
		#alert_danger {
			display: none;
		}

		.logo a {
			text-decoration: none;
			color: #333;
		}
	</style>
</head>

<body>
	<div class="container">
		<div class="row">
			<div class="col-sm-12 logo">
				<a href="./">
					<h2>Pigeon Admin</h2>
				</a>
				<p>请选择一个用户进行操作</p>
				<br>
			</div>
			<div class="col-sm-9">
				<div id="alert_success"></div>
				<div id="alert_danger"></div>
				<table class="table userlist table-responsive" id="userlist_table"></table>
			</div>
			<div class="col-sm-3">
				<p>用户名</p>
				<p><input type="text" id="username" class="form-control" autocomplete="new-password"></p>
				<p>邮箱</p>
				<p><input type="email" id="email" class="form-control" autocomplete="new-password"></p>
				<p>密码</p>
				<p><input type="password" id="password" class="form-control" placeholder="留空则不修改" autocomplete="new-password"></p>
				<p>权限</p>
				<p><select id="permission" class="form-control">
						<option value="user">普通用户</option>
						<option value="admin">管理员</option>
						<option value="root">超级管理员</option>
					</select></p>
				<p>账号状态</p>
				<p><select id="status" class="form-control">
						<option value="200">正常</option>
						<option value="401">未验证</option>
						<option value="403">禁止登录</option>
					</select></p>
				<p><b>注册 IP：</b><span id="registe_ip">--</span></p>
				<p><b>注册时间：</b><span id="registe_time">--</span></p>
				<p><b>上次访问 IP：</b><span id="latest_ip">--</span></p>
				<p><b>上次访问时间：</b><span id="latest_time">--</span></p>
				<hr>
				<button class="btn btn-danger" onclick="deleteUser()">删除用户</button>
				<button class="btn btn-success pull-right" onclick="saveUser()">保存修改</button>
				<hr>
				<p>您正在使用的 Pigeon 版本为：<?php echo $pigeon->version; ?></p>
				<p>当前最新版本为：<span id="newest_version">检查中...</span></p>
				<p id="updatemsg"></p>
			</div>
			<div class="col-sm-12">
				<hr>
				<p>&copy; <?php echo date("Y"); ?> <?php echo $pigeonConfig['sitename']; ?> | Powered by <a href="https://github.com/kasuganosoras/Pigeon" target="_blank">Pigeon</a></p>
			</div>
		</div>
		<style type="text/css">
			.margin-footer {
				margin-bottom: 32px;
			}

			body {
				padding-bottom: 64px;
			}
		</style>
	</div>
	<script type="text/javascript">
		var seid = '<?php echo isset($_SESSION['seid']) ? $_SESSION['seid'] : ""; ?>';
		var selectId;
		var version = '<?php echo $pigeon->version; ?>';
		var dismissSuccess = '<div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>';
		var dismissDanger = '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>';

		function fetchUser(id) {
			var htmlobj = $.ajax({
				type: 'GET',
				url: `?s=fetchUser&id=${id}&seid=${seid}`,
				async: true,
				error: function() {
					errorMsg("错误：" + htmlobj.responseText);
				},
				success: function() {
					try {
						var data = JSON.parse(htmlobj.responseText);
						$("#username").val(data.username);
						$("#email").val(data.email);
						$("#permission").val(data.permission);
						$("#status").val(data.status);
						$("#registe_ip").text(data.registe_ip);
						$("#registe_time").text(data.registe_time);
						$("#latest_ip").text(data.latest_ip);
						$("#latest_time").text(data.latest_time);
						selectId = id;
					} catch (e) {
						errorMsg("错误：" + e.message);
					}
				}
			});
		}

		function saveUser() {
			if (selectId == undefined) {
				errorMsg("您还没有选择要操作的用户");
				return;
			}
			var htmlobj = $.ajax({
				type: 'POST',
				url: `?s=saveUser&id=${selectId}&seid=${seid}`,
				data: {
					username: $("#username").val(),
					password: $("#password").val(),
					email: $("#email").val(),
					permission: $("#permission").val(),
					status: $("#status").val()
				},
				async: true,
				error: function() {
					errorMsg("错误：" + htmlobj.responseText);
				},
				success: function() {
					successMsg("用户信息保存成功！");
					fetchUserList();
				}
			});
		}

		function deleteUser() {
			if (selectId == undefined) {
				errorMsg("您还没有选择要操作的用户");
				return;
			}
			if (confirm("您确定要删除此用户吗？该操作是不可逆的，请谨慎选择！")) {
				var htmlobj = $.ajax({
					type: 'GET',
					url: `?s=deleteUser&id=${selectId}&seid=${seid}`,
					async: true,
					error: function() {
						errorMsg("错误：" + htmlobj.responseText);
					},
					success: function() {
						successMsg("用户删除成功！");
						fetchUserList();
					}
				});
			}
		}

		function fetchUserList(cb) {
			var htmlobj = $.ajax({
				type: 'GET',
				url: "?s=fetchUserList&seid=" + seid,
				async: true,
				error: function() {
					errorMsg("错误：" + htmlobj.responseText);
				},
				success: function() {
					userlist_table.innerHTML = htmlobj.responseText;
					if (cb) {
						cb();
					}
				}
			});
		}

		function checkUpdate() {
			var htmlobj = $.ajax({
				type: 'GET',
				url: `?s=checkUpdate&seid=${seid}`,
				async: true,
				error: function() {
					errorMsg("错误：" + htmlobj.responseText);
				},
				success: function() {
					try {
						var update = JSON.parse(htmlobj.responseText);
						newest_version.innerHTML = update.version;
						if (update.version != version && parseInt(update.version.replace(/\./g, "")) > parseInt(version.replace(/\./g, ""))) {
							updatemsg.innerHTML = "发现新更新：" + update.description + "</p><p><button class='btn btn-primary' onclick='executeUpdate()'>立即更新</button>";
						} else {
							updatemsg.innerHTML = "已经是最新版本！";
						}
					} catch (e) {
						errorMsg(e.message);
					}
				}
			});
		}

		function executeUpdate() {
			if (confirm("您确定要更新吗？更新可能会覆盖您对系统自带模板的修改，但是不会影响您的自定义模板，建议您备份好数据后再执行。\n\n更新可能需要较长时间，请耐心等待，不要关闭网页！")) {
				var htmlobj = $.ajax({
					type: 'GET',
					url: `?s=executeUpdate&seid=${seid}`,
					async: true,
					timeout: 100000,
					error: function() {
						errorMsg("错误：" + htmlobj.responseText);
					},
					success: function() {
						successMsg(htmlobj.responseText);
					}
				});
			}
		}

		function successMsg(text) {
			$("#alert_success").html(dismissSuccess + text + "</div>");
			$("#alert_success").fadeIn(500);
			setTimeout(function() {
				$("#alert_success").fadeOut(500);
			}, 3000);
		}

		function errorMsg(text) {
			$("#alert_danger").html(dismissDanger + text + "</div>");
			$("#alert_danger").fadeIn(500);
		}

		$(document).ready(function() {
			fetchUserList(function() {
				checkUpdate();
			});
		});
	</script>
</body>

</html>