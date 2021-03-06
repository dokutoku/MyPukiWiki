<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// search.inc.php
// Copyright 2003-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Search plugin

// Allow search via GET method 'index.php?plugin=search&word=keyword'
// NOTE: Also allows DoS to your site more easily by SPAMbot or worm or ...

// 1, 0
define('PLUGIN_SEARCH_DISABLE_GET_ACCESS', 1);

define('PLUGIN_SEARCH_MAX_LENGTH', 80);

// #search(1,2,3,...,15,16)
define('PLUGIN_SEARCH_MAX_BASE', 16);

// Show a search box on a page
function plugin_search_convert(string ...$args) : string
{
	return plugin_search_search_form('', '', $args);
}

function plugin_search_action() : array
{
	global $post;
	global $vars;
	global $_title_result;
	global $_title_search;
	global $_msg_searching;

	if (PLUGIN_SEARCH_DISABLE_GET_ACCESS) {
		$s_word = (isset($post['word'])) ? (htmlspecialchars($post['word'], ENT_COMPAT, 'UTF-8')) : ('');
	} else {
		$s_word = (isset($vars['word'])) ? (htmlspecialchars($vars['word'], ENT_COMPAT, 'UTF-8')) : ('');
	}

	if (strlen($s_word) > PLUGIN_SEARCH_MAX_LENGTH) {
		// Stop using $_msg_word at lib/html.php
		unset($vars['word']);

		die_message('Search words too long');
	}

	$type = (isset($vars['type'])) ? ($vars['type']) : ('');
	$base = (isset($vars['base'])) ? ($vars['base']) : ('');

	if ($s_word != '') {
		// Search
		$msg = str_replace('$1', $s_word, $_title_result);
		$body = do_search($vars['word'], $type, false, $base);
	} else {
		// Init

		// Stop using $_msg_word at lib/html.php
		unset($vars['word']);

		$msg = $_title_search;
		$body = '<br />'."\n".$_msg_searching."\n";
	}

	// Show search form
	$bases = ($base == '') ? ([]) : ([$base]);
	$body .= plugin_search_search_form($s_word, $type, $bases);

	return ['msg'=>$msg, 'body'=>$body];
}

function plugin_search_search_form(string $s_word = '', string $type = '', array $bases = []) : string
{
	global $_btn_and;
	global $_btn_or;
	global $_btn_search;
	global $_search_pages;
	global $_search_all;

	$script = get_base_uri();
	$or_check = '';
	$and_check = '';

	if ($type == 'OR') {
		$or_check = ' checked="checked"';
	} else {
		$and_check = ' checked="checked"';
	}

	$base_option = '';

	if (!empty($bases)) {
		$base_msg = '';
		$_num = 0;
		$check = ' checked="checked"';

		foreach ($bases as $base) {
			$_num++;

			if (PLUGIN_SEARCH_MAX_BASE < $_num) {
				break;
			}

			$s_base = htmlspecialchars($base, ENT_COMPAT, 'UTF-8');
			$base_str = '<strong>'.$s_base.'</strong>';
			$base_label = str_replace('$1', $base_str, $_search_pages);
			$base_msg .= <<<EOD
		<div>
			<label><input type="radio" name="base" value="{$s_base}" {$check} /> {$base_label}</label>
		</div>
EOD;
			$check = '';
		}

		$base_msg .= <<<EOD
		<label><input type="radio" name="base" value="" /> {$_search_all}</label>
EOD;
		$base_option = "\n\t".'<div class="small">'."\n".$base_msg."\n\t".'</div>';
	}

	return <<<EOD
<form action="{$script}?cmd=search" method="post">
	<div>
		<input type="text"  name="word" value="{$s_word}" size="20" />
		<label><input type="radio" name="type" value="AND" {$and_check} /> {$_btn_and}</label>
		<label><input type="radio" name="type" value="OR" {$or_check} /> {$_btn_or}</label>
		&nbsp;<input type="submit" value="{$_btn_search}" />
	</div>{$base_option}
</form>
EOD;
}
