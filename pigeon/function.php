<?php
class Pigeon
{

	public $cacheData;
	public $writeToCache;
	public $publicMode = true;
	public $version = "1.0.182";

	/**
	 *
	 *	构造函数，初始化 MySQL 连接
	 *
	 */
	public function __construct()
	{
		if (!file_exists(ROOT . "/pigeon/config.php")) {
			$this->Exception("Configure file not found");
		} else {
			include(ROOT . "/pigeon/config.php");
			$this->config   = $pigeonConfig;
			$this->gravatar = $this->config['gravatar_mirror'];
			$this->conn     = new PDO(
				sprintf(
					'mysql:host=%s;port=%s;dbname=%s',
					$pigeonConfig['mysql']['host'],
					$pigeonConfig['mysql']['port'],
					$pigeonConfig['mysql']['name']
				),
				$pigeonConfig['mysql']['user'],
				$pigeonConfig['mysql']['pass']
			);
			$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->conn->exec("SET NAMES 'utf8mb4'");
		}
	}

	/**
	 *
	 *	createSession 创建新的会话
	 *
	 */
	public function createSession()
	{
		session_start();
		if (isset($_SESSION['destroyed'])) {
			if ($_SESSION['destroyed'] < time() - 300) {
				unset($_SESSION['user']);
				unset($_SESSION['email']);
				unset($_SESSION['token']);
				remove_all_authentication_flag_from_active_sessions($_SESSION['userid']);
				exit();
			}
			if (isset($_SESSION['new_session_id'])) {
				session_commit();
				session_id($_SESSION['new_session_id']);
				session_start();
				return;
			}
		}
	}

	/**
	 *
	 *	updateSessionId 更新会话 ID
	 *
	 */
	public function updateSessionId()
	{
		$newSessionId = session_regenerate_id();
		$_SESSION['new_session_id'] = $newSessionId;
		$_SESSION['destroyed'] = time();
		session_commit();
		session_id($newSessionId);
		ini_set('session.use_strict_mode', 0);
		session_start();
		@ini_set('session.use_strict_mode', 1);
		unset($_SESSION['destroyed']);
		unset($_SESSION['new_session_id']);
	}

	/**
	 *
	 *	reCaptcha 验证函数
	 *
	 */
	public function recaptchaVerification($userData)
	{
		if ($this->config['recaptcha_key_post'] == '') {
			return true;
		}
		if (empty($userdata)) {
			return false;
		}
		$postData = http_build_query(array(
			'secret' => $this->config['recaptcha_key_post'],
			'response' => $userData
		));
		$options = array(
			'http' => array(
				'method' => 'POST',
				'header' => 'Content-type:application/x-www-form-urlencoded',
				'content' => $postData,
				'timeout' => 15 * 60
			)
		);
		$context = stream_context_create($options);
		$result = file_get_contents('https://recaptcha.net/recaptcha/api/siteverify', false, $context);
		$json = json_decode($result, true);
		return $json ? $json['success'] : false;
	}

