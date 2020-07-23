<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// convert_html.php
// Copyright
//   2002-2016 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// function 'convert_html()', wiki text parser
// and related classes-and-functions

function convert_html($lines) : string
{
	global $vars;
	global $digest;
	static $contents_id = 0;

	// Set digest
	$digest = md5(implode('', get_source($vars['page'])));

	if ($lines === null) {
		$lines = [];
	} elseif (!is_array($lines)) {
		$lines = explode("\n", $lines);
	}

	$body = new Body(++$contents_id);
	$body->parse($lines);

	return $body->toString();
}

// Block elements
class Element
{
	public /* object */ $parent;

	// References of childs
	public /* array */ $elements;

	// Insert new one at the back of the $last
	public /* object */ $last;

	public function Element() : void
	{
		$this->__construct();
	}

	public function __construct()
	{
		$this->elements = [];
		$this->last = $this;
	}

	public function setParent(object &$parent) : void
	{
		$this->parent = &$parent;
	}

	public function add(object $obj)
	{
		if ($this->canContain($obj)) {
			return $this->insert($obj);
		} else {
			return $this->parent->add($obj);
		}
	}

	public function insert(object $obj) : object
	{
		$obj->setParent($this);
		$this->elements[] = $obj;

		return $this->last = $obj->last;
	}

	public function canContain(object $obj) : bool
	{
		return true;
	}

	public function wrap(string $string, string $tag, string $param = '', bool $canomit = true) : string
	{
		return (($canomit) && ($string == '')) ? ('') : ('<'.$tag.$param.'>'.$string.'</'.$tag.'>');
	}

	public function toString() : string
	{
		$ret = [];

		foreach (array_keys($this->elements) as $key) {
			$ret[] = $this->elements[$key]->toString();
		}

		return implode("\n", $ret);
	}

	public function dump(int $indent = 0) : string
	{
		$ret = str_repeat(' ', $indent).get_class($this)."\n";
		$indent += 2;

		foreach (array_keys($this->elements) as $key) {
			$ret .= (is_object($this->elements[$key])) ? ($this->elements[$key]->dump($indent)) : ('');
			//str_repeat(' ', $indent) . $this->elements[$key];
		}

		return $ret;
	}
}

// Returns inline-related object
function Factory_Inline(string $text) : object
{
	// Check the first letter of the line
	if (substr($text, 0, 1) == '~') {
		return new Paragraph(' '.substr($text, 1));
	} else {
		return new Inline($text);
	}
}

function Factory_DList(object &$root, string $text) : object
{
	$out = explode('|', ltrim($text), 2);

	if (count($out) < 2) {
		return Factory_Inline($text);
	} else {
		return new DList($out);
	}
}

// '|'-separated table
function Factory_Table(object &$root, string $text) : object
{
	if (!preg_match('/^\|(.+)\|([hHfFcC]?)$/', $text, $out)) {
		return Factory_Inline($text);
	} else {
		return new Table($out);
	}
}

// Comma-separated table
function Factory_YTable(object &$root, string $text) : object
{
	if ($text == ',') {
		return Factory_Inline($text);
	} else {
		return new YTable(csv_explode(',', substr($text, 1)));
	}
}

function Factory_Div(object &$root, string $text) : object
{
	$matches = [];

	// Seems block plugin?
	if (PKWKEXP_DISABLE_MULTILINE_PLUGIN_HACK) {
		// Usual code
		if ((preg_match('/^\#([^\(]+)(?:\((.*)\))?/', $text, $matches)) && (exist_plugin_convert($matches[1]))) {
			return new Div($matches);
		}
	} else {
		// Hack code
		if ((preg_match('/^#([^\(\{]+)(?:\(([^\r]*)\))?(\{*)/', $text, $matches)) && (exist_plugin_convert($matches[1]))) {
			$len = strlen($matches[3]);
			$body = [];

			if ($len == 0) {
				// Seems legacy block plugin
				return new Div($matches);
			} elseif (preg_match('/\{{'.$len.'}\s*\r(.*)\r\}{'.$len.'}/', $text, $body)) {
				$matches[2] .= "\r".$body[1]."\r";

				// Seems multiline-enabled block plugin
				return new Div($matches);
			}
		}
	}

	return new Paragraph($text);
}

