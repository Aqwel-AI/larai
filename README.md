# LarAI

LarAI is a Laravel-first AI toolkit built by Aqwel AI. It offers a clean facade, modular providers, and a consistent API for text, chat, images, summarization, and embeddings.

## Features

- Text generation and chat completions
- Image generation from prompts
- Summarization and prompt templates
- Embeddings for semantic search
- Queue/async support for heavy tasks
- API usage logging
- Provider-agnostic design

## Requirements

- PHP 8.1+
- Laravel 11+

## Installation

```bash
composer require aqwelai/larai
```

Publish the config:

```bash
php artisan vendor:publish --tag=larai-config
```

## Configuration

Set your API keys in `.env`:

```
LARAI_PROVIDER=openai
OPENAI_API_KEY=your-key
ANTHROPIC_API_KEY=your-key
LLAMA_API_KEY=your-key
```

Common config options (`config/larai.php`):

- `larai.default` default provider
- `larai.timeout` request timeout in seconds
- `larai.logging.enabled` enable usage logging
- `larai.queue.enabled` enable queue support
- `larai.providers.*` per-provider API keys and defaults

## Quick Start

```php
use AqwelAI\LarAI\Facades\LarAI;

$text = LarAI::text('Write a short product description.');

$chat = LarAI::chat([
    ['role' => 'system', 'content' => LarAI::prompt('chat_system')],
    ['role' => 'user', 'content' => 'What can you do?'],
]);

$summary = LarAI::summarize($longText);

$embeddings = LarAI::embeddings('A short sentence');

$image = LarAI::image('A cozy cabin in the snow');
```

### Facade Alias

LarAI ships with a short alias facade:

```php
use AqwelAI\LarAI\Facades\AI;

$text = AI::text('Write a short product description.');
$image = AI::image('A cozy cabin in the snow');
$recommendations = AI::recommend('cozy winter scene', [
    'snowy cabin',
    'tropical beach',
    'city skyline at night',
]);
```

## API Reference

### Text

```php
LarAI::text('Write a tagline for a coffee shop.');
```

### Chat

```php
LarAI::chat([
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
    ['role' => 'user', 'content' => 'Summarize this article.'],
]);
```

### Image

```php
LarAI::image('A minimalist poster of a city skyline', [
    'size' => '1024x1024',
]);
```

### Summarize

```php
LarAI::summarize($longText);
```

### Embeddings

```php
LarAI::embeddings(['First sentence', 'Second sentence']);
```

### Recommend

```php
LarAI::recommend('cozy winter scene', [
    'snowy cabin',
    'tropical beach',
    'city skyline at night',
]);
```

### Prompt Templates

```php
$prompt = LarAI::prompt('summarize', ['text' => $text]);
```

## Response Format

All provider calls return a standardized array:

- `content` for text/chat results
- `images` for image generations
- `embeddings` for vector embeddings
- `recommendations` for similarity-ranked items
- `usage` when the provider reports token usage
- `raw` full provider payload

## Common Options

You can pass options to any call:

- `provider` override default provider
- `model` select a model
- `temperature` control creativity (chat/text)
- `max_tokens` cap output length
- `async` queue in the background when enabled

Example:

```php
LarAI::text('Hello', [
    'provider' => 'claude',
    'model' => 'claude-3-5-sonnet-20240620',
    'max_tokens' => 200,
]);
```

## Example Services

```php
use AqwelAI\LarAI\Services\TextService;
use AqwelAI\LarAI\Services\ImageService;
use AqwelAI\LarAI\Services\EmbeddingsService;

$textService = app(TextService::class);
$result = $textService->generate('Write a tagline for a coffee shop.');

$imageService = app(ImageService::class);
$image = $imageService->generate('A minimalist poster of a city skyline.');

$embeddingsService = app(EmbeddingsService::class);
$vectors = $embeddingsService->generate(['First sentence', 'Second sentence']);
```

## Queue and Async

Enable queue support in `.env`:

```
LARAI_QUEUE=true
```

Queue a job directly:

```php
LarAI::queueText('Generate a long report', [
    'provider' => 'openai',
]);
```

Or request async within the call:

```php
LarAI::text('Draft a press release', [
    'async' => true,
]);
```

## Logging

Enable usage logging:

```
LARAI_LOGGING=true
LARAI_LOG_CHANNEL=stack
```

When the provider returns usage data, LarAI logs provider and token usage.

## Providers

Built-in providers:

- OpenAI (`openai`)
- Claude (Anthropic) (`claude`)
- LLaMA via OpenAI-compatible APIs (`llama`)

Each provider reads configuration from `config/larai.php`.

## Custom Providers

Implement the `Provider` contract and register it at runtime:

```php
use AqwelAI\LarAI\Contracts\Provider;
use AqwelAI\LarAI\Facades\LarAI;

class MyProvider implements Provider
{
    public function name(): string { return 'custom'; }
    public function text(string $prompt, array $options = []): array { /* ... */ }
    public function chat(array $messages, array $options = []): array { /* ... */ }
    public function image(string $prompt, array $options = []): array { /* ... */ }
    public function summarize(string $text, array $options = []): array { /* ... */ }
    public function embeddings(string|array $input, array $options = []): array { /* ... */ }
}

LarAI::registerProvider('custom', new MyProvider());
```

## Troubleshooting

- Ensure API keys are present in `.env`
- Verify `LARAI_PROVIDER` matches a configured provider
- If queueing, run a Laravel queue worker

## License

MIT
