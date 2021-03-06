<?php
declare(strict_types=1);

// $Id: stationary.inc.php,v 1.9 2011/01/25 15:01:01 henoheno Exp $
//
// Stationary plugin
// License: The same as PukiWiki

// Define someting like this
define('PLUGIN_STATIONARY_MAX', 10);

// Init someting
function plugin_stationary_init() : void
{
	if ((PKWK_SAFE_MODE) || (PKWK_READONLY)) {
		// Do nothing
		return;
	}

	$messages =
	[
		'_plugin_stationary_A'=>'a',
		'_plugin_stationary_B'=>['C'=>'c', 'D'=>'d'],
	];

	set_plugin_messages($messages);
}

// Convert-type plugin: #stationary or #stationary(foo)
function plugin_stationary_convert(string ...$args) : string
{
	// If you don't want this work at secure/productive site,
	if (PKWK_SAFE_MODE) {
		// Show nothing
		return '';
	}

	// If this plugin will write someting,
	if (PKWK_READONLY) {
		// Show nothing
		return '';
	}

	// Init
	$result = '';

	// Get arguments
	if (func_num_args()) {
		foreach (array_keys($args) as $key) {
			$args[$key] = trim($args[$key]);
		}

		$result = implode(',', $args);
	}

	return '#stationary('.htmlspecialchars($result, ENT_COMPAT, 'UTF-8').')<br />';
}

// In-line type plugin: &stationary; or &stationary(foo); , or &stationary(foo){bar};
function plugin_stationary_inline(string ...$args) : string
{
	if ((PKWK_SAFE_MODE) || (PKWK_READONLY)) {
		// See above
		return '';
	}

	// {bar} is always exists, and already sanitized
	// {bar}
	$body = strip_autolink(array_pop($args));

	foreach (array_keys($args) as $key) {
		$args[$key] = trim($args[$key]);
	}

	$result = implode(',', $args);

	return '&amp;stationary('.htmlspecialchars($result, ENT_COMPAT, 'UTF-8').'){'.$body.'};';
}

// Action-type plugin: ?plugin=stationary&foo=bar
function plugin_stationary_action() : array
{
	// See above
	if ((PKWK_SAFE_MODE) || (PKWK_READONLY)) {
		die_message('PKWK_SAFE_MODE or PKWK_READONLY prohibits this');
	}

	$msg = 'Message';
	$body = 'Message body';

	return ['msg'=>htmlspecialchars($msg, ENT_COMPAT, 'UTF-8'), 'body'=>htmlspecialchars($body, ENT_COMPAT, 'UTF-8')];
}