// Inline elements
class Inline extends Element
{
	public function Inline(string $text) : void
	{
		$this->__construct($text);
	}

	public function __construct(string $text)
	{
		parent::__construct();
		$this->elements[] = trim((substr($text, 0, 1) === "\n") ? ($text) : (make_link($text)));
	}

	public function insert(object $obj) : object
	{
		$this->elements[] = $obj->elements[0];

		return $this;
	}

	public function canContain(object $obj) : bool
	{
		return is_a($obj, 'Inline');
	}

	public function toString() : string
	{
		global $line_break;

		return implode((($line_break) ? ('<br />'."\n") : ("\n")), $this->elements);
	}

	public function toPara(string $class = '') : object
	{
		$obj = new Paragraph('', $class);
		$obj->insert($this);

		return $obj;
	}
}

// Paragraph: blank-line-separated sentences
class Paragraph extends Element
{
	public /* string */ $param;

	public function Paragraph(string $text, string $param = '') : void
	{
		$this->__construct($text, $param);
	}

	public function __construct(string $text, string $param = '')
	{
		parent::__construct();
		$this->param = $param;

		if ($text == '') {
			return;
		}

		if (substr($text, 0, 1) == '~') {
			$text = ' '.substr($text, 1);
		}

		$this->insert(Factory_Inline($text));
	}

	public function canContain(object $obj) : bool
	{
		return is_a($obj, 'Inline');
	}

	public function toString() : string
	{
		return $this->wrap(parent::toString(), 'p', $this->param);
	}
}

// * Heading1
// ** Heading2
// *** Heading3
class Heading extends Element
{
	public /* int */ $level;

	public /* string */ $id;

	public /* string */ $msg_top;

	public function Heading(object &$root, string $text) : void
	{
		$this->__construct($root, $text);
	}

	public function __construct(object &$root, string $text)
	{
		parent::__construct();

		$this->level = min(3, strspn($text, '*'));
		[$text, $this->msg_top, $this->id] = $root->getAnchor($text, $this->level);
		$this->insert(Factory_Inline($text));

		// h2,h3,h4
		$this->level++;
	}

	public function insert(object $obj) : object
	{
		parent::insert($obj);

		return $this->last = $this;
	}

	public function canContain(object $obj) : bool
	{
		return false;
	}

	public function toString() : string
	{
		return $this->msg_top.$this->wrap(parent::toString(), 'h'.$this->level, ' id="'.$this->id.'"');
	}
}

// ----
// Horizontal Rule
class HRule extends Element
{
	public function HRule(object &$root, string $text) : void
	{
		$this->__construct($root, $text);
	}

	public function __construct(object &$root, string $text)
	{
		parent::__construct();
	}

	public function canContain(object $obj) : bool
	{
		return false;
	}

	public function toString() : string
	{
		global $hr;

		return $hr;
	}
}

// Lists (UL, OL, DL)
class ListContainer extends Element
{
	public /* string */ $tag;

	public /* string */ $tag2;

	public /* int */ $level;

	public /* string */ $style;

	public function ListContainer(string $tag, string $tag2, string $head, string $text) : void
	{
		$this->__construct($tag, $tag2, $head, $text);
	}

	public function __construct(string $tag, string $tag2, string $head, string $text)
	{
		parent::__construct();

		$this->tag = $tag;
		$this->tag2 = $tag2;
		$this->level = min(3, strspn($text, $head));
		$text = ltrim(substr($text, $this->level));

		parent::insert(new ListElement($this->level, $tag2));

		if ($text != '') {
			$this->last = $this->last->insert(Factory_Inline($text));
		}
	}

	public function canContain(object $obj) : bool
	{
		return (!is_a($obj, 'ListContainer')) || (($this->tag === $obj->tag) && ($this->level === $obj->level));
	}

