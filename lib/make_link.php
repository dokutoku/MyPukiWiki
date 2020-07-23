<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// make_link.php
// Copyright
//   2003-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// Hyperlink-related functions

// To get page exists or filetimes without accessing filesystem
// Type: array (page=>filetime)
$_cached_page_filetime = null;

// Get filetime from cache
function fast_get_filetime(string $page) : int
{
	global $_cached_page_filetime;

	if ($_cached_page_filetime === null) {
		return get_filetime($page);
	}

	if (isset($_cached_page_filetime[$page])) {
		return $_cached_page_filetime[$page];
	}

	return get_filetime($page);
}

// Hyperlink decoration
function make_link(string $string, string $page = '') : string
{
	global $vars;
	static $converter;

	if (!isset($converter)) {
		$converter = new InlineConverter();
	}

	$clone = $converter->get_clone($converter);

	return $clone->convert($string, ($page != '') ? ($page) : ($vars['page']));
}

// Converters of inline element
class InlineConverter
{
	// as array()
	public /* array */ $converters;

	public /* string */ $pattern;

	public $pos;

	public /* array */ $result;

	public function get_clone(object $obj) : object
	{
		static $clone_exists;

		if (!isset($clone_exists)) {
			if (version_compare(PHP_VERSION, '5.0.0', '<')) {
				$clone_exists = false;
			} else {
				$clone_exists = true;
			}
		}

		if ($clone_exists) {
			return clone $obj;
		}

		return $obj;
	}

	public function __clone()
	{
		$converters = [];

		foreach ($this->converters as $key=>$converter) {
			$converters[$key] = $this->get_clone($converter);
		}

		$this->converters = $converters;
	}

	public function InlineConverter(?array $converters = null, array $excludes = null) : void
	{
		$this->__construct($converters, $excludes);
	}

	public function __construct(?array $converters = null, ?array $excludes = null)
	{
		if ($converters === null) {
			$converters =
			[
				// Inline plugins
				'plugin',

				// Footnotes
				'note',

				// URLs
				'url',

				// URLs (interwiki definition)
				'url_interwiki',

				// mailto: URL schemes
				'mailto',

				// InterWikiNames
				'interwikiname',

				// AutoAlias
				'autoalias',

				// AutoLinks
				'autolink',

				// BracketNames
				'bracketname',

				// WikiNames
				'wikiname',

				// AutoAlias(alphabet)
				'autoalias_a',

				// AutoLinks(alphabet)
				'autolink_a',
			];
		}

		if ($excludes !== null) {
			$converters = array_diff($converters, $excludes);
		}

		$patterns = [];
		$this->converters = [];
		$start = 1;

		foreach ($converters as $name) {
			$classname = 'Link_'.$name;
			$converter = new $classname($start);
			$pattern = $converter->get_pattern();

			if ($pattern === '') {
				continue;
			}

			$patterns[] = '('."\n".$pattern."\n".')';
			$this->converters[$start] = $converter;
			$start += $converter->get_count();
			$start++;
		}

		$this->pattern = implode('|', $patterns);
	}

	public function convert(string $string, string $page) : string
	{
		$this->page = $page;
		$this->result = [];

		$string = preg_replace_callback('/'.$this->pattern.'/x', [$this, 'replace'], $string);

		$arr = explode("\x08", make_line_rules(htmlsc($string)));
		$retval = '';

		while (!empty($arr)) {
			$retval .= array_shift($arr).array_shift($this->result);
		}

		return $retval;
	}

	public function replace(array $arr) : string
	{
		$obj = $this->get_converter($arr);

		$this->result[] = (($obj !== null) && ($obj->set($arr, $this->page) !== false)) ? ($obj->toString()) : (make_line_rules(htmlsc($arr[0])));

		// Add a mark into latest processed part
		return "\x08";
	}

