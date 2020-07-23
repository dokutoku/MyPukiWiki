<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// proxy.php
// Copyright: 2003-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// HTTP-Proxy related functions

// Max number of 'track' redirection message with 301 or 302 response
define('PKWK_HTTP_REQUEST_URL_REDIRECT_MAX', 2);

// We also define deprecated function 'http_request' for backward compatibility
if (!function_exists('http_request')) {
	// pecl_http extension also have the function named 'http_request'
	function http_request($url, $method = 'GET', $headers = '', $post = [], $redirect_max = PKWK_HTTP_REQUEST_URL_REDIRECT_MAX, $content_charset = '')
	{
		return pkwk_http_request($url, $method, $headers, $post, $redirect_max, $content_charset);
	}
}

/*
 * pkwk_http_request($url)
 *     Get / Send data via HTTP request
 * $url     : URI started with http:// (http://user:pass@host:port/path?query)
 * $method  : GET, POST, or HEAD
 * $headers : Additional HTTP headers, ended with "\r\n"
 * $post    : An array of data to send via POST method ('key'=>'value')
 * $redirect_max : Max number of HTTP redirect
 * $content_charset : Content charset. Use '' or CONTENT_CHARSET
*/
function pkwk_http_request($url, $method = 'GET', $headers = '', $post = [], $redirect_max = PKWK_HTTP_REQUEST_URL_REDIRECT_MAX, $content_charset = '')
{
	global $use_proxy;
	global $no_proxy;
	global $proxy_host;
	global $proxy_port;
	global $need_proxy_auth;
	global $proxy_auth_user;
	global $proxy_auth_pass;

	$rc = [];
	$arr = parse_url($url);

	$via_proxy = ($use_proxy) ? (!in_the_net($no_proxy, $arr['host'])) : (false);

	// query
	$arr['query'] = (isset($arr['query'])) ? ('?'.$arr['query']) : ('');

	// port
	if (!isset($arr['port'])) {
		if ($arr['scheme'] === 'https') {
			$arr['port'] = 443;
		} else {
			$arr['port'] = 80;
		}
	}

	$url_base = $arr['scheme'].'://'.$arr['host'].':'.$arr['port'];
	$url_path = (isset($arr['path'])) ? ($arr['path']) : ('/');
	$url = (($via_proxy) ? ($url_base) : ('')).$url_path.$arr['query'];

	$query = $method.' '.$url.' HTTP/1.0'."\r\n";
	$query .= 'Host: '.$arr['host']."\r\n";
	$query .= 'User-Agent: PukiWiki/'.S_VERSION."\r\n";

	// Basic-auth for HTTP proxy server
	if (($need_proxy_auth) && (isset($proxy_auth_user)) && (isset($proxy_auth_pass))) {
		$query .= 'Proxy-Authorization: Basic '.base64_encode($proxy_auth_user.':'.$proxy_auth_pass)."\r\n";
	}

	// (Normal) Basic-auth for remote host
	if ((isset($arr['user'])) && (isset($arr['pass']))) {
		$query .= 'Authorization: Basic '.base64_encode($arr['user'].':'.$arr['pass'])."\r\n";
	}

	$query .= $headers;

	if (strtoupper($method) == 'POST') {
		// 'application/x-www-form-urlencoded', especially for TrackBack ping
		$POST = [];

		foreach ($post as $name=>$val) {
			$POST[] = $name.'='.urlencode($val);
		}

		$data = implode('&', $POST);

		if (preg_match('/^[a-zA-Z0-9_-]+$/', $content_charset)) {
			// Legacy but simple
			$query .= 'Content-Type: application/x-www-form-urlencoded'."\r\n";
		} else {
			// With charset (NOTE: Some implementation may hate this)
			$query .= 'Content-Type: application/x-www-form-urlencoded; charset='.strtolower($content_charset)."\r\n";
		}

		$query .= 'Content-Length: '.strlen($data)."\r\n";
		$query .= "\r\n";
		$query .= $data;
	} else {
		$query .= "\r\n";
	}

	$errno = 0;
	$errstr = '';
	$ssl_prefix = '';

	if ($arr['scheme'] === 'https') {
		$ssl_prefix = 'ssl://';
	}

	$fp = fsockopen($ssl_prefix.(($via_proxy) ? ($proxy_host) : ($arr['host'])), (($via_proxy) ? ($proxy_port) : ($arr['port'])), $errno, $errstr, 30);

	if ($fp === false) {
		return
		[
			// Query string
			'query'=>$query,

			// Error number
			'rc'=>$errno,

			// Header
			'header'=>'',

			// Error message
			'data'=>$errstr,
		];
	}

	fwrite($fp, $query);
	$response = '';

	while (!feof($fp)) {
		$response .= fread($fp, 4096);
	}

	fclose($fp);

	$resp = explode("\r\n\r\n", $response, 2);

	// ['HTTP/1.1', '200', 'OK\r\n...']
	$rccd = explode(' ', $resp[0], 3);

	$rc = (int) ($rccd[1]);

	switch ($rc) {
		case 301: // Moved Permanently
		case 302: // Moved Temporarily
			$matches = [];

			if ((preg_match('/^Location: (.+)$/m', $resp[0], $matches)) && (--$redirect_max > 0)) {
				$url = trim($matches[1]);

				if (!preg_match('/^https?:\//', $url)) {
					// Relative path to Absolute
					if ($url[0] != '/') {
						$url = substr($url_path, 0, strrpos($url_path, '/')).'/'.$url;
					}

					// Add sheme, host
					$url = $url_base.$url;
				}

				// Redirect
				return pkwk_http_request($url, $method, $headers, $post, $redirect_max);
			}

			break;

		default:
			break;
	}

	return
	[
		// Query String
		'query'=>$query,

		// Response Code
		'rc'=>$rc,

		// Header
		'header'=>$resp[0],

		// Data
		'data'=>$resp[1],
	];
}

// Separate IPv4 network-address and its netmask
define('PKWK_CIDR_NETWORK_REGEX', '/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?:\/([0-9.]+))?$/');

// Check if the $host is in the specified network(s)
function in_the_net($networks = [], $host = '')
{
	if ((empty($networks)) || ($host == '')) {
		return false;
	}

	if (!is_array($networks)) {
		$networks = [$networks];
	}

	$matches = [];

	if (preg_match(PKWK_CIDR_NETWORK_REGEX, $host, $matches)) {
		$ip = $matches[1];
	} else {
		// May heavy
		$ip = gethostbyname($host);
	}

	$l_ip = ip2long($ip);

	foreach ($networks as $network) {
		if ((preg_match(PKWK_CIDR_NETWORK_REGEX, $network, $matches)) && (is_int($l_ip)) && (long2ip($l_ip) == $ip)) {
			// $host seems valid IPv4 address
			// Sample: '10.0.0.0/8' or '10.0.0.0/255.0.0.0'

			// '10.0.0.0'
			$l_net = ip2long($matches[1]);

			// '8' or '255.0.0.0'
			$mask = (isset($matches[2])) ? ($matches[2]) : (32);

			$mask =
				((is_numeric($mask)))
				? ((pow(2, 32) - pow(2, 32 - $mask))) // '8' means '8-bit mask'
				: ((ip2long($mask)));                   // '255.0.0.0' (the same)

			if (($l_ip & $mask) == $l_net) {
				return true;
			}
		} else {
			// $host seems not IPv4 address. May be a DNS name like 'foobar.example.com'?
			foreach ($networks as $network) {
				if (preg_match('/\.?\b'.preg_quote($network, '/').'$/', $host)) {
					return true;
				}
			}
		}
	}

	// Not found
	return false;
}