	public function setParent(object &$parent) : void
	{
		parent::setParent($parent);

		$step = $this->level;

		if ((isset($parent->parent)) && (is_a($parent->parent, 'ListContainer'))) {
			$step -= $parent->parent->level;
		}

		$this->style = sprintf(pkwk_list_attrs_template(), $this->level, $step);
	}

	public function insert(object $obj) : object
	{
		if (!is_a($obj, get_class($this))) {
			return $this->last = $this->last->insert($obj);
		}

		// Break if no elements found (BugTrack/524)
		if ((count($obj->elements) == 1) && (empty($obj->elements[0]->elements))) {
			// up to ListElement
			return $this->last->parent;
		}

		// Move elements
		foreach (array_keys($obj->elements) as $key) {
			parent::insert($obj->elements[$key]);
		}

		return $this->last;
	}

	public function toString() : string
	{
		return $this->wrap(parent::toString(), $this->tag, $this->style);
	}
}

class ListElement extends Element
{
	public function ListElement(int $level, string $head) : void
	{
		$this->__construct($level, $head);
	}

	public function __construct(int $level, string $head)
	{
		parent::__construct();
		$this->level = $level;
		$this->head = $head;
	}

	public function canContain(object $obj) : bool
	{
		return (!is_a($obj, 'ListContainer')) || ($obj->level > $this->level);
	}

	public function toString() : string
	{
		return $this->wrap(parent::toString(), $this->head);
	}
}

// - One
// - Two
// - Three
class UList extends ListContainer
{
	public function UList(object &$root, string $text) : void
	{
		$this->__construct($root, $text);
	}

	public function __construct(object &$root, string $text)
	{
		parent::__construct('ul', 'li', '-', $text);
	}
}

// + One
// + Two
// + Three
class OList extends ListContainer
{
	public function OList(object &$root, string $text) : void
	{
		$this->__construct($root, $text);
	}

	public function __construct(object &$root, string $text)
	{
		parent::__construct('ol', 'li', '+', $text);
	}
}

// : definition1 | description1
// : definition2 | description2
// : definition3 | description3
class DList extends ListContainer
{
	public function DList(array $out) : void
	{
		$this->__construct($out);
	}

	public function __construct(array $out)
	{
		assert(count($out) >= 2);

		parent::__construct('dl', 'dt', ':', $out[0]);
		$this->last = Element::insert(new ListElement($this->level, 'dd'));

		if ($out[1] != '') {
			$this->last = $this->last->insert(Factory_Inline($out[1]));
		}
	}
}

// > Someting cited
// > like E-mail text
class BQuote extends Element
{
	public /* int */ $level;

	public function BQuote(object &$root, string $text) : void
	{
		$this->__construct($root, $text);
	}

	public function __construct(object &$root, string $text)
	{
		parent::__construct();

		$head = substr($text, 0, 1);
		$this->level = min(3, strspn($text, $head));
		$text = ltrim(substr($text, $this->level));

		// Blockquote close
		if ($head == '<') {
			$level = $this->level;
			$this->level = 0;
			$this->last = $this->end($root, $level);

			if ($text != '') {
				$this->last = $this->last->insert(Factory_Inline($text));
			}
		} else {
			$this->insert(Factory_Inline($text));
		}
	}

	public function canContain(object $obj) : bool
	{
		return (!is_a($obj, get_class($this))) || ($obj->level >= $this->level);
	}

	public function insert(object $obj) : object
	{
		// BugTrack/521, BugTrack/545
		if (is_a($obj, 'inline')) {
			return parent::insert($obj->toPara(' class="quotation"'));
		}

		if ((is_a($obj, 'BQuote')) && ($obj->level == $this->level) && (count($obj->elements))) {
			$obj = $obj->elements[0];

			if ((is_a($this->last, 'Paragraph')) && (count($obj->elements))) {
				$obj = $obj->elements[0];
			}
		}

		return parent::insert($obj);
	}

	public function toString() : string
	{
		return $this->wrap(parent::toString(), 'blockquote');
	}