	public function get_objects(string $string, string $page) : array
	{
		$arr = [];
		$matches = [];
		preg_match_all('/'.$this->pattern.'/x', $string, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			$obj = $this->get_converter($match);

			if ($obj === null) {
				continue;
			}

			if ($obj->set($match, $page) !== false) {
				$arr[] = $this->get_clone($obj);

				if ($obj->body != '') {
					$arr = array_merge($arr, $this->get_objects($obj->body, $page));
				}
			}
		}

		return $arr;
	}

	public function get_converter(array &$arr) : ?object
	{
		foreach (array_keys($this->converters) as $start) {
			if ($arr[$start] == $arr[0]) {
				return $this->converters[$start];
			}
		}

		return null;
	}
}

// Base class of inline elements
class Link
{
	// Origin number of parentheses (0 origin)
	public /* int */ $start;

	// Matched string
	public /* string */ $text;

	public /* string */ $type;

	public /* string */ $page;

	public /* string */ $name;

	public /* string */ $body;

	public /* string */ $alias;

	public function Link(int $start) : void
	{
		$this->__construct($start);
	}

	public function __construct(int $start)
	{
		$this->start = $start;
	}

	// Return a regex pattern to match
	public function get_pattern() : string
	{
		return '';
	}

	// Return number of parentheses (except (?:...) )
	public function get_count() : int
	{
		return 0;
	}

	// Set pattern that matches
	public function set(array $arr, string $page) : bool
	{
		return false;
	}

	public function toString() : string
	{
		return '';
	}

	// Private: Get needed parts from a matched array()
	public function splice(array $arr) : array
	{
		$count = $this->get_count() + 1;
		$arr = array_pad(array_splice($arr, $this->start, $count), $count, '');
		$this->text = $arr[0];

		return $arr;
	}

	// Set basic parameters
	public function setParam(string $page, string $name, string $body, string $type = '', string $alias = '') : bool
	{
		static $converter = null;

		$this->page = $page;
		$this->name = $name;
		$this->body = $body;
		$this->type = $type;

		if ((!PKWK_DISABLE_INLINE_IMAGE_FROM_URI) && (is_url($alias)) && (preg_match('/\.(gif|png|jpe?g)$/i', $alias))) {
			$alias = '<img src="'.htmlsc($alias).'" alt="'.$name.'" />';
		} elseif ($alias !== '') {
			if ($converter === null) {
				$converter = new InlineConverter(['plugin']);
			}

			$alias = make_line_rules($converter->convert($alias, $page));

			// BugTrack/669: A hack removing anchor tags added by AutoLink
			$alias = preg_replace('#</?a[^>]*>#i', '', $alias);
		}

		$this->alias = $alias;

		return true;
	}
}

// Inline plugins
class Link_plugin extends Link
{
	public /* string */ $pattern;

	public /* string */ $plain;

	public /* string */ $param;

	public function Link_plugin(int $start) : void
	{
		$this->__construct($start);
	}

	public function __construct(int $start)
	{
		parent::__construct($start);
	}

	public function get_pattern() : string
	{
		$this->pattern = <<<'EOD'
&
(      # (1) plain
 (\w+) # (2) plugin name
 (?:
  \(
   ((?:(?!\)[;{]).)*) # (3) parameter
  \)
 )?
)
EOD;

		return <<<EOD
{$this->pattern}
(?:
 \\{
  ((?:(?R)|(?!};).)*) # (4) body
 \\}
)?
;
EOD;
	}

	public function get_count() : int
	{
		return 4;
	}

	public function set(array $arr, string $page) : bool
	{
		[$all, $this->plain, $name, $this->param, $body] = $this->splice($arr);

		// Re-get true plugin name and patameters (for PHP 4.1.2)
		$matches = [];

		if ((preg_match('/^'.$this->pattern.'/x', $all, $matches)) && ($matches[1] != $this->plain)) {
			[, $this->plain, $name, $this->param] = $matches;
		}

		return parent::setParam($page, $name, $body, 'plugin');
	}

	public function toString() : string
	{
		$body = ($this->body == '') ? ('') : (make_link($this->body));
		$str = false;

		// Try to call the plugin
		if (exist_plugin_inline($this->name)) {
			$str = do_plugin_inline($this->name, $this->param, $body);
		}

		if ($str !== false) {
			// Succeed
			return $str;
		} else {
			// No such plugin, or Failed
			$body = (($body == '') ? ('') : ('{'.$body.'}')).';';

			return make_line_rules(htmlsc('&'.$this->plain).$body);
		}
	}
}

