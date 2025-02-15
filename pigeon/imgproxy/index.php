<?php
@require_once("../config.php");

error_reporting(0);
set_time_limit(6);
SESSION_START();

// 最大缓存文件数量
$max_cache = 30;
// 最大缓存文件大小
$max_cache_length = 1024 * 1024 * 10;
// 缓存文件类型
$cache_list = array(
	'jpg' => true,
	'png' => true,
	'bmp' => true,
	'gif' => true
);

function curl_request($url, $post = '', $cookie = '', $headers = '', $return_headers = 0)
{
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
	curl_setopt($curl, CURLOPT_REFERER, $url);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	if ($post) {
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

function unlinkdir($dir)
{
	if ($handle = opendir($dir)) {
		while (($item = readdir($handle)) !== false) {
			if ($item != "." && $item !== "..") {
				if (is_dir("{$dir}/{$item}")) {
					unlinkdir("{$dir}/{$item}");
				} else {
					unlink("{$dir}/{$item}");
				}
			}
		}
		closedir($handle);
	}
}

function getFilesNum($dir)
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

function decrypt($str, $localIV, $encryptKey)
{
	return openssl_decrypt($str, 'AES-256-CFB', $encryptKey, 0, $localIV);
}

function validRequest()
{
	if (!isset($_GET['url'], $_GET['token'])) {
		errorImage();
	}

	$token  = $_GET['token'];
	$url    = base64_decode($_GET['url']);
	$parse  = parse_url($url);
	$host   = isset($parse['host']) ? $parse['host'] : '';
	$scheme = isset($parse['scheme']) ? $parse['scheme'] : '';

	if ($token !== sha1($url . $_SESSION['seid'])) {
		errorImage();
	}

	// 防止死循环请求
	if (stristr($url, "imgproxy")) {
		errorImage();
	}

	// 防止请求非法域名/内网IP
	if (!isset($scheme) || empty($scheme)) {
		errorImage();
	}

	// 只允许 http 和 https 协议
	if (!in_array($scheme, array('http', 'https'))) {
		errorImage();
	}

	// 先判断是否是 IP，如果不是就先解析域名
	if (!filter_var($host, FILTER_VALIDATE_IP)) {
		$host = gethostbyname($host);
	}

	// 判断是否是内网 IP
	if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
		errorImage();
	}

	// 验证通过，返回 URL
	return $url;
}

function processCache($url)
{
	global $cache_list, $max_cache;
	$pathtype = pathinfo($url)['extension'];
	$filecache = sprintf("cache/%s/%s", date("Ymd"), urlencode(base64_encode($url)));
	if (getFilesNum("cache/") > $max_cache) {
		unlinkdir("cache/");
	}
	if (isset($cache_list[$pathtype])) {
		if (!file_exists("cache/" . date("Ymd") . "/")) {
			unlinkdir("cache/");
			mkdir("cache/" . date("Ymd") . "/");
		} else {
			if (file_exists($filecache)) {
				$finfo = finfo_open(FILEINFO_MIME);
				$mimetype = finfo_file($finfo, $filecache);
				finfo_close($finfo);
				if (stristr($mimetype, "image/")) {
					Header("Content-type: {$mimetype}");
					readfile($filecache);
				} else {
					errorImage();
				}
				return true;
			}
		}
	}
	return false;
}

function cacheFile($url)
{
	global $cache_list, $max_cache_length;
	$pathtype  = pathinfo($url)['extension'];
	$filecache = sprintf("cache/%s/%s", date("Ymd"), urlencode(base64_encode($url)));
	$data = curl_request($url);
	if (isset($data['header']) && isset($data['body'])) {
		$body = $data['body'];
		if (isset($cache_list[$pathtype])) {
			if (strlen($body) < $max_cache_length) {
				safeSaveImage($body, $filecache);
			}
		}
		safeSaveImage($body);
	} else {
		errorImage();
	}
}

function safeSaveImage($data, $path = false)
{
	$img = @imagecreatefromstring($data);
	if ($img) {
		// resize image
		$width = imagesx($img);
		$height = imagesy($img);
		$tmp = imagecreatetruecolor($width, $height);
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
		errorImage();
	}
}

function errorImage()
{
	Header("Content-type: image/png", true, 502);
	readfile("502.png");
	exit;
}

$url = validRequest();
if (!processCache($url)) {
	cacheFile($url);
}
