<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const DATA_FILE = __DIR__ . '/storage/requests.json';

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function withRequests(callable $callback): mixed
{
    $directory = dirname(DATA_FILE);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        respond(['ok' => false, 'error' => 'Could not create data storage.'], 500);
    }

    $handle = fopen(DATA_FILE, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        respond(['ok' => false, 'error' => 'Could not lock data storage.'], 500);
    }

    rewind($handle);
    $contents = stream_get_contents($handle);
    $requests = json_decode($contents ?: '[]', true);
    if (!is_array($requests)) {
        $requests = [];
    }

    $result = $callback($requests);
    if (isset($result['write']) && $result['write'] === true) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($handle);
    }

    flock($handle, LOCK_UN);
    fclose($handle);
    return $result['payload'];
}

function seedRequests(): array
{
    $now = time();
    return [
        ['id' => 'demo-104', 'stationId' => 'LAB-204', 'room' => 'Lab 2A · Row B', 'message' => 'The monitor is not displaying anything.', 'priority' => 'high', 'status' => 'open', 'source' => 'demo', 'createdAt' => date(DATE_ATOM, $now - 155), 'acknowledgedAt' => null],
        ['id' => 'demo-103', 'stationId' => 'LAB-208', 'room' => 'Lab 2B · Row A', 'message' => 'I cannot log in to this computer.', 'priority' => 'normal', 'status' => 'acknowledged', 'source' => 'demo', 'createdAt' => date(DATE_ATOM, $now - 490), 'acknowledgedAt' => date(DATE_ATOM, $now - 380)],
        ['id' => 'demo-102', 'stationId' => 'LAB-201', 'room' => 'Lab 2A · Row A', 'message' => 'I need assistance with the lab software.', 'priority' => 'normal', 'status' => 'resolved', 'source' => 'demo', 'createdAt' => date(DATE_ATOM, $now - 1900), 'acknowledgedAt' => date(DATE_ATOM, $now - 1770)],
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $payload = withRequests(static function (array &$requests): array {
        if (!$requests) {
            $requests = seedRequests();
            return ['write' => true, 'payload' => ['ok' => true, 'requests' => $requests]];
        }
        return ['write' => false, 'payload' => ['ok' => true, 'requests' => $requests]];
    });
    respond($payload);
}

if ($method !== 'POST') {
    header('Allow: GET, POST, OPTIONS');
    respond(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    respond(['ok' => false, 'error' => 'A JSON request body is required.'], 400);
}

$action = $input['action'] ?? '';
$payload = withRequests(static function (array &$requests) use ($action, $input): array {
    if ($action === 'create') {
        $stationId = strtoupper(trim((string) ($input['stationId'] ?? '')));
        $message = trim((string) ($input['message'] ?? 'A student is requesting assistance.'));
        $room = trim((string) ($input['room'] ?? 'Computer lab'));
        if (!preg_match('/^[A-Z0-9-]{3,20}$/', $stationId)) {
            return ['write' => false, 'payload' => ['ok' => false, 'error' => 'A valid stationId is required.', '_status' => 422]];
        }
        if ($message === '' || strlen($message) > 240 || strlen($room) > 80) {
            return ['write' => false, 'payload' => ['ok' => false, 'error' => 'Message or room value is invalid.', '_status' => 422]];
        }
        $request = [
            'id' => bin2hex(random_bytes(8)),
            'stationId' => $stationId,
            'room' => $room,
            'message' => $message,
            'priority' => ($input['priority'] ?? '') === 'high' ? 'high' : 'normal',
            'status' => 'open',
            'source' => trim((string) ($input['source'] ?? 'device')) === 'dashboard-demo' ? 'dashboard-demo' : 'device',
            'createdAt' => date(DATE_ATOM),
            'acknowledgedAt' => null,
        ];
        array_unshift($requests, $request);
        $requests = array_slice($requests, 0, 200);
        return ['write' => true, 'payload' => ['ok' => true, 'request' => $request]];
    }

    if (in_array($action, ['acknowledge', 'resolve'], true)) {
        $id = (string) ($input['id'] ?? '');
        foreach ($requests as &$request) {
            if (($request['id'] ?? '') !== $id) {
                continue;
            }
            if ($action === 'acknowledge' && ($request['status'] ?? '') === 'open') {
                $request['status'] = 'acknowledged';
                $request['acknowledgedAt'] = date(DATE_ATOM);
            } elseif ($action === 'resolve' && ($request['status'] ?? '') !== 'resolved') {
                $request['status'] = 'resolved';
            }
            $updatedRequest = $request;
            unset($request);
            return ['write' => true, 'payload' => ['ok' => true, 'request' => $updatedRequest]];
        }
        unset($request);
        return ['write' => false, 'payload' => ['ok' => false, 'error' => 'Request not found.', '_status' => 404]];
    }

    return ['write' => false, 'payload' => ['ok' => false, 'error' => 'Unknown action.', '_status' => 400]];
});

$status = $payload['_status'] ?? 200;
unset($payload['_status']);
respond($payload, $status);
