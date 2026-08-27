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
