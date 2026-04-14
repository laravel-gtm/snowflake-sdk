---
paths: "**/*.php"
---

# Saloon 4.0 Rules

### Architecture

**One connector per API, one request class per endpoint.**

```
Connector (base URL, auth, headers) → Request (method, endpoint) → Response/DTO
```

### Connectors

```php
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;

class ForgeConnector extends Connector
{
    use HasTimeout;

    protected int $connectTimeout = 60;
    protected int $requestTimeout = 120;

    public function __construct(protected readonly string $token) {}

    public function resolveBaseUrl(): string
    {
        return 'https://forge.laravel.com/api/v1';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
```

- Default timeouts: 10s connect, 30s request
- Use constructor injection for dynamic config (tokens, base URLs)
- Override `defaultConfig()` for Guzzle-specific options

### Requests

```php
use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetServerRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected readonly string $id) {}

    public function resolveEndpoint(): string
    {
        return '/servers/' . $this->id;
    }
}
```

**Query parameters:**
```php
protected function defaultQuery(): array
{
    return ['sort' => 'name', 'filter[active]' => 'true'];
}
```

### Request Body

Always implement `HasBody` interface alongside the body trait:

```php
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;

class CreateServerRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected readonly string $name,
        protected readonly string $provider,
    ) {}

    protected function defaultBody(): array
    {
        return [
            'name' => $this->name,
            'provider' => $this->provider,
        ];
    }
}
```

**Available body traits:** `HasJsonBody`, `HasMultipartBody`, `HasStreamBody`, `HasFormBody`, `HasXmlBody`, `HasStringBody`

- `HasJsonBody` auto-sets `Content-Type: application/json`
- When both connector and request use `HasJsonBody`, bodies are merged
- Use `$request->body()->add()`, `merge()`, `remove()`, `set()` for runtime changes

**Multipart:**
```php
use Saloon\Data\MultipartValue;
use Saloon\Traits\Body\HasMultipartBody;

class UploadRequest extends Request implements HasBody
{
    use HasMultipartBody;

    protected function defaultBody(): array
    {
        return [new MultipartValue(name: 'file', value: $this->filePath)];
    }
}
```

### Sending Requests

```php
// Synchronous
$response = $connector->send(new GetServersRequest);

// Asynchronous
$promise = $connector->sendAsync(new GetServersRequest);
$promise
    ->then(fn(Response $response) => /* handle */)
    ->otherwise(fn(RequestException $e) => /* handle */);
$promise->wait();
```

**Always define both `then` and `otherwise` on async promises.**

### Responses

| Method | Returns |
|--------|---------|
| `status()` | HTTP status code |
| `json()` / `array()` | Decoded JSON array |
| `object()` | Decoded JSON as stdClass |
| `body()` | Raw string |
| `headers()` | All headers |
| `ok()`, `successful()`, `failed()` | Status checks |
| `throw()` | Throws on 4xx/5xx |
| `dto()` / `dtoOrFail()` | Convert to DTO |

### Authentication

```php
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Auth\QueryAuthenticator;
use Saloon\Http\Auth\HeaderAuthenticator;
use Saloon\Http\Auth\MultiAuthenticator;

class ApiConnector extends Connector
{
    public function __construct(protected readonly string $token) {}

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator($this->token);
    }
}

// Runtime override
$connector->authenticate(new TokenAuthenticator('new-token'));
```

Built-in: `TokenAuthenticator` (Bearer), `BasicAuthenticator`, `QueryAuthenticator`, `HeaderAuthenticator`, `CertificateAuthenticator`, `MultiAuthenticator`.

Only one authenticator at a time — use `MultiAuthenticator` to combine.

### Data Transfer Objects

