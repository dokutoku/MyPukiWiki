<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// diff.php
// Copyright
//   2003-2016 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//

// Show more information when it conflicts
define('PKWK_DIFF_SHOW_CONFLICT_DETAIL', 1);

// Create diff-style data between arrays
function do_diff(string $strlines1, string $strlines2) : string
{
	$obj = new line_diff();

	return $obj->str_compare($strlines1, $strlines2);
}

// Visualize diff-style-text to text-with-CSS
//   '+Added'=>'<span added>Added</span>'
//   '-Removed'=>'<span removed>Removed</span>'
//   ' Nothing'=>'Nothing'
function diff_style_to_css(string $str = '') : string
{
	// Cut diff markers ('+' or '-' or ' ')
	$str = preg_replace('/^\-(.*)$/m', '<span class="diff_removed">$1</span>', $str);
	$str = preg_replace('/^\+(.*)$/m', '<span class="diff_added"  >$1</span>', $str);

	return preg_replace('/^ (.*)$/m', '$1', $str);
}

// Merge helper (when it conflicts)
function do_update_diff(string $pagestr, string $poststr, string $original) : array
{
	$obj = new line_diff();

	$obj->set_str('left', $original, $pagestr);
	$obj->compare();
	$diff1 = $obj->toArray();

	$obj->set_str('right', $original, $poststr);
	$obj->compare();
	$diff2 = $obj->toArray();

	$arr = $obj->arr_compare('all', $diff1, $diff2);

	if (PKWK_DIFF_SHOW_CONFLICT_DETAIL) {
		global $do_update_diff_table;

		$table = [];
		$table[] = <<<'EOD'
<p>l : between backup data and stored page data.<br />
 r : between backup data and your post data.</p>
<table class="style_table">
 <tr>
  <th>l</th>
  <th>r</th>
  <th>text</th>
 </tr>
EOD;
		$tags = ['th', 'th', 'td'];

		foreach ($arr as $_obj) {
			$table[] = ' <tr>';
			$params = [$_obj->get('left'), $_obj->get('right'), $_obj->text()];

			foreach ($params as $key=>$text) {
				$text = htmlsc(rtrim($text));

				if (empty($text)) {
					$text = '&nbsp;';
				}

				$table[] = '  <'.$tags[$key].' class="style_'.$tags[$key].'">'.$text.'</'.$tags[$key].'>';
			}

			$table[] = ' </tr>';
		}

		$table[] = '</table>';

		$do_update_diff_table = implode("\n", $table)."\n";
		unset($table);
	}

	$body = [];

	foreach ($arr as $_obj) {
		if (($_obj->get('left') != '-') && ($_obj->get('right') != '-')) {
			$body[] = $_obj->text();
		}
	}

	return [rtrim(implode('', $body))."\n", 1];
}

// References of this class:
// S. Wu, <A HREF="http://www.cs.arizona.edu/people/gene/vita.html">
// E. Myers,</A> U. Manber, and W. Miller,
// <A HREF="http://www.cs.arizona.edu/people/gene/PAPERS/np_diff.ps">
// "An O(NP) Sequence Comparison Algorithm,"</A>
// Information Processing Letters 35, 6 (1990), 317-323.
class line_diff
{
	public /* array */ $arr1;

	public /* array */ $arr2;

	public /* int */ $m;

	public /* int */ $n;

	public /* array */ $pos;

	public /* string */ $key;

	public /* string */ $plus;

	public /* string */ $minus;

	public /* string */ $equal;

	public /* bool */ $reverse;

	public function line_diff(string $plus = '+', string $minus = '-', string $equal = ' ') : void
	{
		$this->__construct($plus, $minus, $equal);
	}

	public function __construct(string $plus = '+', string $minus = '-', string $equal = ' ')
	{
		$this->plus = $plus;
		$this->minus = $minus;
		$this->equal = $equal;
	}

	public function arr_compare(string $key, array $arr1, array $arr2) : array
	{
		$this->key = $key;
		$this->arr1 = $arr1;
		$this->arr2 = $arr2;
		$this->compare();

		return $this->toArray();
	}

	public function set_str(string $key, string $str1, string $str2) : void
	{
		$this->key = $key;
		$this->arr1 = [];
		$this->arr2 = [];
		$str1 = str_replace("\r", '', $str1);
		$str2 = str_replace("\r", '', $str2);

		foreach (explode("\n", $str1) as $line) {
			$this->arr1[] = new DiffLine($line);
		}

		foreach (explode("\n", $str2) as $line) {
			$this->arr2[] = new DiffLine($line);
		}
	}

