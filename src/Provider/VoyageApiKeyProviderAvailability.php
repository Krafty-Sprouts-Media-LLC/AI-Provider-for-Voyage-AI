<?php
/**
 * Voyage AI provider availability gated on a non-empty API key.
 *
 * @package KraftySprouts\AiProviderForVoyageAi\Provider
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace KraftySprouts\AiProviderForVoyageAi\Provider;

use Throwable;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;

/**
 * Class VoyageApiKeyProviderAvailability
 *
 * Voyage AI ships a static model catalog, so a discovery-based availability
 * check would always succeed regardless of whether a key is set. Require a
 * non-empty API key on the request authentication instead.
 *
 * @since 1.0.0
 */
class VoyageApiKeyProviderAvailability implements ProviderAvailabilityInterface, WithRequestAuthenticationInterface {

	use WithRequestAuthenticationTrait;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function isConfigured(): bool {
		try {
			$auth = $this->getRequestAuthentication();
		} catch ( Throwable $e ) {
			return false;
		}

		if ( ! $auth instanceof ApiKeyRequestAuthentication ) {
			return false;
		}

		return '' !== trim( $auth->getApiKey() );
	}
}
