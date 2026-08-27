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