	public function str_compare(string $str1, string $str2) : string
	{
		$this->set_str('diff', $str1, $str2);
		$this->compare();

		$str = '';

		foreach ($this->toArray() as $obj) {
			$str .= $obj->get('diff').$obj->text();
		}

		return $str;
	}

	public function compare() : void
	{
		$this->m = count($this->arr1);
		$this->n = count($this->arr2);

		// No need to compare
		if (($this->m == 0) || ($this->n == 0)) {
			$this->result = [['x'=>0, 'y'=>0]];

			return;
		}

		// Sentinel
		array_unshift($this->arr1, new DiffLine(''));
		$this->m++;
		array_unshift($this->arr2, new DiffLine(''));
		$this->n++;

		$this->reverse = ($this->n < $this->m);

		if ($this->reverse) {
			// Swap
			$tmp = $this->m;
			$this->m = $this->n;
			$this->n = $tmp;
			$tmp = $this->arr1;
			$this->arr1 = $this->arr2;
			$this->arr2 = $tmp;
			unset($tmp);
		}

		// Must be >=0;
		$delta = $this->n - $this->m;

		$fp = [];
		$this->path = [];

		for ($p = -($this->m + 1); $p <= ($this->n + 1); $p++) {
			$fp[$p] = -1;
			$this->path[$p] = [];
		}

		for ($p = 0; ; $p++) {
			for ($k = -$p; $k <= ($delta - 1); $k++) {
				$fp[$k] = $this->snake($k, $fp[$k - 1], $fp[$k + 1]);
			}

			for ($k = $delta + $p; $k >= ($delta + 1); $k--) {
				$fp[$k] = $this->snake($k, $fp[$k - 1], $fp[$k + 1]);
			}

			$fp[$delta] = $this->snake($delta, $fp[$delta - 1], $fp[$delta + 1]);

			if ($fp[$delta] >= $this->n) {
				// 経路を決定
				$this->pos = $this->path[$delta];

				return;
			}
		}
	}

	public function snake(int $k, int $y1, int $y2) : int
	{
		if ($y1 >= $y2) {
			$_k = $k - 1;
			$y = $y1 + 1;
		} else {
			$_k = $k + 1;
			$y = $y2;
		}

		// ここまでの経路をコピー
		$this->path[$k] = $this->path[$_k];

		$x = $y - $k;

		while ((($x + 1) < $this->m) && (($y + 1) < $this->n) && ($this->arr1[$x + 1]->compare($this->arr2[$y + 1]))) {
			$x++;
			$y++;

			// 経路を追加
			$this->path[$k][] = ['x'=>$x, 'y'=>$y];
		}

		return $y;
	}

	public function toArray() : array
	{
		$arr = [];

		// 姑息な…
		if ($this->reverse) {
			$_x = 'y';
			$_y = 'x';
			$_m = $this->n;
			$arr1 = $this->arr2;
			$arr2 = $this->arr1;
		} else {
			$_x = 'x';
			$_y = 'y';
			$_m = $this->m;
			$arr1 = $this->arr1;
			$arr2 = $this->arr2;
		}

		$y = 1;
		$x = 1;
		$this->delete_count = 0;
		$this->add_count = 0;

		// Sentinel
		$this->pos[] = ['x'=>$this->m, 'y'=>$this->n];

		foreach ($this->pos as $pos) {
			$this->delete_count += ($pos[$_x] - $x);
			$this->add_count += ($pos[$_y] - $y);

			while ($pos[$_x] > $x) {
				$arr1[$x]->set($this->key, $this->minus);
				$arr[] = $arr1[$x++];
			}

			while ($pos[$_y] > $y) {
				$arr2[$y]->set($this->key, $this->plus);
				$arr[] = $arr2[$y++];
			}

			if ($x < $_m) {
				$arr1[$x]->merge($arr2[$y]);
				$arr1[$x]->set($this->key, $this->equal);
				$arr[] = $arr1[$x];
			}

			$x++;
			$y++;
		}

		return $arr;
	}
}

class DiffLine
{
	public /* string */ $text;

	public /* array */ $status;

	public function DiffLine(string $text) : void
	{
		$this->__construct($text);
	}

	public function __construct(string $text)
	{
		$this->text = $text."\n";
		$this->status = [];
	}

	public function compare(DiffLine $obj)
	{
		return $this->text == $obj->text;
	}

	public function set(string $key, string $status) : void
	{
		$this->status[$key] = $status;
	}

	public function get(string $key) : string
	{
		return (isset($this->status[$key])) ? ($this->status[$key]) : ('');
	}

	public function merge(DiffLine $obj) : void
	{
		$this->status += $obj->status;
	}

	public function text() : string
	{
		return $this->text;
	}
}