// Footnotes
class Link_note extends Link
{
	public function Link_note(int $start) : void
	{
		$this->__construct($start);
	}

	public function __construct(int $start)
	{
		parent::__construct($start);
	}

	public function get_pattern() : string
	{
		return <<<'EOD'
\(\(
 ((?>(?=\(\()(?R)|(?!\)\)).)*) # (1) note body
\)\)
EOD;
	}

	public function get_count() : int
	{
		return 1;
	}

	public function set(array $arr, string $page) : bool
	{
		global $foot_explain;
		global $vars;
		static $note_id = 0;

		[, $body] = $this->splice($arr);

		if (PKWK_ALLOW_RELATIVE_FOOTNOTE_ANCHOR) {
			$script = '';
		} else {
			$script = get_page_uri($page);
		}

		$id = ++$note_id;
		$note = make_link($body);

		// Footnote
		$foot_explain[$id] = '<a id="notefoot_'.$id.'" href="'.$script.'#notetext_'.$id.'" class="note_super">*'.$id.'</a>'."\n".'<span class="small">'.$note.'</span><br />';

		// A hyperlink, content-body to footnote
		if ((!is_numeric(PKWK_FOOTNOTE_TITLE_MAX)) || (PKWK_FOOTNOTE_TITLE_MAX <= 0)) {
			$title = '';
		} else {
			$title = strip_tags($note);
			$count = mb_strlen($title, SOURCE_ENCODING);
			$title = mb_substr($title, 0, PKWK_FOOTNOTE_TITLE_MAX, SOURCE_ENCODING);
			$abbr = (PKWK_FOOTNOTE_TITLE_MAX < $count) ? ('...') : ('');
			$title = ' title="'.$title.$abbr.'"';
		}

		$name = '<a id="notetext_'.$id.'" href="'.$script.'#notefoot_'.$id.'" class="note_super"'.$title.'>*'.$id.'</a>';

		return parent::setParam($page, $name, $body);
	}

	public function toString() : string
	{
		return $this->name;
	}
}

// URLs
class Link_url extends Link
{
	public function Link_url(int $start) : void
	{
		$this->__construct($start);
	}

	public function __construct(int $start)
	{
		parent::__construct($start);
	}

	public function get_pattern() : string
	{
		$s1 = $this->start + 1;

		return <<<EOD
((?:\\[\\[))?       # (1) open bracket
((?({$s1})          # (2) alias
((?:(?!\\]\\]).)+)  # (3) alias name
 (?:>|:)
))?
(                 # (4) url
 (?:(?:https?|ftp|news):\\/\\/|mailto:)[\\w\\/\\@\$()!?&%#:;.,~'=*+-]+
)
(?({$s1})\\]\\])      # close bracket
EOD;
	}

	public function get_count() : int
	{
		return 4;
	}

	public function set(array $arr, string $page) : bool
	{
		[, , , $alias, $name] = $this->splice($arr);

		return parent::setParam($page, htmlsc($name), '', 'url', (($alias == '') ? ($name) : ($alias)));
	}

	public function toString() : string
	{
		if (false) {
			$rel = '';
		} else {
			$rel = ' rel="nofollow"';
		}

		return '<a href="'.$this->name.'"'.$rel.'>'.$this->alias.'</a>';
	}
}

