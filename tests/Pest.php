<?php

/*
|--------------------------------------------------------------------------
| Test Bootstrap
|--------------------------------------------------------------------------
|
| Include the bootstrap file to set up the testing environment.
|
*/

require_once __DIR__ . '/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toHaveLengthGreaterThan', function (int $length) {
    return strlen($this->value) > $length;
});

expect()->extend('toBeJson', function () {
    json_decode($this->value);
    return json_last_error() === JSON_ERROR_NONE;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

// Performance profiling helper
function profile(callable $callback, string $name = 'anonymous')
{
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);
    
    $result = $callback();
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage(true);
    
    $executionTime = ($endTime - $startTime) * 1000; // ms
    $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB
    
    // Store profiling data
    static $profiles = [];
    $profiles[$name] = [
        'time_ms' => round($executionTime, 2),
        'memory_mb' => round($memoryUsed, 2),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Optionally save to file
    if (getenv('SAVE_PROFILE_DATA')) {
        file_put_contents(
            __DIR__ . '/profile_data.json',
            json_encode($profiles, JSON_PRETTY_PRINT)
        );
    }
    
    return $result;
}

// Test grouping helpers
function integrationTest(callable $test)
{
    return test('Integration Test', $test)->group('integration');
}

function performanceTest(callable $test)
{
    return test('Performance Test', $test)->group('performance');
}

function edgeCaseTest(callable $test)
{
    return test('Edge Case Test', $test)->group('edge-case');
}
