<?php
@require_once("../config.php");
error_reporting(0);
set_time_limit(6);
OB_START();
function curl_request($url, $post = '', $cookie = '', $headers = '', $returnHeader = 0) {
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
function unlinkdir($dir) {
	if($handle = opendir($dir)) {
		while(($item = readdir($handle)) !== false) {
			if($item != "." && $item !== "..") {
				if(is_dir("{$dir}/{$item}")) { 
					unlinkdir("{$dir}/{$item}"); 
				} else {
					unlink("{$dir}/{$item}");
				}
			}
		}
		closedir($handle);
	}
}
function getFilesNum($dir) {
	$i = 0;
	if($handle = opendir($dir)) {
		while(($item = readdir($handle)) !== false) {
			if($item != "." && $item !== "..") {
				$i++;
			}
		}
		closedir($handle);
	}
	return $i;
}
function decrypt($str, $localIV, $encryptKey) {
	return openssl_decrypt($str, 'AES-256-CFB', $encryptKey, 0, $localIV);
}
$cache_list = Array(
	'jpg' => true,
	'png' => true,
	'bmp' => true,
	'gif' => true
);
if(!isset($_GET['url'])) {
	Header("Content-type: image/png", true, 502);
	readfile("502.png");
	exit;
}
$url = base64_decode($_GET['url']);
// 防止死循环请求
if(stristr($url, "imgproxy")) {
	exit("403 Forbidden");
}
// 最大缓存文件数量
$max_cache = 30;
// 最大缓存文件大小
$max_cache_length = 1024 * 1024 * 10;
$pathtype = pathinfo($url)['extension'];
$filecache = "cache/" . date("Ymd") . "/" . urlencode(base64_encode($url));
if(getFilesNum("cache/") > $max_cache) {
	unlinkdir("cache/");
}
if(isset($cache_list[$pathtype])) {
	if(!file_exists("cache/" . date("Ymd") . "/")) {
		unlinkdir("cache/");
		mkdir("cache/" . date("Ymd") . "/");
	} else {
		if(file_exists($filecache)) {
			$finfo = finfo_open(FILEINFO_MIME);
			$mimetype = finfo_file($finfo, $filecache);
			finfo_close($finfo);
			if(stristr($mimetype, "image/")) {
				Header("Content-type: {$mimetype}");
				readfile($filecache);
			} else {
				Header("Content-type: image/png", true, 502);
				readfile("502.png");
				exit;
			}
			exit;
		}
	}
}
$data = curl_request($url);
if(isset($data['header']) && isset($data['body'])) {
	$header = $data['header'];
	$exp = explode("\r\n", $header);
	foreach($exp as $hd) {
		if(stristr($hd, "Content-type:") || stristr($hd, "Content-length:")) {
			Header($hd, true);
		}
	}
	$body = $data['body'];
	if(isset($cache_list[$pathtype])) {
		if(strlen($body) < $max_cache_length) {
			@file_put_contents($filecache, $body);
		}
	}
	echo $body;
} else {
	Header("Content-type: image/png", true, 502);
	readfile("502.png");
	exit;
}