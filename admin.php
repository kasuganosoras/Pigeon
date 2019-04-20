<?php
error_reporting(E_ALL);
SESSION_START();
define("ROOT", str_replace("\\", "/", __DIR__));
if(!file_exists(ROOT . "/pigeon/config.php")) {
	echo "请先在命令行下执行 install.php 进行安装。";
	exit;
}
if(!isset($_SESSION['user']) || $_SESSION['user'] == '') {
	echo "<html><head><title>跳转中...</title></head><body><script>window.location='/';</script></body></html>";
	exit;
}
function isAdmin($username) {
	global $pigeon;
	if($pigeon->conn) {
		$username = mysqli_real_escape_string($pigeon->conn, $username);
		$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `username`='{$username}'"));
		return $rs ? ($rs['permission'] == 'root' || $rs['permission'] == 'admin') : false;
	} else {
		return false;
	}
}
function isRoot($username) {
	global $pigeon;
	if($pigeon->conn) {
		$username = mysqli_real_escape_string($pigeon->conn, $username);
		$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `username`='{$username}'"));
		return $rs ? ($rs['permission'] == 'root') : false;
	} else {
		return false;
	}
}
function isEmpty($str) {
	return empty($str) ? "--" : $str;
}
function isZero($str) {
	return $str == '0' ? "--" : $str;
}
function AccountStatus($status) {
	$slist = Array(
		'200' => '正常',
		'401' => '未验证',
		'403' => '禁止登录'
	);
	return isset($slist[$status]) ? $slist[$status] : "未知状态";
}
function ErrorMsg($message) {
	Header("Content-Type: text/html", true, 401);
	exit($message);
}
function checkPermission($file) {
	if(is_dir($file)){
		$dir = $file;
		if($fp = @fopen("{$dir}/.writetest", 'w')) {
			@fclose($fp);
			@unlink("{$dir}/.writetest");
			return true;
		} else {
			return false;
		}
	} else {
		if($fp = @fopen($file, 'a+')) {
			@fclose($fp);
			return true;
		} else {
			return false;
		}
	}
}
function unzipUpdateFiles($fileName, $unzipPath) {
	$zip = new ZipArchive();
	$open = $zip->open($fileName);
	if($open === true) {
		return $zip->extractTo($unzipPath);
	}
	return false;
}

