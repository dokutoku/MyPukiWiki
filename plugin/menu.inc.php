<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// menu.inc.php
// Copyright 2003-2018 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Menu plugin

// Use Submenu if true
define('MENU_ENABLE_SUBMENU', false);

// Name of Submenu
define('MENU_SUBMENUBAR', 'MenuBar');

function plugin_menu_convert(string ...$args) : string
{
	global $vars;
	global $menubar;
	static $menu = null;

	$num = func_num_args();

	if ($num > 0) {
		// Try to change default 'MenuBar' page name (only)
		if ($num > 1) {
			return '#menu(): Zero or One argument needed';
		}

		if ($menu !== null) {
			return '#menu(): Already set: '.htmlspecialchars($menu, ENT_COMPAT, 'UTF-8');
		}

		if (!is_page($args[0])) {
			return '#menu(): No such page: '.htmlspecialchars($args[0], ENT_COMPAT, 'UTF-8');
		} else {
			// Set
			$menu = $args[0];

			return '';
		}
	} else {
		// Output menubar page data
		$page = ($menu === null) ? ($menubar) : ($menu);

		if (MENU_ENABLE_SUBMENU) {
			$path = explode('/', strip_bracket($vars['page']));

			while (!empty($path)) {
				$_page = implode('/', $path).'/'.MENU_SUBMENUBAR;

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
			return '<!-- #menu(): You already view '.htmlspecialchars($page, ENT_COMPAT, 'UTF-8').' -->';
		} elseif (!is_page_readable($page)) {
			return '#menu(): '.htmlspecialchars($page, ENT_COMPAT, 'UTF-8').' is not readable';
		} else {
			// Cut fixed anchors
			$menutext = preg_replace('/^(\*{1,3}.*)\[#[A-Za-z][\w-]+\](.*)$/m', '$1$2', get_source($page));

			return convert_html($menutext);
		}
	}
}
