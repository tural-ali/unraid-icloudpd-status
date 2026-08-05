<?PHP
require_once '/usr/local/emhttp/plugins/icloudpd-status/status.php';

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

    $out = "<div class='ipdw-list'>";
    foreach ($instances as $instance) {
      $authIssue = !empty($instance['authIssue']);
      $healthy = $instance['status'] === 'running' && $instance['health'] === 'healthy' && !$authIssue;
      $state = $authIssue ? 'Authentication required' : ($healthy ? 'Healthy' : ucfirst($instance['status']));
      $stateClass = $authIssue ? 'ipdw-pill-bad' : ($healthy ? 'ipdw-pill-good' : 'ipdw-pill-warn');
      $percent = number_format((float)$instance['percent'], 1);
      $rate = (int)$instance['rate'] > 0 ? ipdw_size($instance['rate']) . '/s' : 'Measuring';
      $sparkline = ipdw_sparkline($instance['rateHistory'] ?? []);
      $eta = (int)$instance['etaSeconds'] > 0 ? '~' . ipdw_duration($instance['etaSeconds']) : 'Calculating';
      if (($instance['phase'] ?? '') === 'Complete') $eta = 'Complete';
      $authText = $instance['authDays'] !== null
        ? number_format((int)$instance['authDays']) . ' days'
        : ($authIssue ? 'Sign-in required' : 'Checking');
      $lastActivity = ipdw_ago($instance['lastActivity'] ?? '');

      $out .= "<article class='ipdw-card'>";
      $out .= "<header class='ipdw-instance'><div><b>" . ipdw_h($instance['name']) . "</b>"
            . "<span>" . ipdw_h($instance['library']) . "</span></div></header>";

      if ($authIssue) {
        $out .= "<div class='ipdw-auth'><div><b>Apple authentication failed</b><span>Renew the session to resume downloads.</span></div>"
              . "<button type='button' onclick=\"openTerminal('docker','" . ipdw_h($instance['name']) . "','/usr/local/bin/reauth.sh');return false;\">Retry authentication</button></div>";
      }

      $out .= "<section class='ipdw-hero'>"
            . "<div class='ipdw-hero-top'><div><span class='ipdw-phase'>" . ipdw_h($instance['phase']) . "</span>"
            . "<strong class='ipdw-percent' title='Estimated from downloaded archive items'>" . $percent . "%</strong></div>"
            . "<div class='ipdw-eta'><span>Finishes in</span><b>" . ipdw_h($eta) . "</b></div></div>"
            . "<div class='ipdw-bar' title='Progress is approximate'><div class='ipdw-fill' style='width:" . $percent . "%'></div></div>"
            . "<div class='ipdw-items'><b>" . number_format((int)$instance['done']) . "</b><span> of " . number_format((int)$instance['total']) . " items</span></div>"
            . "<div class='ipdw-storage'><div><b>" . ipdw_size($instance['downloadedBytes']) . "</b><span> of ~" . ipdw_size($instance['estimatedTotalBytes']) . " downloaded</span></div>"
            . "<div><b>~" . ipdw_size($instance['remainingBytes']) . "</b><span> remaining</span></div></div>"
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
            . "<span>Last log activity</span><b>" . ipdw_h($instance['lastActivity'] ?: 'Waiting') . " UTC</b>"
            . "</div></details>";
      $out .= "</article>";
    }
    $out .= "<div class='ipdw-note'>Progress, remaining size, and ETA are estimates because Apple does not report the final byte total and Live Photos can create paired files.</div></div>";
    return $out;
  }
}