```php
class GetServerRequest extends Request
{
    public function createDtoFromResponse(Response $response): Server
    {
        $data = $response->json();
        return new Server(
            id: $data['id'],
            name: $data['name'],
            ipAddress: $data['ip'],
        );
    }
}

// Usage
$server = $response->dtoOrFail(); // Throws on failed response
$server = $response->dto();       // Creates DTO even on failure
```

**Use `dtoOrFail()` over `dto()`** when you need to ensure success.

**Bidirectional DTOs** — use DTOs to populate requests too:
```php
class UpdateServerRequest extends Request implements HasBody
{
    use HasJsonBody;

    public function __construct(readonly protected Server $server) {}

    protected function defaultBody(): array
    {
        return ['name' => $this->server->name];
    }
}
```

### Error Handling

**Exception hierarchy:**
- `FatalRequestException` — connection errors (always throws)
- `RequestException` (parent)
  - `ServerException` (5xx)
  - `ClientException` (4xx)
    - `UnauthorizedException` (401)
    - `NotFoundException` (404)
    - `TooManyRequestsException` (429)

```php
// Auto-throw on all errors (trait on connector)
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

class ApiConnector extends Connector
{
    use AlwaysThrowOnErrors;
}

// Per-response
$response->throw();

// Custom failure detection (APIs returning 200 with error body)
public function hasRequestFailed(Response $response): ?bool
{
    return $response->json('error') !== null;
}
```

### Retry Logic

```php
class ApiConnector extends Connector
{
    public ?int $tries = 3;
    public ?int $retryInterval = 1000; // ms
    public ?bool $useExponentialBackoff = true;
    public ?bool $throwOnMaxTries = false; // Return last response instead

    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if ($exception instanceof RequestException && $exception->getResponse()->status() === 401) {
            $request->authenticate(new TokenAuthenticator($this->refreshToken()));
        }
        return true; // false stops retrying
    }
}
```

**Retry only works with `send()` (synchronous). NOT with `sendAsync()` or pools.**

### Concurrency / Pools

```php
$pool = $connector->pool(
    requests: [
        'servers' => new GetServersRequest,
        'sites' => new GetSitesRequest,
    ],
    concurrency: 5,
    responseHandler: function (Response $response, string $key) {
        match($key) {
            'servers' => $this->handleServers($response),
            'sites' => $this->handleSites($response),
        };
    },
    exceptionHandler: function (FatalRequestException|RequestException $e) { /* ... */ },
);

$promise = $pool->send();
$promise->wait(); // Must call wait()
```

**Memory-efficient with generators:**
```php
$pool = $connector->pool(function (): Generator {
    for ($i = 0; $i < 100; $i++) {
        yield $i => new UserRequest($i);
    }
});
```

### Middleware

```php
// Boot method (on connector or request)
public function boot(PendingRequest $pendingRequest): void
{
    $pendingRequest->headers()->add('X-Custom', 'value');
}

// Request middleware
$connector->middleware()->onRequest(function (PendingRequest $pendingRequest) {
    // Modify before sending
});

// Response middleware
$connector->middleware()->onResponse(function (Response $response) {
    // Process after receiving
});
```

- Cannot add request middleware inside request middleware
- Can add response middleware inside request middleware
- Add Guzzle middleware only in constructors (prevents duplicate registration)

### Building SDKs

**Resource classes for grouping endpoints:**
```php
use Saloon\Http\BaseResource;

class ServerResource extends BaseResource
{
    public function all(): Response
    {
        return $this->connector->send(new GetServersRequest);
    }

    public function get(string $id): Response
    {
        return $this->connector->send(new GetServerRequest($id));
    }

    public function create(string $name, string $provider): Response
    {
        return $this->connector->send(new CreateServerRequest($name, $provider));
    }
}

// Register on connector
class ForgeConnector extends Connector
{
    public function servers(): ServerResource
    {
        return new ServerResource($this);
    }
}

// Usage: $forge->servers()->get('abc123');
```

### Pagination (Plugin)

```bash
composer require saloonphp/pagination-plugin "^2.0"
```

