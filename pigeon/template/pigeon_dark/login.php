<?php
global $pigeon, $error, $alert;
?>
					<center>
						<h3>登录 <?php echo $pigeon->config['sitename']; ?> </h3>
						<p>登录之后即可发表内容</p>
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
										<input type="text" placeholder="账号" name="username" class="form-control" required style="box-shadow: none;height: 42px;border-radius: 0px 6px 0px 0px;">
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
									<p><button type="submit" class="btn btn-primary" style="width: 100%;">登录</button></p>
									<p>还没有账号？<a href="?s=register">立即注册！</a></p>
								</form>
							</div>
							<div class="col-sm-3"></div>
					</center>