<?php
/**
 * PHPUnit bootstrap for the Voyage embeddings client.
 *
 * @package KraftySprouts\AiProviderForVoyageAi\Tests
 */

declare( strict_types=1 );

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

/**
 * Minimal WP_Error replacement.
 */
class WP_Error {

	/**
	 * Message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Extra error data.
	 *
	 * @var mixed
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 */
	public function __construct( string $code = '', string $message = '', $data = null ) {
		unset( $code );
		$this->message = $message;
		$this->data    = $data;
	}

	/**
	 * Return message.
	 *
	 * @return string
	 */
	public function get_error_message(): string {
		return $this->message;
	}

	/**
	 * Return error data.
	 *
	 * @return mixed
	 */
	public function get_error_data() {
		return $this->data;
	}
}

/**
 * Test whether a value is WP_Error.
 *
 * @param mixed $value Value.
 * @return bool
 */
function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

/**
 * Return untranslated text.
 *
 * @param string $text   Text.
 * @param string $domain Text domain (ignored in tests).
 * @return string
 */
function __( string $text, string $domain = 'default' ): string {
	unset( $domain );
	return $text;
}

/**
 * JSON encode.
 *
 * @param mixed $value Value.
 * @return string|false
 */
function wp_json_encode( $value ) {
	return json_encode( $value );
}

/**
 * Return queued fake HTTP responses.
 *
 * @param string               $url  URL.
 * @param array<string, mixed> $args Request args.
 * @return array<string, mixed>|WP_Error
 */
function wp_remote_request( string $url, array $args ) {
	$GLOBALS['voyage_test_requests'][] = array(
		'url'  => $url,
		'args' => $args,
	);
	return array_shift( $GLOBALS['voyage_test_responses'] );
}

/**
 * Return response status.
 *
 * @param array<string, mixed> $response Response.
 * @return int
 */
function wp_remote_retrieve_response_code( array $response ): int {
	return (int) $response['code'];
}

/**
 * Return response body.
 *
 * @param array<string, mixed> $response Response.
 * @return string
 */
function wp_remote_retrieve_body( array $response ): string {
	return (string) $response['body'];
}

require dirname( __DIR__ ) . '/src/Http/VoyageEmbeddingsClient.php';
