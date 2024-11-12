<?php

defined( 'ABSPATH' ) || exit;

if ( isset( $GLOBALS['wp_version'] ) && function_exists( 'add_action' ) && basename( $dir = dirname( __DIR__ ) ) !== 'pfaciana' && basename( dirname( $dir ) ) !== 'vendor' ) {
	\add_action( 'plugins_loaded', function () {
		\Render\Autoload\ClassLoader::getInstance();
	}, PHP_INT_MIN );
}