<?PHP
/* Read-only multi-instance status collector for iCloudPD on Unraid. */

if (!function_exists('ipdw_shell')) {
  function ipdw_shell($command) {
    $output = @shell_exec($command);
    return is_string($output) ? trim($output) : '';
  }
}

if (!function_exists('ipdw_library_name')) {
  function ipdw_library_name($zone) {
    if (strpos($zone, 'SharedSync') === 0) return 'Shared Library';
    if (strpos($zone, 'PrimarySync') === 0) return 'Primary Library';
    return $zone !== '' ? 'iCloud Photos' : 'Starting';
  }
}

if (!function_exists('ipdw_parse_log')) {
  function ipdw_parse_log($log) {
    $result = [
      'library' => 'Starting',
      'total' => 0,
      'done' => 0,
      'downloadedBytes' => 0,
      'estimatedTotalBytes' => 0,
      'zone' => '',
      'errors' => 0,
      'lastActivity' => '',
      'complete' => false,
    ];

    $assetKeys = [];

    foreach (preg_split('/\r?\n/', $log) as $line) {
      if (preg_match('/Downloading library: ([^\r\n]+)/', $line, $match)) {
        $result['library'] = ipdw_library_name(trim($match[1]));
      }
      if (preg_match('/Downloading ([0-9]+) original photos and videos/', $line, $match)) {
        $result['total'] = (int)$match[1];
        $result['done'] = 0;
        $result['downloadedBytes'] = 0;
        $result['errors'] = 0;
        $result['complete'] = false;
        $assetKeys = [];
      }
      if (preg_match('/ INFO +Downloaded (\/home\/user\/iCloud\/.*)$/', $line, $match)) {
        $containerPath = $match[1];
        $relativePath = substr($containerPath, strlen('/home/user/iCloud/'));
        if ($result['zone'] === '') {
          $result['zone'] = strtok($relativePath, '/');
          $result['library'] = ipdw_library_name($result['zone']);
        }
        $assetKey = preg_replace('/_HEVC$/i', '', preg_replace('/\.[^.\/]+$/', '', $relativePath));
        $assetKeys[$assetKey] = true;
        $result['done'] = count($assetKeys);
        $result['lastActivity'] = substr($line, 0, 19);
      }
      if (stripos($line, 'ERROR') !== false || stripos($line, 'ZONE_NOT_FOUND') !== false || stripos($line, 'Traceback') !== false) {
        $result['errors']++;
      }
      if (stripos($line, 'Download complete') !== false || stripos($line, 'Download finished') !== false) {
        $result['complete'] = true;
      }
    }

    $result['percent'] = $result['total'] > 0
      ? min(100, round(($result['done'] * 100) / $result['total'], 1))
      : 0;
    if ($result['done'] > 0 && $result['downloadedBytes'] > 0) {
      $result['estimatedTotalBytes'] = (int)round(
        ($result['downloadedBytes'] / $result['done']) * $result['total']
      );
    }
    return $result;
  }
}

