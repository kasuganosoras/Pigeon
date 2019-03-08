<?php
class Pigeon {
	
	public $cacheData;
	public $writeToCache;
	public $publicMode = true;
	
	/**
	 *
	 *	构造函数，初始化 MySQL 连接
	 *
	 */
	public function __construct() {
		if(!file_exists(ROOT . "/pigeon/config.php")) {
			$this->Exception("Configure file not found");
		} else {
			include(ROOT . "/pigeon/config.php");
			$this->config = $pigeonConfig;
			$this->conn = mysqli_connect(
				$pigeonConfig['mysql']['host'],
				$pigeonConfig['mysql']['user'],
				$pigeonConfig['mysql']['pass'],
				$pigeonConfig['mysql']['name'],
				$pigeonConfig['mysql']['port']
			);
		}
	}
	
	/**
	 *
	 *	reCaptcha 验证函数
	 *
	 */
	public function recaptcha_verify($userdata) {
		if($this->config['recaptcha_key_post'] == '') {
			return true;
		}
		if(empty($userdata)) {
			return false;
		}
		$data = http_build_query(Array(
			'secret' => $this->config['recaptcha_key_post'],
			'response' => $userdata
		));
		$options = Array(
			'http' => Array(
				'method' => 'POST',
				'header' => 'Content-type:application/x-www-form-urlencoded',
				'content' => $data,
				'timeout' => 15 * 60
			)
		);
		$context = stream_context_create($options);
		$result = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
		$json = json_decode($result, true);
		return $json ? $json['success'] : false;
	}
	
	/**
	 *
	 *	读取网站模板
	 *
	 */
	public function getTemplate($name) {
		if(file_exists(ROOT . "/pigeon/template/{$this->config['template']}/{$name}.php")) {
			include(ROOT . "/pigeon/template/{$this->config['template']}/{$name}.php");
		} else {
			return false;
		}
	}
	
	/**
	 *
	 *	读取用户时间线
	 *
	 */
	public function getTimeline($username, $displayHtml = true, $page = 1) {
		if(!$this->conn) {
			return;
		}
		$enable_foruser = true;
		$Markdown = new Parsedown();
		if($this->config['enable_safemode']) {
			if($this->config['enable_foruser']) {
				$enable_foruser = true;
			} else {
				$enable_foruser = false;
			}
		}
		$Markdown->setBreaksEnabled(true);
		$spage = ($page - 1) * 10;
		if(!empty($username)) {
			$username = mysqli_real_escape_string($this->conn, $username);
			$rs = mysqli_query($this->conn, "SELECT * FROM `posts` WHERE `author`='{$username}' AND time <= {$this->before} ORDER BY `id` DESC LIMIT {$spage},10");
		} else {
			$rs = mysqli_query($this->conn, "SELECT * FROM `posts` WHERE time <= {$this->before} ORDER BY `id` DESC LIMIT {$spage},10");
		}
		if($displayHtml) {
			$i = 0;
			$delete = "";
			$html = "<div id='pagecontent'><table style='width: 100%;'>";
			if(isset($_SESSION['user']) && $this->isAdmin($_SESSION['user'])) {
				$delete = "&nbsp;&nbsp;|&nbsp;&nbsp;<span style='cursor: pointer;' onclick='deletepost({id})'>删除</span>";
			}
			while($rw = mysqli_fetch_row($rs)) {
				if($this->isAdmin($rw[2])) {
					$Markdown->setSafeMode(false);
				} else {
					$Markdown->setSafeMode(true);
				}
				if($rw[4] == "1" && !$this->isLogin) {
					continue;
				}
				if($rw[4] == "2" && $_SESSION['user'] !== $rw[2]) {
					continue;
				}
				$pstatus = $rw[4] == '2' ? "&nbsp;&nbsp;<code>仅自己可见</code>" : "";
				$html .= "<tr><td class='headimg'><img src='https://secure.gravatar.com/avatar/" . md5($this->getUserInfo($rw[2])['email']) . "?s=64'</td><td class='thread'><p>{$rw[2]} 发表于" . $this->getDateFormat($rw[3]) . $pstatus . str_replace("{id}", $rw[0], $delete) . "</p>";
				$html .= "<p>" . $Markdown->text($rw[1]) . "</p></td></tr>";
				$i++;
			}
			// 莫名其妙的 bug，未登录用户的循环次数会比已登录的用户少 1 次
			$pagesplit = $this->isLogin ? 10 : 9;
			if($i == 0) {
				if(!$this->isAjax) {
					$html .= "</table><p>这是一只寂寞的鸽子，暂时没有人咕咕咕！</p>";
				} else {
					$this->Exception("<center><p>已经到底啦~</p></center>");
				}
			} elseif($i < $pagesplit) {
				$html .= "</table><center><p>已经到底啦~</p></center>";
			} else {
				$html .= "</table><center class='loadMore'><p style='cursor: pointer;' onclick='loadMore()'>加载更多</p></center>";
			}
			$html .= "<script>var current_page = '{$page}';</script>";
			$html .= "</div>";
			echo $html;
		}
	}
	
