<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// pukiwiki.php
// Copyright
//   2002-2016 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// PukiWiki main script

if (!defined('DATA_HOME')) {
	define('DATA_HOME', '');
}

/////////////////////////////////////////////////
// Include subroutines

if (!defined('LIB_DIR')) {
	define('LIB_DIR', '');
}

assert(version_compare(PHP_VERSION, '7.2.0') >= 0);
assert(extension_loaded('mbstring'));

require LIB_DIR.'func.php';
require LIB_DIR.'file.php';
require LIB_DIR.'plugin.php';
require LIB_DIR.'html.php';
require LIB_DIR.'backup.php';

require LIB_DIR.'convert_html.php';
require LIB_DIR.'make_link.php';
require LIB_DIR.'diff.php';
require LIB_DIR.'config.php';
require LIB_DIR.'link.php';
require LIB_DIR.'auth.php';
require LIB_DIR.'proxy.php';

// Defaults
$notify = 0;

// Load *.ini.php files and init PukiWiki
require LIB_DIR.'init.php';

// Load optional libraries
if ($notify) {
	// Mail notification
	require LIB_DIR.'mail.php';
}

/////////////////////////////////////////////////
// Main
if (manage_page_redirect()) {
	exit;
}

$retvars = [];
$is_cmd = false;

if (isset($vars['cmd'])) {
	$is_cmd = true;
	$plugin = &$vars['cmd'];
} elseif (isset($vars['plugin'])) {
	$plugin = &$vars['plugin'];
} else {
	$plugin = '';
}

if ($plugin != '') {
	ensure_valid_auth_user();

	if (exist_plugin_action($plugin)) {
		// Found and exec
		$retvars = do_plugin_action($plugin);

		if ($retvars === false) {
			// Done
			exit;
		}

		if ($is_cmd) {
			$base = (isset($vars['page'])) ? ($vars['page']) : ('');
		} else {
			$base = (isset($vars['refer'])) ? ($vars['refer']) : ('');
		}
	} else {
		// Not found
		$msg = 'plugin='.htmlsc($plugin).' is not implemented.';
		$retvars = ['msg'=>$msg, 'body'=>$msg];
		$base = &$defaultpage;
	}
}

$title = htmlsc(strip_bracket($base));
$page = make_search($base);

if ((isset($retvars['msg'])) && ($retvars['msg'] != '')) {
	$title = str_replace('$1', $title, $retvars['msg']);
	$page = str_replace('$1', $page, $retvars['msg']);
}

if ((isset($retvars['body'])) && ($retvars['body'] != '')) {
	$body = &$retvars['body'];
} else {
	if (($base == '') || (!is_page($base))) {
		check_readable($defaultpage, true, true);
		$base = &$defaultpage;
		$title = htmlsc(strip_bracket($base));
		$page = make_search($base);
	}

	$vars['cmd'] = 'read';
	$vars['page'] = &$base;

	prepare_display_materials();
	$body = convert_html(get_source($base));
}

// Output
catbody($title, $page, $body);