if (!function_exists('ipdw_collect_instance')) {
  function ipdw_collect_instance($name, $image, $previous, $now) {
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $name)) return null;
    $quotedName = escapeshellarg($name);
    $inspect = json_decode(ipdw_shell('/usr/bin/docker inspect ' . $quotedName . ' 2>/dev/null'), true);
    $container = is_array($inspect) && isset($inspect[0]) ? $inspect[0] : [];
    if (!$container) return null;

    $state = $container['State'] ?? [];
    $status = (string)($state['Status'] ?? 'missing');
    $health = (string)($state['Health']['Status'] ?? 'unknown');
    $hostPath = '';
    foreach ($container['Mounts'] ?? [] as $mount) {
      if (($mount['Destination'] ?? '') === '/home/user/iCloud') {
        $hostPath = (string)($mount['Source'] ?? '');
        break;
      }
    }

    $log = $status === 'running'
      ? ipdw_shell('/usr/bin/docker exec ' . $quotedName . ' cat /tmp/icloudpd/icloudpd_sync.log 2>/dev/null')
      : '';

    $healthOutput = '';
    $healthLogs = $state['Health']['Log'] ?? [];
    if ($healthLogs) {
      $lastHealth = end($healthLogs);
      $healthOutput = trim((string)($lastHealth['Output'] ?? ''));
    }
    $recentLines = array_slice(preg_split('/\r?\n/', $log), -120);
    $recentLog = implode("\n", $recentLines);
    $authPattern = '/authentication (?:has )?failed|not authenticated|cookie (?:has )?expired|cookie does not exist|MFA.*expired|two.factor authentication/i';
    $authIssue = preg_match($authPattern, $healthOutput) === 1
      || ($health !== 'healthy' && preg_match($authPattern, $recentLog) === 1);
    $authDays = null;
    if (preg_match('/valid for ([0-9]+) day/i', $healthOutput, $authMatch)) {
      $authDays = (int)$authMatch[1];
    }

    $progress = ipdw_parse_log($log);

    $bytes = 0;
    $files = 0;
    $parts = 0;
    if ($hostPath !== '' && is_dir($hostPath)) {
      $quotedPath = escapeshellarg($hostPath);
      $bytes = (int)ipdw_shell('/usr/bin/du -sb ' . $quotedPath . ' 2>/dev/null | /usr/bin/cut -f1');
      $files = (int)ipdw_shell('/usr/bin/find ' . $quotedPath . " -type f ! -name '.mounted' 2>/dev/null | /usr/bin/wc -l");
      $parts = (int)ipdw_shell('/usr/bin/find ' . $quotedPath . " -type f -name '*.part' 2>/dev/null | /usr/bin/wc -l");
    }

    $zone = (string)($progress['zone'] ?? '');
    if ($hostPath !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $zone)) {
      $libraryPath = rtrim($hostPath, '/') . '/' . $zone;
      if (is_dir($libraryPath)) {
        $quotedLibrary = escapeshellarg($libraryPath);
        $progress['downloadedBytes'] = (int)ipdw_shell('/usr/bin/du -sb ' . $quotedLibrary . ' 2>/dev/null | /usr/bin/cut -f1');
        $progress['done'] = (int)ipdw_shell('/usr/bin/find ' . $quotedLibrary . " -type f ! -name '*.part' ! -name '.mounted' -printf '%P\\n' 2>/dev/null | /usr/bin/sed -E 's/\\.[^./]+$//; s/_HEVC$//' | /usr/bin/sort -u | /usr/bin/wc -l");
        $progress['percent'] = $progress['total'] > 0
          ? min(100, round(($progress['done'] * 100) / $progress['total'], 1))
          : 0;
        $progress['estimatedTotalBytes'] = $progress['done'] > 0
          ? (int)round(($progress['downloadedBytes'] / $progress['done']) * $progress['total'])
          : 0;
      }
    }

    $rate = 0;
    $oldTime = (int)($previous['generated'] ?? 0);
    $oldBytes = (int)($previous['bytes'] ?? 0);
    if ($oldTime > 0 && $now > $oldTime && $bytes >= $oldBytes) {
      $rate = (int)(($bytes - $oldBytes) / ($now - $oldTime));
    }

    return array_merge($progress, [
      'name' => $name,
      'image' => $image,
      'status' => $status,
      'health' => $health,
      'restarts' => (int)($container['RestartCount'] ?? 0),
      'authIssue' => $authIssue,
      'authDays' => $authDays,
      'authAction' => '/usr/local/bin/reauth.sh',
      'hostPath' => $hostPath,
      'files' => $files,
      'parts' => $parts,
      'bytes' => $bytes,
      'rate' => $rate,
      'approximate' => true,
    ]);
  }
}

if (!function_exists('ipdw_status')) {
  function ipdw_status($force = false) {
    $cache = '/var/local/emhttp/icloudpd-status-cache.json';
    $now = time();
    if (!$force && is_file($cache) && ($now - filemtime($cache)) < 15) {
      $cached = @json_decode(@file_get_contents($cache), true);
      if (is_array($cached)) return $cached;
    }

    $old = is_file($cache) ? @json_decode(@file_get_contents($cache), true) : [];
    $oldByName = [];
    foreach (($old['instances'] ?? []) as $instance) {
      $oldByName[(string)($instance['name'] ?? '')] = [
        'generated' => (int)($old['generated'] ?? 0),
        'bytes' => (int)($instance['bytes'] ?? 0),
      ];
    }

    $instances = [];
    $listing = ipdw_shell("/usr/bin/docker ps -a --format '{{.Names}}\t{{.Image}}' 2>/dev/null");
    foreach (preg_split('/\r?\n/', $listing) as $line) {
      if ($line === '') continue;
      [$name, $image] = array_pad(explode("\t", $line, 2), 2, '');
      if (stripos($name, 'icloudpd') === false && stripos($image, 'icloudpd') === false) continue;
      $instance = ipdw_collect_instance($name, $image, $oldByName[$name] ?? [], $now);
      if ($instance) $instances[] = $instance;
    }
    usort($instances, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });

    $result = ['generated' => $now, 'instances' => $instances];
    $tmp = $cache . '.tmp';
    if (@file_put_contents($tmp, json_encode($result)) !== false) {
      @chmod($tmp, 0600);
      @rename($tmp, $cache);
    }
    return $result;
  }
}
