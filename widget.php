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

if (!function_exists('ipdw_render_body')) {
  function ipdw_render_body() {
    $status = ipdw_status();
    $instances = $status['instances'] ?? [];
    if (!$instances) return "<div class='ipdw-empty'>No iCloudPD containers found.</div>";

    $out = "<div class='ipdw-list'>";
    foreach ($instances as $instance) {
      $authIssue = !empty($instance['authIssue']);
      $healthy = $instance['status'] === 'running' && $instance['health'] === 'healthy' && !$authIssue;
      $state = $authIssue ? 'Authentication required' : ($healthy ? 'Healthy' : $instance['status'] . ' / ' . $instance['health']);
      $color = $authIssue ? '#dc2626' : ($healthy ? '#16a34a' : '#b45309');
      $percent = number_format((float)$instance['percent'], 1);
      $rate = (int)$instance['rate'] > 0 ? ipdw_size($instance['rate']) . '/s' : 'measuring';
      $authText = $instance['authDays'] !== null
        ? 'Valid for ' . number_format((int)$instance['authDays']) . ' days'
        : ($authIssue ? 'Sign-in required' : 'Checking');
      $out .= "<div class='ipdw-card'>";
      $out .= "<div class='ipdw-head'><b>" . ipdw_h($instance['name']) . "</b>"
            . "<span class='ipdw-state' style='color:" . $color . "'>" . ipdw_h($state) . "</span></div>";
      $out .= "<div class='ipdw-sub'>" . ipdw_h($instance['library']) . "</div>";
      if ($authIssue) {
        $out .= "<div class='ipdw-auth'><b>Apple authentication failed.</b> "
              . "<button type='button' onclick=\"openTerminal('docker','" . ipdw_h($instance['name']) . "','/usr/local/bin/reauth.sh');return false;\">Retry authentication</button></div>";
      }
      $out .= "<div class='ipdw-bar'><div class='ipdw-fill' style='width:" . $percent . "%'></div>"
            . "<div class='ipdw-pct'>" . $percent . "% approx</div></div>";
      $out .= "<div class='ipdw-grid'>"
            . "<span>Downloaded / total</span><b>" . number_format((int)$instance['done']) . " / " . number_format((int)$instance['total']) . " items</b>"
            . "<span>Downloaded size</span><b>" . ipdw_size($instance['downloadedBytes']) . "</b>"
            . "<span>Estimated total size</span><b>~" . ipdw_size($instance['estimatedTotalBytes']) . "</b>"
            . "<span>Archive on disk</span><b>" . ipdw_size($instance['bytes']) . " / " . number_format((int)$instance['files']) . " files</b>"
            . "<span>Current rate</span><b>" . ipdw_h($rate) . "</b>"
            . "<span>Last activity</span><b>" . ipdw_h($instance['lastActivity'] ?: 'waiting') . "</b>"
            . "<span>Authentication</span><b>" . ipdw_h($authText) . "</b>"
            . "<span>Errors / restarts</span><b>" . number_format((int)$instance['errors']) . " / " . number_format((int)$instance['restarts']) . "</b>"
            . "</div>";
      $out .= "</div>";
    }
    $out .= "<div class='ipdw-note'>Counts and total size are approximate. Live Photos create multiple files, and Apple does not report the final byte total in advance.</div></div>";
    return $out;
  }
}