	/**
	 *
	 *	读取网站模板
	 *
	 */
	public function getTemplate($name)
	{
		if (file_exists(ROOT . "/pigeon/template/{$this->config['template']}/{$name}.php")) {
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
	public function getTimeline($userName, $displayHtml = true, $page = 1)
	{
		if (!$this->conn) {
			return;
		}
		$markdown = new Parsedown();
		$markdown->setBreaksEnabled(false);
		$sPage = ($page - 1) * 10;
		$loginUser = isset($_SESSION['user']) ? $_SESSION['user'] : "";
		$isAdmin = $this->isAdmin($loginUser);

		// 一大堆 SQL 语句，不管这么多了写就是了
		$beforeSql = $this->before ? " AND time <= {$this->before}" : "";
		$searchSql = $this->search ? " AND (POSITION(:search IN `content`) OR POSITION(:search IN `author`))" : "";
		$userSql2  = $this->isLogin ? ($isAdmin ? " WHERE (`public`='0' OR `public`='1' OR `public`='2')" : " WHERE (`public`='0' OR `public`='1' OR (`public`='2' AND `author`='{$_SESSION['user']}'))") : " WHERE `public`='0'";

		// 到这里开始查询
		if (!empty($userName)) {
			$stmt = $this->conn->prepare("SELECT * FROM `posts` WHERE `author`=:username{$beforeSql}{$searchSql} ORDER BY `id` DESC LIMIT :sPage,10");
			$stmt->bindParam(':username', $userName);
			$stmt->bindParam(':sPage', $sPage, PDO::PARAM_INT);
			if ($this->search) {
				$stmt->bindParam(':search', $this->search);
			}
			$stmt->execute();
			$rs = $stmt->fetchAll();
		} else {
			$stmt = $this->conn->prepare("SELECT * FROM `posts`{$userSql2} {$beforeSql}{$searchSql} ORDER BY `id` DESC LIMIT :sPage,10");
			$stmt->bindParam(':sPage', $sPage, PDO::PARAM_INT);
			if ($this->search) {
				$stmt->bindParam(':search', $this->search);
			}
			$stmt->execute();
			$rs = $stmt->fetchAll();
		}
		if ($displayHtml) {
			$i      = 0;
			$manage = "";
			$ids    = "";
			$html   = "<div id='pagecontent'><table style='width: 100%;'>";
			if ($isAdmin) {
				$manage = "<span class='hoverdisplay'>&nbsp;&nbsp;|&nbsp;&nbsp;<a style='cursor: pointer;' onclick='edit({id})'>编辑</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a style='cursor: pointer;' onclick='deletepost({id})'>删除</a>&nbsp;&nbsp;|&nbsp;&nbsp;设置状态 &lt;<a style='cursor: pointer;' onclick='changepublic({id}, 0)'>公开</a> | <a style='cursor: pointer;' onclick='changepublic({id}, 1)'>登录可见</a> | <a style='cursor: pointer;' onclick='changepublic({id}, 2)'>仅作者可见</a>&gt;</span>";
			} elseif ($loginUser !== "") {
				$manage = "<span class='hoverdisplay'>&nbsp;&nbsp;|&nbsp;&nbsp;<a style='cursor: pointer;' onclick='edit({id})'>编辑</a></span>";
			}
			foreach ($rs as $rw) {
				$ids        .= "{$rw['id']},";
				$tempIsAdmin = $this->isAdmin($rw['author']);
				$i++;
				if ($this->config['enable_safemode']) {
					if ($this->config['enable_foruser']) {
						if ($tempIsAdmin) {
							$markdown->setSafeMode(false);
						} else {
							$markdown->setSafeMode(true);
						}
					} else {
						$markdown->setSafeMode(true);
					}
				}
				if ($rw['public'] == "1" && !$this->isLogin) {
					continue;
				}
				if ($rw['public'] == "2" && $loginUser !== $rw['author']) {
					if (!$isAdmin) {
						continue;
					}
				}
				$sManage  = ($rw['author'] == $loginUser || $isAdmin) ? $manage : "";
				$pStatus  = $rw['public'] == '2' ? "&nbsp;&nbsp;<code>仅自己可见</code>" : "";
				$gravatar = $this->gravatar;
				$viewLink = $this->config['enable_rewrite'] ? "/msg/{$rw['id']}" : "?s=msg&id={$rw['id']}";
				$html    .= "<tr><td class='headimg'><img src='" . $gravatar . md5($this->getUserInfo($rw['author'])['email']) . "?s=64'</td><td class='thread'><p><small>{$rw['author']} 发表于" . $this->getDateFormat($rw['time']) . "&nbsp;&nbsp;<a href='{$viewLink}' target='_blank'><i class='fa fa-external-link'></i></a>" . $pStatus . str_replace("{id}", $rw['id'], $sManage) . "</small></p>";
				$html    .= "<div class='message'>" . $markdown->text($rw['content']) . "</div></td></tr>";
			}
			if ($i == 0) {
				if (!$this->isAjax) {
					$html .= "</table><p>这是一只寂寞的鸽子，暂时没有人咕咕咕！</p>";
				} else {
					$this->Exception("<center><p>已经到底啦~</p></center>");
				}
			} elseif ($i <= 9) {
				$html .= "</table><center><p>已经到底啦~</p></center>";
			} else {
				$html .= "</table><center class='loadMore'><p style='cursor: pointer;' onclick='loadMore()'>加载更多</p></center>";
			}
			$html .= "<script>var current_page = '{$page}';</script>";
			$html .= "</div>";
			@Header("ids: {$ids}");
			$_SESSION['ids'] = $ids;
			echo $html;
		}
	}

	/**
	 *
	 *	获取指定消息
	 *
	 */
	public function getMessageById($id)
	{
		if (!preg_match("/^[0-9]{1,10}$/", $id)) {
			return false;
		}
		if (!$this->conn) {
			return false;
		}
		$manage = "";
		$markdown = new Parsedown();
		$markdown->setBreaksEnabled(false);
		$stmt = $this->conn->prepare("SELECT * FROM `posts` WHERE `id`=:id");
		$stmt->bindParam(':id', $id);
		$stmt->execute();
		$rs = $stmt->fetch();
		$this->isLogin = (isset($_SESSION['user']) && $_SESSION['user'] !== '');
		if ($rs) {
			$html = "<div id='pagecontent'><table style='width: 100%;'>";
			if (isset($_SESSION['user']) && $this->isAdmin($_SESSION['user'])) {
				$manage = "<span class='hoverdisplay'>&nbsp;&nbsp;|&nbsp;&nbsp;<a style='cursor: pointer;' onclick='deletepost({id})'>删除</a>&nbsp;&nbsp;|&nbsp;&nbsp;设置状态 &lt;<a style='cursor: pointer;' onclick='changepublic({id}, 0)'>公开</a> | <a style='cursor: pointer;' onclick='changepublic({id}, 1)'>登录可见</a> | <a style='cursor: pointer;' onclick='changepublic({id}, 2)'>仅作者可见</a>&gt;</span>";
			}
			$loginUser = isset($_SESSION['user']) ? $_SESSION['user'] : "";
			$isAdmin = $this->isAdmin($loginUser);
			if ($this->config['enable_safemode']) {
				if ($this->config['enable_foruser']) {
					if ($this->isAdmin($rs['author'])) {
						$markdown->setSafeMode(false);
					} else {
						$markdown->setSafeMode(true);
					}
				} else {
					$markdown->setSafeMode(true);
				}
			}
			if ($rs['public'] == "1" && !$this->isLogin) {
				return false;
			}
			if ($rs['public'] == "2" && $loginUser !== $rs['author']) {
				if (!$isAdmin) {
					return false;
				}
			}
			$pStatus  = $rs['public'] == '2' ? "&nbsp;&nbsp;<code>仅自己可见</code>" : "";
			$gravatar = $this->gravatar;
			$html .= "<tr><td class='headimg'><img src='" . $gravatar . md5($this->getUserInfo($rs['author'])['email']) . "?s=64'</td><td class='thread'><p><small>{$rs['author']} 发表于" . $this->getDateFormat($rs['time']) . $pStatus . str_replace("{id}", $id, $manage) . "</small></p>";
			$html .= "<div class='message'>" . $markdown->text($rs['content']) . "</div></td></tr>";
			$html .= "</table></div>";
			return $html;
		} else {
			return false;
		}
	}

	/**
	 *
	 *	获取指定消息原始内容
	 *
	 */
	public function getRawMessageById($id)
	{
		if (!preg_match("/^[0-9]{1,10}$/", $id)) {
			return false;
		}
		if (!$this->conn) {
			return false;
		}
		$stmt = $this->conn->prepare("SELECT * FROM `posts` WHERE `id`=:id");
		$stmt->bindParam(':id', $id);
		$stmt->execute();
		$rs = $stmt->fetch();
		$this->isLogin = (isset($_SESSION['user']) && $_SESSION['user'] !== '');
		$loginUser     = isset($_SESSION['user']) ? $_SESSION['user'] : "";
		$isAdmin       = $this->isAdmin($loginUser);
		if ($rs) {
			if ($rs['public'] == "1" && !$this->isLogin) {
				return false;
			}
			if ($rs['public'] == "2" && $loginUser !== $rs['author']) {
				if (!$isAdmin) {
					return false;
				}
			}
			return [
				'content' => $rs['content'],
				'author'  => $rs['author'],
				'time'    => $rs['time'],
				'public'  => $rs['public']
			];
		} else {
			return false;
		}
	}

	/**
	 *
	 *	获取指定用户信息
	 *
	 */
	private function getUserInfo($userName)
	{
		if (!$this->conn) {
			return false;
		}
		if (empty($userName)) {
			return false;
		}
		$stmt = $this->conn->prepare("SELECT * FROM `users` WHERE `username`=:username");
		$stmt->bindParam(':username', $userName);
		$stmt->execute();
		$rs = $stmt->fetch();
		if ($rs) {
			unset($rs['password']);
			return $rs;
		}
		return false;
	}

	/**
	 *
	 *	根据 Token 获取用户信息
	 *
	 */
	public function getUserByToken($token)
	{
		if (!$this->conn) {
			return false;
		}
		if (empty($token)) {
			return false;
		}
		$stmt = $this->conn->prepare("SELECT * FROM `users` WHERE `token`=:token");
		$stmt->bindParam(':token', $token);
		$stmt->execute();
		$rs = $stmt->fetch();
		if ($rs) {
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
	public function isAdmin($userName)
	{
		if (!$this->conn) {
			return false;
		}
		if (empty($userName)) {
			return false;
		}
		$stmt = $this->conn->prepare("SELECT * FROM `users` WHERE `username`=:username");
		$stmt->bindParam(':username', $userName);
		$stmt->execute();
		$rs = $stmt->fetch();
		return $rs ? ($rs['permission'] == 'root' || $rs['permission'] == 'admin') : false;
	}

	/**
	 *
	 *	发送邮件函数
	 *
	 */
	public function sendMail($mailTo, $mailSubject, $mailBody)
	{
		include(ROOT . "/pigeon/smtp.php");
		$smtp        = new smtp($this->config['smtp']['host'], $this->config['smtp']['port'], true, $this->config['smtp']['user'], $this->config['smtp']['pass']);
		$smtp->debug = false;
		$smtp->sendmail($mailTo, $this->config['smtp']['name'], $mailSubject, $mailBody, "HTML");
	}

	/**
	 *
	 *	获取格式化的日期
	 *
	 */
	private function getDateFormat($time)
	{
		$nowTime = time();
		if (($nowTime - $time) <= 10) {
			return "几秒前";
		} elseif (($nowTime - $time) <= 60) {
			return " " . ($nowTime - $time) . " 秒前";
		} elseif (($nowTime - $time) <= 3600) {
			return " " . round(($nowTime - $time) / 60) . " 分钟前";
		} elseif (($nowTime - $time) <= 86400) {
			return " " . round(($nowTime - $time) / 3600) . " 小时前";
		} elseif (($nowTime - $time) <= 604800) {
			return " " . round(($nowTime - $time) / 86400) . " 天前";
		} else {
			return " " . date("Y-m-d H:i:s", $time);
		}
	}

	/**
	 *
	 *	生成 UUID
	 *
	 */
	public function guid()
	{
		if (function_exists('com_create_guid')) {
			return com_create_guid();
		} else {
			mt_srand((float)microtime() * 10000);
			$charId = strtoupper(md5(uniqid(rand(), true)));
			$uuid = substr($charId, 0, 8) . "-"
				. substr($charId, 8, 4) . "-"
				. substr($charId, 12, 4) . "-"
				. substr($charId, 16, 4) . "-"
				. substr($charId, 20, 12);
			return $uuid;
		}
	}

	/**
	 *
	 *	写出内容
	 *
	 */
	public function write($data)
	{
		if ($this->writeToCache) {
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
	public function writeln($data)
	{
		if ($this->writeToCache) {
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
	public function Exception($error)
	{
		$this->sendHeader(403);
		$this->write($error);
		exit;
	}

	/**
	 *
	 *	发送 Header
	 *
	 */
	public function sendHeader($statusCode)
	{
		if (preg_match("/^[0-9]{0,3}$/", $statusCode) || is_integer($statusCode)) {
			Header("Software: pigeon", true, $statusCode);
		} else {
			Header("Software: pigeon", true, 500);
		}
	}
}