	/**
	 *
	 *	获取指定用户信息
	 *
	 */
	private function getUserInfo($username) {
		if(!$this->conn) {
			return false;
		}
		if(empty($username)) {
			return false;
		}
		$username = mysqli_real_escape_string($this->conn, $username);
		$rs = mysqli_fetch_array(mysqli_query($this->conn, "SELECT * FROM `users` WHERE `username`='{$username}'"));
		if($rs) {
			unset($rs['password']);
			return $rs;
		}
		return false;
	}
	
	/**
	 *
	 *	判断是否是管理员权限
	 *
	 */
	public function isAdmin($username) {
		if(!$this->conn) {
			return false;
		}
		if(empty($username)) {
			return false;
		}
		$username = mysqli_real_escape_string($this->conn, $username);
		$rs = mysqli_fetch_array(mysqli_query($this->conn, "SELECT * FROM `users` WHERE `username`='{$username}'"));
		return $rs ? ($rs['permission'] == 'root' || $rs['permission'] == 'admin') : false;
	}
	
	/**
	 *
	 *	发送邮件函数
	 *
	 */
	public function sendMail($mailto, $mailsub, $mailbd) {
		include(ROOT . "/pigeon/smtp.php");
		$smtpemailto = $mailto;
		$mailsubject = $mailsub;
		$mailbody    = $mailbd;
		$mailtype    = "HTML";
		$smtp        = new smtp($this->config['smtp']['host'], $this->config['smtp']['port'], true, $this->config['smtp']['user'], $this->config['smtp']['pass']);
		$smtp->debug = false;
		$smtp->sendmail($smtpemailto, $this->config['smtp']['name'], $mailsubject, $mailbody, $mailtype);
	}
	
	/**
	 *
	 *	获取格式化的日期
	 *
	 */
	private function getDateFormat($time) {
		$nowTime = time();
		if(($nowTime - $time) <= 10) {
			return "几秒前";
		} elseif(($nowTime - $time) <= 60) {
			return " " . ($nowTime - $time) . " 秒前";
		} elseif(($nowTime - $time) <= 3600) {
			return " " . round(($nowTime - $time) / 60) . " 分钟前";
		} elseif(($nowTime - $time) <= 86400) {
			return " " . round(($nowTime - $time) / 3600) . " 小时前";
		} elseif(($nowTime - $time) <= 604800) {
			return " " . round(($nowTime - $time) / 86400) . " 天前";
		} else {
			return " " . date("Y-m-d H:i:s", $time);
		}
	}
	
	/**
	 *
	 *	写出内容
	 *
	 */
	public function write($data) {
		if($this->writeToCache) {
			$this->cacheData .= $data;
		} else {
			echo $data;
		}
	}
	
	/**
	 *
	 *	写出内容（带换行）
	 *
	 */
	public function writeln($data) {
		if($this->writeToCache) {
			$this->cacheData .= $data . "\n";
		} else {
			echo $data . "\n";
		}
	}
	
	/**
	 *
	 *	抛出异常
	 *
	 */
	public function Exception($error) {
		$this->sendHeader(404);
		$this->write($error);
		exit;
	}
	
	/**
	 *
	 *	发送 Header
	 *
	 */
	public function sendHeader($statusCode) {
		if(preg_match("/^[0-9]{0,3}$/", $statusCode) || is_integer($statusCode)) {
			Header("Software: pigeon", true, $statusCode);
		} else {
			Header("Software: pigeon", true, 500);
		}
	}
}