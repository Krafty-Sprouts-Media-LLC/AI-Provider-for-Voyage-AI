<?php
/**
 * Voyage AI text-embedding model.
 *
 * @package KraftySprouts\AiProviderForVoyageAi\Models
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace KraftySprouts\AiProviderForVoyageAi\Models;

use InvalidArgumentException;
use KraftySprouts\AiProviderForVoyageAi\Http\VoyageEmbeddingsClient;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Embedding;
use WordPress\AiClient\Results\DTO\EmbeddingResult;
use WordPress\AiClient\Results\DTO\TokenUsage;

/**
 * Class VoyageEmbeddingModel
 *
 * Delegates the actual HTTP call to {@see VoyageEmbeddingsClient}, which has
 * no dependency on WordPress\AiClient classes and is unit tested directly.
 * This class's job is translating between AI Client DTOs and that plain
 * client's array-based request/response shape.
 *
 * @since 1.0.0
 */
class VoyageEmbeddingModel extends AbstractApiBasedModel implements EmbeddingGenerationModelInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function generateEmbeddingResult( array $inputs ): EmbeddingResult {
		$texts = $this->extractTexts( $inputs );

		$config           = $this->getConfig();
		$output_dimension = $config->getDimensions();
		$custom_options   = $config->getCustomOptions();

		$api_key = $this->apiKey();

		$response = VoyageEmbeddingsClient::embed( $api_key, $this->metadata()->getId(), $texts, $output_dimension, $custom_options );

		if ( is_wp_error( $response ) ) {
			throw new ResponseException(
				esc_html( $response->get_error_message() )
			);
		}

		return $this->parseResponseToEmbeddingResult( $response, count( $texts ) );
	}

	/**
	 * Extract plain text strings from the prompt's message parts.
	 *
	 * Voyage's plain embeddings endpoint accepts text only; a file/image part
	 * is rejected here with a clear error rather than silently dropped.
	 *
	 * @since 1.0.0
	 * @param list<MessagePart> $inputs Message parts to embed, one per input.
	 * @return list<string>
	 */
	private function extractTexts( array $inputs ): array {
		$texts = array();

		foreach ( $inputs as $index => $part ) {
			$text = $part->getText();

			if ( null === $text ) {
				throw new InvalidArgumentException(
					esc_html(
						sprintf(
							/* translators: %d: zero-based input index. */
							__( 'Voyage AI embedding input %d must be text; file/image inputs are not supported.', 'ai-provider-for-voyage-ai' ),
							$index
						)
					)
				);
			}

			$texts[] = $text;
		}

		return $texts;
	}

	/**
	 * Resolve the Voyage API key from the request authentication.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function apiKey(): string {
		$auth = $this->getRequestAuthentication();

		if ( ! $auth instanceof ApiKeyRequestAuthentication ) {
			return '';
		}

		return $auth->getApiKey();
	}

	/**
	 * Parse Voyage's decoded response body into an EmbeddingResult.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $response_data  Decoded Voyage response body.
	 * @param int                  $expected_count Number of inputs sent (must match embeddings returned).
	 * @return EmbeddingResult
	 */
	private function parseResponseToEmbeddingResult( array $response_data, int $expected_count ): EmbeddingResult {
		$rows = $response_data['data'];

		if ( count( $rows ) !== $expected_count ) {
			throw new ResponseException(
				esc_html(
					sprintf(
						/* translators: 1: number of inputs sent, 2: number of embeddings returned. */
						__( 'Voyage AI returned %2$d embedding(s) for %1$d input(s).', 'ai-provider-for-voyage-ai' ),
						$expected_count,
						count( $rows )
					)
				)
			);
		}

		// Voyage returns rows tagged with `index`; sort defensively into input order.
		usort(
			$rows,
			static function ( $a, $b ): int {
				$a_index = isset( $a['index'] ) ? (int) $a['index'] : 0;
				$b_index = isset( $b['index'] ) ? (int) $b['index'] : 0;
				return $a_index <=> $b_index;
			}
		);

		$dimensions = 0;
		$vectors    = array();

		foreach ( $rows as $row ) {
			if ( ! isset( $row['embedding'] ) || ! is_array( $row['embedding'] ) ) {
				throw new ResponseException(
					esc_html( __( 'Voyage AI response row is missing an embedding vector.', 'ai-provider-for-voyage-ai' ) )
				);
			}

			$values     = array_values( $row['embedding'] );
			$dimensions = count( $values );
			$vectors[]  = new Embedding( $values, $dimensions );
		}

		$total_tokens = 0;
		if ( isset( $response_data['usage']['total_tokens'] ) ) {
			$total_tokens = (int) $response_data['usage']['total_tokens'];
		}

		$additional_data = array();
		if ( isset( $response_data['model'] ) && is_string( $response_data['model'] ) ) {
			$additional_data['model'] = $response_data['model'];
		}

		return new EmbeddingResult(
			wp_generate_uuid4(),
			$vectors,
			$dimensions,
			new TokenUsage( $total_tokens, 0, $total_tokens ),
			$this->providerMetadata(),
			$this->metadata(),
			$additional_data
		);
	}
}