include(ROOT . "/pigeon/config.php");
include(ROOT . "/pigeon/function.php");
$pigeon = new Pigeon();
if(!isAdmin($_SESSION['user'])) {
	echo "<html><head><title>跳转中...</title></head><body><script>window.location='/';</script></body></html>";
	exit;
}
if(isset($_GET['s'])) {
	switch($_GET['s']) {
		case "getuser":
			if(!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				ErrorMsg("CSRF 验证失败，请尝试重新登录。");
			}
			if(isset($_GET['id']) && preg_match("/^[0-9]{0,10}$/", $_GET['id'])) {
				$uid = mysqli_real_escape_string($pigeon->conn, $_GET['id']);
				$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `id`='{$uid}'"));
				if($rs) {
					$user = Array(
						'id' => $rs['id'],
						'username' => $rs['username'],
						'email' => $rs['email'],
						'permission' => $rs['permission'],
						'registe_ip' => isEmpty($rs['registe_ip']),
						'registe_time' => $rs['registe_time'] == "" ? "--" : date("Y-m-d H:i:s", Intval($rs['registe_time'])),
						'latest_ip' => isEmpty($rs['latest_ip']),
						'latest_time' => $rs['latest_time'] == "" ? "--" : date("Y-m-d H:i:s", Intval($rs['latest_time'])),
						'status' => $rs['status']
					);
					echo json_encode($user);
				} else {
					ErrorMsg("User not found");
				}
			}
			break;
		case "userlist":
			if(!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				ErrorMsg("CSRF 验证失败，请尝试重新登录。");
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
				$rs = mysqli_query($pigeon->conn, "SELECT * FROM `users`");
				while($rw = mysqli_fetch_row($rs)) {
					echo "<tr>
					<td class='t-id'>{$rw[0]}</td>
					<td nowrap>{$rw[1]}</td>
					<td nowrap>{$rw[3]}</td>
					<td nowrap>{$rw[4]}</td>
					<td nowrap>" . AccountStatus($rw[9]) . "</td>
					<td nowrap><a onclick='getUser({$rw[0]})'>[选择]</a></td>
				</tr>
					";
				}
				?>
			</tbody>
			<?php
			break;
		case "saveuser":
			if(!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				ErrorMsg("CSRF 验证失败，请尝试重新登录。");
			}
			if(isset($_GET['id']) && preg_match("/^[0-9]{0,10}$/", $_GET['id'])) {
				$uid = mysqli_real_escape_string($pigeon->conn, $_GET['id']);
				$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `id`='{$uid}'"));
				if($rs) {
					if(isset($_POST['username']) && isset($_POST['email']) && isset($_POST['permission']) && isset($_POST['status'])) {
						if(!preg_match("/^[A-Za-z0-9\_\-]+$/", $_POST['username'])) {
							ErrorMsg("用户名不合法，只允许 <code>A-Z a-z 0-9 _ -</code>");
						}
						if(!preg_match("/^[a-zA-Z0-9]+([-_.][a-zA-Z0-9]+)*@([a-zA-Z0-9]+[-.])+([a-z]{2,5})$/ims", $_POST['email'])) {
							ErrorMsg("邮箱格式不正确。");
						}
						if($_POST['permission'] !== 'user' && $_POST['permission'] !== 'admin' && $_POST['permission'] !== 'root') {
							ErrorMsg("权限类型不正确。");
						}
						if($_POST['status'] !== '200' && $_POST['status'] !== '401' && $_POST['status'] !== '403') {
							ErrorMsg("账号状态不正确。");
						}
						$username = mysqli_real_escape_string($pigeon->conn, $_POST['username']);
						$email = mysqli_real_escape_string($pigeon->conn, $_POST['email']);
						$permission = mysqli_real_escape_string($pigeon->conn, $_POST['permission']);
						$status = mysqli_real_escape_string($pigeon->conn, $_POST['status']);
						if(isset($_POST['password']) && $_POST['password'] !== '') {
							if(mb_strlen($_POST['password']) < 5 || mb_strlen($_POST['password']) > 32) {
								ErrorMsg("密码最少为 5 个字符，最大为 32 个字符。");
							} else {
								$password = password_hash($_POST['password'], PASSWORD_BCRYPT);
								$passwordSQL = ",`password`='{$password}'";
							}
						} else {
							$passwordSQL = "";
						}
						mysqli_query($pigeon->conn, "UPDATE `users` SET `username`='{$username}',`email`='{$email}',`permission`='{$permission}',`status`='{$status}'{$passwordSQL} WHERE `id`='{$uid}'");
						echo "Successful";
					} else {
						ErrorMsg("请将用户信息填写完整");
					}
				} else {
					ErrorMsg("User not found");
				}
			}
			break;
		case "deleteuser":
			if(!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				ErrorMsg("CSRF 验证失败，请尝试重新登录。");
			}
			if(isset($_GET['id']) && preg_match("/^[0-9]{0,10}$/", $_GET['id'])) {
				$uid = mysqli_real_escape_string($pigeon->conn, $_GET['id']);
				$rs = mysqli_fetch_array(mysqli_query($pigeon->conn, "SELECT * FROM `users` WHERE `id`='{$uid}'"));
				if($rs) {
					mysqli_query($pigeon->conn, "DELETE FROM `users` WHERE `id`='{$rs['id']}'");
					echo "Successful";
				} else {
					ErrorMsg("User not found");
				}
			}
			break;
		case "updatecheck":
			if(!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				ErrorMsg("CSRF 验证失败，请尝试重新登录。");
			}
			$update = @file_get_contents("https://cdn.tcotp.cn:4443/pigeon/");
			echo $update;
			break;
		case "updateexecute":
			if(!isset($_GET['seid']) || $_GET['seid'] !== $_SESSION['seid']) {
				ErrorMsg("CSRF 验证失败，请尝试重新登录。");
			}
			$update = @file_get_contents("https://cdn.tcotp.cn:4443/pigeon/");
			$update = json_decode($update, true);
			if(!$update) {
				ErrorMsg("获取更新信息时出错，请稍后重试。");
			}
			if(!checkPermission("./")) {
				ErrorMsg("网站目录不可写，请修改权限或手动更新。");
			} elseif(!class_exists("ZipArchive")) {
				ErrorMsg("未检测到 ZipArchive 组件，请先修改 php.ini 启用 php_zip 扩展。");
			} else {
				$file = @file_get_contents($update['download']);
				if(strlen($file) == 0) {
					ErrorMsg("下载的文件长度为 0，请检查网络是否正常。");
				} elseif(file_put_contents('update-temp.zip', $file) === false) {
					ErrorMsg("写入文件时发生错误，请检查目录是否有读写权限。");
				} elseif(md5_file('update-temp.zip') !== $update['md5']) {
					@unlink('update-temp.zip');
					ErrorMsg("文件 MD5 验证失败，请尝试重新更新。");
				} else {
					if(unzipUpdateFiles('update-temp.zip', './')) {
						@unlink('update-temp.zip');
						echo "Pigeon 更新成功。";
					} else {
						@unlink('update-temp.zip');
						ErrorMsg("解压文件时发生错误，无法打开文件或解压失败。");
					}
				}
			}
			break;
		default:
			ErrorMsg("Action undefined");
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
				transition-duration:0.5s;
			}
			.userlist, blockquote {
				width: 100%;
				font-size: 14px;
			}
			.userlist tr {
				height: 28px;
			}
			.userlist tr td, .userlist tr th {
				padding-left: 4px;
				padding-right: 4px;
			}
			.userlist tr:nth-child(2n+3), .userlist tr:hover {  
				background-color:#F1F1F1;  
			}
			.userlist a {
				cursor: pointer;
			}
			.t-id {
				text-align: center;
				width: 32px;
			}
			#alert_success, #alert_danger {
				display: none;
			}
		</style>
	</head>
	<body>
		<div class="container">
			<div class="row">
				<div class="col-sm-12 logo">
					<h2><?php echo $pigeonConfig['sitename']; ?></h2>
					<p><?php echo $pigeonConfig['description']; ?></p>
					<hr>
					<div id="alert_success"></div>
					<div id="alert_danger"></div>
				</div>
				<div class="col-sm-3">
					<p><blockquote><b>提示：</b>选择一个用户进行设置</blockquote></p>
					<p>用户名</p>
					<p><input type="text" id="username" class="form-control"></p>
					<p>邮箱</p>
					<p><input type="email" id="email" class="form-control"></p>
					<p>密码</p>
					<p><input type="password" id="password" class="form-control" placeholder="留空则不修改"></p>
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
				<div class="col-sm-9">
					<table class="table userlist table-responsive" id="userlist_table"></table>
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
			var selectid;
			var version = '<?php echo $pigeon->version; ?>';
			var dismiss_success = '<div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>';
			var dismiss_danger = '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>';
			function getUser(id) {
				var htmlobj = $.ajax({
					type: 'GET',
					url: "?s=getuser&id=" + id + "&seid=" + seid,
					async:true,
					error: function() {
						ErrorMsg("错误：" + htmlobj.responseText);
						return;
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
							selectid = id;
						} catch(e) {
							ErrorMsg("错误：" + e.message);
						}
						return;
					}
				});
			}
			function saveUser() {
				if(selectid == undefined) {
					ErrorMsg("您还没有选择要操作的用户");
					return;
				}
				var htmlobj = $.ajax({
					type: 'POST',
					url: "?s=saveuser&id=" + selectid + "&seid=" + seid,
					data: {
						username: $("#username").val(),
						password: $("#password").val(),
						email: $("#email").val(),
						permission: $("#permission").val(),
						status: $("#status").val()
					},
					async:true,
					error: function() {
						ErrorMsg("错误：" + htmlobj.responseText);
						return;
					},
					success: function() {
						SuccessMsg("用户信息保存成功！");
						LoadUserList();
						return;
					}
				});
			}
			function deleteUser() {
				if(selectid == undefined) {
					ErrorMsg("您还没有选择要操作的用户");
					return;
				}
				if(confirm("您确定要删除此用户吗？该操作是不可逆的，请谨慎选择！")) {
					var htmlobj = $.ajax({
						type: 'GET',
						url: "?s=deleteuser&id=" + selectid + "&seid=" + seid,
						async:true,
						error: function() {
							ErrorMsg("错误：" + htmlobj.responseText);
							return;
						},
						success: function() {
							SuccessMsg("用户删除成功！");
							LoadUserList();
							return;
						}
					});
				}
			}
			function LoadUserList() {
				var htmlobj = $.ajax({
					type: 'GET',
					url: "?s=userlist&seid=" + seid,
					async:true,
					error: function() {
						ErrorMsg("错误：" + htmlobj.responseText);
						return;
					},
					success: function() {
						userlist_table.innerHTML = htmlobj.responseText;
						return;
					}
				});
			}
			function CheckNewVersion() {
				var htmlobj = $.ajax({
					type: 'GET',
					url: "?s=updatecheck&seid=" + seid,
					async:true,
					error: function() {
						ErrorMsg("错误：" + htmlobj.responseText);
						return;
					},
					success: function() {
						try {
							var update = JSON.parse(htmlobj.responseText);
							newest_version.innerHTML = update.version;
							if(update.version != version) {
								updatemsg.innerHTML = "发现新更新：" + update.description + "</p><p><button class='btn btn-primary' onclick='updateExecute()'>立即更新</button>";
							} else {
								updatemsg.innerHTML = "已经是最新版本！";
							}
						} catch(e) {
							ErrorMsg(e.message);
						}
						return;
					}
				});
			}
			function updateExecute() {
				if(confirm("您确定要更新吗？更新可能会覆盖您对系统自带模板的修改，但是不会影响您的自定义模板，建议您备份好数据后再执行。\n\n更新可能需要较长时间，请耐心等待，不要关闭网页！")) {
					var htmlobj = $.ajax({
						type: 'GET',
						url: "?s=updateexecute&seid=" + seid,
						async: true,
						timeout: 100000,
						error: function() {
							ErrorMsg("错误：" + htmlobj.responseText);
							return;
						},
						success: function() {
							SuccessMsg(htmlobj.responseText);
							return;
						}
					});
				}
			}
			function SuccessMsg(text) {
				$("#alert_success").html(dismiss_success + text + "</div>");
				$("#alert_success").fadeIn(500);
			}
			function ErrorMsg(text) {
				$("#alert_danger").html(dismiss_danger + text + "</div>");
				$("#alert_danger").fadeIn(500);
			}
			window.onload = function() {
				LoadUserList();
				CheckNewVersion();
			}
		</script>
	</body>
</html>