<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// $Id: setlinebreak.inc.php,v 1.5 2011/01/25 15:01:01 henoheno Exp $
//
// Set linebreak plugin - on/of linebreak-to-'<br />' conversion
//
// Usage:
//	#setlinebreak          : Invert on/off
//	#setlinebreak(on)      : ON  (from this line)
//	#setlinebreak(off)     : OFF (from this line)
//	#setlinebreak(default) : Reset

function plugin_setlinebreak_convert(string ...$args) : string
{
	global $line_break;
	static $default;

	if (!isset($default)) {
		$default = $line_break;
	}

	if (func_num_args() == 0) {
		// Invert
		$line_break = !$line_break;
	} else {
		switch (strtolower($args[0])) {
			case 'on':
			case 'true':
			case '1':
				$line_break = 1;

				break;

			case 'off':
			case 'false':
			case '0':
				$line_break = 0;

				break;

			case 'default':
				$line_break = $default;

				break;

			default:
				return '#setlinebreak: Invalid argument: '.htmlsc($args[0]).'<br />';
		}
	}

	return '';
}