	public function end(object &$root, int $level) : object
	{
		$parent = &$root->last;

		while (is_object($parent)) {
			if ((is_a($parent, 'BQuote')) && ($parent->level === $level)) {
				return $parent->parent;
			}

			$parent = &$parent->parent;
		}

		return $this;
	}
}

class TableCell extends Element
{
	// {td|th}
	public /* string */ $tag = 'td';

	public /* int */ $colspan = 1;

	public /* int */ $rowspan = 1;

	// is array('width'=>, 'align'=>...);
	public /* array */ $style;

	public function TableCell(string $text, bool $is_template = false) : void
	{
		$this->__construct($text, $is_template);
	}

	public function __construct(string $text, bool $is_template = false)
	{
		parent::__construct();
		$matches = [];
		$this->style = [];

		while (preg_match('/^(?:(LEFT|CENTER|RIGHT)|(BG)?COLOR\(([#\w]+)\)|SIZE\((\d+)\)):(.*)$/', $text, $matches)) {
			if ($matches[1]) {
				$this->style['align'] = 'text-align:'.strtolower($matches[1]).';';
				$text = $matches[5];
			} elseif ($matches[3]) {
				$name = ($matches[2]) ? ('background-color') : ('color');
				$this->style[$name] = $name.':'.htmlsc($matches[3]).';';
				$text = $matches[5];
			} elseif ($matches[4]) {
				$this->style['size'] = 'font-size:'.htmlsc($matches[4]).'px;';
				$text = $matches[5];
			}
		}

		if (($is_template) && (is_numeric($text))) {
			$this->style['width'] = 'width:'.$text.'px;';
		}

		if ($text == '>') {
			$this->colspan = 0;
		} elseif ($text == '~') {
			$this->rowspan = 0;
		} elseif (substr($text, 0, 1) == '~') {
			$this->tag = 'th';
			$text = substr($text, 1);
		}

		if (($text != '') && ($text[0] == '#')) {
			// Try using Div class for this $text
			$obj = Factory_Div($this, $text);

			if (is_a($obj, 'Paragraph')) {
				$obj = $obj->elements[0];
			}
		} else {
			$obj = Factory_Inline($text);
		}

		$this->insert($obj);
	}

	public function setStyle(array &$style) : void
	{
		foreach ($style as $key=>$value) {
			if (!isset($this->style[$key])) {
				$this->style[$key] = $value;
			}
		}
	}

	public function toString() : string
	{
		if (($this->rowspan == 0) || ($this->colspan == 0)) {
			return '';
		}

		$param = ' class="style_'.$this->tag.'"';

		if ($this->rowspan > 1) {
			$param .= ' rowspan="'.((string) ($this->rowspan)).'"';
		}

		if ($this->colspan > 1) {
			$param .= ' colspan="'.((string) ($this->colspan)).'"';
			unset($this->style['width']);
		}

		if (!empty($this->style)) {
			$param .= ' style="'.implode(' ', $this->style).'"';
		}

		return $this->wrap(parent::toString(), $this->tag, $param, false);
	}
}

// | title1 | title2 | title3 |
// | cell1  | cell2  | cell3  |
// | cell4  | cell5  | cell6  |
class Table extends Element
{
	public /* string */ $type;

	public /* array */ $types;

	// number of column
	public /* int */ $col;

	public function Table(array $out) : void
	{
		$this->__construct($out);
	}

	public function __construct(array $out)
	{
		parent::__construct();

		$cells = explode('|', $out[1]);
		$this->col = count($cells);
		$this->type = strtolower($out[2]);
		$this->types = [$this->type];
		$is_template = ($this->type == 'c');
		$row = [];

		foreach ($cells as $cell) {
			$row[] = new TableCell($cell, $is_template);
		}

		$this->elements[] = $row;
	}

	public function canContain(object $obj) : bool
	{
		return is_a($obj, 'Table') && ($obj->col == $this->col);
	}

	public function insert(object $obj) : object
	{
		$this->elements[] = $obj->elements[0];
		$this->types[] = $obj->type;

		return $this;
	}

