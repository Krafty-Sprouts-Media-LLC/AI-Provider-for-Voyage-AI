<?php
/**
 * Plain HTTP client for the Voyage AI embeddings endpoint.
 *
 * Kept independent of the PHP AI Client's model base classes so it can be
 * unit tested with WordPress's HTTP functions faked, mirroring the approach
 * the fal.ai provider plugin uses for its queue client.
 *
 * @package KraftySprouts\AiProviderForVoyageAi\Http
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace KraftySprouts\AiProviderForVoyageAi\Http;

use WP_Error;

/**
 * Class VoyageEmbeddingsClient
 *
 * @since 1.0.0
 */
class VoyageEmbeddingsClient {

	/**
	 * Voyage's embeddings endpoint.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const ENDPOINT = 'https://api.voyageai.com/v1/embeddings';

	/**
	 * Per-call HTTP timeout (seconds).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const HTTP_TIMEOUT = 30;

	/**
	 * Max inputs accepted by Voyage in one request.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const MAX_INPUTS = 1000;

	/**
	 * Request embeddings for a batch of texts.
	 *
	 * @since 1.0.0
	 * @param string               $api_key          Voyage API key.
	 * @param string               $model_id         Voyage model id.
	 * @param list<string>         $texts            Texts to embed, in order.
	 * @param int|null             $output_dimension Requested output dimension, or null for the model default.
	 * @param array<string, mixed> $custom_options   Extra Voyage body params (e.g. input_type, truncation).
	 * @return array{data:list<array<string,mixed>>,model:string,usage:array<string,mixed>}|WP_Error
	 */
	public static function embed( string $api_key, string $model_id, array $texts, ?int $output_dimension, array $custom_options ) {
		if ( '' === trim( $api_key ) ) {
			return new WP_Error(
				'aipfva_voyage_no_key',
				__( 'Voyage AI API key is not configured.', 'ai-provider-for-voyage-ai' )
			);
		}

		if ( array() === $texts ) {
			return new WP_Error(
				'aipfva_voyage_no_inputs',
				__( 'At least one input text is required.', 'ai-provider-for-voyage-ai' )
			);
		}

		if ( count( $texts ) > self::MAX_INPUTS ) {
			return new WP_Error(
				'aipfva_voyage_too_many_inputs',
				sprintf(
					/* translators: %d: maximum number of inputs Voyage accepts per request. */
					__( 'Voyage AI accepts at most %d inputs per request.', 'ai-provider-for-voyage-ai' ),
					self::MAX_INPUTS
				)
			);
		}

		$body = array(
			'model' => $model_id,
			'input' => array_values( $texts ),
		);

		if ( null !== $output_dimension ) {
			$body['output_dimension'] = $output_dimension;
		}

		foreach ( $custom_options as $key => $value ) {
			if ( isset( $body[ $key ] ) ) {
				return new WP_Error(
					'aipfva_voyage_option_conflict',
					sprintf(
						/* translators: %s: custom option key. */
						__( 'The custom option "%s" conflicts with an existing parameter.', 'ai-provider-for-voyage-ai' ),
						$key
					)
				);
			}
			$body[ $key ] = $value;
		}

		$response = wp_remote_request(
			self::ENDPOINT,
			array(
				'method'  => 'POST',
				'timeout' => self::HTTP_TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = '';
			if ( is_array( $data ) && isset( $data['detail'] ) && is_string( $data['detail'] ) ) {
				$message = $data['detail'];
			} elseif ( is_array( $data ) && isset( $data['error'] ) && is_string( $data['error'] ) ) {
				$message = $data['error'];
			}
			if ( '' === $message ) {
				$message = sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Voyage AI HTTP error %d', 'ai-provider-for-voyage-ai' ),
					$code
				);
			}
			return new WP_Error(
				'aipfva_voyage_http',
				$message,
				array(
					'status'   => $code,
					'response' => $data,
				)
			);
		}

		if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return new WP_Error(
				'aipfva_voyage_bad_json',
				__( 'Voyage AI returned an unexpected response body.', 'ai-provider-for-voyage-ai' )
			);
		}

		return $data;
	}
}
