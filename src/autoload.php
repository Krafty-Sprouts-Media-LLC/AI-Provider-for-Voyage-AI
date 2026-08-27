<?php
/**
 * PSR-4 style autoloader for AI Provider for Voyage AI.
 *
 * @package KraftySprouts\AiProviderForVoyageAi
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix   = 'KraftySprouts\\AiProviderForVoyageAi\\';
		$base_dir = __DIR__ . '/';
		$len      = strlen( $prefix );

		if ( 0 !== strncmp( $class, $prefix, $len ) ) {
			return;
		}

		$relative = substr( $class, $len );
		$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);
