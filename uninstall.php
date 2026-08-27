<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This provider stores no options of its own. API keys live in WordPress
 * Connectors (or environment / constants) and are left intact so other
 * Voyage-capable tools keep working after uninstall.
 *
 * @package KraftySprouts\AiProviderForVoyageAi
 * @since   1.0.0
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
