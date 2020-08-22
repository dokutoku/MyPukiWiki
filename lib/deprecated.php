<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// func.php
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// deprecated functions

/////////////////////////////////////////////////
// INI_FILE: $agents:  UserAgentの識別

$ua = 'HTTP_USER_AGENT';
$user_agent = [];

// Profile-related init and setting
if (isset($agents)) {
	$matches = [];

	$user_agent['agent'] = (isset($_SERVER['HTTP_USER_AGENT'])) ? ($_SERVER['HTTP_USER_AGENT']) : ('');

	foreach ($agents as $agent) {
		if (preg_match($agent['pattern'], $user_agent['agent'], $matches)) {
			$user_agent['profile'] = (isset($agent['profile'])) ? ($agent['profile']) : ('');

			// device or browser name
			$user_agent['name'] = (isset($matches[1])) ? ($matches[1]) : ('');

			// 's version
			$user_agent['vers'] = (isset($matches[2])) ? ($matches[2]) : ('');

			break;
		}
	}

	unset($agents, $matches);

	define('UA_PROFILE', (isset($user_agent['profile'])) ? ($user_agent['profile']) : (''));
	define('UA_INI_FILE', DATA_HOME.'config/user_agent/'.UA_PROFILE.'.ini.php');
} else {
	define('UA_PROFILE', 'default');
	define('UA_INI_FILE', DATA_HOME.'config/user_agent/default.ini.php');
}

//define('UA_NAME', (isset($user_agent['name'])) ? ($user_agent['name']) : (''));
//define('UA_VERS', (isset($user_agent['vers'])) ? ($user_agent['vers']) : (''));

/////////////////////////////////////////////////
define('PKWK_UTF8_ENABLE', 1);

/**
 * Internal content encoding (for mbstring extension) 'UTF-8'
 */
define('SOURCE_ENCODING', 'UTF-8');

/**
 * Internal content encoding = Output content charset 'UTF-8'
 */
define('CONTENT_CHARSET', 'UTF-8');

function _mb_convert_kana__enable(string $str, string $option) : string
{
	return mb_convert_kana($str, $option, 'UTF-8');
}

function _mb_convert_kana__none(string $str, string $option) : string
{
	return $str;
}

/**
 * ctype_digit that supports PHP4+.
 *
 * PHP official document says PHP4 has ctype_digit() function.
 * But sometimes it doen't exists on PHP 4.1.
 */
function pkwk_ctype_digit(?string $s) : bool
{
	return ctype_digit($s);
}

// Sugar with default settings
function htmlsc(string $string = '', int $flags = ENT_COMPAT, string $charset = 'UTF-8') : string
{
	return htmlspecialchars($string, $flags, $charset);
}

/**
 * Get JSON string with htmlspecialchars().
 */
function htmlsc_json($obj) : string
{
	return htmlspecialchars(json_encode($obj, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ENT_COMPAT, 'UTF-8');
}
