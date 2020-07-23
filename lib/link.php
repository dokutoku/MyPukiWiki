<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// link.php
// Copyright 2003-2020 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Backlinks / AutoLinks related functions

// ------------------------------------------------------------
// DATA STRUCTURE of *.ref and *.rel files

// CACHE_DIR/encode('foobar').ref
// ---------------------------------
// Page-name1<tab>0<\n>
// Page-name2<tab>1<\n>
// ...
// Page-nameN<tab>0<\n>
//
//	0 = Added when link(s) to 'foobar' added clearly at this page
//	1 = Added when the sentence 'foobar' found from the page
//	    by AutoLink feature

// CACHE_DIR/encode('foobar').rel
// ---------------------------------
// Page-name1<tab>Page-name2<tab> ... <tab>Page-nameN
//
//	List of page-names linked from 'foobar'

// ------------------------------------------------------------

// データベースから関連ページを得る
function links_get_related_db(string $page) : array
{
	$ref_name = CACHE_DIR.encode($page).'.ref';

	if (!file_exists($ref_name)) {
		return [];
	}

	$times = [];

	foreach (file($ref_name) as $line) {
		[$_page] = explode("\t", rtrim($line));
		$time = get_filetime($_page);

		if ($time != 0) {
			$times[$_page] = $time;
		}
	}

	return $times;
}

//ページの関連を更新する
function links_update(string $page) : void
{
	if (PKWK_READONLY) {
		// Do nothing
		return;
	}

	if (ini_get('safe_mode') == '0') {
		set_time_limit(0);
	}

	$time = (is_page($page, true)) ? (get_filetime($page)) : (0);

	$rel_old = [];
	$rel_file = CACHE_DIR.encode($page).'.rel';
	$rel_file_exist = file_exists($rel_file);

	if ($rel_file_exist === true) {
		$lines = file($rel_file);
		unlink($rel_file);

		if (isset($lines[0])) {
			$rel_old = explode("\t", rtrim($lines[0]));
		}
	}

	// 参照先
	$rel_new = [];

	// オートリンクしている参照先
	$rel_auto = [];

	$links = links_get_objects($page, true);

	foreach ($links as $_obj) {
		if ((!isset($_obj->type)) || ($_obj->type != 'pagename') || ($_obj->name === $page) || ($_obj->name == '')) {
			continue;
		}

		// 行儀が悪い
		if (is_a($_obj, 'Link_autolink')) {
			$rel_auto[] = $_obj->name;
		} elseif (is_a($_obj, 'Link_autoalias')) {
			$_alias = get_autoalias_right_link($_obj->name);

			if (is_pagename($_alias)) {
				$rel_auto[] = $_alias;
			}
		} else {
			$rel_new[] = $_obj->name;
		}
	}

	$rel_new = array_unique($rel_new);

	// autolinkしか向いていないページ
	$rel_auto = array_diff(array_unique($rel_auto), $rel_new);

	// 全ての参照先ページ
	$rel_new = array_merge($rel_new, $rel_auto);

	// .rel:$pageが参照しているページの一覧
	if ($time) {
		// ページが存在している
		if (!empty($rel_new)) {
			if (!($fp = fopen($rel_file, 'w'))) {
				die_message('cannot write '.htmlsc($rel_file));
			}

			fwrite($fp, implode("\t", $rel_new));
			fclose($fp);
		}
	}

	// .ref:$_pageを参照しているページの一覧
	links_add($page, array_diff($rel_new, $rel_old), $rel_auto);
	links_delete($page, array_diff($rel_old, $rel_new));

	global $WikiName;
	global $autolink;
	global $nowikiname;

	// $pageが新規作成されたページで、AutoLinkの対象となり得る場合
	if (($time) && (!$rel_file_exist) && ($autolink) && ((preg_match('/^'.$WikiName.'$/', $page)) ? ($nowikiname) : (strlen($page) >= $autolink))) {
		// $pageを参照していそうなページを一斉更新する(おい)
		$pages = links_do_search_page($page);

		foreach ($pages as $_page) {
			if ($_page !== $page) {
				links_update($_page);
			}
		}
	}

	$ref_file = CACHE_DIR.encode($page).'.ref';

	// $pageが削除されたときに、
	if ((!$time) && (file_exists($ref_file))) {
		foreach (file($ref_file) as $line) {
			[$ref_page, $ref_auto] = explode("\t", rtrim($line));

			// $pageをAutoLinkでしか参照していないページを一斉更新する(おいおい)
			if ($ref_auto) {
				links_delete($ref_page, [$page]);
			}
		}
	}
}

