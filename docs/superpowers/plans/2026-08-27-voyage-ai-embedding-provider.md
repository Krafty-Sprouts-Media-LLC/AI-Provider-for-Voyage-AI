# AI Provider for Voyage AI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a standalone WordPress plugin that registers Voyage AI as a text-embedding provider for the WP 7.1 AI Client (`AiClient::input(...)->generateEmbedding()`).

**Architecture:** Mirrors the existing, WP.org-approved `ai-provider-for-fal-ai` plugin's provider pattern (`AbstractApiProvider` + static model catalog + Connectors-based auth), swapping fal's `ImageGenerationModelInterface` model for one implementing the new `EmbeddingGenerationModelInterface`. The HTTP call is isolated in a plain, dependency-free class (`VoyageEmbeddingsClient`) so it can be unit tested the same way `FalQueueClient` is tested in the fal.ai plugin — with WordPress's HTTP functions faked, no real AI Client classes required.

**Tech Stack:** PHP 7.4+ (target; developed/tested here on PHP 8.3 via Herd), WordPress 7.1+ AI Client (`php-ai-client` 1.4.0+, bundled in WP core — not vendored by this plugin), PHPUnit 10.5 (`phpunit.phar`, not committed).

**Spec:** [`docs/DESIGN.md`](../DESIGN.md) in this plugin's own directory.

## Global Constraints