// URLs (InterWiki definition on "InterWikiName")
class Link_url_interwiki extends Link
{
	public function Link_url_interwiki(int $start) : void
	{
		$this->__construct($start);
	}

	public function __construct(int $start)
	{
		parent::__construct($start);
	}

	public function get_pattern() : string
	{
		return <<<'EOD'
\[       # open bracket
(        # (1) url
 (?:(?:https?|ftp|news):\/\/|\.\.?\/)[!~*'();\/?:\@&=+$,%#\w.-]*
)
\s
([^\]]+) # (2) alias
\]       # close bracket
EOD;
	}

	public function get_count() : int
	{
		return 2;
	}

	public function set(array $arr, string $page) : bool
	{
		[, $name, $alias] = $this->splice($arr);

		return parent::setParam($page, htmlsc($name), '', 'url', $alias);
	}

	public function toString() : string
	{
		return '<a href="'.$this->name.'" rel="nofollow">'.$this->alias.'</a>';
	}
}

// mailto: URL schemes
class Link_mailto extends Link
{
	public /* bool */ $is_image;

	public /* bool */ $image;

	public function Link_mailto(int $start) : void
	{
		$this->__construct($start);
	}

	public function __construct(int $start)
	{
		parent::__construct($start);
	}

	public function get_pattern() : string
	{
		$s1 = $this->start + 1;

		return <<<EOD
(?:
 \\[\\[
 ((?:(?!\\]\\]).)+)(?:>|:)  # (1) alias
)?
([\\w.-]+@[\\w-]+\\.[\\w.-]+) # (2) mailto
(?({$s1})\\]\\])              # close bracket if (1)
EOD;
	}

	public function get_count() : int
	{
		return 2;
	}

	public function set(array $arr, string $page) : bool
	{
		[, $alias, $name] = $this->splice($arr);

		return parent::setParam($page, $name, '', 'mailto', (($alias == '') ? ($name) : ($alias)));
	}

	public function toString() : string
	{
		return '<a href="mailto:'.$this->name.'" rel="nofollow">'.$this->alias.'</a>';
	}
}

// InterWikiName-rendered URLs
class Link_interwikiname extends Link
{
	public /* string */ $url = '';

	public /* string */ $param = '';

	public /* string */ $anchor = '';

	public function Link_interwikiname(int $start) : void
	{
		$this->__construct($start);
	}

	public function __construct(int $start)
	{
		parent::__construct($start);
	}

	public function get_pattern() : string
	{
		$s2 = $this->start + 2;
		$s5 = $this->start + 5;

		return <<<EOD
\\[\\[                  # open bracket
(?:
 ((?:(?!\\]\\]).)+)>    # (1) alias
)?
(\\[\\[)?               # (2) open bracket
((?:(?!\\s|:|\\]\\]).)+) # (3) InterWiki
(?<! > | >\\[\\[ )      # not '>' or '>[['
:                     # separator
(                     # (4) param
 (\\[\\[)?              # (5) open bracket
 (?:(?!>|\\]\\]).)+
 (?({$s5})\\]\\])         # close bracket if (5)
)
(?({$s2})\\]\\])          # close bracket if (2)
\\]\\]                  # close bracket
EOD;
	}

	public function get_count() : int
	{
		return 5;
	}

	public function set(array $arr, string $page) : bool
	{
		[, $alias, , $name, $this->param] = $this->splice($arr);

		$matches = [];

		if (preg_match('/^([^#]+)(#[A-Za-z][\w-]*)$/', $this->param, $matches)) {
			[, $this->param, $this->anchor] = $matches;
		}

		$url = get_interwiki_url($name, $this->param);
		$this->url = ($url === false) ? (get_base_uri().'?'.pagename_urlencode('[['.$name.':'.$this->param.']]')) : (htmlsc($url));

		return parent::setParam($page, htmlsc($name.':'.$this->param), '', 'InterWikiName', (($alias == '') ? ($name.':'.$this->param) : ($alias)));
	}

	public function toString() : string
	{
		return '<a href="'.$this->url.$this->anchor.'" title="'.$this->name.'" rel="nofollow">'.$this->alias.'</a>';
	}
}

