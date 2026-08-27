<?php
/**
 * Static model catalog for Voyage AI embedding models.
 *
 * @package KraftySprouts\AiProviderForVoyageAi\Metadata
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace KraftySprouts\AiProviderForVoyageAi\Metadata;

use InvalidArgumentException;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Class VoyageModelMetadataDirectory
 *
 * Voyage does not expose a public capability-list endpoint we rely on here,
 * so capabilities are declared statically (same approach fal.ai uses).
 *
 * @since 1.0.0
 */
class VoyageModelMetadataDirectory implements ModelMetadataDirectoryInterface {

	/**
	 * Cached metadata list.
	 *
	 * @var list<ModelMetadata>|null
	 */
	private ?array $models = null;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function listModelMetadata(): array {
		if ( null === $this->models ) {
			$this->models = $this->buildCatalog();
		}

		return $this->models;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function hasModelMetadata( string $modelId ): bool {
		foreach ( $this->listModelMetadata() as $model ) {
			if ( $model->getId() === $modelId ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function getModelMetadata( string $modelId ): ModelMetadata {
		foreach ( $this->listModelMetadata() as $model ) {
			if ( $model->getId() === $modelId ) {
				return $model;
			}
		}

		throw new InvalidArgumentException(
			esc_html(
				sprintf(
					/* translators: %s: Voyage AI model id. */
					__( 'Unknown Voyage AI model: %s', 'ai-provider-for-voyage-ai' ),
					$modelId
				)
			)
		);
	}

	/**
	 * Build the static model catalog.
	 *
	 * @since 1.0.0
	 * @return list<ModelMetadata>
	 */
	private function buildCatalog(): array {
		$capabilities = array( CapabilityEnum::embeddingGeneration() );

		$base_options = array(
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::customOptions() ),
		);

		$flexible_dimensions_option = new SupportedOption(
			OptionEnum::dimensions(),
			array( 2048, 1024, 512, 256 )
		);

		$flexible = array(
			array( 'voyage-4-large', 'Voyage 4 Large' ),
			array( 'voyage-4', 'Voyage 4' ),
			array( 'voyage-4-lite', 'Voyage 4 Lite' ),
			array( 'voyage-3-large', 'Voyage 3 Large' ),
			array( 'voyage-3.5', 'Voyage 3.5' ),
			array( 'voyage-3.5-lite', 'Voyage 3.5 Lite' ),
			array( 'voyage-code-3', 'Voyage Code 3' ),
		);

		$fixed = array(
			array( 'voyage-finance-2', 'Voyage Finance 2' ),
			array( 'voyage-law-2', 'Voyage Law 2' ),
		);

		$models = array();

		foreach ( $flexible as $definition ) {
			$models[] = new ModelMetadata(
				$definition[0],
				$definition[1],
				$capabilities,
				array_merge( $base_options, array( $flexible_dimensions_option ) )
			);
		}

		foreach ( $fixed as $definition ) {
			$models[] = new ModelMetadata(
				$definition[0],
				$definition[1],
				$capabilities,
				$base_options
			);
		}

		return $models;
	}
}
