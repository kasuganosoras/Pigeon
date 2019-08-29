<?php
global $pigeon, $error, $alert;
if(!$pigeon) {
	exit();
}
$temp_user = "";
$temp_email = "";
if(preg_match("/^[A-Za-z0-9\_\-]+$/", $_POST['username'])) {
	$temp_user = isset($_POST['username']) ? $_POST['username'] : "";
}
if(preg_match("/^[a-zA-Z0-9]+([-_.][a-zA-Z0-9]+)*@([a-zA-Z0-9]+[-.])+([a-z]{2,5})$/ims", $_POST['email'])) {
	$temp_email = isset($_POST['email']) ? $_POST['email'] : "";
}
?>
					<center>
						<h3>注册 <?php echo $pigeon->config['sitename']; ?> </h3>
						<p>欢迎加入我们，一起参与创作</p>
						<div class="row">
							<div class="col-sm-3"></div>
							<div class="col-sm-6">
								<?php
								if($error !== '') {
									echo "<div class='alert alert-{$alert}'>{$error}</div>";
								}
								?>
								<form method="POST">
									<input type="hidden" name="seid" value="<?php echo isset($_SESSION['seid']) ? $_SESSION['seid'] : ""; ?>" />
									<div class="input-group">
										<span class="input-group-addon" style="border-radius: 6px 0px 0px 0px;"><i class="fa fa-user" style="width: 16px;"></i></span>
										<input type="text" placeholder="账号" value="<?php echo htmlspecialchars($temp_user); ?>" name="username" class="form-control" required style="box-shadow: none;height: 42px;border-radius: 0px 6px 0px 0px;">
									</div>
									<div class="input-group">
										<span class="input-group-addon" style="border-top: 0px;border-radius: 0px;"><i class="fa fa-envelope-o" style="width: 16px;"></i></span>
										<input type="email" placeholder="邮箱" value="<?php echo htmlspecialchars($temp_email); ?>" name="email" class="form-control" required style="box-shadow: none;height: 41px;border-radius: 0px;border-top: 0px;">
									</div>
									<div class="input-group">
										<span class="input-group-addon" style="border-top: 0px;border-radius: 0px 0px 0px 6px;"><i class="fa fa-key" style="width: 16px;"></i></span>
										<input type="password" placeholder="密码" name="password" class="form-control" required style="box-shadow: none;height: 41px;border-radius: 0px 0px 6px 0px;border-top: 0px;">
									</div>
									<br>
									<?php
									if($pigeon->config['recaptcha_key'] !== '') {
									?>
									<div class="g-recaptcha" data-sitekey="<?php echo $pigeon->config['recaptcha_key']; ?>"></div>
									<br>
									<?php
									}
									?>
									<p><button type="submit" class="btn btn-primary" style="width: 100%;">注册</button></p>
									<p>已经注册过账号？<a href="?s=login">立即登录！</a></p>
								</form>
							</div>
							<div class="col-sm-3"></div>
					</center>