// BracketNames
class Link_bracketname extends Link
{
	public /* string */ $anchor;

	public /* string */ $refer;

	public function Link_bracketname(int $start) : void
	{
		$this->__construct($start);
	}

	public function __construct(int $start)
	{
		parent::__construct($start);
	}

	public function get_pattern() : string
	{
		global $WikiName;
		global $BracketName;

		$s2 = $this->start + 2;

		return <<<EOD
\\[\\[                     # Open bracket
(?:((?:(?!\\]\\]).)+)>)?   # (1) Alias
(\\[\\[)?                  # (2) Open bracket
(                        # (3) PageName
 (?:{$WikiName})
 |
 (?:{$BracketName})
)?
(\\#(?:[a-zA-Z][\\w-]*)?)? # (4) Anchor
(?({$s2})\\]\\])             # Close bracket if (2)
\\]\\]                     # Close bracket
EOD;
	}

	public function get_count() : int
	{
		return 4;
	}

	public function set(array $arr, string $page) : bool
	{
		global $WikiName;

		[, $alias, , $name, $this->anchor] = $this->splice($arr);

		if (($name == '') && ($this->anchor == '')) {
			return false;
		}

		if (($name == '') || (!preg_match('/^'.$WikiName.'$/', $name))) {
			if ($alias == '') {
				$alias = $name.$this->anchor;
			}

			if ($name != '') {
				$name = get_fullname($name, $page);

				if (!is_pagename($name)) {
					return false;
				}
			}
		}

		return parent::setParam($page, $name, '', 'pagename', $alias);
	}

	public function toString() : string
	{
		return make_pagelink($this->name, $this->alias, $this->anchor, $this->page);
	}
}

// WikiNames
class Link_wikiname extends Link
{
	public function Link_wikiname(int $start) : void
	{
		$this->__construct($start);
	}

	public function __construct(int $start)
	{
		parent::__construct($start);
	}

	public function get_pattern() : string
	{
		global $WikiName;
		global $nowikiname;

		return ($nowikiname) ? ('') : ('('.$WikiName.')');
	}

	public function get_count() : int
	{
		return 1;
	}

	public function set(array $arr, string $page) : bool
	{
		[$name] = $this->splice($arr);

		return parent::setParam($page, $name, '', 'pagename', $name);
	}

	public function toString() : string
	{
		return make_pagelink($this->name, $this->alias, '', $this->page);
	}
}

// AutoLinks
class Link_autolink extends Link
{
	public /* array */ $forceignorepages = [];

	public /* string */ $auto;

	// alphabet only
	public /* string */ $auto_a;

	public function Link_autolink(int $start) : void
	{
		$this->__construct($start);
	}

	public function __construct(int $start)
	{
		global $autolink;

		parent::__construct($start);

		if ((!$autolink) || (!file_exists(CACHE_DIR.'autolink.dat'))) {
			return;
		}

		@[$auto, $auto_a, $forceignorepages] = file(CACHE_DIR.'autolink.dat');
		$this->auto = $auto;
		$this->auto_a = $auto_a;
		$this->forceignorepages = explode("\t", trim($forceignorepages));
	}

	public function get_pattern() : string
	{
		return (isset($this->auto)) ? ('('.$this->auto.')') : ('');
	}

	public function get_count() : int
	{
		return 1;
	}

	public function set(array $arr, string $page) : bool
	{
		global $WikiName;

		[$name] = $this->splice($arr);

		// Ignore pages listed, or Expire ones not found
		if ((in_array($name, $this->forceignorepages, true)) || (!is_page($name))) {
			return false;
		}

		return parent::setParam($page, $name, '', 'pagename', $name);
	}

