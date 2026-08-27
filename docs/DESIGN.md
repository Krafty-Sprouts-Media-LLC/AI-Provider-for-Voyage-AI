# AI Provider for Voyage AI — Design

**Status:** Approved (build)
**Slug:** `ai-provider-for-voyage-ai`
**Provider ID:** `voyage-ai`
**Depends on:** WordPress 7.1+ AI Client / Connectors (first version shipping `EmbeddingGenerationModelInterface`, php-ai-client 1.4.0+)

## Goal

Standalone WordPress plugin that registers Voyage AI as a text-embedding provider for `AiClient::input(...)->generateEmbedding()`, with API keys managed in **Settings → Connectors**.

No other plugin is required. Consumers talk only to the AI Client; this plugin is the Voyage transport. Separate from `ai-provider-for-fal-ai` — a distinct plugin, distinct capability (embeddings, not image generation).

## Architecture

```
Any consumer (theme / plugin / custom code)
  → AiClient::input($text)->usingDimensions(...)->generateEmbedding()
    → AiClient ProviderRegistry → VoyageProvider
      → VoyageEmbeddingModel
        → POST https://api.voyageai.com/v1/embeddings
        → Authorization: Bearer $VOYAGE_API_KEY
        → parse data[].embedding → Embedding[] → EmbeddingResult
```

Connectors auto-discovers the provider from `AiClient::defaultRegistry()` after `registerProvider( VoyageProvider::class )` on `init` priority 5.

## Auth

Voyage's documented auth (`Authorization: Bearer $VOYAGE_API_KEY`) is exactly the AI Client's default `ApiKeyRequestAuthentication` behavior. Unlike fal.ai (which needed a custom `Key` header), **no custom authentication class is needed** — `VoyageProvider` uses `RequestAuthenticationMethod::apiKey()` unmodified.

Connectors env/constant names map to Voyage's `VOYAGE_API_KEY`.

## Availability

`VoyageApiKeyProviderAvailability` — same pattern as `FalApiKeyProviderAvailability`: Voyage ships a static model catalog (no discovery endpoint we rely on for this), so availability is gated on a non-empty API key rather than a live probe.

## Models (v1 static catalog — all 9 current Voyage models)

| Model ID | Role | Flexible dimensions |
|---|---|---|
| `voyage-4-large` | Highest quality, general purpose | 2048 / 1024 (default) / 512 / 256 |
| `voyage-4` | Balanced general purpose | 2048 / 1024 (default) / 512 / 256 |
| `voyage-4-lite` | Fast, general purpose | 2048 / 1024 (default) / 512 / 256 |
| `voyage-3-large` | Previous-gen large | 2048 / 1024 (default) / 512 / 256 |
| `voyage-3.5` | Previous-gen balanced | 2048 / 1024 (default) / 512 / 256 |
| `voyage-3.5-lite` | Previous-gen fast | 2048 / 1024 (default) / 512 / 256 |
| `voyage-code-3` | Code embeddings | 2048 / 1024 (default) / 512 / 256 |
| `voyage-finance-2` | Finance-domain embeddings | Fixed 1024 |
| `voyage-law-2` | Legal-domain embeddings | Fixed 1024 |

Each model declares:
- `capabilities`: `[embeddingGeneration()]`
- `options`:
  - `inputModalities` → `[text]` only (Voyage's plain embeddings endpoint is text-only; a file/image `MessagePart` input is rejected with a clear error)
  - `dimensions` → the flexible list above where supported; omitted for the two fixed-1024 models, so `usingDimensions()` only resolves to a model that actually honors the request
  - `customOptions` → passthrough for Voyage-specific tuning with no first-class AI Client equivalent: `input_type` (`query`/`document`, null default) and `truncation` (bool, default true)

## Request / response mapping

- Batch every `MessagePart` in the `generateEmbeddingResult(array $inputs)` call into one Voyage `input: [...]` array (max 1,000 items per Voyage's limit — the plugin throws a clear `RuntimeException` past that rather than silently truncating or splitting into multiple requests)
- `usingDimensions(N)` → Voyage's `output_dimension`
- `output_dtype` is not exposed — always `float`, matching what the `Embedding` DTO expects
- Response `data[].embedding` (ordered by `data[].index`) → `Embedding` instances, in input order
- Response `usage.total_tokens` → `EmbeddingResult`'s `TokenUsage` (prompt tokens = total tokens; Voyage doesn't separate a completion count for embeddings)
- Response `model` → carried into `EmbeddingResult`'s `additionalData` for diagnostics

## Error handling

- Non-2xx HTTP response → `RuntimeException` with Voyage's error message surfaced
- Empty API key → `VoyageApiKeyProviderAvailability::isConfigured()` returns false, so Connectors shows "Not connected" before any request is attempted
- Non-text input part (file/image) → rejected before the HTTP call, with a message naming the unsupported modality
- More than 1,000 inputs in one call → rejected before the HTTP call

## Testing

Unit tests for `VoyageEmbeddingModel`: request-body construction (batching, dimensions, custom options) and response parsing (embeddings, token usage, error paths), with mocked HTTP — no live API calls, mirroring `tests/FalQueueClientTest.php`'s approach in the fal.ai plugin.

## Out of scope (v1)

- Reranking (`/v1/rerank`) — no AI Client capability exists for it yet
- Multimodal / image embeddings (`/v1/multimodalembeddings`) — out of scope until the AI Client has a multimodal embedding contract
- Quantized output formats (`int8`, `uint8`, `binary`, `ubinary`) — always `float`
- Dynamic model discovery — static catalog only, same rationale as fal.ai
