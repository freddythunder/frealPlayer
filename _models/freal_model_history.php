<?php
class PlayHistory
{
	private $file;

	public function __construct($file = null)
	{
		$this->file = $file ?: dirname(__DIR__) . '/tmp/play-history.log';
	}

	public function log($path, $ip = '', $agent = '')
	{
		$path = rawurldecode(trim((string)$path));
		$path = str_replace(["\r", "\n", "\t"], '', $path);
		if ($path === '' || strlen($path) > 1000 || !$this->pathAllowed($path)) {
			return false;
		}
		if (!is_dir(dirname($this->file))) {
			return false;
		}
		$ip = $this->cleanField($ip, 80);
		$agent = $this->cleanField($agent, 400);
		$line = date('Y-m-d H:i:s') . "\t" . $path . "\t" . $ip . "\t" . $agent . "\n";
		return file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX) !== false;
	}

	public function recent($limit = 50)
	{
		$limit = max(1, min(200, (int)$limit));
		if (!is_file($this->file)) {
			return [];
		}
		$lines = file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!$lines) {
			return [];
		}
		$entries = [];
		foreach ($lines as $line) {
			$parts = explode("\t", $line);
			if (count($parts) < 2 || $parts[1] === '') {
				continue;
			}
			$entries[] = [
				'time' => $parts[0],
				'path' => $parts[1],
				'ip' => $parts[2] ?? '',
				'agent' => $parts[3] ?? ''
			];
		}
		return array_reverse(array_slice($entries, -$limit));
	}

	private function cleanField($value, $max)
	{
		$value = trim(str_replace(["\r", "\n", "\t"], ' ', (string)$value));
		$value = preg_replace('/\s+/', ' ', $value);
		if (strlen($value) > $max) {
			$value = substr($value, 0, $max);
		}
		return $value;
	}

	private function pathAllowed($path)
	{
		foreach (['/hdd3/music/', '/hdd/repo/', '/repo/'] as $root) {
			if (strpos($path, $root) === 0) {
				return true;
			}
		}
		return false;
	}
}