	public function toString() : string
	{
		return make_pagelink($this->name, $this->alias, '', $this->page, true);
	}
}

class Link_autolink_a extends Link_autolink
{
	public function Link_autolink_a(int $start) : void
	{
		$this->__construct($start);
	}

	public function __construct(int $start)
	{
		parent::__construct($start);
	}

	public function get_pattern() : string
	{
		return (isset($this->auto_a)) ? ('('.$this->auto_a.')') : ('');
	}
}

// AutoAlias
class Link_autoalias extends Link
{
	public /* array */ $forceignorepages = [];

	public /* string */ $auto;

	// alphabet only
	public /* string */ $auto_a;

	public /* string */ $alias;

	public function Link_autoalias(int $start) : void
	{
		global $autoalias;
		global $aliaspage;

		parent::Link($start);

		if ((!$autoalias) || (!file_exists(CACHE_DIR.PKWK_AUTOALIAS_REGEX_CACHE)) || ($this->page == $aliaspage)) {
			return;
		}

		@[$auto, $auto_a, $forceignorepages] = file(CACHE_DIR.PKWK_AUTOALIAS_REGEX_CACHE);
		$this->auto = $auto;
		$this->auto_a = $auto_a;
		$this->forceignorepages = explode("\t", trim($forceignorepages));
		$this->alias = '';
	}

	public function __construct()
	{
	}

	public function get_pattern() : string
	{
		return (isset($this->auto)) ? ('('.$this->auto.')') : ('');
	}

	public function get_count() : int
	{
		return 1;
	}

	public function set(array $arr, string $page) : bool
	{
		[$name] = $this->splice($arr);

		// Ignore pages listed
		if ((in_array($name, $this->forceignorepages, true)) || (get_autoalias_right_link($name) == '')) {
			return false;
		}

		return parent::setParam($page, $name, '', 'pagename', $name);
	}

	public function toString() : string
	{
		$this->alias = get_autoalias_right_link($this->name);

		if ($this->alias != '') {
			$link = '[['.$this->name.'>'.$this->alias.']]';

			return make_link($link);
		}

		return '';
	}
}

class Link_autoalias_a extends Link_autoalias
{
	public function Link_autoalias_a(int $start) : void
	{
		parent::Link_autoalias($start);
	}

	public function __construct()
	{
	}

	public function get_pattern() : string
	{
		return (isset($this->auto_a)) ? ('('.$this->auto_a.')') : ('');
	}
}

// Make hyperlink for the page
function make_pagelink(string $page, string $alias = '', string $anchor = '', string $refer = '', bool $isautolink = false) : string
{
	global $vars;
	global $link_compact;
	global $related;
	global $_symbol_noexists;

	$script = get_base_uri();
	$s_page = htmlsc(strip_bracket($page));
	$s_alias = ($alias == '') ? ($s_page) : ($alias);

	if ($page == '') {
		return '<a href="'.$anchor.'">'.$s_alias.'</a>';
	}

	$r_page = pagename_urlencode($page);
	$r_refer = ($refer == '') ? ('') : ('&amp;refer='.rawurlencode($refer));

	$page_filetime = fast_get_filetime($page);
	$is_page = $page_filetime !== 0;

	if ((!isset($related[$page])) && ($page !== $vars['page']) && ($is_page)) {
		$related[$page] = $page_filetime;
	}

	if (($isautolink) || ($is_page)) {
		// Hyperlink to the page
		$attrs = get_filetime_a_attrs($page_filetime);

		// AutoLink marker
		if ($isautolink) {
			$al_left = '<!--autolink-->';
			$al_right = '<!--/autolink-->';
		} else {
			$al_right = '';
			$al_left = '';
		}

		$title_attr_html = '';

		if ($s_page !== $s_alias) {
			$title_attr_html = ' title="'.$s_page.'"';
		}

		return $al_left.'<a href="'.$script.'?'.$r_page.$anchor.'"'.$title_attr_html.' class="'.$attrs['class'].'" data-mtime="'.$attrs['data_mtime'].'">'.$s_alias.'</a>'.$al_right;
	} else {
		// Support Page redirection
		$redirect_page = get_pagename_on_redirect($page);

		if ($redirect_page !== false) {
			return make_pagelink($redirect_page, $s_alias);
		}

		// Dangling link
		if (PKWK_READONLY) {
			// No dacorations
			return $s_alias;
		}

		$symbol_html = '';

		if ($_symbol_noexists !== '') {
			$symbol_html = '<span style="user-select:none;">'.htmlsc($_symbol_noexists).'</span>';
		}

		$href = $script.'?cmd=edit&amp;page='.$r_page.$r_refer;

		if (($link_compact) && ($_symbol_noexists != '')) {
			$retval = '<a href="'.$href.'">'.$_symbol_noexists.'</a>';

			return $retval;
		} else {
			$retval = '<a href="'.$href.'">'.$s_alias.'</a>';

			return '<span class="noexists">'.$retval.$symbol_html.'</span>';
		}
	}
}

