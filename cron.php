<?php
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
define( 'WP_USE_THEMES', false );
define( 'WP_DEBUG', true );
define( 'CO_CLI_CRON', true );
require './wp-blog-header.php';