// Init link cache (Called from link plugin)
function links_init() : void
{
	global $whatsnew;

	if (PKWK_READONLY) {
		// Do nothing
		return;
	}

	if (ini_get('safe_mode') == '0') {
		set_time_limit(0);
	}

	// Init database
	foreach (get_existfiles(CACHE_DIR, '.ref') as $cache) {
		unlink($cache);
	}

	foreach (get_existfiles(CACHE_DIR, '.rel') as $cache) {
		unlink($cache);
	}

	// 参照元
	$ref = [];

	foreach (get_existpages() as $page) {
		if ($page == $whatsnew) {
			continue;
		}

		// 参照先
		$rel = [];

		$links = links_get_objects($page);

		foreach ($links as $_obj) {
			if ((!isset($_obj->type)) || ($_obj->type != 'pagename') || ($_obj->name == $page) || ($_obj->name == '')) {
				continue;
			}

			$_name = $_obj->name;

			if (is_a($_obj, 'Link_autoalias')) {
				$_alias = get_autoalias_right_link($_name);

				if (!is_pagename($_alias)) {
					// not PageName
					continue;
				}

				$_name = $_alias;
			}

			$rel[] = $_name;

			if (!isset($ref[$_name][$page])) {
				$ref[$_name][$page] = 1;
			}

			if (!is_a($_obj, 'Link_autolink')) {
				$ref[$_name][$page] = 0;
			}
		}

		$rel = array_unique($rel);

		if (!empty($rel)) {
			if (!($fp = fopen(CACHE_DIR.encode($page).'.rel', 'w'))) {
				die_message('cannot write '.htmlsc(CACHE_DIR.encode($page).'.rel'));
			}

			fwrite($fp, implode("\t", $rel));
			fclose($fp);
		}
	}

	foreach ($ref as $page=>$arr) {
		if (!($fp = fopen(CACHE_DIR.encode($page).'.ref', 'w'))) {
			die_message('cannot write '.htmlsc(CACHE_DIR.encode($page).'.ref'));
		}

		foreach ($arr as $ref_page=>$ref_auto) {
			fwrite($fp, $ref_page."\t".$ref_auto."\n");
		}

		fclose($fp);
	}
}

function links_add(string $page, array $add, array $rel_auto) : void
{
	if (PKWK_READONLY) {
		// Do nothing
		return;
	}

	$rel_auto = array_flip($rel_auto);

	foreach ($add as $_page) {
		$all_auto = isset($rel_auto[$_page]);
		$is_page = is_page($_page);
		$ref = $page."\t".(($all_auto) ? (1) : (0))."\n";

		$ref_file = CACHE_DIR.encode($_page).'.ref';

		if (file_exists($ref_file)) {
			foreach (file($ref_file) as $line) {
				[$ref_page, $ref_auto] = explode("\t", rtrim($line));

				if (!$ref_auto) {
					$all_auto = false;
				}

				if ($ref_page !== $page) {
					$ref .= $line;
				}
			}

			unlink($ref_file);
		}

		if (($is_page) || (!$all_auto)) {
			if (!($fp = fopen($ref_file, 'w'))) {
				die_message('cannot write '.htmlsc($ref_file));
			}

			fwrite($fp, $ref);
			fclose($fp);
		}
	}
}

function links_delete(string $page, array $del) : void
{
	if (PKWK_READONLY) {
		// Do nothing
		return;
	}

	foreach ($del as $_page) {
		$ref_file = CACHE_DIR.encode($_page).'.ref';

		if (!file_exists($ref_file)) {
			continue;
		}

		$all_auto = true;
		$is_page = is_page($_page);

		$ref = '';

		foreach (file($ref_file) as $line) {
			[$ref_page, $ref_auto] = explode("\t", rtrim($line));

			if ($ref_page !== $page) {
				if (!$ref_auto) {
					$all_auto = false;
				}

				$ref .= $line;
			}
		}

		unlink($ref_file);

		if ((($is_page) || (!$all_auto)) && ($ref != '')) {
			if (!($fp = fopen($ref_file, 'w'))) {
				die_message('cannot write '.htmlsc($ref_file));
			}

			fwrite($fp, $ref);
			fclose($fp);
		}
	}
}

function links_get_objects(string $page, bool $refresh = false) : array
{ 
	static $obj;

	if ((!isset($obj)) || ($refresh)) {
		$obj = new InlineConverter(null, ['note']);
	}

	$result = $obj->get_objects(implode('', preg_grep('/^(?!\/\/|\s)./', get_source($page))), $page);

	return $result;
}

/**
 * Search function for AutoLink updating.
 *
 * @param $word page name
 *
 * @return list of page name that contains $word
 */
function links_do_search_page(string $word) : array
{
	global $whatsnew;

	$keys = get_search_words(preg_split('/\s+/', $word, -1, PREG_SPLIT_NO_EMPTY));

	foreach ($keys as $key=>$value) {
		$keys[$key] = '/'.$value.'/S';
	}

	$pages = get_existpages();
	$pages = array_flip($pages);
	unset($pages[$whatsnew]);
	$count = count($pages);

	foreach (array_keys($pages) as $page) {
		$b_match = false;
		// Search for page contents
		foreach ($keys as $key) {
			$body = get_source($page, true, true, true);
			$b_match = preg_match($key, remove_author_header($body));

			if (!$b_match) {
				break;
			} // OR
		}

		if ($b_match) {
			continue;
		}

		// Miss
		unset($pages[$page]);
	}

	return array_keys($pages);
}