// Resolve relative / (Unix-like)absolute path of the page
function get_fullname(?string $name, string $refer) : string
{
	global $defaultpage;

	// 'Here'
	if (($name == '') || ($name == './')) {
		return $refer;
	}

	// Absolute path
	if ($name[0] == '/') {
		$name = substr($name, 1);

		return ($name == '') ? ($defaultpage) : ($name);
	}

	// Relative path from 'Here'
	if (substr($name, 0, 2) == './') {
		$arrn = preg_split('#/#', $name, -1, PREG_SPLIT_NO_EMPTY);
		$arrn[0] = $refer;

		return implode('/', $arrn);
	}

	// Relative path from dirname()
	if (substr($name, 0, 3) == '../') {
		$arrn = preg_split('#/#', $name, -1, PREG_SPLIT_NO_EMPTY);
		$arrp = preg_split('#/#', $refer, -1, PREG_SPLIT_NO_EMPTY);

		while ((!empty($arrn)) && ($arrn[0] == '..')) {
			array_shift($arrn);
			array_pop($arrp);
		}

		$name = (!empty($arrp)) ? (implode('/', array_merge($arrp, $arrn))) : ((!empty($arrn)) ? ($defaultpage.'/'.implode('/', $arrn)) : ($defaultpage));
	}

	return $name;
}

// Render an InterWiki into a URL
function get_interwiki_url(string $name, string $param)
{
	global $WikiName;
	global $interwiki;
	static $interwikinames;
	static $encode_aliases = ['sjis'=>'SJIS', 'euc'=>'EUC-JP', 'utf8'=>'UTF-8'];

	if (!isset($interwikinames)) {
		$matches = [];
		$interwikinames = [];

		foreach (get_source($interwiki) as $line) {
			if (preg_match('/\[((?:(?:https?|ftp|news):\/\/|\.\.?\/)[!~*\'();\/?:\@&=+\$,%#\w.-]*)\s([^\]]+)\]\s?([^\s]*)/', $line, $matches)) {
				$interwikinames[$matches[2]] = [$matches[1], $matches[3]];
			}
		}
	}

	if (!isset($interwikinames[$name])) {
		return false;
	}

	[$url, $opt] = $interwikinames[$name];

	// Encoding
	switch ($opt) {
		case '':
		case 'std': // Simply URL-encode the string, whose base encoding is the internal-encoding
			$param = rawurlencode($param);

			break;

		case 'asis':
		case 'raw': // Truly as-is
			break;

		case 'yw': // YukiWiki
			if (!preg_match('/'.$WikiName.'/', $param)) {
				$param = '[['.mb_convert_encoding($param, 'SJIS', SOURCE_ENCODING).']]';
			}

			break;

		case 'moin': // MoinMoin
			$param = str_replace('%', '_', rawurlencode($param));

			break;

		default:
			// Alias conversion of $opt
			if (isset($encode_aliases[$opt])) {
				$opt = &$encode_aliases[$opt];
			}

			// Encoding conversion into specified encode, and URLencode
			if ((strpos($url, '$1') === false) && (substr($url, -1) === '?')) {
				// PukiWiki site
				$param = pagename_urlencode(mb_convert_encoding($param, $opt, SOURCE_ENCODING));
			} else {
				$param = rawurlencode(mb_convert_encoding($param, $opt, SOURCE_ENCODING));
			}

			break;
	}

	// Replace or Add the parameter
	if (strpos($url, '$1') !== false) {
		$url = str_replace('$1', $param, $url);
	} else {
		$url .= $param;
	}

	$len = strlen($url);

	if ($len > 512) {
		die_message('InterWiki URL too long: '.$len.' characters');
	}

	return $url;
}