- **Requires at least:** WordPress 7.1 (first version bundling `EmbeddingGenerationModelInterface` — verified against `php-ai-client` GitHub tag `1.4.0`, since this dev site's WP core is still on 7.0.4 with the older 1.3.1 stub).
- **Requires PHP:** 7.4 (matches `ai-provider-for-fal-ai`'s floor).
- **No runtime Composer / vendored dependencies.** The plugin relies entirely on classes WordPress core provides; only a hand-rolled PSR-4-style autoloader (`src/autoload.php`) is shipped, exactly like `ai-provider-for-fal-ai`.
- **WPCS formatting throughout:** tabs for indentation, space inside parens `function foo( $x )`, Yoda conditions (`'' === $x`, not `$x === ''`), full PHPDoc on every file/class/method/property/constant, no closing `?>` tag.
- **Text domain:** `ai-provider-for-voyage-ai` on every translatable string.
- **Plugin slug:** `ai-provider-for-voyage-ai`. **Provider ID:** `voyage-ai`.
- **v1 scope is text embeddings only** — no reranking, no multimodal embeddings, no quantized (`int8`/`binary`) output formats. See `docs/DESIGN.md` "Out of scope" section.
- **Voyage's documented embeddings env var is `VOYAGE_API_KEY`** (their SDK convention) — this differs from what WordPress Connectors auto-derives from the provider id `voyage-ai` (`VOYAGE_AI_API_KEY`), so an explicit `wp_connectors_init` mapping is required (same reason fal.ai needed one for `FAL_KEY`).

---

## Task 1: Plugin scaffold, licensing, and provider registration wiring

**Files:**
- Create: `ai-provider-for-voyage-ai.php`
- Create: `index.php`
- Create: `uninstall.php`
- Create: `.gitignore`
- Create: `.distignore`
- Create: `LICENSE`
- Create: `license.txt`
- Create: `README.md`
- Create: `CHANGELOG.md`
- Create: `readme.txt`
- Create: `phpunit.xml.dist`
- Create: `src/index.php`
- Create: `src/autoload.php`
- Create: `languages/index.php`
- Create: `tests/bootstrap.php` (empty placeholder overwritten fully in Task 2 — see note in Step 8)

**Interfaces:**
- Produces: constants `AIPFVA_AI_PROVIDER_FOR_VOYAGE_AI_VERSION`, `AIPFVA_AI_PROVIDER_FOR_VOYAGE_AI_PLUGIN_DIR`, `AIPFVA_AI_PROVIDER_FOR_VOYAGE_AI_PLUGIN_FILE`; function `KraftySprouts\AiProviderForVoyageAi\register_provider(): void` (hooked on `init` priority 5) referencing `KraftySprouts\AiProviderForVoyageAi\Provider\VoyageProvider::class` — a class that does not exist until Task 3. This task's deliverable is the scaffold + wiring; it is not expected to run without error until Task 3 lands, but every file must be lint-clean PHP on its own.

- [ ] **Step 1: Create `.gitignore`**

```
# Local / tooling ignores (not shipped in the wp.org zip — see .distignore).
.DS_Store
Thumbs.db
*.log
.phpunit.result.cache
phpunit.phar
vendor/
node_modules/
.idea/
.vscode/
```

- [ ] **Step 2: Create `.distignore`**

```
# Files excluded from the WordPress.org / distribution zip.
.git
.gitignore
.gitattributes
.github
.wordpress-org
.distignore
node_modules
vendor
phpcs.xml.dist
phpstan.neon.dist
composer.lock
tests
*.md
```

- [ ] **Step 3: Copy license files**

Copy `ai-provider-for-fal-ai/LICENSE` and `ai-provider-for-fal-ai/license.txt` verbatim into this plugin's root as `LICENSE` and `license.txt` (both are the standard, unmodified GPLv2 license text — no plugin-specific content to change).

```bash
cp "../ai-provider-for-fal-ai/LICENSE" "./LICENSE"
cp "../ai-provider-for-fal-ai/license.txt" "./license.txt"
```

- [ ] **Step 4: Create `index.php` (directory-listing guard, root)**

```php
<?php
/**
 * Silence is golden.
 *
 * @package KraftySprouts\AiProviderForVoyageAi
 */

// phpcs:ignore Squiz.Commenting.FileComment.Missing -- Directory protection stub.
```

- [ ] **Step 5: Create `src/index.php` and `languages/index.php`**

Both files are byte-for-byte identical to Step 4's `index.php` — copy it to both locations:

```bash
cp "index.php" "src/index.php"
cp "index.php" "languages/index.php"
```

- [ ] **Step 6: Create `src/autoload.php`**

```php
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
```

- [ ] **Step 7: Create `ai-provider-for-voyage-ai.php` (main plugin file)**

```php
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
```

- [ ] **Step 8: Create `uninstall.php`**

```php
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
```

- [ ] **Step 9: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!-- PHPUnit configuration for AI Provider for Voyage AI. -->
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" bootstrap="tests/bootstrap.php" colors="true" xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd">
	<testsuites>
		<testsuite name="Unit">
			<directory>tests/</directory>
		</testsuite>
	</testsuites>
</phpunit>
```

- [ ] **Step 10: Create a placeholder `tests/bootstrap.php`**

This is fully rewritten in Task 2, Step 1 — create it now only so `phpunit.xml.dist`'s bootstrap path resolves and the directory exists:

```php
<?php
/**
 * PHPUnit bootstrap for AI Provider for Voyage AI (placeholder — see Task 2).
 *
 * @package KraftySprouts\AiProviderForVoyageAi\Tests
 */

declare( strict_types=1 );
```

- [ ] **Step 11: Download the PHPUnit phar for local test runs**

Not committed (already in `.gitignore` from Step 1). Download once per working copy:

```bash
curl -L -o phpunit.phar https://phar.phpunit.de/phpunit-10.phar
```

- [ ] **Step 12: Lint every PHP file created so far**

```bash
"C:\Users\kings\.config\herd\bin\php.bat" -l ai-provider-for-voyage-ai.php
"C:\Users\kings\.config\herd\bin\php.bat" -l index.php
"C:\Users\kings\.config\herd\bin\php.bat" -l uninstall.php
"C:\Users\kings\.config\herd\bin\php.bat" -l src/index.php
"C:\Users\kings\.config\herd\bin\php.bat" -l src/autoload.php
"C:\Users\kings\.config\herd\bin\php.bat" -l languages/index.php
"C:\Users\kings\.config\herd\bin\php.bat" -l tests/bootstrap.php
```

Expected: `No syntax errors detected` for every file.

- [ ] **Step 13: Write `readme.txt` (WordPress.org format)**

```
=== AI Provider for Voyage AI ===
Contributors: iamkingsleyf, kraftysprouts
Tags: ai, voyage, embeddings, semantic search, vector search
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Standalone Voyage AI text-embedding provider for the WordPress AI Client.

== Description ==

AI Provider for Voyage AI is a **standalone** WordPress plugin. It integrates [Voyage AI](https://www.voyageai.com/) with the WordPress [AI Client](https://developer.wordpress.org/reference/functions/wp_ai_client_prompt/) and [Connectors](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/) APIs.

It is not tied to any other commercial plugin. Any theme, plugin, or custom code that uses `AiClient::input(...)->generateEmbedding()` can use Voyage once this provider is active and an API key is configured.

**Features**

* Text embeddings with voyage-4-large, voyage-4, voyage-4-lite, voyage-3-large, voyage-3.5, voyage-3.5-lite, voyage-code-3, voyage-finance-2, and voyage-law-2
* Appears automatically on **Settings → Connectors** (API key authentication)
* Uses Voyage's official `VOYAGE_API_KEY` environment variable / PHP constant when set
* Configurable output dimensions on models that support it (2048 / 1024 / 512 / 256)

**Requirements**

* PHP 7.4 or higher
* WordPress 7.1 or higher (first version with AI Client embedding support)

== Installation ==

1. Upload the plugin to `/wp-content/plugins/ai-provider-for-voyage-ai`, or install it via **Plugins → Add New**.
2. Activate the plugin.
3. Go to **Settings → Connectors** and add your Voyage AI API key (get one at https://dashboard.voyageai.com/organization/api-keys).

== Frequently Asked Questions ==

= Does this plugin need any other plugin installed? =

No. It only requires WordPress 7.1+, which ships the AI Client the provider registers against.

= What can I do with this? =

Any code that calls `AiClient::input($text)->usingProvider('voyage-ai')->generateEmbedding()` gets a Voyage-generated embedding vector back, for use in semantic/vector search, similarity matching, clustering, and related features.

== Changelog ==

= 1.0.0 =
* Initial release. Text embeddings for all 9 current Voyage AI models.
```

- [ ] **Step 14: Write `README.md`**

```markdown
# AI Provider for Voyage AI

Standalone WordPress plugin that registers [Voyage AI](https://www.voyageai.com/) as a text-embedding provider for the WordPress AI Client (`AiClient::input(...)->generateEmbedding()`), introduced in WordPress 7.1.

## Requirements

- WordPress 7.1+
- PHP 7.4+
- A Voyage AI API key (https://dashboard.voyageai.com/organization/api-keys)

## Setup

1. Activate the plugin.
2. Add your Voyage AI API key under **Settings → Connectors**.

See [`docs/DESIGN.md`](docs/DESIGN.md) for architecture details.
```

- [ ] **Step 15: Write `CHANGELOG.md`**

```markdown
# Changelog

## 1.0.0

- Initial release. Text embeddings for all 9 current Voyage AI models (voyage-4-large, voyage-4, voyage-4-lite, voyage-3-large, voyage-3.5, voyage-3.5-lite, voyage-code-3, voyage-finance-2, voyage-law-2).
```

- [ ] **Step 16: Commit**

No git repo exists in this plugin directory yet — this step only applies once one is initialized (mirroring how `ai-provider-for-fal-ai` was `git init`'d after its code was written, per that plugin's own history). Skip `git add`/`git commit` for now; the plan's later tasks assume the same working-copy-first, git-later flow.

---

## Task 2: `VoyageEmbeddingsClient` — HTTP client with unit tests

**Files:**
- Create: `src/Http/VoyageEmbeddingsClient.php`
- Create: `tests/VoyageEmbeddingsClientTest.php`
- Modify: `tests/bootstrap.php` (replace Task 1's placeholder)

**Interfaces:**
- Consumes: nothing from other tasks (deliberately dependency-free — no `WordPress\AiClient\*` classes, no other plugin class).
- Produces: `KraftySprouts\AiProviderForVoyageAi\Http\VoyageEmbeddingsClient::embed( string $api_key, string $model_id, array $texts, ?int $output_dimension, array $custom_options )` returning `array{data: list<array{embedding: list<float>, index: int}>, model: string, usage: array{total_tokens: int}}` on success, or `WP_Error` on failure. Also exposes public constant `VoyageEmbeddingsClient::MAX_INPUTS` (int, `1000`). Task 4's `VoyageEmbeddingModel` calls this method directly.

- [ ] **Step 1: Replace `tests/bootstrap.php` with the real fake-WP harness**

```php
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
```

- [ ] **Step 2: Write the failing tests in `tests/VoyageEmbeddingsClientTest.php`**

```php
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
```

- [ ] **Step 3: Run tests to verify they fail (class doesn't exist yet)**

Run: `"C:\Users\kings\.config\herd\bin\php.bat" phpunit.phar --configuration phpunit.xml.dist`
Expected: FAIL — `Class "KraftySprouts\AiProviderForVoyageAi\Http\VoyageEmbeddingsClient" not found` (via the `require` in bootstrap.php, which will fatal before tests even collect — expected at this point).

- [ ] **Step 4: Implement `src/Http/VoyageEmbeddingsClient.php`**

```php
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
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `"C:\Users\kings\.config\herd\bin\php.bat" phpunit.phar --configuration phpunit.xml.dist`
Expected: `OK (6 tests, ...)` — all six tests pass.

- [ ] **Step 6: Commit**

Skipped for the same reason as Task 1, Step 16 — no git repo initialized yet in this working copy. If one has been initialized by the time this task executes, commit with:

```bash
git add src/Http/VoyageEmbeddingsClient.php tests/VoyageEmbeddingsClientTest.php tests/bootstrap.php
git commit -m "feat: add Voyage embeddings HTTP client with unit tests"
```

---

## Task 3: Provider registration — `VoyageProvider`, availability, and model catalog

**Files:**
- Create: `src/Provider/VoyageProvider.php`
- Create: `src/Provider/VoyageApiKeyProviderAvailability.php`
- Create: `src/Metadata/VoyageModelMetadataDirectory.php`

**Interfaces:**
- Consumes: `KraftySprouts\AiProviderForVoyageAi\Models\VoyageEmbeddingModel` (constructed by `VoyageProvider::createModel()` — the class itself is created in Task 4; this task references it but the reference only resolves once Task 4 lands, same forward-reference situation as Task 1 referencing `VoyageProvider`).
- Produces: `KraftySprouts\AiProviderForVoyageAi\Provider\VoyageProvider` (extends `WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider`) — this is the class Task 1's `register_provider()` registers. Produces `VoyageApiKeyProviderAvailability::isConfigured(): bool`. Produces `VoyageModelMetadataDirectory::listModelMetadata(): list<ModelMetadata>` covering all 9 Voyage model ids listed in `docs/DESIGN.md`.

No PHPUnit tests in this task: these classes extend/implement `WordPress\AiClient\*` interfaces that only exist inside a real WordPress 7.1 + AI Client runtime, not in the dependency-free PHPUnit harness from Task 2 (the same reason `ai-provider-for-fal-ai`'s `FalProvider`, `FalApiKeyProviderAvailability`, and `FalModelMetadataDirectory` have no PHPUnit tests either — verify by checking `ai-provider-for-fal-ai/tests/` only contains `FalQueueClientTest.php`). Verification here is PHP lint plus the manual WordPress smoke test at the end of Task 4.

- [ ] **Step 1: Create `src/Provider/VoyageApiKeyProviderAvailability.php`**

```php
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
```

- [ ] **Step 2: Create `src/Metadata/VoyageModelMetadataDirectory.php`**

```php
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
```

- [ ] **Step 3: Create `src/Provider/VoyageProvider.php`**

```php
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
```

Note: unlike `FalProvider`, this does not version-gate the `ProviderMetadata` description/icon args behind `AiClient::VERSION` checks — Voyage requires WP 7.1+ (`php-ai-client` 1.4.0+) unconditionally, where the full 7-argument `ProviderMetadata` constructor already exists, so there is no older-version case to support. No icon (`logoPath`) argument is passed — no Voyage logo asset is bundled in v1; adding one is a follow-up, not a blocker (`ProviderMetadata`'s `$logoPath` parameter is optional/nullable).

- [ ] **Step 4: Lint the three new files**

```bash
"C:\Users\kings\.config\herd\bin\php.bat" -l src/Provider/VoyageApiKeyProviderAvailability.php
"C:\Users\kings\.config\herd\bin\php.bat" -l src/Metadata/VoyageModelMetadataDirectory.php
"C:\Users\kings\.config\herd\bin\php.bat" -l src/Provider/VoyageProvider.php
```

Expected: `No syntax errors detected` for all three.

- [ ] **Step 5: Re-run the Task 2 test suite to confirm no regression**

Run: `"C:\Users\kings\.config\herd\bin\php.bat" phpunit.phar --configuration phpunit.xml.dist`
Expected: `OK (6 tests, ...)` — unchanged from Task 2 (these new classes aren't exercised by the existing suite, this just confirms autoloading/bootstrap wasn't broken).

- [ ] **Step 6: Commit** (once a git repo exists — see Task 1 Step 16 note)

```bash
git add src/Provider/VoyageProvider.php src/Provider/VoyageApiKeyProviderAvailability.php src/Metadata/VoyageModelMetadataDirectory.php
git commit -m "feat: register Voyage AI provider and static model catalog"
```

---

## Task 4: `VoyageEmbeddingModel` and end-to-end verification

**Files:**
- Create: `src/Models/VoyageEmbeddingModel.php`

**Interfaces:**
- Consumes: `KraftySprouts\AiProviderForVoyageAi\Http\VoyageEmbeddingsClient::embed()` (Task 2) and `VoyageEmbeddingsClient::MAX_INPUTS` (only used indirectly — the client itself enforces the cap; this class does not duplicate that check).
- Produces: `KraftySprouts\AiProviderForVoyageAi\Models\VoyageEmbeddingModel implements WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface`, specifically `generateEmbeddingResult( array $inputs ): EmbeddingResult` — this is the method `VoyageProvider::createModel()` (Task 3) instantiates and the AI Client's `EmbeddingBuilder::generateEmbeddingResult()` calls at runtime.

No PHPUnit test for this class for the same reason as Task 3 (depends on real `WordPress\AiClient\*` DTOs unavailable in the dependency-free test harness). Verified via lint plus the manual WordPress smoke test in Step 3.

- [ ] **Step 1: Implement `src/Models/VoyageEmbeddingModel.php`**

```php
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
```

- [ ] **Step 2: Lint the new file**

```bash
"C:\Users\kings\.config\herd\bin\php.bat" -l src/Models/VoyageEmbeddingModel.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual end-to-end smoke test in a real WordPress 7.1 install**

This cannot be exercised by the dependency-free PHPUnit suite (it needs the real AI Client, Connectors, and a live Voyage API key), so verify by hand once WordPress core here is updated to 7.1 (this dev site is currently on 7.0.4 — updating WP core is outside this plan's scope, but is required before this step can run):

1. Activate **AI Provider for Voyage AI** alongside WordPress core 7.1.
2. Go to **Settings → Connectors**, confirm a "Voyage AI" card appears, and add a real Voyage API key (or set the `VOYAGE_API_KEY` constant in `wp-config.php` and confirm the card shows "Connected" without entering one in the DB).
3. Add a temporary must-use plugin (`wp-content/mu-plugins/voyage-smoke-test.php`) with:

```php
<?php
add_action(
	'init',
	static function (): void {
		if ( ! isset( $_GET['voyage_smoke_test'] ) ) {
			return;
		}

		$embedding = \WordPress\AiClient\AiClient::input( 'PHP powers a large part of the web.' )
			->usingProvider( 'voyage-ai' )
			->generateEmbedding();

		wp_die( esc_html( sprintf( 'dimensions=%d first_value=%s', $embedding->getDimensions(), $embedding->getValues()[0] ) ) );
	}
);
```

4. Visit `/?voyage_smoke_test=1` on the site and confirm the page reports a dimension count (1024 by default) and a numeric first value, with no PHP errors in the debug log.
5. Delete the must-use plugin file afterward — it was only for this one-time check.

Expected: the page renders `dimensions=1024 first_value=0.0...` (or similar), proving the full path (`AiClient` → `VoyageProvider` → `VoyageEmbeddingModel` → `VoyageEmbeddingsClient` → Voyage's live API → `EmbeddingResult`) works end to end.

- [ ] **Step 4: Re-run the full PHPUnit suite one last time**

Run: `"C:\Users\kings\.config\herd\bin\php.bat" phpunit.phar --configuration phpunit.xml.dist`
Expected: `OK (6 tests, ...)` — still unchanged; this confirms the whole plugin's automated coverage remains green after every file is in place.

- [ ] **Step 5: Commit** (once a git repo exists — see Task 1 Step 16 note)

```bash
git add src/Models/VoyageEmbeddingModel.php
git commit -m "feat: implement Voyage embedding generation model"
```

---

## Post-plan follow-ups (explicitly out of scope here)

- Initializing a git repo for this plugin and setting up the GitHub Actions SVN-deploy workflow — do this the same way it was done for `ai-provider-for-fal-ai` (`git init`, add the GitHub remote, `.github/workflows/deploy.yml` using `10up/action-wordpress-plugin-deploy@stable`), once this plugin is ready to ship.
- Submitting to the WordPress.org Plugin Directory for hosting review.
- A Voyage AI icon/logo asset for `ProviderMetadata`'s `logoPath` argument.
- Anything in `docs/DESIGN.md`'s "Out of scope (v1)" list: reranking, multimodal embeddings, quantized output formats, dynamic model discovery.
