<?PHP
function ipdw_fixture($name, $phase, $percent, $bytes, $hostPath) {
  return [
    'name' => $name,
    'library' => 'Primary Library',
    'status' => 'running',
    'health' => 'healthy',
    'authIssue' => false,
    'authDays' => 29,
    'phase' => $phase,
    'percent' => $percent,
    'rate' => 1048576,
    'rateHistory' => [
      ['time' => time() - 30, 'rate' => 900000],
      ['time' => time() - 15, 'rate' => 1048576],
    ],
    'etaSeconds' => $phase === 'Complete' ? 0 : 3600,
    'lastActivity' => gmdate('Y-m-d H:i:s'),
    'done' => $phase === 'Complete' ? 100 : 20,
    'total' => 100,
    'downloadedBytes' => $bytes,
    'estimatedTotalBytes' => $bytes * 2,
    'remainingBytes' => $phase === 'Complete' ? 0 : $bytes,
    'errors' => 0,
    'files' => 100,
    'parts' => 0,
    'restarts' => 0,
    'bytes' => $bytes,
    'hostPath' => $hostPath,
  ];
}

function ipdw_status() {
  $suza = ipdw_fixture('icloudpd-suza', 'Authentication required', 0, 0, '/mnt/suza');
  $suza['health'] = 'unhealthy';
  $suza['authIssue'] = true;
  $suza['authInitialized'] = false;
  $suza['authAction'] = '/usr/local/bin/sync-icloud.sh --Initialise';

  return [
    'instances' => [
      ipdw_fixture('icloudpd-tural', 'Downloading originals…', 20, 100, '/mnt/tural'),
      ipdw_fixture('icloudpd-anna', 'Complete', 100, 200, '/mnt/anna'),
      $suza,
    ],
  ];
}

require dirname(__DIR__) . '/widget.php';

$html = ipdw_render_body();
$checks = [
  "<b>3</b><span>Archives</span>",
  "<b>1</b><span>Active download</span>",
  "<b>300 B</b><span>Archived</span>",
  "data-instance='icloudpd-tural' open",
  "data-instance='icloudpd-anna'>",
  '<b>Tural</b>',
  '<b>Anna</b>',
  '<b>Suza</b>',
  'Apple authentication required',
  'Set up authentication',
  '/usr/local/bin/sync-icloud.sh --Initialise',
  'Show details',
  'Download speed',
];

foreach ($checks as $check) {
  if (strpos($html, $check) === false) {
    fwrite(STDERR, 'Missing rendered fragment: ' . $check . PHP_EOL);
    exit(1);
  }
}

if (substr_count($html, " class='ipdw-card ") !== 3) {
  fwrite(STDERR, 'Expected exactly three archive cards.' . PHP_EOL);
  exit(1);
}

if (substr_count($html, " data-instance=") !== 3 || substr_count($html, ' open') !== 1) {
  fwrite(STDERR, 'Expected only the active archive to open by default.' . PHP_EOL);
  exit(1);
}

echo 'Render fixture passed.' . PHP_EOL;
