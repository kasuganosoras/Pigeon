<?php
@require_once("../config.php");

error_reporting(0);
set_time_limit(6);
SESSION_START();

class ImageProxy {

	// 最大缓存文件数量
	private $max_cache = 30;
	// 最大缓存文件大小
	private $max_cache_length = 1024 * 1024 * 10;
	// 缓存文件类型
	private $cache_list = [
		'jpg' => true,
		'png' => true,
		'bmp' => true,
		'gif' => true
	];

	private function httpRequest($url, $post = false, $cookie = false, $headers = false, $return_headers = 0)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
		curl_setopt($curl, CURLOPT_REFERER, $url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		if ($post && is_array($post)) {
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
		}
		if ($cookie) {
			curl_setopt($curl, CURLOPT_COOKIE, $cookie);
		}
		if ($headers) {
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		}
		curl_setopt($curl, CURLOPT_HEADER, 1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 5);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($curl);
		if (curl_errno($curl)) {
			return curl_error($curl);
		}
		curl_close($curl);
		list($header, $body) = explode("\r\n\r\n", $data, 2);
		$info['header'] = $header;
		$info['body'] = $body;
		return $info;
	}

	private function unlinkFolder($dir)
	{
		if ($handle = opendir($dir)) {
			while (($item = readdir($handle)) !== false) {
				if ($item != "." && $item !== "..") {
					if (is_dir("{$dir}/{$item}")) {
						$this->unlinkFolder("{$dir}/{$item}");
					} else {
						unlink("{$dir}/{$item}");
					}
				}
			}
			closedir($handle);
		}
	}

	private function getFilesNum($dir)
	{
		$i = 0;
		if ($handle = opendir($dir)) {
			while (($item = readdir($handle)) !== false) {
				if ($item != "." && $item !== "..") {
					$i++;
				}
			}
			closedir($handle);
		}
		return $i;
	}

	private function validRequest()
	{
		if (!isset($_GET['url'], $_GET['token'])) {
			$this->errorImage();
		}

		$token  = $_GET['token'];
		$url    = base64_decode($_GET['url']);
		$parse  = parse_url($url);
		$host   = isset($parse['host']) ? $parse['host'] : '';
		$scheme = isset($parse['scheme']) ? $parse['scheme'] : '';

		if ($token !== sha1($url . $_SESSION['seid'])) {
			$this->errorImage();
		}

		// 防止死循环请求
		if (stristr($url, "imgproxy")) {
			$this->errorImage();
		}

		// 防止请求非法域名/内网IP
		if (!isset($scheme) || empty($scheme)) {
			$this->errorImage();
		}

		// 只允许 http 和 https 协议
		if (!in_array($scheme, array('http', 'https'))) {
			$this->errorImage();
		}

		// 先判断是否是 IP，如果不是就先解析域名
		if (!filter_var($host, FILTER_VALIDATE_IP)) {
			$host = gethostbyname($host);
		}

		// 判断是否是内网 IP
		if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			$this->errorImage();
		}

		// 验证通过，返回 URL
		return $url;
	}

	private function processCache($url)
	{
		$fileExts  = pathinfo($url)['extension'];
		$fileCache = sprintf("cache/%s/%s", date("Ymd"), urlencode(base64_encode($url)));
		if ($this->getFilesNum("cache/") > $this->max_cache) {
			$this->unlinkFolder("cache/");
		}
		if (isset($this->cache_list[$fileExts])) {
			$cacheFolder = sprintf("cache/%s/", date("Ymd"));
			if (!file_exists($cacheFolder)) {
				mkdir($cacheFolder);
			} else {
				if (file_exists($fileCache)) {
					$finfo = finfo_open(FILEINFO_MIME);
					$mimeType = finfo_file($finfo, $fileCache);
					finfo_close($finfo);
					if (stristr($mimeType, "image/")) {
						Header("Content-Type: {$mimeType}");
						readfile($fileCache);
					} else {
						$this->errorImage();
					}
					return true;
				}
			}
		}
		return false;
	}

	private function cacheFile($url)
	{
		$fileExts  = pathinfo($url)['extension'];
		$fileCache = sprintf("cache/%s/%s", date("Ymd"), urlencode(base64_encode($url)));
		$httpResp  = $this->httpRequest($url);

		if (isset($httpResp['header']) && isset($httpResp['body'])) {
			$body = $httpResp['body'];
			if (isset($this->cache_list[$fileExts])) {
				if (strlen($body) < $this->max_cache_length) {
					$this->safeSaveImage($body, $fileCache);
				}
			}
			$this->safeSaveImage($body);
		} else {
			$this->errorImage();
		}
	}

	private function safeSaveImage($data, $path = false)
	{
		$img = @imagecreatefromstring($data);
		if ($img) {
			// resize image
			$width  = imagesx($img);
			$height = imagesy($img);
			$tmp    = imagecreatetruecolor($width, $height);
			// transparent background
			imagealphablending($tmp, false);
			imagesavealpha($tmp, true);
			$transparent = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
			imagefilledrectangle($tmp, 0, 0, $width, $height, $transparent);
			imagecopyresampled($tmp, $img, 0, 0, 0, 0, $width, $height, $width, $height);
			imagedestroy($img);
			if ($path) {
				imagepng($tmp, $path);
			} else {
				header("Content-type: image/png");
				imagepng($tmp);
			}
			imagedestroy($tmp);
		} else {
			$this->errorImage();
		}
	}

	private function errorImage()
	{
		Header("Content-type: image/png", true, 502);
		readfile("502.png");
		exit;
	}

	public function run()
	{
		$url = $this->validRequest();
		if (!$this->processCache($url)) {
			$this->cacheFile($url);
		}
	}
}

$imgProxy = new ImageProxy();
$imgProxy->run();
