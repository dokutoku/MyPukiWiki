<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// topicpath.inc.php
// Copyright
//   2004-2018 PukiWiki Development Team
//   2003      reimy       (Some bug fix)
//   2003      t.m         (Migrate to 1.3)
//   2003      Nibun-no-ni (Originally written for PukiWiki 1.4.x)
// License: GPL v2 or (at your option) any later version
//
// 'topicpath' plugin for PukiWiki

// Show a link to $defaultpage or not
define('PLUGIN_TOPICPATH_TOP_DISPLAY', 1);

// Label for $defaultpage
define('PLUGIN_TOPICPATH_TOP_LABEL', 'Top');

// Separetor / of / topic / path
define('PLUGIN_TOPICPATH_TOP_SEPARATOR', '<span class="topicpath-slash">/</span>');

// Show the page itself or not
define('PLUGIN_TOPICPATH_THIS_PAGE_DISPLAY', 1);

// If PLUGIN_TOPICPATH_THIS_PAGE_DISPLAY, add a link to itself
define('PLUGIN_TOPICPATH_THIS_PAGE_LINK', 0);

function plugin_topicpath_convert() : string
{
	return '<div>'.plugin_topicpath_inline().'</div>';
}

function plugin_topicpath_parent_links(string $page) : array 
{
	$links = plugin_topicpath_parent_all_links($page);

	if (PKWK_READONLY) {
		$active_links = [];

		foreach ($links as $link) {
			if (is_page($link['page'])) {
				$active_links[] = $link;
			} else {
				$active_links[] =
				[
					'page'=>$link['page'],
					'leaf'=>$link['leaf'],
				];
			}
		}

		return $active_links;
	}

	return $links;
}

function plugin_topicpath_parent_all_links(string $page) : array
{
	$parts = explode('/', $page);
	$parents = [];

	for ($i = 0, $pos = 0; $pos = strpos($page, '/', $i); $i = $pos + 1) {
		$p = substr($page, 0, $pos);

		$parents[] =
		[
			'page'=>$p,
			'leaf'=>substr($p, $i),
			'uri'=>get_page_uri($p),
		];
	}

	return $parents;
}

function plugin_topicpath_inline() : string
{
	global $vars;
	global $defaultpage;

	$page = (isset($vars['page'])) ? ($vars['page']) : ('');

	if (($page == '') || ($page == $defaultpage)) {
		return '';
	}

	$parents = plugin_topicpath_parent_all_links($page);
	$topic_path = [];

	foreach ($parents as $p) {
		if ((PKWK_READONLY) && (!is_page($p['page']))) {
			// Page not exists
			$topic_path[] = htmlspecialchars($p['leaf'], ENT_COMPAT, 'UTF-8');
		} else {
			// Page exists or not exists
			$topic_path[] = '<a href="'.$p['uri'].'">'.$p['leaf'].'</a>';
		}
	}

	// This page
	if (PLUGIN_TOPICPATH_THIS_PAGE_DISPLAY) {
		$leaf_name = preg_replace('#^.*/#', '', $page);

		if (PLUGIN_TOPICPATH_THIS_PAGE_LINK) {
			$topic_path[] = '<a href="'.get_page_uri($page).'">'.$leaf_name.'</a>';
		} else {
			$topic_path[] = htmlspecialchars($leaf_name, ENT_COMPAT, 'UTF-8');
		}
	}

	$s = implode(PLUGIN_TOPICPATH_TOP_SEPARATOR, $topic_path);

	if (PLUGIN_TOPICPATH_TOP_DISPLAY) {
		$s = '<span class="topicpath-top">'.make_pagelink($defaultpage, PLUGIN_TOPICPATH_TOP_LABEL).PLUGIN_TOPICPATH_TOP_SEPARATOR.'</span>'.$s;
	}

	return $s;
}
