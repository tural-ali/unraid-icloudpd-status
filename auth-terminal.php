<?PHP
$docroot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once $docroot . '/webGui/include/Secure.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

$name = (string)($_POST['name'] ?? '');
if (!preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid container name']);
  exit;
}

$inspect = @shell_exec('/usr/bin/docker inspect --type container ' . escapeshellarg($name) . ' 2>/dev/null');
if (!is_string($inspect) || trim($inspect) === '') {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Container not found']);
  exit;
}

$socket = '/var/tmp/' . $name . '.sock';
$type = @filetype($socket);
if ($type !== false && $type !== 'socket') {
  http_response_code(409);
  echo json_encode(['ok' => false, 'error' => 'Terminal path is not a socket']);
  exit;
}

if ($type === 'socket' && !@unlink($socket)) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Could not reset terminal socket']);
  exit;
}

echo json_encode(['ok' => true]);