	public function toString() : string
	{
		static $parts = ['h'=>'thead', 'f'=>'tfoot', ''=>'tbody'];

		// Set rowspan (from bottom, to top)
		for ($ncol = 0; $ncol < $this->col; $ncol++) {
			$rowspan = 1;

			foreach (array_reverse(array_keys($this->elements)) as $nrow) {
				$row = $this->elements[$nrow];

				if ($row[$ncol]->rowspan == 0) {
					$rowspan++;

					continue;
				}

				$row[$ncol]->rowspan = $rowspan;
				// Inherits row type
				while (--$rowspan) {
					$this->types[$nrow + $rowspan] = $this->types[$nrow];
				}

				$rowspan = 1;
			}
		}

		// Set colspan and style
		$stylerow = null;

		foreach (array_keys($this->elements) as $nrow) {
			$row = $this->elements[$nrow];

			if ($this->types[$nrow] == 'c') {
				$stylerow = &$row;
			}

			$colspan = 1;

			foreach (array_keys($row) as $ncol) {
				if ($row[$ncol]->colspan == 0) {
					$colspan++;

					continue;
				}

				$row[$ncol]->colspan = $colspan;

				if ($stylerow !== null) {
					$row[$ncol]->setStyle($stylerow[$ncol]->style);
					// Inherits column style
					while (--$colspan) {
						$row[$ncol - $colspan]->setStyle($stylerow[$ncol]->style);
					}
				}

				$colspan = 1;
			}
		}

		// toString
		$string = '';

		foreach ($parts as $type=>$part) {
			$part_string = '';

			foreach (array_keys($this->elements) as $nrow) {
				if ($this->types[$nrow] != $type) {
					continue;
				}

				$row = $this->elements[$nrow];
				$row_string = '';

				foreach (array_keys($row) as $ncol) {
					$row_string .= $row[$ncol]->toString();
				}

				$part_string .= $this->wrap($row_string, 'tr')."\n";
			}

			$string .= $this->wrap($part_string, $part);
		}

		$string = $this->wrap($string, 'table', ' class="style_table" cellspacing="1" border="0"');

		return $this->wrap($string, 'div', ' class="ie5"');
	}
}

// , cell1  , cell2  ,  cell3
// , cell4  , cell5  ,  cell6
// , cell7  ,        right,==
// ,left          ,==,  cell8
class YTable extends Element
{
	// Number of columns
	public /* int */ $col;

	public function YTable(array $row = ['cell1 ', ' cell2 ', ' cell3']) : void
	{
		$this->__construct($row);
	}

	// TODO: Seems unable to show literal '==' without tricks.
	//       But it will be imcompatible.
	// TODO: Why toString() or toXHTML() here
	public function __construct(array $row = ['cell1 ', ' cell2 ', ' cell3'])
	{
		parent::__construct();

		$str = [];
		$col = count($row);

		$_align = [];
		$_value = [];
		$matches = [];

		foreach ($row as $cell) {
			if (preg_match('/^(\s+)?(.+?)(\s+)?$/', $cell, $matches)) {
				if ($matches[2] == '==') {
					// Colspan
					$_value[] = false;
					$_align[] = false;
				} else {
					$_value[] = $matches[2];

					if ($matches[1] == '') {
						// left
						$_align[] = '';
					} elseif (isset($matches[3])) {
						$_align[] = 'center';
					} else {
						$_align[] = 'right';
					}
				}
			} else {
				$_value[] = $cell;
				$_align[] = '';
			}
		}

		for ($i = 0; $i < $col; $i++) {
			if ($_value[$i] === false) {
				continue;
			}

			$colspan = 1;

			while ((isset($_value[$i + $colspan])) && ($_value[$i + $colspan] === false)) {
				$colspan++;
			}

			$colspan = ($colspan > 1) ? (' colspan="'.((string) ($colspan)).'"') : ('');
			$align = ($_align[$i]) ? (' style="text-align:'.$_align[$i].'"') : ('');
			$str[] = '<td class="style_td"'.$align.((string) ($colspan)).'>';
			$str[] = make_link($_value[$i]);
			$str[] = '</td>';
			unset($_value[$i], $_align[$i]);
		}

		$this->col = $col;
		$this->elements[] = implode('', $str);
	}

