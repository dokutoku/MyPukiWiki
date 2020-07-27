<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// basicauthlogout.inc.php
// Copyright 2016-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// "Basic auth logout" plugin

function plugin_basicauthlogout_inline() : string
{
	$logout_param = '?plugin=basicauthlogout';

	return '<a href="'.htmlspecialchars(get_base_uri().$logout_param, ENT_COMPAT, 'UTF-8').'">Log out</a>';
}

function plugin_basicauthlogout_convert() : string
{
	return '<div>'.plugin_basicauthlogout_inline().'</div>';
}

function plugin_basicauthlogout_action() : array
{
	global $auth_flag;
	global $_msg_auth;

	pkwk_common_headers();

	if (isset($_SERVER['PHP_AUTH_USER'])) {
		header('WWW-Authenticate: Basic realm="Please cancel to log out"');
		http_response_code(401);
	}

	return ['msg'=>'Log out', 'body'=>'Logged out completely'];
}
