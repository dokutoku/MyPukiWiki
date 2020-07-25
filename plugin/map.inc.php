<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// map.inc.php
// Copyright 2002-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Site map plugin

/*
 * プラグイン map: サイトマップ(のようなもの)を表示
 * Usage : http://.../pukiwiki.php?plugin=map
 * パラメータ
 *   &refer=ページ名
 *     起点となるページを指定
 *   &reverse=true
 *     あるページがどこからリンクされているかを一覧。
*/

// Show $non_list files
// 0, 1
define('PLUGIN_MAP_SHOW_HIDDEN', 0);

function plugin_map_action() : array
{
	global $vars;
	global $whatsnew;
	global $defaultpage;
	global $non_list;

	$reverse = isset($vars['reverse']);
	$refer = (isset($vars['refer'])) ? ($vars['refer']) : ('');

	if (($refer == '') || (!is_page($refer))) {
		$refer = $defaultpage;
		$vars['refer'] = $defaultpage;
	}

	$retval['msg'] = ($reverse) ? ('Relation map (link from)') : ('Relation map, from $1');
	$retval['body'] = '';

	// Get pages
	$pages = array_values(array_diff(get_existpages(), [$whatsnew]));

	if (!PLUGIN_MAP_SHOW_HIDDEN) {
		$pages = array_diff($pages, preg_grep('/'.$non_list.'/', $pages));
	}

	if (empty($pages)) {
		$retval['body'] = 'No pages.';

		return $retval;
	} else {
		$retval['body'] .= '<p>'."\n".'Total: '.count($pages).' page(s) on this site.'."\n".'</p>'."\n";
	}

	// Generate a tree
	$nodes = [];

	foreach ($pages as $page) {
		$nodes[$page] = new MapNode($page, $reverse);
	}

	// Node not found: Because of filtererd by $non_list
	if (!isset($nodes[$refer])) {
		$refer = $defaultpage;
		$vars['refer'] = $defaultpage;
	}

	if ($reverse) {
		$keys = array_keys($nodes);
		sort($keys, SORT_STRING);
		$alone = [];
		$retval['body'] .= '<ul>'."\n";

		foreach ($keys as $page) {
			if (!empty($nodes[$page]->rels)) {
				$retval['body'] .= $nodes[$page]->toString($nodes, 1, $nodes[$page]->parent_id);
			} else {
				$alone[] = $page;
			}
		}

		$retval['body'] .= '</ul>'."\n";

		if (!empty($alone)) {
			$retval['body'] .= '<hr />'."\n".'<p>No link from anywhere in this site.</p>'."\n";
			$retval['body'] .= '<ul>'."\n";

			foreach ($alone as $page) {
				$retval['body'] .= $nodes[$page]->toString($nodes, 1, $nodes[$page]->parent_id);
			}

			$retval['body'] .= '</ul>'."\n";
		}
	} else {
		$nodes[$refer]->chain($nodes);
		$retval['body'] .= '<ul>'."\n".$nodes[$refer]->toString($nodes).'</ul>'."\n";
		$retval['body'] .= '<hr />'."\n".'<p>Not related from '.htmlsc($refer).'</p>'."\n";
		$keys = array_keys($nodes);
		sort($keys, SORT_STRING);
		$retval['body'] .= '<ul>'."\n";

		foreach ($keys as $page) {
			if (!$nodes[$page]->done) {
				$nodes[$page]->chain($nodes);
				$retval['body'] .= $nodes[$page]->toString($nodes, 1, $nodes[$page]->parent_id);
			}
		}

		$retval['body'] .= '</ul>'."\n";
	}

	// 終了
	return $retval;
}

class MapNode
{
	public /* string */ $page;

	public /* bool */ $is_page;

	public /* string */ $link;

	public /* int */ $id;

	public /* array */ $rels;

	public /* int */ $parent_id = 0;

	public /* bool */ $done;

	public /* string */ $hide_pattern;

	public /* string */ $cache;

	public /* string */ $mark;

	public function MapNode(string $page, bool $reverse = false) : void
	{
		$this->__construct($page, $reverse);
	}

	public function __construct(string $page, bool $reverse = false)
	{
		global $non_list;

		static $id = 0;

		$this->page = $page;
		$this->is_page = is_page($page);
		$this->cache = CACHE_DIR.encode($page);
		$this->done = !$this->is_page;
		$this->link = make_pagelink($page);
		$this->id = ++$id;
		$this->hide_pattern = '/'.$non_list.'/';

		$this->rels = ($reverse) ? ($this->ref()) : ($this->rel());
		$mark = ($reverse) ? ('') : ('<sup>+</sup>');
		$this->mark = '<a id="rel_'.$this->id.'" href="'.get_base_uri().'?plugin=map&amp;refer='.rawurlencode($this->page).'">'.$mark.'</a>';
	}

	public function hide(&$pages) : array
	{
		if (!PLUGIN_MAP_SHOW_HIDDEN) {
			$pages = array_diff($pages, preg_grep($this->hide_pattern, $pages));
		}

		return $pages;
	}

	public function ref() : array
	{
		$refs = [];
		$file = $this->cache.'.ref';

		if (file_exists($file)) {
			foreach (file($file) as $line) {
				$ref = explode("\t", $line);
				$refs[] = $ref[0];
			}

			$this->hide($refs);
			sort($refs, SORT_STRING);
		}

		return $refs;
	}

	public function rel() : array
	{
		$rels = [];
		$file = $this->cache.'.rel';

		if (file_exists($file)) {
			$data = file($file);
			$rels = explode("\t", trim($data[0]));
			$this->hide($rels);
			sort($rels, SORT_STRING);
		}

		return $rels;
	}

	public function chain(array &$nodes) : void
	{
		if ($this->done) {
			return;
		}

		$this->done = true;

		if ($this->parent_id == 0) {
			$this->parent_id = -1;
		}

		foreach ($this->rels as $page) {
			if (!isset($nodes[$page])) {
				$nodes[$page] = new MapNode($page);
			}

			if ($nodes[$page]->parent_id == 0) {
				$nodes[$page]->parent_id = $this->id;
			}
		}

		foreach ($this->rels as $page) {
			$nodes[$page]->chain($nodes);
		}
	}

	public function toString(array &$nodes, int $level = 1, int $parent_id = -1) : string
	{
		$indent = str_repeat("\t", $level);

		if (!$this->is_page) {
			return $indent.'<li>'.$this->link.'</li>'."\n";
		} elseif ($this->parent_id != $parent_id) {
			return $indent.'<li>'.$this->link.'<a href="#rel_'.$this->id.'">...</a></li>'."\n";
		}

		$retval = $indent.'<li>'.$this->mark.$this->link."\n";

		if (!empty($this->rels)) {
			$childs = [];
			$level += 2;

			foreach ($this->rels as $page) {
				if ((isset($nodes[$page])) && ($this->parent_id != $nodes[$page]->id)) {
					$childs[] = $nodes[$page]->toString($nodes, $level, $this->id);
				}
			}

			if (!empty($childs)) {
				$retval .= $indent."\t".'<ul>'."\n".implode('', $childs).$indent.' </ul>'."\n";
			}
		}

		$retval .= $indent.'</li>'."\n";

		return $retval;
	}
}