```php
use Saloon\PaginationPlugin\CursorPaginator;
use Saloon\PaginationPlugin\Contracts\HasPagination;

class ApiConnector extends Connector implements HasPagination
{
    public function paginate(Request $request): CursorPaginator
    {
        return new class(connector: $this, request: $request) extends CursorPaginator
        {
            protected function getNextCursor(Response $response): int|string
            {
                return $response->json('next_cursor');
            }

            protected function isLastPage(Response $response): bool
            {
                return is_null($response->json('next_cursor'));
            }

            protected function getPageItems(Response $response, Request $request): array
            {
                return $response->json('items');
            }
        };
    }
}

// Usage — memory efficient (one page at a time)
foreach ($connector->paginate(new ListItemsRequest)->items() as $item) {
    // Process each item
}
```

Three types: `PagedPaginator`, `OffsetPaginator`, `CursorPaginator`.

### Testing

```php
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

$connector = new ApiConnector('token');
$connector->withMockClient(new MockClient([
    GetServersRequest::class => MockResponse::make(['servers' => [...]], 200),
]));

$response = $connector->send(new GetServersRequest);
```

**Mock types:**
- `MockResponse::make(body, status, headers)` — manual
- `MockResponse::fixture('filename')` — recorded responses
- Closures for dynamic logic
- Sequential responses for multiple calls

**Assertions:**
```php
$mockClient->assertSent(GetServersRequest::class);
$mockClient->assertNotSent(DeleteServerRequest::class);
$mockClient->assertSentCount(1);
$mockClient->assertNothingSent();
```

**Safety:**
```php
use Saloon\Config;
use Saloon\MockConfig;

Config::preventStrayRequests();         // Throws on real API calls
MockConfig::throwOnMissingFixtures();   // Prevents recording in CI
```

**Laravel facade testing:**
```php
use Saloon\Laravel\Facades\Saloon;

Saloon::fake([
    GetServersRequest::class => MockResponse::make(body: '', status: 200),
]);
```

### Delaying Requests

```php
// On connector or request
protected function defaultDelay(): ?int
{
    return 500; // milliseconds
}

// Dynamically
$connector->delay()->set(500);
```

Request delay overrides connector delay.

### Saloon v4 Breaking Changes

1. **SSRF Prevention**: Fully qualified URLs in `resolveEndpoint()` no longer override connector base URL. Opt-in with `public ?bool $allowBaseUrlOverride = true;`
2. **No serialize/unserialize**: `AccessTokenAuthenticator` serialization removed (RCE fix). Access `accessToken`, `refreshToken`, `expiresAt` properties directly.
3. **Fixture path traversal blocked**: `MockResponse::fixture('../../path')` throws.

### Solo Requests

For one-off requests without a connector:
```php
use Saloon\Http\SoloRequest;

class GetStatusRequest extends SoloRequest
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return 'https://api.example.com/status';
    }
}

$response = (new GetStatusRequest)->send();
```

**Never use user input in `resolveEndpoint()` for SoloRequests (SSRF risk).**

### Common Mistakes

| Mistake | Fix |
|---------|-----|
| Missing `HasBody` interface with body trait | Always `implements HasBody` alongside `HasJsonBody` etc. |
| Using `sendAsync`/pools with retry | Retry only works with synchronous `send()` |
| Not calling `$promise->wait()` on pools | Execution won't complete without it |
| Using `dto()` without checking status | Use `dtoOrFail()` to ensure success first |
| Guzzle middleware outside constructor | Causes duplicate registration on repeated sends |
| Missing `otherwise` on async promises | Always define both `then` and `otherwise` |
| User input in `resolveEndpoint()` | SSRF risk — validate or avoid |
| Serializing `AccessTokenAuthenticator` (v4) | Access properties directly, don't serialize |
| Path traversal in fixture names (v4) | Use simple names without `../` or `~/` |
