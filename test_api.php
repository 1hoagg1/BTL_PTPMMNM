<?php

/**
 * DevTrack REST API Automated Test Suite (Runner file)
 */

$phpBinary = 'C:\xampp\php\php.exe';

echo "=== DevTrack API Test Suite ===\n\n";

function callApi(string $queryString, string $method = 'GET', array $postData = []): array
{
    global $phpBinary;
    $runner = __DIR__ . '/api_runner_temp.php';
    $postStr = var_export($postData, true);

    $script = "<?php\n"
            . "\$_SERVER['REQUEST_METHOD'] = '{$method}';\n"
            . "parse_str('{$queryString}', \$_GET);\n"
            . "\$_POST = {$postStr};\n"
            . "require __DIR__ . '/api.php';\n";

    file_put_contents($runner, $script);
    $cmd = '"' . $phpBinary . '" "' . $runner . '"';
    $output = shell_exec($cmd);
    @unlink($runner);

    $json = json_decode($output, true);
    return [
        'raw' => $output,
        'json' => $json
    ];
}

$passCount = 0;
$failCount = 0;

function assertApi(string $name, bool $condition, string $detail = ''): void
{
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] $name\n";
    } else {
        $failCount++;
        echo " [FAIL] $name: $detail\n";
    }
}

// 1. Root API Info
$res = callApi('');
assertApi('GET /api.php info endpoint', isset($res['json']['name']) && $res['json']['name'] === 'DevTrack API', $res['raw'] ?? '');

// 2. GET projects
$res = callApi('resource=projects');
assertApi('GET /api.php?resource=projects', is_array($res['json']) && count($res['json']) > 0, $res['raw'] ?? '');

// 3. GET members
$res = callApi('resource=members');
assertApi('GET /api.php?resource=members', is_array($res['json']) && count($res['json']) > 0, $res['raw'] ?? '');

// 4. GET tasks
$res = callApi('resource=tasks');
assertApi('GET /api.php?resource=tasks', is_array($res['json']) && count($res['json']) > 0, $res['raw'] ?? '');

// 5. GET issues
$res = callApi('resource=issues');
assertApi('GET /api.php?resource=issues', is_array($res['json']) && count($res['json']) > 0, $res['raw'] ?? '');

// 6. GET releases
$res = callApi('resource=releases');
assertApi('GET /api.php?resource=releases', is_array($res['json']) && count($res['json']) > 0, $res['raw'] ?? '');

// 7. GET summary
$res = callApi('resource=summary');
assertApi('GET /api.php?resource=summary', isset($res['json']['issues']) && isset($res['json']['completion_percent']), $res['raw'] ?? '');

// 8. GET activity_log
$res = callApi('resource=activity_log&limit=5');
assertApi('GET /api.php?resource=activity_log', is_array($res['json']) && count($res['json']) > 0, $res['raw'] ?? '');

// 9. POST auth login
$res = callApi('resource=auth&action=login', 'POST', [
    'login' => 'admin',
    'password' => 'admin123'
]);
assertApi('POST /api.php?resource=auth&action=login', !empty($res['json']['success']) && $res['json']['user']['username'] === 'admin', $res['raw'] ?? '');

echo "\n=====================================\n";
echo "API Results: $passCount PASSED, $failCount FAILED\n";
echo "=====================================\n";

if ($failCount > 0) {
    exit(1);
}

