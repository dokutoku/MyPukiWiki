<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// freeze.inc.php
// Copyright 2003-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Freeze(Lock) plugin

// Reserve 'Do nothing'. '^#freeze' is for internal use only.
function plugin_freeze_convert() : string
{
	return '';
}

function plugin_freeze_action() : array
{
	global $vars;
	global $function_freeze;
	global $_title_isfreezed;
	global $_title_freezed;
	global $_title_freeze;
	global $_msg_invalidpass;
	global $_msg_freezing;
	global $_btn_freeze;

	$script = get_base_uri();
	$page = (isset($vars['page'])) ? ($vars['page']) : ('');

	if ((!$function_freeze) || (!is_page($page))) {
		return ['msg'=>'', 'body'=>''];
	}

	$pass = (isset($vars['pass'])) ? ($vars['pass']) : (null);
	$body = '';
	$msg = '';

	if (is_freeze($page)) {
		// Freezed already
		$msg = &$_title_isfreezed;
		$body = str_replace('$1', htmlsc(strip_bracket($page)), $_title_isfreezed);
	} elseif (($pass !== null) && (pkwk_login($pass))) {
		// Freeze
		$postdata = get_source($page);
		array_unshift($postdata, "#freeze\n");
		file_write(DATA_DIR, $page, implode('', $postdata), true);

		// Update
		is_freeze($page, true);
		$vars['cmd'] = 'read';
		$msg = &$_title_freezed;
		$body = '';
	} else {
		// Show a freeze form
		$msg = &$_title_freeze;
		$s_page = htmlsc($page);
		$body = ($pass === null) ? ('') : ('<p><strong>'.$_msg_invalidpass.'</strong></p>'."\n");
		$body .= <<<EOD
<p>{$_msg_freezing}</p>
<form action="{$script}" method="post">
	<div>
		<input type="hidden" name="cmd" value="freeze" />
		<input type="hidden" name="page" value="{$s_page}" />
		<input type="password" name="pass" size="12" />
		<input type="submit" name="ok" value="{$_btn_freeze}" />
	</div>
</form>
EOD;
	}

	return ['msg'=>$msg, 'body'=>$body];
}
