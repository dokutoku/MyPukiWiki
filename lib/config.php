<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// config.php
// Copyright 2003-2016 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Parse a PukiWiki page as a configuration page

/*
 * $obj = new Config('plugin/plugin_name/')
 * $obj->read();
 * $array = & $obj->get($title);
 * $array[] = [4, 5, 6];		// Add - directly
 * $obj->add($title, [4, 5, 6]);	// Add - method of Config object
 * $array = [1=>[1, 2, 3]];		// Replace - directly
 * $obj->put($title, [1=>[1, 2, 3]]);	// Replace - method of Config object
 * $obj->put_values($title, null);	// Delete
 * $obj->write();
 */

// Fixed prefix of configuration-page's name
define('PKWK_CONFIG_PREFIX', ':config/');

// Configuration-page manager
class Config
{
	public /* string */ $name;

	// Page name
	public /* string */ $page;

	public /* array */ $objs = [];

	public function Config(string $name) : void
	{
		$this->__construct($name);
	}

	public function __construct(string $name)
	{
		$this->name = $name;
		$this->page = PKWK_CONFIG_PREFIX.$name;
	}

	// Load the configuration-page
	public function read() : bool
	{
		if (!is_page($this->page)) {
			return false;
		}

		$this->objs = [];
		$obj = new ConfigTable('');
		$matches = [];

		foreach (get_source($this->page) as $line) {
			if ($line == '') {
				continue;
			}

			// The first letter
			$head = $line[0];

			$level = strspn($line, $head);

			if ($level > 3) {
				$obj->add_line($line);
			} elseif ($head == '*') {
				// Cut fixed-heading anchors
				$line = preg_replace('/^(\*{1,3}.*)\[#[A-Za-z][\w-]+\](.*)$/', '$1$2', $line);

				if ($level == 1) {
					$this->objs[$obj->title] = $obj;
					$obj = new ConfigTable($line);
				} else {
					if (!is_a($obj, 'ConfigTable_Direct')) {
						$obj = new ConfigTable_Direct('', $obj);
					}

					$obj->set_key($line);
				}
			} elseif (($head == '-') && ($level > 1)) {
				if (!is_a($obj, 'ConfigTable_Direct')) {
					$obj = new ConfigTable_Direct('', $obj);
				}

				$obj->add_value($line);
			} elseif (($head == '|') && (preg_match('/^\|(.+)\|\s*$/', $line, $matches))) {
				// Table row
				if (!is_a($obj, 'ConfigTable_Sequential')) {
					$obj = new ConfigTable_Sequential('', $obj);
				}

				// Trim() each table cell
				$obj->add_value(array_map('trim', explode('|', $matches[1])));
			} else {
				$obj->add_line($line);
			}
		}

		$this->objs[$obj->title] = $obj;

		return true;
	}

	// Get an array
	public function get(string $title)
	{
		$obj = $this->get_object($title);

		return $obj->values;
	}

	// Set an array (Override)
	public function put(string $title, array $values) : void
	{
		$obj = $this->get_object($title);
		$obj->values = $values;
	}

	// Add a line
	public function add(string $title, array $value) : void
	{
		$obj = $this->get_object($title);
		$obj->values[] = $value;
	}

	// Get an object (or create it)
	public function get_object(string $title)
	{
		if (!isset($this->objs[$title])) {
			$this->objs[$title] = new ConfigTable('*'.trim($title)."\n");
		}

		return $this->objs[$title];
	}

	public function write() : void
	{
		page_write($this->page, $this->toString());
	}

	public function toString() : string
	{
		$retval = '';

		foreach ($this->objs as $title=>$obj) {
			$retval .= $obj->toString();
		}

		return $retval;
	}
}

// Class holds array values
class ConfigTable
{
	// Table title
	public /* string */ $title = '';

	// Page contents (except table ones)
	public /* array */ $before = [];

	// Page contents (except table ones)
	public /* array */ $after = [];

	// Table contents
	public /* array */ $values = [];

	public function ConfigTable(string $title, ConfigTable $obj = null) : void
	{
		$this->__construct($title, $obj);
	}

	public function __construct(string $title, ?ConfigTable $obj = null)
	{
		if ($obj !== null) {
			$this->title = $obj->title;
			$this->before = array_merge($obj->before, $obj->after);
		} else {
			$this->title = trim(substr($title, strspn($title, '*')));
			$this->before[] = $title;
		}
	}

	// Addi an  explanation
	public function add_line(string $line) : void
	{
		$this->after[] = $line;
	}

	public function toString() : string
	{
		return implode('', $this->before).implode('', $this->after);
	}
}

class ConfigTable_Sequential extends ConfigTable
{
	// Add a line
	public function add_value(array $value) : void
	{
		$this->values[] = (count($value) == 1) ? ($value[0]) : ($value);
	}

	public function toString() : string
	{
		$retval = implode('', $this->before);

		if (is_array($this->values)) {
			foreach ($this->values as $value) {
				$value = (is_array($value)) ? (implode('|', $value)) : ($value);
				$retval .= '|'.$value.'|'."\n";
			}
		}

		$retval .= implode('', $this->after);

		return $retval;
	}
}

class ConfigTable_Direct extends ConfigTable
{
	// Used at initialization phase
	public /* array */ $_keys = [];

	public function set_key(string $line) : void
	{
		$level = strspn($line, '*');
		$this->_keys[$level] = trim(substr($line, $level));
	}

	// Add a line
	public function add_value(string $line) : void
	{
		$level = strspn($line, '-');
		$arr = $this->values;

		for ($n = 2; $n <= $level; $n++) {
			$arr = &$arr[$this->_keys[$n]];
		}

		$arr[] = trim(substr($line, $level));
	}

	public function toString(array $values = null, int $level = 2) : string
	{
		$retval = '';
		$root = $values === null;

		if ($root) {
			$retval = implode('', $this->before);
			$values = $this->values;
		}

		foreach ($values as $key=>$value) {
			if (is_array($value)) {
				$retval .= str_repeat('*', $level).$key."\n";
				$retval .= $this->toString($value, $level + 1);
			} else {
				$retval .= str_repeat('-', $level - 1).$value."\n";
			}
		}

		if ($root) {
			$retval .= implode('', $this->after);
		}

		return $retval;
	}
}
