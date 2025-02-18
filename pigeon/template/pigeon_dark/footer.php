<?php
global $pigeon;
if (!$pigeon) {
	exit();
}
?>
</div>
<div class="col-sm-3">
	<p>
	<div class="input-group">
		<input type="text" id="search" class="form-control" placeholder="搜索">
		<span class="input-group-btn">
			<button class="btn btn-primary" onclick="search()" style="height: 34px;"><i class="fa fa-search"></i></button>
		</span>
	</div>
	</p>
	<?php
	if (isset($_SESSION['user']) && isset($_SESSION['email'])) {
	?>
		<center>
			<img src="<?php echo $pigeon->config['gravatar_mirror'] . md5($_SESSION['email']); ?>?s=256" class="loginhead">
		</center>
		<h3><?php echo $_SESSION['user']; ?></h3>
		<p>欢迎回来！<a href="?s=logout&seid=<?php echo isset($_SESSION['seid']) ? $_SESSION['seid'] : ""; ?>">[退出登录]</a></p>
		<p>你的 Token（可用于 API 发布）</p>
		<p>
		<pre><?php echo $_SESSION['token']; ?></pre>
		</p>
	<?php
	} else {
	?>
		<p>欢迎来到本站，请登陆。</p>
		<div class="row">
			<div class="col-sm-6">
				<p><a href="?s=login"><button class="btn btn-primary right-btn">立即登录</button></a></p>
			</div>
			<div class="col-sm-6">
				<p><a href="?s=register"><button class="btn btn-success right-btn">注册账号</button></a></p>
			</div>
		</div>
	<?php
	}
	?>
	<hr>
	<p><b>输入一个时间来进行筛选</b></p>
	<p>
	<div class="input-group">
		<input type="datetime-local" id="time" class="form-control">
		<span class="input-group-btn">
			<button class="btn btn-primary" onclick="setTime()">确定</button>
		</span>
	</div>
	</p>
</div>
</div>
<div class="row">
	<div class="col-sm-12">
		<hr>
		<p>&copy; <?php echo date("Y"); ?> <?php echo $pigeon->config['sitename']; ?> | Powered by <a href="https://github.com/kasuganosoras/Pigeon" target="_blank">Pigeon</a></p>
	</div>
</div>
<script src="https://apps.bdimg.com/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.13.1/highlight.min.js"></script>
<script src="https://imgchr.com/sdk/pup.js" data-url="https://imgchr.com/upload" data-auto-insert="markdown-embed-medium" async id="chevereto-pup-src"></script>
<script src="pigeon/template/<?php echo $pigeon->config['template']; ?>/js/highlight.pack.js"></script>
<?php
if (isset($_GET['s']) && ($_GET['s'] == 'login' || $_GET['s'] == 'register') && $pigeon->config['recaptcha_key'] !== '') {
	echo '<script src="https://recaptcha.net/recaptcha/api.js" async defer></script>';
}
?>
<script type="text/javascript">
	var seid = '<?php echo isset($_SESSION['seid']) ? $_SESSION['seid'] : ""; ?>';
	var autoRefresh = true;
	var ptime = '';
	var psearch = '';
	var puser = "<?php $user = (isset($_GET['user']) && preg_match("/^[A-Za-z0-9\_\-]{0,32}$/", $_GET['user'])) ? $_GET['user'] : "";
					echo str_replace('"', "", $user); ?>";
	var storage = '<?php echo $_SESSION['ids']; ?>';
	var dismissSuccess = '<div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>';
	var dismissDanger = '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>';
	var isBlur = false;
	var pageTitle = document.title;
	hljs.initHighlightingOnLoad();
</script>
<script src="pigeon/template/<?php echo $pigeon->config['template']; ?>/js/main.js"></script>
</body>

</html>