function get_autoticketlink_def_page() : string
{
	return 'AutoTicketLinkName';
}

/**
 * Get AutoTicketLink - JIRA projects from AutoTiketLinkName page.
 */
function get_ticketlink_jira_projects() : array
{
	$autoticketlink_def_page = get_autoticketlink_def_page();
	$active_jira_base_url = null;
	$jira_projects = [];

	foreach (get_source($autoticketlink_def_page) as $line) {
		if (substr($line, 0, 1) !== '-') {
			$active_jira_base_url = null;

			continue;
		}

		$m = null;

		if (preg_match('/^-\s*(jira)\s+(https?:\/\/[!~*\'();\/?:\@&=+\$,%#\w.-]+)\s*$/', $line, $m)) {
			$active_jira_base_url = $m[2];
		} elseif (preg_match('/^--\s*([A-Z][A-Z0-9]{1,10}(?:_[A-Z0-9]{1,10}){0,2})(\s+(.+?))?\s*$/', $line, $m)) {
			if ($active_jira_base_url) {
				$project_key = $m[1];
				$title = $m[2];
				array_push($jira_projects, ['key'=>$m[1], 'title'=>$title, 'base_url'=>$active_jira_base_url]);
			}
		} else {
			$active_jira_base_url = null;
		}
	}

	return $jira_projects;
}

function init_autoticketlink_def_page() : void
{
	global $ticket_jira_default_site;

	$autoticketlink_def_page = get_autoticketlink_def_page();

	if (is_page($autoticketlink_def_page)) {
		return;
	}

	$body = <<<EOS
#freeze
* AutoTicketLink definition [#def]

Reference: https://pukiwiki.osdn.jp/?AutoTicketLink

 - jira https://site1.example.com/jira/browse/
 -- AAA Project title \$1
 -- BBB Project title \$1
 - jira https://site2.example.com/jira/browse/
 -- PROJECTA Site2 \$1

 (Default definition) pukiwiki.ini.php
 {$ticket_jira_default_site} =
 [
   'title'=>'My JIRA - \$1',
   'base_url'=>'https://issues.example.com/jira/browse/',
 ];
EOS;
	page_write($autoticketlink_def_page, $body);
}

function init_autoalias_def_page() : void
{
	// 'AutoAliasName'
	global $aliaspage;

	$autoticketlink_def_page = get_autoticketlink_def_page();

	if (is_page($aliaspage)) {
		return;
	}

	$body = <<<'EOS'
#freeze
*AutoAliasName [#qf9311bb]
AutoAlias definition

Reference: https://pukiwiki.osdn.jp/?AutoAlias

* PukiWiki [#ee87d39e]
-[[pukiwiki.official>https://pukiwiki.osdn.jp/]]
-[[pukiwiki.dev>https://pukiwiki.osdn.jp/dev/]]
EOS;

	page_write($aliaspage, $body);
	update_autoalias_cache_file();
}
