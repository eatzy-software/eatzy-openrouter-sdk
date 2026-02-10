# 🧪 Test Suite Documentation

## Overview

This SDK includes a comprehensive test suite built with PestPHP that covers both unit and integration testing. The tests follow Laravel coding standards and Clean Architecture principles.

## Test Structure

```
tests/
├── Pest.php              # Pest configuration
├── bootstrap.php         # Test environment setup
├── Unit/                 # Unit tests for individual components
│   ├── Models/          # DTO and model tests
│   └── Support/         # Utility and helper tests
└── Integration/         # Integration tests for component interactions
    ├── ChatServiceTest.php      # Main service integration tests
    ├── HttpClientTest.php       # HTTP client integration tests
    ├── LaravelIntegrationTest.php # Laravel service provider tests
    └── ConfigurationTest.php    # Configuration integration tests
```

## Running Tests

### All Tests
```bash
composer test
```

### Unit Tests Only
```bash
composer test-unit
```

### Integration Tests Only
```bash
composer test-integration
```

### Test Coverage
```bash
composer test-coverage
```

### HTML Coverage Report
```bash
composer test-coverage-html
```

## Test Categories

### 1. Unit Tests (`tests/Unit/`)
- Test individual components in isolation
- Mock external dependencies
- Focus on pure functions and class methods
- Fast execution without external services

### 2. Integration Tests (`tests/Integration/`)
- Test component interactions
- Test real HTTP client behavior with mocks
- Test Laravel service provider registration
- Test configuration merging and validation
- Test end-to-end flows with mocked dependencies

## Key Test Features

### ✅ Comprehensive Coverage
- All public methods are tested
- Edge cases and error conditions covered
- Parameter validation tested
- Configuration edge cases handled

### ✅ Mock-Based Testing
- External HTTP calls are mocked
- Laravel container bindings tested without Laravel framework
- Guzzle HTTP client behavior simulated
- No actual API calls during testing

### ✅ Clean Architecture Compliance
- Tests respect dependency inversion
- Service contracts are tested, not concrete implementations
- HTTP client abstraction is verified
- Configuration interface behavior validated

### ✅ Laravel Integration Testing
- Service provider registration tested
- Container binding verification
- Facade accessibility confirmed
- Configuration merging behavior validated

## Test Examples

### Chat Service Integration Test
```php
it('creates chat completion successfully', function () {
    // Arrange
    $config = m::mock(ConfigurationInterface::class);
    $httpClient = m::mock(HttpClientInterface::class);
    $httpClient->shouldReceive('request')->andReturn(createMockChatResponse());
    
    $service = new ChatService($httpClient, $config);
    $request = new ChatCompletionRequest(
        messages: [ChatMessage::user('Hello!')],
        model: 'openai/gpt-4'
    );

    // Act
    $response = $service->create($request);

    // Assert
    expect($response)->toBeInstanceOf(ChatCompletionResponse::class);
    expect($response->getContent())->toBe('Hello! How can I help you today?');
});
```

### HTTP Client Integration Test
```php
it('makes successful HTTP requests', function () {
    // Arrange
    $mockResponses = [new Response(200, [], json_encode(['data' => 'success']))];
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    // Act
    $result = $httpClient->request('GET', 'https://api.example.com/test');

    // Assert
    expect($result['data'])->toBe('success');
});
```

### Laravel Integration Test
```php
it('registers service provider correctly', function () {
    // Arrange
    $serviceProvider = new OpenRouterServiceProvider($this->app);

    // Act
    $serviceProvider->register();

    // Assert
    expect($this->app->make(ChatServiceInterface::class))
        ->toBeInstanceOf(ChatService::class);
});
```

## Mock Data Helpers

The test suite includes helper functions for generating consistent mock data:

```php
function createMockChatResponse(array $overrides = []): array
function createMockErrorResponse(int $statusCode = 400): array
```

## Best Practices Implemented

### 1. AAA Pattern (Arrange-Act-Assert)
All tests follow the clear three-phase structure for readability.

### 2. Descriptive Test Names
Test names clearly describe the behavior being tested using Pest's `it()` syntax.

### 3. Isolation
Each test is independent and doesn't rely on state from other tests.

### 4. Proper Mocking
External dependencies are properly mocked to avoid flaky tests.

### 5. Comprehensive Assertions
Multiple assertions verify different aspects of the expected behavior.

## Continuous Integration

The test suite is designed to run in CI/CD pipelines with:
- Fast execution times
- No external dependencies
- Consistent results
- Clear failure messages

## Coverage Goals

- **Unit Tests**: 100% coverage of business logic
- **Integration Tests**: 100% coverage of component interactions
- **Overall**: 90%+ total code coverage

## Adding New Tests

When adding new functionality:

1. **Write failing tests first** (TDD approach)
2. **Follow existing test patterns**
3. **Use descriptive test names**
4. **Mock external dependencies**
5. **Test both happy path and error conditions**
6. **Verify all edge cases**

## Troubleshooting

### Common Issues

1. **Mockery not closing properly**
   ```php
   beforeEach(function () {
       m::close();
   });
   
   afterEach(function () {
       m::close();
   });
   ```

2. **HTTP client test failures**
   - Ensure mock handlers are properly configured
   - Check that expected request parameters match

3. **Laravel integration test issues**
   - Verify mock application setup
   - Check container binding names match

### Debugging Tips

- Use `dd()` or `dump()` for debugging test state
- Run individual tests with `--filter` option
- Check Pest documentation for advanced features
- Use coverage reports to identify untested code
