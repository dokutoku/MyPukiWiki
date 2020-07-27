<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// rightbar.inc.php
// Copyright 2020 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// RightBar plugin

// Use Submenu if true
define('RIGHTBAR_ENABLE_SUBMENU', false);

// Name of Submenu
define('RIGHTBAR_SUBMENUBAR', 'RightBar');

function plugin_rightbar_convert(string ...$args) : string
{
	global $vars;
	global $rightbar_name;

	static $menu = null;

	$num = func_num_args();

	if ($num > 0) {
		// Try to change default 'RightBar' page name (only)
		if ($num > 1) {
			return '#rightbar(): Zero or One argument needed';
		}

		if ($menu !== null) {
			return '#rightbar(): Already set: '.htmlspecialchars($menu, ENT_COMPAT, 'UTF-8');
		}

		if (!is_page($args[0])) {
			return '#rightbar(): No such page: '.htmlspecialchars($args[0], ENT_COMPAT, 'UTF-8');
		} else {
			// Set
			$menu = $args[0];

			return '';
		}
	}

	// Output rightbar page data
	$page = ($menu === null) ? ($rightbar_name) : ($menu);

	if (RIGHTBAR_ENABLE_SUBMENU) {
		$path = explode('/', strip_bracket($vars['page']));

		while (!empty($path)) {
			$_page = implode('/', $path).'/'.RIGHTBAR_SUBMENUBAR;

			if (is_page($_page)) {
				$page = $_page;

				break;
			}

			array_pop($path);
		}
	}

	if (!is_page($page)) {
		return '';
	} elseif ($vars['page'] === $page) {
		return '<!-- #rightbar(): You already view '.htmlspecialchars($page, ENT_COMPAT, 'UTF-8').' -->';
	} elseif (!is_page_readable($page)) {
		return '#rightbar(): '.htmlspecialchars($page, ENT_COMPAT, 'UTF-8').' is not readable';
	} else {
		// Cut fixed anchors
		$menutext = preg_replace('/^(\*{1,3}.*)\[#[A-Za-z][\w-]+\](.*)$/m', '$1$2', get_source($page));

		return convert_html($menutext);
	}
}
