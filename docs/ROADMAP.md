# Roadmap — deferred past v1

Everything below was scoped out of v1 (see `docs/DESIGN.md` → "Out of scope (v1)") to keep the first release matched exactly to what WordPress 7.1's AI Client can actually call today. None of it is blocked on anything except someone picking it up.

## Capability gaps (blocked on upstream AI Client support)

These need a new capability contract in `WordPress/php-ai-client` before this plugin can implement them — there's currently no `RerankModelInterface` or multimodal-embedding equivalent to `EmbeddingGenerationModelInterface` to hook into, the same reason embeddings itself didn't exist before 1.4.0.

- **Reranking** — Voyage's `POST /v1/rerank` (rerank a list of documents against a query). Worth watching `make.wordpress.org/ai` for a rerank capability announcement; implementation would mirror `VoyageEmbeddingModel`/`VoyageEmbeddingsClient` almost exactly once a contract exists.
- **Multimodal embeddings** — Voyage's `POST /v1/multimodalembeddings` (embed images, or text+image pairs, not just plain text). Blocked the same way; `EmbeddingGenerationModelInterface::generateEmbeddingResult()` currently only receives text/file `MessagePart`s with no defined image-embedding path.

## Independent of the AI Client (buildable any time)

- **Quantized output formats** — Voyage's `output_dtype` supports `int8`, `uint8`, `binary`, `ubinary` in addition to `float`. The AI Client's `Embedding` DTO only accepts `int|float` values today, so `int8`/`uint8` would fit as-is; `binary`/`ubinary` (packed bit vectors) would need a shape decision — probably a `custom_option` passthrough with the raw packed result kept in `EmbeddingResult`'s `additionalData` rather than forced into `Embedding`. Mainly a storage/bandwidth optimization for large-scale vector search — not needed until this plugin has a consumer at that scale.
- **Dynamic model discovery** — replace the static 9-model catalog in `VoyageModelMetadataDirectory` with a live call to a Voyage models-list endpoint, if/when Voyage publishes one. Same rationale fal.ai used to stay static: no such endpoint exists today for the models this plugin cares about.
- **Voyage logo/icon asset** — `ProviderMetadata`'s optional `logoPath` argument is currently omitted in `VoyageProvider::createProviderMetadata()`. Add an `assets/images/voyage-ai.svg` (using Voyage's actual brand mark, sourced/licensed properly — not fabricated) and pass its path once available, mirroring fal.ai's `assets/images/fal-ai.svg`.

## Shipping mechanics (not a code feature, but tracked here since it's next)

- **Git + CI** — `git init` this plugin, add the GitHub remote, and copy over the `.github/workflows/deploy.yml` pattern from `ai-provider-for-fal-ai` (`10up/action-wordpress-plugin-deploy@stable`, triggered on a bare version-number tag push).
- **WordPress.org submission** — submit for plugin hosting review once the above is in place and the plan's manual smoke test (Task 4, Step 3) has been run against a real WP 7.1 install with a live Voyage API key.

## Explicitly not planned

Nothing here is a commitment — this is a parking lot for "known left out," not a backlog with priority order. Pull an item in only when a real consumer needs it.
