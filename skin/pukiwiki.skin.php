<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// pukiwiki.skin.php
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// PukiWiki default skin

// ------------------------------------------------------------
// Settings (define before here, if you want)

// Set site identities
$_IMAGE['skin']['logo'] = 'pukiwiki.png';

// Sample: 'image/favicon.ico';
$_IMAGE['skin']['favicon'] = '';

// SKIN_DEFAULT_DISABLE_TOPICPATH
//   1 = Show reload URL
//   0 = Show topicpath
if (!defined('SKIN_DEFAULT_DISABLE_TOPICPATH')) {
	// 1, 0
	define('SKIN_DEFAULT_DISABLE_TOPICPATH', 1);
}

// Show / Hide navigation bar UI at your choice
// NOTE: This is not stop their functionalities!
if (!defined('PKWK_SKIN_SHOW_NAVBAR')) {
	// 1, 0
	define('PKWK_SKIN_SHOW_NAVBAR', 1);
}

// Show / Hide toolbar UI at your choice
// NOTE: This is not stop their functionalities!
if (!defined('PKWK_SKIN_SHOW_TOOLBAR')) {
	// 1, 0
	define('PKWK_SKIN_SHOW_TOOLBAR', 1);
}

// ------------------------------------------------------------
// Code start

// Prohibit direct access
if (!defined('UI_LANG')) {
	die('UI_LANG is not set');
}

if (!isset($_LANG)) {
	die('$_LANG is not set');
}

if (!defined('PKWK_READONLY')) {
	die('PKWK_READONLY is not set');
}

$lang = &$_LANG['skin'];
$link = &$_LINK;
$image = &$_IMAGE['skin'];
$rw = !PKWK_READONLY;

// MenuBar
$menu = ((arg_check('read')) && (exist_plugin_convert('menu'))) ? (do_plugin_convert('menu')) : (false);

// RightBar
$rightbar = false;

if ((arg_check('read')) && (exist_plugin_convert('rightbar'))) {
	$rightbar = do_plugin_convert('rightbar');
}

// ------------------------------------------------------------
// Output

// HTTP headers
pkwk_common_headers();
header('Cache-control: no-cache');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php
if (($nofollow) || (!$is_read)) {
	echo "\t\t".'<meta name="robots" content="NOINDEX,NOFOLLOW" />'."\n";
}

if ($html_meta_referrer_policy) {
	echo "\t\t".'<meta name="referrer" content="'.htmlspecialchars(html_meta_referrer_policy, ENT_COMPAT, 'UTF-8').'" />'."\n";
}
?>
		<title><?php echo $title; ?> - <?php echo $page_title; ?></title>
		<link rel="icon" href="<?php echo $image['favicon']; ?>" />
		<link rel="stylesheet" href="<?php echo SKIN_DIR; ?>pukiwiki.css" />
		<link rel="alternate" type="application/rss+xml" title="RSS" href="<?php echo $link['rss']; /* RSS auto-discovery */ ?>" />
		<script src="skin/main.js" defer></script>
		<script src="skin/search2.js" defer></script><?php echo (!empty($head_tag)) ? ("\n\t\t".$head_tag."\n") : ("\n"); ?>
	</head>
	<body><?php echo "\n".((!empty($html_scripting_data)) ? ("\t\t".str_replace("\n", "\n\t\t", $html_scripting_data)."\n\n") : ('')); ?>
		<div id="header">
			<a href="<?php echo $link['top']; ?>"><img id="logo" src="<?php echo IMAGE_DIR.$image['logo']; ?>" width="80" height="80" alt="[PukiWiki]" title="[PukiWiki]" decoding="async" /></a>
			<h1 class="title"><?php echo $page; ?></h1>
