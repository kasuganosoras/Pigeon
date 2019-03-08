<?php
global $pigeon;
?>
				</div>
				<div class="col-sm-3">
					<?php
					if(isset($_SESSION['user']) && isset($_SESSION['email'])) {
						?>
						<center>
							<img src="https://secure.gravatar.com/avatar/<?php echo md5($_SESSION['email']); ?>?s=256" class="loginhead">
						</center>
						<h3><?php echo $_SESSION['user']; ?></h3>
						<p>欢迎回来！<a href="?s=logout">[退出登录]</a></p>
						<p>你的 Token（可用于 API 发布）</p>
						<p><pre><?php echo $_SESSION['token']; ?></pre></p>
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
					<p>时间格式：<?php echo date("Y-m-d H:i:s"); ?></p>
					<p>
						<div class="input-group">
							<input type="text" id="time" class="form-control">
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
		<script type="text/javascript">
			var auto_refresh = true;
			var ptime = '';
			var puser = "<?php $user = isset($_GET['user']) ? $_GET['user'] : ""; echo str_replace('"', "", $user); ?>";
			function setTime() {
				ptime = $("#time").val();
				RefreshHome();
			}
			function newpost() {
				var htmlobj = $.ajax({
					type: 'POST',
					url: "?s=newpost",
					data: {
						ispublic: $("#ispublic").val(),
						content: $("#newpost").val()
					},
					async:true,
					error: function() {
						alert("错误：" + htmlobj.responseText);
						return;
					},
					success: function() {
						$("#newpost").val("");
						RefreshHome();
						return;
					}
				});
			}
			function RefreshHome() {
				current_page = '1';
				auto_refresh = true;
				var htmlobj = $.ajax({
					type: 'GET',
					url: "?s=timeline&page=1&time=" + ptime + "&user=" + puser,
					async:true,
					error: function() {
						alert("错误：" + htmlobj.responseText);
						return;
					},
					success: function() {
						$("#pagecontent").html(htmlobj.responseText);
						return;
					}
				});
			}
			function loadMore() {
				auto_refresh = false;
				var newPage = parseInt(current_page) + 1;
				var htmlobj = $.ajax({
					type: 'GET',
					url: "?s=timeline&ajax=1&page=" + newPage + "&time=" + ptime + "&user=" + puser,
					async:true,
					error: function() {
						return;
					},
					success: function() {
						$("#pagecontent").append(htmlobj.responseText);
						current_page = newPage;
						$(".loadMore").css({display:'none'});
						return;
					}
				});
			}
			function deletepost(id) {
				auto_refresh = false;
				var htmlobj = $.ajax({
					type: 'GET',
					url: "?s=deletepost&id=" + id,
					async:true,
					error: function() {
						alert("错误：" + htmlobj.responseText);
						return;
					},
					success: function() {
						RefreshHome();
						return;
					}
				});
			}
			window.onload = function() {
				setInterval(function() {
					if(auto_refresh) {
						RefreshHome();
					}
				}, 10000);
			}
		</script>
	</body>
</html>