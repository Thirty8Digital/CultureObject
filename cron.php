<?php
/**
 * CLI entry point for the Culture Object cron sync: php wp-content/plugins/<plugin-folder>/cron.php
 *
 * Run from the WordPress root. It bootstraps WordPress itself and only works from the command line.
 *
 * @package CultureObject
 */

if ( ! defined( 'ABSPATH' ) ) {
	if ( PHP_SAPI !== 'cli' ) {
		exit;
	}
	define( 'WP_USE_THEMES', false );
	define( 'WP_DEBUG', true );
	define( 'CO_CLI_CRON', true );
	require './wp-blog-header.php';
}
