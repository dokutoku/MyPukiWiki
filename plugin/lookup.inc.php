<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// lookup.inc.php
// Copyright
//   2002-2017 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// InterWiki lookup plugin

define('PLUGIN_LOOKUP_USAGE', '#lookup(interwikiname[,button_name[,default]])');

function plugin_lookup_convert()
{
	global $vars;
	static $id = 0;

	$num = func_num_args();

	if (($num == 0) || ($num > 3)) {
		return PLUGIN_LOOKUP_USAGE;
	}

	$args = func_get_args();
	$interwiki = htmlsc(trim($args[0]));
	$button = (isset($args[1])) ? (trim($args[1])) : ('');
	$button = ($button != '') ? (htmlsc($button)) : ('lookup');
	$default = ($num > 2) ? (htmlsc(trim($args[2]))) : ('');
	$s_page = htmlsc($vars['page']);
	$id++;

	$script = get_base_uri();
	$ret = <<<EOD
<form action="{$script}" method="post">
 <div>
  <input type="hidden" name="plugin" value="lookup" />
  <input type="hidden" name="refer"  value="{$s_page}" />
  <input type="hidden" name="inter"  value="{$interwiki}" />
  <label for="_p_lookup_{$id}">{$interwiki}:</label>
  <input type="text" name="page" id="_p_lookup_{$id}" size="30" value="{$default}" />
  <input type="submit" value="{$button}" />
 </div>
</form>
EOD;

	return $ret;
}

function plugin_lookup_action()
{
	// Deny GET method to avlid GET loop
	global $post;

	$page = (isset($post['page'])) ? ($post['page']) : ('');
	$inter = (isset($post['inter'])) ? ($post['inter']) : ('');

	if ($page == '') {
		// Do nothing
		return false;
	}

	if ($inter == '') {
		return ['msg'=>'Invalid access', 'body'=>''];
	}

	$url = get_interwiki_url($inter, $page);

	if ($url === false) {
		$msg = sprintf('InterWikiName "%s" not found', $inter);
		$msg = htmlsc($msg);

		return ['msg'=>'Not found', 'body'=>$msg];
	}

	pkwk_headers_sent();

	// Publish as GET method
	header('Location: '.$url);

	exit;
}
