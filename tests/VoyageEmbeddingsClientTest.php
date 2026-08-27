<?php
/**
 * Voyage embeddings HTTP client tests.
 *
 * @package KraftySprouts\AiProviderForVoyageAi\Tests
 */

declare( strict_types=1 );

namespace KraftySprouts\AiProviderForVoyageAi\Tests;

use KraftySprouts\AiProviderForVoyageAi\Http\VoyageEmbeddingsClient;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Verifies request construction and response parsing.
 */
final class VoyageEmbeddingsClientTest extends TestCase {

	/**
	 * Reset fake HTTP state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['voyage_test_requests']  = array();
		$GLOBALS['voyage_test_responses'] = array();
	}

	/**
	 * A successful call sends a Bearer header and the input batch, and
	 * returns the decoded body.
	 *
	 * @return void
	 */
	public function test_embed_sends_bearer_auth_and_input_batch(): void {
		$GLOBALS['voyage_test_responses'][] = self::response(
			array(
				'data'  => array(
					array(
						'embedding' => array( 0.1, 0.2 ),
						'index'     => 0,
					),
				),
				'model' => 'voyage-4',
				'usage' => array( 'total_tokens' => 5 ),
			)
		);

		$result = VoyageEmbeddingsClient::embed( 'test-key', 'voyage-4', array( 'hello world' ), null, array() );

		self::assertIsArray( $result );
		self::assertSame( 'voyage-4', $result['model'] );
		self::assertSame( 'Bearer test-key', $GLOBALS['voyage_test_requests'][0]['args']['headers']['Authorization'] );

		$sent_body = json_decode( $GLOBALS['voyage_test_requests'][0]['args']['body'], true );
		self::assertSame( array( 'hello world' ), $sent_body['input'] );
		self::assertSame( 'voyage-4', $sent_body['model'] );
		self::assertArrayNotHasKey( 'output_dimension', $sent_body );
	}

	/**
	 * The output_dimension parameter is included when provided.
	 *
	 * @return void
	 */
	public function test_embed_sends_output_dimension_when_provided(): void {
		$GLOBALS['voyage_test_responses'][] = self::response(
			array(
				'data'  => array(
					array(
						'embedding' => array( 0.1 ),
						'index'     => 0,
					),
				),
				'model' => 'voyage-4',
				'usage' => array( 'total_tokens' => 3 ),
			)
		);

		VoyageEmbeddingsClient::embed( 'test-key', 'voyage-4', array( 'hi' ), 256, array() );

		$sent_body = json_decode( $GLOBALS['voyage_test_requests'][0]['args']['body'], true );
		self::assertSame( 256, $sent_body['output_dimension'] );
	}

	/**
	 * Custom options (e.g. input_type) are merged into the request body.
	 *
	 * @return void
	 */
	public function test_embed_merges_custom_options(): void {
		$GLOBALS['voyage_test_responses'][] = self::response(
			array(
				'data'  => array(
					array(
						'embedding' => array( 0.1 ),
						'index'     => 0,
					),
				),
				'model' => 'voyage-4',
				'usage' => array( 'total_tokens' => 3 ),
			)
		);

		VoyageEmbeddingsClient::embed( 'test-key', 'voyage-4', array( 'hi' ), null, array( 'input_type' => 'query' ) );

		$sent_body = json_decode( $GLOBALS['voyage_test_requests'][0]['args']['body'], true );
		self::assertSame( 'query', $sent_body['input_type'] );
	}

	/**
	 * An empty API key fails fast without an HTTP call.
	 *
	 * @return void
	 */
	public function test_embed_rejects_empty_api_key(): void {
		$result = VoyageEmbeddingsClient::embed( '', 'voyage-4', array( 'hi' ), null, array() );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( array(), $GLOBALS['voyage_test_requests'] );
	}

	/**
	 * More than MAX_INPUTS inputs is rejected before the HTTP call.
	 *
	 * @return void
	 */
	public function test_embed_rejects_too_many_inputs(): void {
		$texts = array_fill( 0, VoyageEmbeddingsClient::MAX_INPUTS + 1, 'x' );

		$result = VoyageEmbeddingsClient::embed( 'test-key', 'voyage-4', $texts, null, array() );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( array(), $GLOBALS['voyage_test_requests'] );
	}

	/**
	 * A non-2xx response is surfaced as a WP_Error carrying Voyage's message.
	 *
	 * @return void
	 */
	public function test_embed_returns_error_on_non_200_response(): void {
		$GLOBALS['voyage_test_responses'][] = array(
			'code' => 401,
			'body' => (string) json_encode( array( 'detail' => 'Invalid API key' ) ),
		);

		$result = VoyageEmbeddingsClient::embed( 'bad-key', 'voyage-4', array( 'hi' ), null, array() );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'Invalid API key', $result->get_error_message() );
	}

	/**
	 * Create a fake successful WordPress HTTP response.
	 *
	 * @param array<string, mixed> $body Body.
	 * @return array{code:int,body:string}
	 */
	private static function response( array $body ): array {
		return array(
			'code' => 200,
			'body' => (string) json_encode( $body ),
		);
	}
}
