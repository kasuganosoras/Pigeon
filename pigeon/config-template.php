<?php
// 请配置这里
$pigeonConfig = [
	// 数据库配置
	'mysql' => [
		'host' => '{DB_HOST}',
		// 数字
		'port' => {DB_PORT},
		'user' => '{DB_USER}',
		'pass' => '{DB_PASS}',
		'name' => '{DB_NAME}'
	],
	// SMTP 发送邮件设置
	'smtp' => [
		// 是否开启注册邮件验证 （true/false）
		'enable' => {ENABLE_SMTP},
		'host'   => '{SMTP_HOST}',
		'port'   => {SMTP_PORT},
		'user'   => '{SMTP_USER}',
		'pass'   => '{SMTP_PASS}',
		'name'   => '{SMTP_NAME}'
	],
	// 模板名称
	'template'           => 'pigeon',
	// 站点名称（显示在标题和网页上）
	'sitename'           => '{SITENAME}',
	// 站点介绍（显示在网页上）
	'description'        => '{DESCRIPTION}',
	// 允许注册（true/false）
	'enable_register'    => {ENABLE_REGISTER},
	// 开启安全模式（开启后将不会解析 Markdown 里的 HTML）
	'enable_safemode'    => true,
	// 只对普通用户开启安全模式（设置为 false 则代表所有用户均无法使用 HTML）
	'enable_foruser'     => true,
	// 是否启用伪静态
	'enable_rewrite'     => {ENABLE_REWRITE},
	// reCaptcha 谷歌验证码 v2 前端 Key，留空以禁用此功能
	'recaptcha_key'      => '{RECAPTCHA_KEY}',
	// reCaptcha 的服务端 Key，如果上面留空了这里就是废的
	'recaptcha_key_post' => '{RECAPTCHA_KEY_POST}',
	
	// 头像源镜像地址 例如
	// https://gravatar.loli.net/avatar/
	// https://sdn.geekzu.org/avatar/
	// https://cravatar.cn/avatar/
	'gravatar_mirror' => 'https://secure.gravatar.com/avatar/'
];