<?php
if ($is_page) {
	if (SKIN_DEFAULT_DISABLE_TOPICPATH) {
		echo "\t\t\t".'<a href="',$link['canonical_url'].'"><span class="small">'.$link['canonical_url'].'</span></a>'."\n";
	} else {
		echo "\t\t\t".'<span class="small">';
		require_once PLUGIN_DIR.'topicpath.inc.php';
		echo plugin_topicpath_inline();
		echo '</span>'."\n";
	}
}
?>
		</div>

		<div id="navigator">
<?php

// PKWK_SKIN_SHOW_NAVBAR
if (PKWK_SKIN_SHOW_NAVBAR) {
	function _navigator(string $key, string $value = '', string $javascript = '') : bool
	{
		$lang = &$GLOBALS['_LANG']['skin'];
		$link = &$GLOBALS['_LINK'];

		if (!isset($lang[$key])) {
			echo 'LANG NOT FOUND';

			return false;
		}

		if (!isset($link[$key])) {
			echo 'LINK NOT FOUND';

			return false;
		}

		echo '<a href="'.$link[$key].'" '.$javascript.'>'.(($value === '') ? ($lang[$key]) : ($value)).'</a>';

		return true;
	}

	echo "\t\t\t".'[ ';
	_navigator('top');
	echo ' ] &nbsp;'."\n";

	if ($is_page) {
		echo "\t\t\t".'[ ';

		if ($rw) {
			_navigator('edit');
			echo ' | ';

			if (($is_read) && ($function_freeze)) {
				(!$is_freeze) ? (_navigator('freeze')) : (_navigator('unfreeze'));
				echo ' | ';
			}
		}

		_navigator('diff');

		if ($do_backup) {
			echo ' | ';
			_navigator('backup');
		}

		if (($rw) && ((bool) (ini_get('file_uploads')))) {
			echo ' | ';
			_navigator('upload');
		}

		echo ' | ';
		_navigator('reload');

		echo ' ] &nbsp;'."\n";
	}

	echo "\t\t\t".'[ ';

	if ($rw) {
		_navigator('new');
		echo ' | ';
	}

	_navigator('list');

	if (arg_check('list')) {
		echo ' | ';
		_navigator('filelist');
	}

	echo ' | ';
	_navigator('search');

	echo ' | ';
	_navigator('recent');

	echo ' | ';
	_navigator('help');

	if ($enable_login) {
		echo ' | ';
		_navigator('login');
	}

	if ($enable_logout) {
		echo ' | ';
		_navigator('logout');
	}

	echo ' ]'."\n";
}
?>
		</div>

<?php echo "\t\t".$hr."\n"; ?>

		<div id="contents">
<?php
echo "\t\t\t".'<div id="body">'."\n";
echo "\t\t\t\t".str_replace("\n", "\n\t\t\t\t", trim($body));
echo "\n\t\t\t".'</div>'."\n";

if ($menu !== false) {
	echo "\t\t\t".'<div id="menubar">'."\n".("\t\t\t\t".str_replace("\n", "\n\t\t\t\t", rtrim($menu)))."\n\t\t\t".'</div>'."\n";
}

if ($rightbar) {
	echo "\t\t\t".'<div id="rightbar">'."\n".("t\t\t\t".str_replace("\n", "\n\t\t\t\t", rtrim($rightbar)))."\n\t\t\t".'</div>'."\n";
}
?>
		</div>

<?php
if ($notes != '') {
	echo "\t\t".'<div id="note">'."\n".("\t\t\t".str_replace("\n", "\n\t\t\t", rtrim($notes)))."\n\t\t".'</div>'."\n";
}

if ($attaches != '') {
	echo "\t\t".'<div id="attach">'."\n";
	echo "\t\t\t".$hr."\n";
	echo "\t\t\t".str_replace("\n", "\n\t\t\t", $attaches);
	echo "\t\t\t".'</div>'."\n";
}

echo "\n"."\t\t".$hr."\n\n";