	public function canContain(object $obj) : bool
	{
		return is_a($obj, 'YTable') && ($obj->col == $this->col);
	}

	public function insert(object $obj) : object
	{
		$this->elements[] = $obj->elements[0];

		return $this;
	}

	public function toString() : string
	{
		$rows = '';

		foreach ($this->elements as $str) {
			$rows .= "\n".'<tr class="style_tr">'.$str.'</tr>'."\n";
		}

		$rows = $this->wrap($rows, 'table', ' class="style_table" cellspacing="1" border="0"');

		return $this->wrap($rows, 'div', ' class="ie5"');
	}
}

// ' 'Space-beginning sentence
// ' 'Space-beginning sentence
// ' 'Space-beginning sentence
class Pre extends Element
{
	public function Pre(object &$root, string $text) : void
	{
		$this->__construct($root, $text);
	}

	public function __construct(object &$root, string $text)
	{
		global $preformat_ltrim;

		parent::__construct();
		$this->elements[] = htmlsc(((!$preformat_ltrim) || ($text == '') || ($text[0] != ' ')) ? ($text) : (substr($text, 1)));
	}

	public function canContain(object $obj) : bool
	{
		return is_a($obj, 'Pre');
	}

	public function insert(object $obj) : object
	{
		$this->elements[] = $obj->elements[0];

		return $this;
	}

	public function toString() : string
	{
		return $this->wrap(implode("\n", $this->elements), 'pre');
	}
}

// Block plugin: #something (started with '#')
class Div extends Element
{
	public /* string */ $name;

	public /* string */ $param;

	public function Div(array $out) : void
	{
		$this->__construct($out);
	}

	public function __construct(array $out)
	{
		parent::__construct();
		[, $this->name, $this->param] = array_pad($out, 3, '');
	}

	public function canContain(object $obj) : bool
	{
		return false;
	}

	public function toString() : string
	{
		// Call #plugin
		return do_plugin_convert($this->name, $this->param);
	}
}

// LEFT:/CENTER:/RIGHT:
class Align extends Element
{
	public /* string */ $align;

	public function Align(string $align) : void
	{
		$this->__construct($align);
	}

	public function __construct(string $align)
	{
		parent::__construct();
		$this->align = $align;
	}

	public function canContain(object $obj) : bool
	{
		return is_a($obj, 'Inline');
	}

	public function toString() : string
	{
		return $this->wrap(parent::toString(), 'div', ' style="text-align:'.$this->align.'"');
	}
}

// Body
class Body extends Element
{
	public /* string */ $id;

	public /* int */ $count = 0;

	public /* object */ $contents;

	public /* object */ $contents_last;

	public /* array */ $classes =
	[
		'-'=>'UList',
		'+'=>'OList',
		'>'=>'BQuote',
		'<'=>'BQuote',
	];

	public /* array */ $factories =
	[
		':'=>'DList',
		'|'=>'Table',
		','=>'YTable',
		'#'=>'Div',
	];

	public function Body(int $id) : void
	{
		$this->__construct($id);
	}

	public function __construct(int $id)
	{
		$this->id = (string) ($id);
		$this->contents = new Element();
		$this->contents_last = $this->contents;
		parent::__construct();
	}

