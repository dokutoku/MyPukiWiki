<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// external_link.inc.php
// Copyright
//   2018 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// PukiWiki External Link Plugin

function plugin_external_link_action() : void
{
	global $vars;
	global $external_link_cushion;
	global $_external_link_messages;

	header('Content-Type: text/html; charset=utf-8');
	$valid_url = false;

	if (isset($vars['url'])) {
		$url = $vars['url'];

		if (is_url($url)) {
			$valid_url = true;
		}
	}

	if (!$valid_url) {
		$error_message = <<< 'EOM'
<html>
	<body>
		The URL is invalid.
	</body>
</html>
EOM;
		echo $error_message;

		exit;
	}

	$encoded_url = htmlspecialchars($url, ENT_COMPAT, 'UTF-8');
	$refreshwait = $external_link_cushion['wait_seconds'];
	$h_title = htmlspecialchars(str_replace('%s', $url, $_external_link_messages['page_title']), ENT_COMPAT, 'UTF-8');
	$h_desc = htmlspecialchars($_external_link_messages['desc'], ENT_COMPAT, 'UTF-8');
	$h_wait = htmlspecialchars(str_replace('%s', (string) ($external_link_cushion['wait_seconds']), $_external_link_messages['wait_n_seconds']), ENT_COMPAT, 'UTF-8');
	$body = <<< EOM
<html>
	<head>
		<meta charset="UTF-8" />
		<meta http-equiv="Refresh" content="{$refreshwait};URL={$encoded_url}" />
		<title>{$h_title}</title>
	</head>
	<body>
		<p>{$h_desc}</p>
		<p>{$h_wait}</p>
		<p><a href="{$encoded_url}">{$encoded_url}</a></p>
	</body>
</html>
EOM;
	echo $body;

	exit;
}
