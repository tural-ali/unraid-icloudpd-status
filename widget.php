<?PHP
$ipdwStatusFile = '/usr/local/emhttp/plugins/icloudpd-status/status.php';
require_once is_file($ipdwStatusFile) ? $ipdwStatusFile : __DIR__ . '/status.php';

if (!function_exists('ipdw_h')) {
  function ipdw_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES);
  }
}

if (!function_exists('ipdw_size')) {
  function ipdw_size($bytes) {
    $bytes = max(0, (int)$bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $index = $bytes > 0 ? min(4, (int)floor(log($bytes, 1024))) : 0;
    return number_format($bytes / pow(1024, $index), $index ? 1 : 0) . ' ' . $units[$index];
  }
}

if (!function_exists('ipdw_duration')) {
  function ipdw_duration($seconds) {
    $seconds = max(0, (int)$seconds);
    if ($seconds < 60) return '< 1m';
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    if ($days > 0) return $days . 'd ' . $hours . 'h';
    if ($hours > 0) return $hours . 'h ' . $minutes . 'm';
    return $minutes . 'm';
  }
}

if (!function_exists('ipdw_ago')) {
  function ipdw_ago($stamp) {
    if (!$stamp) return 'Waiting for activity';
    $time = strtotime($stamp . ' UTC');
    if (!$time) return $stamp;
    $seconds = max(0, time() - $time);
    if ($seconds < 90) return 'just now';
    if ($seconds < 5400) return (int)round($seconds / 60) . 'm ago';
    if ($seconds < 172800) return (int)round($seconds / 3600) . 'h ago';
    return (int)round($seconds / 86400) . 'd ago';
  }
}

if (!function_exists('ipdw_display_name')) {
  function ipdw_display_name($name) {
    $display = preg_replace('/^icloudpd[-_.]?/i', '', (string)$name);
    if ($display === '') $display = (string)$name;
    return ucwords(str_replace(['-', '_', '.'], ' ', $display));
  }
}

if (!function_exists('ipdw_sparkline')) {
  function ipdw_sparkline($samples) {
    $series = [];
    foreach (is_array($samples) ? $samples : [] as $sample) {
      if (!is_array($sample) || !isset($sample['time'], $sample['rate'])) continue;
      $series[] = [
        'time' => (int)$sample['time'],
        'rate' => max(0, (int)$sample['rate']),
      ];
    }
    if (count($series) < 2) {
      return "<div class='ipdw-sparkline-empty'>Collecting 10-minute history</div>";
    }

    $rates = array_column($series, 'rate');
    $minimum = min($rates);
    $range = max(1, max($rates) - $minimum);
    $windowStart = time() - 600;
    $points = [];
    foreach ($series as $sample) {
      $x = round((max($windowStart, $sample['time']) - $windowStart) / 5, 1);
      $y = round(27 - ((($sample['rate'] - $minimum) / $range) * 22), 1);
      $points[] = $x . ',' . $y;
    }
    $line = implode(' ', $points);
    $firstX = explode(',', reset($points))[0];
    $lastX = explode(',', end($points))[0];
    $area = $firstX . ',30 ' . $line . ' ' . $lastX . ',30';
    return "<svg class='ipdw-sparkline' viewBox='0 0 120 30' preserveAspectRatio='none' role='img' aria-label='Measured download speed over the last 10 minutes'>"
      . "<polygon points='" . $area . "'></polygon><polyline points='" . $line . "'></polyline></svg>";
  }
}

if (!function_exists('ipdw_render_body')) {
  function ipdw_render_body() {
    $status = ipdw_status();
    $instances = $status['instances'] ?? [];
    if (!$instances) return "<div class='ipdw-empty'>No iCloudPD containers found.</div>";

    $activeCount = 0;
    $archiveBytes = 0;
    $seenPaths = [];
    $defaultOpenName = count($instances) === 1 ? (string)$instances[0]['name'] : '';
    foreach ($instances as $instance) {
      $phase = (string)($instance['phase'] ?? '');
      $isActive = strpos($phase, 'Downloading') === 0
        && ($instance['status'] ?? '') === 'running'
        && empty($instance['authIssue']);
      if ($isActive) {
        $activeCount++;
        if ($defaultOpenName === '') $defaultOpenName = (string)$instance['name'];
      }
      $path = (string)($instance['hostPath'] ?? '');
      $archiveKey = $path !== '' ? $path : '__instance__' . (string)$instance['name'];
      if (!isset($seenPaths[$archiveKey])) {
        $archiveBytes += max(0, (int)($instance['bytes'] ?? 0));
        $seenPaths[$archiveKey] = true;
      }
    }

    $archiveLabel = count($instances) === 1 ? 'Archive' : 'Archives';
    $activeLabel = $activeCount === 1 ? 'Active download' : 'Active downloads';
    $out = "<div class='ipdw-list'><section class='ipdw-overview'>"
      . "<div><b>" . number_format(count($instances)) . "</b><span>" . $archiveLabel . "</span></div>"
      . "<div><b>" . number_format($activeCount) . "</b><span>" . $activeLabel . "</span></div>"
      . "<div><b>" . ipdw_size($archiveBytes) . "</b><span>Archived</span></div>"
      . "</section><div class='ipdw-archives'>";

    foreach ($instances as $instance) {
      $authIssue = !empty($instance['authIssue']);
      $healthy = $instance['status'] === 'running' && $instance['health'] === 'healthy' && !$authIssue;
      $state = $authIssue ? 'Authentication required' : ($healthy ? 'Healthy' : ucfirst($instance['status']));
      $stateClass = $authIssue ? 'ipdw-pill-bad' : ($healthy ? 'ipdw-pill-good' : 'ipdw-pill-warn');
      $phase = (string)($instance['phase'] ?? 'Starting');
      $isActive = strpos($phase, 'Downloading') === 0 && !$authIssue;
      $isComplete = $phase === 'Complete';
      $cardClass = $authIssue ? 'ipdw-card-bad' : ($isActive ? 'ipdw-card-active' : ($isComplete && $healthy ? 'ipdw-card-complete' : 'ipdw-card-idle'));
      $open = (string)$instance['name'] === $defaultOpenName ? ' open' : '';
      $percent = number_format((float)$instance['percent'], 1);
      $rate = (int)$instance['rate'] > 0 ? ipdw_size($instance['rate']) . '/s' : 'Measuring';
      $sparkline = ipdw_sparkline($instance['rateHistory'] ?? []);
      $eta = (int)$instance['etaSeconds'] > 0 ? '~' . ipdw_duration($instance['etaSeconds']) : 'Calculating';
      if ($isComplete) $eta = 'Complete';
      $etaSummary = $isComplete ? 'Completed' : ($eta === 'Calculating' ? 'Calculating ETA' : $eta . ' remaining');
      $authText = $instance['authDays'] !== null
        ? number_format((int)$instance['authDays']) . ' days'
        : ($authIssue ? 'Sign-in required' : 'Checking');
      $lastActivity = ipdw_ago($instance['lastActivity'] ?? '');
      $displayName = ipdw_display_name($instance['name']);

      $out .= "<details class='ipdw-card " . $cardClass . "' data-instance='" . ipdw_h($instance['name']) . "'" . $open . ">"
            . "<summary class='ipdw-card-summary'>"
            . "<div class='ipdw-instance'><div><b>" . ipdw_h($displayName) . "</b><span>" . ipdw_h($instance['library']) . "</span></div></div>"
            . "<span class='ipdw-phase'>" . ipdw_h($phase) . "</span>"
            . "<div class='ipdw-summary-progress'><strong>" . $percent . "%</strong><span>" . ipdw_h($etaSummary) . "</span></div>"
            . "<div class='ipdw-bar' title='Progress is approximate'><div class='ipdw-fill' style='width:" . $percent . "%'></div></div>"
            . "<div class='ipdw-summary-foot'><span><b>" . number_format((int)$instance['done']) . "</b> / " . number_format((int)$instance['total']) . " items</span>"
            . "<span class='ipdw-pill " . $stateClass . "'><i></i>" . ipdw_h($state) . "</span></div>"
            . "<div class='ipdw-toggle'><span class='ipdw-show'><i class='fa fa-chevron-right'></i> Show details</span>"
            . "<span class='ipdw-hide'><i class='fa fa-chevron-down'></i> Hide details</span></div>"
            . "</summary><div class='ipdw-expanded'>";

      if ($authIssue) {
        $out .= "<div class='ipdw-auth'><div><b>Apple authentication failed</b><span>Renew the session to resume downloads.</span></div>"
              . "<button type='button' onclick=\"openTerminal('docker','" . ipdw_h($instance['name']) . "','/usr/local/bin/reauth.sh');return false;\">Retry authentication</button></div>";
      }

      $out .= "<section class='ipdw-expanded-stats'>"
            . "<div><span>Items</span><b>" . number_format((int)$instance['done']) . " of " . number_format((int)$instance['total']) . "</b></div>"
            . "<div><span>Downloaded</span><b>" . ipdw_size($instance['downloadedBytes']) . " of ~" . ipdw_size($instance['estimatedTotalBytes']) . "</b></div>"
            . "<div><span>Remaining</span><b>~" . ipdw_size($instance['remainingBytes']) . "</b></div>"
            . "</section>";

      $out .= "<div class='ipdw-groups'>"
            . "<section class='ipdw-group'><h4>Download speed</h4>"
            . "<div class='ipdw-big-value'><i class='fa fa-tachometer'></i><b>" . ipdw_h($rate) . "</b></div>"
            . $sparkline
            . "<div class='ipdw-secondary'><span>Last 10 min</span><span>Updated " . ipdw_h($lastActivity) . "</span></div></section>"
            . "<section class='ipdw-group'><h4>Status</h4>"
            . "<div><span class='ipdw-pill " . $stateClass . "'><i></i>" . ipdw_h($state) . "</span></div>"
            . "<div class='ipdw-status-rows'>"
            . "<div><span class='" . ($authIssue ? 'ipdw-row-bad' : '') . "'><i class='fa " . ($authIssue ? 'fa-times' : 'fa-check') . "'></i> Authentication</span><b>" . ipdw_h($authText) . "</b></div>"
            . "<div><span class='" . ((int)$instance['errors'] > 0 ? 'ipdw-row-bad' : '') . "'><i class='fa " . ((int)$instance['errors'] > 0 ? 'fa-exclamation' : 'fa-check') . "'></i> Errors</span><b>" . number_format((int)$instance['errors']) . "</b></div>"
            . "</div></section>"
            . "</div>";

      $out .= "<details class='ipdw-details'><summary>Archive details</summary><div class='ipdw-detail-grid'>"
            . "<span>Archive on disk</span><b>" . ipdw_size($instance['bytes']) . "</b>"
            . "<span>Files on disk</span><b>" . number_format((int)$instance['files']) . "</b>"
            . "<span>Active partial files</span><b>" . number_format((int)$instance['parts']) . "</b>"
            . "<span>Container restarts</span><b>" . number_format((int)$instance['restarts']) . "</b>"
            . "<span>Container name</span><b>" . ipdw_h($instance['name']) . "</b>"
            . "<span>Last log activity</span><b>" . ipdw_h($instance['lastActivity'] ?: 'Waiting') . " UTC</b>"
            . "</div></details>";
      $out .= "</div></details>";
    }
    $out .= "</div><div class='ipdw-note'>Progress, remaining size, and ETA are estimates because Apple does not report the final byte total and Live Photos can create paired files.</div></div>";
    return $out;
  }
}