if (PKWK_SKIN_SHOW_TOOLBAR) {
	echo "\t\t".'<!-- Toolbar -->'."\n";
	echo "\t\t".'<div id="toolbar">';

	// Set toolbar-specific images
	$_IMAGE['skin']['reload'] = 'reload.png';
	$_IMAGE['skin']['new'] = 'new.png';
	$_IMAGE['skin']['edit'] = 'edit.png';
	$_IMAGE['skin']['freeze'] = 'freeze.png';
	$_IMAGE['skin']['unfreeze'] = 'unfreeze.png';
	$_IMAGE['skin']['diff'] = 'diff.png';
	$_IMAGE['skin']['upload'] = 'file.png';
	$_IMAGE['skin']['copy'] = 'copy.png';
	$_IMAGE['skin']['rename'] = 'rename.png';
	$_IMAGE['skin']['top'] = 'top.png';
	$_IMAGE['skin']['list'] = 'list.png';
	$_IMAGE['skin']['search'] = 'search.png';
	$_IMAGE['skin']['recent'] = 'recentchanges.png';
	$_IMAGE['skin']['backup'] = 'backup.png';
	$_IMAGE['skin']['help'] = 'help.png';
	$_IMAGE['skin']['rss'] = 'rss.png';
	$_IMAGE['skin']['rss10'] = &$_IMAGE['skin']['rss'];
	$_IMAGE['skin']['rss20'] = 'rss20.png';
	$_IMAGE['skin']['rdf'] = 'rdf.png';

	function _toolbar(string $key, int $x = 20, int $y = 20) : bool
	{
		$lang = &$GLOBALS['_LANG']['skin'];
		$link = &$GLOBALS['_LINK'];
		$image = &$GLOBALS['_IMAGE']['skin'];

		if (!isset($lang[$key])) {
			echo 'LANG NOT FOUND';

			return false;
		}

		if (!isset($link[$key])) {
			echo 'LINK NOT FOUND';

			return false;
		}

		if (!isset($image[$key])) {
			echo 'IMAGE NOT FOUND';

			return false;
		}

		echo '<a href="'.$link[$key].'"><img src="'.IMAGE_DIR.$image[$key].'" width="'.((string) ($x)).'" height="'.((string) ($y)).'" alt="'.$lang[$key].'" title="'.$lang[$key].'" decoding="async" /></a>';

		return true;
	}

	_toolbar('top');

	if ($is_page) {
		echo '&nbsp;';

		if ($rw) {
			_toolbar('edit');

			if (($is_read) && ($function_freeze)) {
				if (!$is_freeze) {
					_toolbar('freeze');
				} else {
					_toolbar('unfreeze');
				}
			}
		}

		_toolbar('diff');

		if ($do_backup) {
			_toolbar('backup');
		}

		if ($rw) {
			if ((bool) (ini_get('file_uploads'))) {
				_toolbar('upload');
			}

			_toolbar('copy');
			_toolbar('rename');
		}

		_toolbar('reload');
	}

	echo '&nbsp;';

	if ($rw) {
		_toolbar('new');
	}

	_toolbar('list');
	_toolbar('search');
	_toolbar('recent');

	echo '&nbsp;';
	_toolbar('help');

	echo '&nbsp;';
	_toolbar('rss10', 36, 14);

	echo '</div>'."\n";
}

if ($lastmodified != '') {
	echo "\t\t".'<div id="lastmodified">Last-modified: '.$lastmodified.'</div>'."\n";
}

if ($related != '') {
	echo "\t\t".'<div id="related">'."\n\t\t\t".'Link: '."\n\t\t\t".str_replace("\n", "\n\t\t\t", $related)."\n\t\t".'</div>'."\n";
}
?>

		<div id="footer">
			Site admin: <a href="<?php echo $modifierlink; ?>"><?php echo $modifier; ?></a>
			<?php echo '<p>'.S_COPYRIGHT.'.'.(((defined('PKWK_OPTIMISE')) && (!PKWK_OPTIMISE)) ? (' Powered by PHP '.PHP_VERSION.'. HTML convert time: '.elapsedtime().' sec.') : ('')).'</p>'."\n"; ?>
		</div>
	</body>
</html>
