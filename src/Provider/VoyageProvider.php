<?php
/**
 * Voyage AI provider registration for the PHP AI Client.
 *
 * @package KraftySprouts\AiProviderForVoyageAi\Provider
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace KraftySprouts\AiProviderForVoyageAi\Provider;

use KraftySprouts\AiProviderForVoyageAi\Metadata\VoyageModelMetadataDirectory;
use KraftySprouts\AiProviderForVoyageAi\Models\VoyageEmbeddingModel;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class VoyageProvider
 *
 * @since 1.0.0
 */
class VoyageProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		return 'https://api.voyageai.com/v1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {
		$capabilities = $model_metadata->getSupportedCapabilities();

		foreach ( $capabilities as $capability ) {
			if ( $capability->isEmbeddingGeneration() ) {
				return new VoyageEmbeddingModel( $model_metadata, $provider_metadata );
			}
		}

		throw new RuntimeException(
			esc_html(
				sprintf(
					/* translators: %s: comma-separated model capability names. */
					__( 'Unsupported model capabilities: %s', 'ai-provider-for-voyage-ai' ),
					implode( ', ', $capabilities )
				)
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'voyage-ai',
			'Voyage AI',
			ProviderTypeEnum::cloud(),
			'https://dashboard.voyageai.com/organization/api-keys',
			RequestAuthenticationMethod::apiKey(),
			__( 'Text embeddings with voyage-4, voyage-3.5, voyage-code-3, and other Voyage AI models.', 'ai-provider-for-voyage-ai' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new VoyageApiKeyProviderAvailability();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new VoyageModelMetadataDirectory();
	}
}
