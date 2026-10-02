<?php
/**
 * Plugin Name:       AI Provider for Voyage AI
 * Plugin URI:        https://wordpress.org/plugins/ai-provider-for-voyage-ai/
 * Description:       Voyage AI text-embedding provider for the WordPress AI Client (voyage-4, voyage-3.5, voyage-code-3, and related models).
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      7.4
 * Author:            Krafty Sprouts Media, LLC
 * Author URI:        https://kraftysprouts.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-provider-for-voyage-ai
 * Domain Path:       /languages
 *
 * @package KraftySprouts\AiProviderForVoyageAi
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace KraftySprouts\AiProviderForVoyageAi;

use KraftySprouts\AiProviderForVoyageAi\Provider\VoyageProvider;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WP_Connector_Registry;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIPFVA_AI_PROVIDER_FOR_VOYAGE_AI_VERSION', '1.0.0' );
define( 'AIPFVA_AI_PROVIDER_FOR_VOYAGE_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIPFVA_AI_PROVIDER_FOR_VOYAGE_AI_PLUGIN_FILE', __FILE__ );

require_once AIPFVA_AI_PROVIDER_FOR_VOYAGE_AI_PLUGIN_DIR . 'src/autoload.php';

/**
 * Registers the Voyage AI provider with the PHP AI Client.
 *
 * @since 1.0.0
 * @return void
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( $registry->hasProvider( VoyageProvider::class ) ) {
		return;
	}

	$registry->registerProvider( VoyageProvider::class );
}
add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );

/**
 * Inject Voyage's official VOYAGE_API_KEY into the AI Client when no auth is set yet.
 *
 * The PHP AI Client defaults to VOYAGE_AI_API_KEY (derived from the `voyage-ai`
 * provider id); Connectors skips passing the DB key when VOYAGE_API_KEY is
 * present as env/constant. Without this bridge, availability would stay
 * "not configured" despite a valid VOYAGE_API_KEY.
 *
 * @since 1.0.0
 * @return void
 */
function maybe_set_voyage_key_from_environment(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( ! $registry->hasProvider( VoyageProvider::class ) ) {
		return;
	}

	if ( null !== $registry->getProviderRequestAuthentication( VoyageProvider::class ) ) {
		return;
	}

	$key = getenv( 'VOYAGE_API_KEY' );
	if ( false === $key && defined( 'VOYAGE_API_KEY' ) ) {
		$constant = constant( 'VOYAGE_API_KEY' );
		$key      = is_scalar( $constant ) ? (string) $constant : '';
	}

	if ( ! is_string( $key ) || '' === trim( $key ) ) {
		return;
	}

	$registry->setProviderRequestAuthentication(
		VoyageProvider::class,
		new ApiKeyRequestAuthentication( trim( $key ) )
	);
}
add_action( 'init', __NAMESPACE__ . '\\maybe_set_voyage_key_from_environment', 15 );

/**
 * Prefer Voyage's official VOYAGE_API_KEY env/constant name on the Connectors card.
 *
 * Connectors auto-discovers provider id `voyage-ai` as VOYAGE_AI_API_KEY;
 * Voyage's documented variable is VOYAGE_API_KEY. Override the
 * auto-registered connector (unregister → register) with the correct names.
 *
 * @since 1.0.0
 * @param WP_Connector_Registry $registry Connector registry.
 * @return void
 */
function map_voyage_key_env( WP_Connector_Registry $registry ): void {
	if ( ! $registry->is_registered( 'voyage-ai' ) ) {
		return;
	}

	$existing = $registry->get_registered( 'voyage-ai' );
	if ( ! is_array( $existing ) ) {
		return;
	}

	if ( ! isset( $existing['authentication'] ) || ! is_array( $existing['authentication'] ) ) {
		$existing['authentication'] = array( 'method' => 'api_key' );
	}

	$existing['authentication']['env_var_name']    = 'VOYAGE_API_KEY';
	$existing['authentication']['constant_name']   = 'VOYAGE_API_KEY';
	$existing['authentication']['credentials_url'] = 'https://dashboard.voyageai.com/organization/api-keys';

	// WP 7.0+ forbids register() on an existing ID — must unregister first.
	$registry->unregister( 'voyage-ai' );
	$registry->register( 'voyage-ai', $existing );
}
add_action( 'wp_connectors_init', __NAMESPACE__ . '\\map_voyage_key_env', 20 );