	public function parse(array &$lines) : void
	{
		$this->last = $this;
		$matches = [];

		while (!empty($lines)) {
			$line = array_shift($lines);

			// Escape comments
			if (substr($line, 0, 2) == '//') {
				continue;
			}

			if (preg_match('/^(LEFT|CENTER|RIGHT):(.*)$/', $line, $matches)) {
				// <div style="text-align:...">
				$this->last = $this->last->add(new Align(strtolower($matches[1])));

				if ($matches[2] == '') {
					continue;
				}

				$line = $matches[2];
			}

			$line = rtrim($line, "\r\n");

			// Empty
			if ($line == '') {
				$this->last = $this;

				continue;
			}

			// Horizontal Rule
			if (substr($line, 0, 4) == '----') {
				$this->insert(new HRule($this, $line));

				continue;
			}

			// Multiline-enabled block plugin
			if ((!PKWKEXP_DISABLE_MULTILINE_PLUGIN_HACK) && (preg_match('/^#[^{]+(\{\{+)\s*$/', $line, $matches))) {
				$len = strlen($matches[1]);

				// Delimiter
				$line .= "\r";

				while (!empty($lines)) {
					$next_line = preg_replace("/[\r\n]*$/", '', array_shift($lines));

					if (preg_match('/\}{'.$len.'}/', $next_line)) {
						$line .= $next_line;

						break;
					} else {
						// Delimiter
						$next_line .= "\r";
						$line .= $next_line;
					}
				}
			}

			// The first character
			$head = $line[0];

			// Heading
			if ($head == '*') {
				$this->insert(new Heading($this, $line));

				continue;
			}

			// Pre
			if (($head == ' ') || ($head == "\t")) {
				$this->last = $this->last->add(new Pre($this, $line));

				continue;
			}

			// Line Break
			if (substr($line, -1) == '~') {
				$line = substr($line, 0, -1)."\r";
			}

			// Other Character
			if (isset($this->classes[$head])) {
				$classname = $this->classes[$head];
				$this->last = $this->last->add(new $classname($this, $line));

				continue;
			}

			// Other Character
			if (isset($this->factories[$head])) {
				$factoryname = 'Factory_'.$this->factories[$head];
				$this->last = $this->last->add($factoryname($this, $line));

				continue;
			}

			// Default
			$this->last = $this->last->add(Factory_Inline($line));
		}
	}

	public function getAnchor(string $text, int $level) : array
	{
		global $top;
		global $_symbol_anchor;

		// Heading id (auto-generated)
		$autoid = 'content_'.$this->id.'_'.$this->count;
		$this->count++;

		// Heading id (specified by users)
		// Cut fixed-anchor from $text
		$id = make_heading($text, false);

		if ($id == '') {
			// Not specified
			$id = &$autoid;
			$anchor = '';
		} else {
			$anchor = ' &aname('.$id.',super,full,nouserselect){'.$_symbol_anchor.'};';
		}

		$text = ' '.$text;

		// Add 'page contents' link to its heading
		$this->contents_last = $this->contents_last->add(new Contents_UList($text, $level, $id));

		// Add heding
		return [$text.$anchor, (($this->count > 1) ? ("\n".$top) : ('')), $autoid];
	}

	public function insert(object $obj) : object
	{
		if (is_a($obj, 'Inline')) {
			$obj = $obj->toPara();
		}

		return parent::insert($obj);
	}

	public function toString() : string
	{
		global $vars;

		$text = parent::toString();

		// #contents
		$text = preg_replace_callback('/<#_contents_>/', [$this, 'replace_contents'], $text);

		return $text."\n";
	}

	public function replace_contents(array $arr) : string
	{
		return '<div class="contents">'."\n".'<a id="contents_'.$this->id.'"></a>'."\n".$this->contents->toString()."\n".'</div>'."\n";
	}
}

class Contents_UList extends ListContainer
{
	public function Contents_UList(string $text, int $level, string $id) : void
	{
		$this->__construct($text, $level, $id);
	}

	public function __construct(string $text, int $level, string $id)
	{
		// Reformatting $text
		// A line started with "\n" means "preformatted" ... X(
		make_heading($text);
		$text = "\n".'<a href="#'.$id.'">'.$text.'</a>'."\n";
		parent::__construct('ul', 'li', '-', str_repeat('-', $level));
		$this->insert(Factory_Inline($text));
	}

	public function setParent(object &$parent) : void
	{
		parent::setParent($parent);
		$step = $this->level;

		if ((isset($parent->parent)) && (is_a($parent->parent, 'ListContainer'))) {
			$step -= $parent->parent->level;
		}

		$indent_level = ($step == $this->level) ? (1) : ($step);
		$this->style = sprintf(pkwk_list_attrs_template(), $this->level, $indent_level);
	}
}
