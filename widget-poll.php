<?PHP
require_once '/usr/local/emhttp/plugins/icloudpd-status/widget.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
echo ipdw_render_body();
