<?PHP
$docroot = $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once $docroot . '/webGui/include/Secure.php';

header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo 'Method not allowed';
  exit;
}

$name = (string)($_POST['name'] ?? '');
if (!preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
  http_response_code(400);
  echo 'Invalid container name';
  exit;
}

$inspect = @shell_exec('/usr/bin/docker inspect --type container ' . escapeshellarg($name) . ' 2>/dev/null');
if (!is_string($inspect) || trim($inspect) === '') {
  http_response_code(404);
  echo 'Container not found';
  exit;
}

$socket = '/var/tmp/' . $name . '.sock';
$launcher = __DIR__ . '/auth-terminal-launch.sh';
$output = [];
$result = 1;
@exec(escapeshellarg($launcher) . ' ' . escapeshellarg($name), $output, $result);

if ($result !== 0) {
  http_response_code(500);
  echo 'Could not launch the authentication terminal';
  exit;
}

for ($attempt = 0; $attempt < 40 && @filetype($socket) !== 'socket'; $attempt++) {
  usleep(50000);
  clearstatcache(true, $socket);
}

if (@filetype($socket) !== 'socket') {
  http_response_code(500);
  echo 'Authentication terminal did not start';
  exit;
}

header('Location: /logterminal/' . rawurlencode($name) . '/');
exit;
