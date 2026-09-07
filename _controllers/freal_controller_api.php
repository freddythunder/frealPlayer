<?php
session_start();
class FrealApi
{
	private $bs;
	private $dbi;
	
	private $stockAudioModel;
	private $songList;
	private $groupData;
	private $dirs;
	
	private $playlist;
	
	public function __construct()
	{
		// get bootstrap
		require_once('_copy/bootstrap.php');
		require_once('_models/freal_model_playlist.php');
		// connect to database
		$this->bs = new Bootstrap();
		$this->dbi = $this->bs->dbi;
		// get stuff from database
		$this->stockAudioModel = new StockAudio($this->dbi);
		$this->playlist = new Playlist($this->dbi);
		
		if (($_REQUEST['cmd'] ?? false) == 'doSearch') {
			$this->doSearch();
		}

		if (($_REQUEST['cmd'] ?? false) == 'getLyrics') {
			$this->getLyrics();
			return;
		}

		if (($_REQUEST['cmd'] ?? false) == 'deleteLyricsCache') {
			$this->deleteLyricsCache();
			return;
		}

		if (($_REQUEST['cmd'] ?? false) == 'getAlbumArt') {
			$this->getAlbumArt();
			return;
		}
		
		if (isset($_REQUEST['cmd']) && method_exists($this, $_REQUEST['cmd'])) {
			$this->{$_REQUEST['cmd']}();
		}
		
	}
	
	private function getPlaylists() {
		$response = $this->playlist->getPlaylists();
		echo json_encode($response);
	}
	
	private function savePlaylist() {
		$response = $this->playlist->savePlaylist($_REQUEST);
		echo json_encode($response);
	}
	
	private function removeFromPlaylist() {
		$this->playlist->removeFromPlaylist($_REQUEST['name']);
		echo json_encode(['success' => true]);
	}
	
	public function doSearch() {
		$srch = preg_replace("/[^a-zA-Z0-9\s]/", "", (string)($_REQUEST['srch'] ?? ''));
		$html = '<div class="searchResultContainer">';

		if (!empty($_SESSION['stockmode'])) {
			$songs = $this->stockAudioModel->searchStockAudio($_REQUEST['srch'] ?? '');
			if (count($songs)) {
				$html .= '<div class="searchHeader">Stock Audio</div>';
				foreach ($songs as $song) {
					$html .= '<div class="searchSong">';
					$html .= '<div class="searchSongMain dopost" data-stock-id="' . (int)$song['id'] . '">';
					$html .= '<div class="name">' . $this->h($song['name']) . '</div>';
					$html .= '<div class="tiny">' . $this->h($song['source']) . ' :: ' . $this->h($song['genre']) . '</div>';
					$html .= '<div class="tiny">' . $this->h($song['path']) . '</div>';
					if (!empty($song['notes'])) {
						$html .= '<div class="tiny"><em>' . $this->h($song['notes']) . '</em></div>';
					}
					$html .= '</div>';
					$html .= $this->lyricsButtonHtml($song['path'] ?? '', $song['name'] ?? '');
					$html .= '</div>';
				}
			}
			$html .= '</div>';
			echo json_encode(['success' => true, 'html' => $html]);
			die();
		}

		$bands = [];
		$songs = [];
		// search directories
		$dir = '/hdd3/music/';
		$cmd = "find $dir -type d -iname \"*" . escapeshellcmd($srch) . "*\" 2>/dev/null";
		exec($cmd, $response);
		foreach ($response as $band) {
			$bands[] = $band;
		}
		
		// search files 
		$dir = '/hdd3/music/';

		$cmd = "find $dir -type f -iname \"*" . escapeshellcmd($srch) . "*\" 2>/dev/null";
		// error_log($cmd);
		exec($cmd, $response);
		foreach ($response as $song) {
			if (stripos($song, '.mp3') !== false || stripos($song, '.flac') !== false) {
				$songs[] = $song;
			}
		}
		$dir = '/hdd/repo/brenda/';
		$cmd = "find $dir -type f -iname \"*" . escapeshellcmd($srch) . "*\" 2>/dev/null";
		exec($cmd, $response);
		foreach ($response as $song) {
			if (stripos($song, '.mp3') !== false || stripos($song, '.flac') !== false) {
				$songs[] = $song;
			}
		}
		
		// bands
		if (count($bands)) {
			$bands = array_unique($bands);
			sort($bands);
			$html .= '<div class="searchHeader">Bands / Folders</div>';
			foreach ($bands as $band) {
				$html .= '<div class="searchBand dopost" data-band="' . $band . '">' . basename($band);

				$html .= '</div>';
			}
		}
		
		// playlists 
		$playlists = $this->playlist->searchPlaylist($srch);
		if (count($playlists)) {
			$html .= '<div class="searchHeader">Playlist</div>';
			foreach ($playlists as $playlist) {
				$html .= '<div class="searchSong dopost" data-playlist="' . $playlist['name'] . '">' . $playlist['name'];
				
				$html .= '</div>';
			}
		}
				
		// songs
		if (count($songs)) {
			$songs = array_unique($songs);
			sort($songs);
			$html .= '<div class="searchHeader">Songs / Files</div>';
			foreach ($songs as $song) {
				$html .= '<div class="searchSong">';
				$html .= '<div class="searchSongMain dopost" data-song="' . $this->h($song) . '">' . $this->h(basename($song));
				$html .= '<div class="tiny">' . $this->h($this->pathToUser($song)) . '</div>';
				$html .= '</div>';
				$html .= $this->lyricsButtonHtml($song, pathinfo($song, PATHINFO_FILENAME));
				$html .= '</div>';
			}
		}
		
		
		
		$html .= '</div>';
		
		echo json_encode(['success' => true, 'html' => $html]);
		die();
	}
	
	private function addNewSong() {
		$response = ['success' => false];
		$url = trim((string)($_REQUEST['url'] ?? ''));
		$filename = trim((string)($_REQUEST['filename'] ?? ''));
		$filepath = rawurldecode(trim((string)($_REQUEST['filepath'] ?? '')));

		if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
			$response['msg'] = 'A valid URL is required.';
			echo json_encode($response);
			return;
		}
		$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
		if (!in_array($scheme, ['http', 'https'], true)) {
			$response['msg'] = 'A valid URL is required.';
			echo json_encode($response);
			return;
		}

		if ($filename !== '') {
			if (!preg_match('/^[a-zA-Z0-9\s._-]+$/', $filename)) {
				$response['msg'] = 'Filename may only contain letters, numbers, spaces, dashes, underscores, and periods.';
				echo json_encode($response);
				return;
			}
			if (!preg_match('/\.mp3$/i', $filename)) {
				$filename .= '.mp3';
			}
			if (!preg_match('/\.mp3$/i', $filename)) {
				$response['msg'] = 'Filename must end in .mp3.';
				echo json_encode($response);
				return;
			}
			$output = $filename;
		} else {
			$output = '%(title)s.%(ext)s';
		}

		if ($filepath === '') {
			$response['msg'] = 'Open a music folder first so the song has a place to save.';
			echo json_encode($response);
			return;
		}
		if (is_file($filepath)) {
			$filepath = dirname($filepath);
		}
		$filepath = rtrim($filepath, '/');
		$realPath = realpath($filepath);
		$musicRoot = realpath('/hdd3/music');
		if ($realPath === false || $musicRoot === false || !is_dir($realPath)) {
			$response['msg'] = 'Songs can only be added to a folder under the music library.';
			echo json_encode($response);
			return;
		}
		$underMusic = ($realPath === $musicRoot) || (strpos($realPath, $musicRoot . DIRECTORY_SEPARATOR) === 0);
		if (!$underMusic) {
			$response['msg'] = 'Songs can only be added to a folder under the music library.';
			echo json_encode($response);
			return;
		}
		if (!chdir($realPath)) {
			$response['msg'] = 'Could not enter the current folder.';
			echo json_encode($response);
			return;
		}

		require_once(__DIR__ . '/../_copy/ytdlp.php');
		if (!ytdlpRunBackground($output, $url, [], 'ytdlp-addsong.log')) {
			$response['msg'] = 'yt-dlp is not available on the server.';
			echo json_encode($response);
			return;
		}

		$response['success'] = true;
		$response['msg'] = 'Download started in the background.';
		echo json_encode($response);
	}

	private function getAlbumArt() {
		$path = rawurldecode(trim((string)($_REQUEST['path'] ?? '')));
		$art = $this->findAlbumArt($path);
		$raw = isset($_REQUEST['raw']) && (string)$_REQUEST['raw'] === '1';
		if ($raw) {
			if ($art === '' || !is_file($art)) {
				http_response_code(404);
				return;
			}
			header('Content-Type: image/jpeg');
			header('Content-Length: ' . filesize($art));
			header('Cache-Control: public, max-age=86400');
			header('Access-Control-Allow-Origin: *');
			readfile($art);
			return;
		}
		echo json_encode([
			'success' => $art !== '',
			'path' => $art
		]);
	}

	private function findAlbumArt($songPath) {
		$musicRoot = realpath('/hdd3/music');
		if ($musicRoot === false) {
			return '';
		}
		$path = (string)$songPath;
		if ($path === '') {
			return '';
		}
		if (is_file($path) || preg_match('/\.(mp3|flac|ogg|wav)$/i', $path)) {
			$start = dirname($path);
		} else {
			$start = $path;
		}
		$dir = realpath($start);
		if ($dir === false) {
			return '';
		}
		$rootPrefix = $musicRoot . DIRECTORY_SEPARATOR;
		if ($dir !== $musicRoot && strpos($dir, $rootPrefix) !== 0) {
			return '';
		}
		while ($dir !== $musicRoot) {
			$jpg = $this->firstJpegInDir($dir);
			if ($jpg !== '') {
				return $jpg;
			}
			$parent = dirname($dir);
			if ($parent === $dir) {
				break;
			}
			if ($parent !== $musicRoot && strpos($parent, $rootPrefix) !== 0) {
				break;
			}
			$dir = $parent;
		}
		return '';
	}

	private function firstJpegInDir($dir) {
		$entries = @scandir($dir);
		if (!is_array($entries)) {
			return '';
		}
		foreach ($entries as $entry) {
			if ($entry === '' || $entry[0] === '.') {
				continue;
			}
			if (!preg_match('/\.(jpe?g)$/i', $entry)) {
				continue;
			}
			$file = $dir . '/' . $entry;
			if (is_file($file) && filesize($file) > 0) {
				return $file;
			}
		}
		return '';
	}

	private function getLyrics() {
		$response = [
			'success' => false,
			'lyrics' => '',
			'title' => '',
			'artist' => '',
			'url' => ''
		];
		$path = rawurldecode(trim((string)($_REQUEST['path'] ?? '')));
		$name = trim((string)($_REQUEST['name'] ?? ''));
		$artist = $this->artistFromPath($path);
		if ($this->isGenericArtist($artist)) {
			$artist = '';
		}
		$title = $this->cleanSongTitle($name !== '' ? $name : $this->titleFromPath($path));
		$response['title'] = $title;
		$response['artist'] = $artist;

		$cached = $this->lyricsCacheGet($path, $artist, $title);
		if ($cached) {
			echo json_encode($cached);
			return;
		}

		$key = (string)($_SERVER['GENKEY'] ?? getenv('GENKEY') ?: '');
		if ($key === '') {
			$response['msg'] = 'Lyrics API is not configured.';
			echo json_encode($response);
			return;
		}
		$query = $artist !== '' ? trim($artist . ' ' . $title) : $title;
		if ($query === '') {
			$response['msg'] = 'Not enough song info to search lyrics.';
			echo json_encode($response);
			return;
		}

		$searchJson = $this->httpGet(
			'https://api.genius.com/search?q=' . rawurlencode($query),
			[
				'Authorization: Bearer ' . $key,
				'Accept: application/json'
			]
		);
		$search = json_decode($searchJson, true);
		$hits = $search['response']['hits'] ?? [];
		$best = $this->pickGeniusHit($hits, $artist, $title);
		if (!$best) {
			$response['msg'] = 'No lyrics match found.';
			echo json_encode($response);
			return;
		}

		$response['title'] = $best['title'] ?? $title;
		$response['artist'] = $best['primary_artist']['name'] ?? $response['artist'];
		$response['url'] = $best['url'] ?? '';
		if ($response['url'] === '') {
			$response['msg'] = 'No lyrics match found.';
			echo json_encode($response);
			return;
		}

		$pageHtml = $this->httpGet($response['url']);
		$lyrics = $this->extractGeniusLyrics($pageHtml);
		if ($lyrics === '') {
			$response['msg'] = 'Found the song, but could not load the lyrics.';
			echo json_encode($response);
			return;
		}

		$response['success'] = true;
		$response['lyrics'] = $lyrics;
		$this->lyricsCacheSet($path, $response);
		echo json_encode($response);
	}

	private function deleteLyricsCache() {
		$response = ['success' => false];
		$path = rawurldecode(trim((string)($_REQUEST['path'] ?? '')));
		$name = trim((string)($_REQUEST['name'] ?? ''));
		$folderArtist = $this->artistFromPath($path);
		$artist = $this->isGenericArtist($folderArtist) ? '' : $folderArtist;
		$title = $this->cleanSongTitle($name !== '' ? $name : $this->titleFromPath($path));
		$db = $this->lyricsCacheDb();
		if (!$db) {
			$response['msg'] = 'Lyrics cache is not available.';
			echo json_encode($response);
			return;
		}
		$keys = array_values(array_unique(array_filter([
			$this->lyricsCacheKey($artist, $title),
			$this->lyricsCacheKey($folderArtist, $title)
		], function($key) {
			return $key !== '|';
		})));
		if (!$this->lyricsCacheDeleteRows($path, $keys)) {
			$response['msg'] = 'Could not remove lyrics from cache.';
			echo json_encode($response);
			return;
		}

		$response['success'] = true;
		$response['msg'] = 'Lyrics removed from cache.';
		echo json_encode($response);
	}

	private function lyricsCacheDeleteRows($path, $keys = []) {
		$db = $this->lyricsCacheDb();
		if (!$db) {
			return false;
		}
		$keys = array_values(array_filter((array)$keys, function($key) {
			return $key && $key !== '|';
		}));
		try {
			if ($db instanceof PDO) {
				if ($path !== '') {
					$stmt = $db->prepare('DELETE FROM lyrics_cache WHERE path = :path');
					$stmt->execute([':path' => $path]);
				}
				if ($keys) {
					$placeholders = implode(',', array_fill(0, count($keys), '?'));
					$stmt = $db->prepare('DELETE FROM lyrics_cache WHERE cache_key IN (' . $placeholders . ')');
					$stmt->execute($keys);
				}
				$stmt = $db->prepare("DELETE FROM lyrics_cache WHERE lyrics = '__BLOCKED__' OR url = 'blocked'");
				$stmt->execute();
			} else if ($db instanceof SQLite3) {
				if ($path !== '') {
					$stmt = $db->prepare('DELETE FROM lyrics_cache WHERE path = :path');
					$stmt->bindValue(':path', $path, SQLITE3_TEXT);
					$stmt->execute();
				}
				foreach ($keys as $key) {
					$stmt = $db->prepare('DELETE FROM lyrics_cache WHERE cache_key = :key');
					$stmt->bindValue(':key', $key, SQLITE3_TEXT);
					$stmt->execute();
				}
				$db->exec("DELETE FROM lyrics_cache WHERE lyrics = '__BLOCKED__' OR url = 'blocked'");
			}
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	private function lyricsCacheDir() {
		$candidates = [
			__DIR__ . '/../_data',
			__DIR__ . '/../tmp',
			rtrim(sys_get_temp_dir(), '/') . '/frealPlayer'
		];
		foreach ($candidates as $dir) {
			if (!is_dir($dir)) {
				@mkdir($dir, 0775, true);
			}
			if (is_dir($dir) && is_writable($dir)) {
				return $dir;
			}
		}
		return '';
	}

	private function lyricsCacheKey($artist, $title) {
		$normalize = function($value) {
			$value = strtolower(trim((string)$value));
			$value = preg_replace('/^the\s+/', '', $value);
			$value = str_replace('various artists', 'various', $value);
			return preg_replace('/[^a-z0-9]+/', '', $value);
		};
		return $normalize($artist) . '|' . $normalize($title);
	}

	private function lyricsCacheDb() {
		static $db = false;
		if ($db !== false) {
			return $db;
		}
		$db = null;
		$dir = $this->lyricsCacheDir();
		if ($dir === '') {
			return null;
		}
		$file = $dir . '/lyrics-cache.sqlite';
		$createSql = 'CREATE TABLE IF NOT EXISTS lyrics_cache (
			cache_key TEXT PRIMARY KEY,
			path TEXT,
			artist TEXT,
			title TEXT,
			lyrics TEXT NOT NULL,
			url TEXT,
			created_at TEXT NOT NULL
		)';
		try {
			if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
				$pdo = new PDO('sqlite:' . $file);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$pdo->exec($createSql);
				$db = $pdo;
				return $db;
			}
			if (class_exists('SQLite3')) {
				$sqlite = new SQLite3($file);
				$sqlite->exec($createSql);
				$db = $sqlite;
				return $db;
			}
		} catch (Exception $e) {
			$db = null;
		}
		return $db;
	}

	private function lyricsCacheGet($path, $artist, $title) {
		$db = $this->lyricsCacheDb();
		if (!$db) {
			return null;
		}
		$key = $this->lyricsCacheKey($artist, $title);
		$row = null;
		try {
			if ($db instanceof PDO) {
				if ($key !== '|') {
					$stmt = $db->prepare('SELECT artist, title, lyrics, url FROM lyrics_cache WHERE cache_key = :key LIMIT 1');
					$stmt->execute([':key' => $key]);
					$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
				}
				if (!$row && $path !== '') {
					$stmt = $db->prepare('SELECT artist, title, lyrics, url FROM lyrics_cache WHERE path = :path LIMIT 1');
					$stmt->execute([':path' => $path]);
					$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
				}
			} else if ($db instanceof SQLite3) {
				if ($key !== '|') {
					$stmt = $db->prepare('SELECT artist, title, lyrics, url FROM lyrics_cache WHERE cache_key = :key LIMIT 1');
					$stmt->bindValue(':key', $key, SQLITE3_TEXT);
					$result = $stmt->execute();
					$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
				}
				if (!$row && $path !== '') {
					$stmt = $db->prepare('SELECT artist, title, lyrics, url FROM lyrics_cache WHERE path = :path LIMIT 1');
					$stmt->bindValue(':path', $path, SQLITE3_TEXT);
					$result = $stmt->execute();
					$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
				}
			}
		} catch (Exception $e) {
			return null;
		}
		if (!$row) {
			return null;
		}
		if (($row['url'] ?? '') === 'blocked' || ($row['lyrics'] ?? '') === '__BLOCKED__') {
			$this->lyricsCacheDeleteRows($path, [$this->lyricsCacheKey($artist, $title)]);
			return null;
		}
		if (trim((string)($row['lyrics'] ?? '')) === '') {
			return null;
		}
		return [
			'success' => true,
			'lyrics' => $row['lyrics'],
			'title' => $row['title'] !== '' ? $row['title'] : $title,
			'artist' => $row['artist'] !== '' ? $row['artist'] : ($artist !== '' ? $artist : 'FrealPlayer'),
			'url' => $row['url'] ?? ''
		];
	}

	private function lyricsCacheSet($path, $response) {
		$db = $this->lyricsCacheDb();
		if (!$db || empty($response['lyrics'])) {
			return;
		}
		$key = $this->lyricsCacheKey($response['artist'] ?? '', $response['title'] ?? '');
		if ($key === '|') {
			return;
		}
		$values = [
			'cache_key' => $key,
			'path' => $path,
			'artist' => $response['artist'] ?? '',
			'title' => $response['title'] ?? '',
			'lyrics' => $response['lyrics'],
			'url' => $response['url'] ?? '',
			'created_at' => date('c')
		];
		try {
			$sql = 'INSERT OR REPLACE INTO lyrics_cache (cache_key, path, artist, title, lyrics, url, created_at)
				VALUES (:cache_key, :path, :artist, :title, :lyrics, :url, :created_at)';
			if ($db instanceof PDO) {
				$stmt = $db->prepare($sql);
				$stmt->execute($values);
			} else if ($db instanceof SQLite3) {
				$stmt = $db->prepare($sql);
				foreach ($values as $name => $value) {
					$stmt->bindValue(':' . $name, $value, SQLITE3_TEXT);
				}
				$stmt->execute();
			}
		} catch (Exception $e) {
		}
	}

	private function artistFromPath($path) {
		$parts = array_values(array_filter(explode('/', (string)$path), function($part) {
			return $part !== '';
		}));
		$musicIndex = array_search('music', $parts, true);
		if ($musicIndex !== false && isset($parts[$musicIndex + 1])) {
			return $parts[$musicIndex + 1];
		}
		return '';
	}

	private function isGenericArtist($artist) {
		$value = strtolower(trim((string)$artist));
		$value = preg_replace('/[^a-z0-9]+/', '', $value);
		return in_array($value, [
			'various',
			'variousartists',
			'va',
			'compilation',
			'compilations',
			'soundtrack',
			'soundtracks'
		], true);
	}

	private function titleFromPath($path) {
		return pathinfo((string)$path, PATHINFO_FILENAME);
	}

	private function cleanSongTitle($title) {
		$title = trim((string)$title);
		$title = preg_replace('/^\d+[\s.\-_]+/', '', $title);
		return trim((string)$title);
	}

	private function artistNamesMatch($a, $b) {
		$normalize = function($value) {
			$value = strtolower(trim((string)$value));
			$value = preg_replace('/^the\s+/', '', $value);
			$value = str_replace('various artists', 'various', $value);
			return preg_replace('/[^a-z0-9]+/', '', $value);
		};
		$left = $normalize($a);
		$right = $normalize($b);
		return $left !== '' && $right !== '' && ($left === $right || strpos($left, $right) !== false || strpos($right, $left) !== false);
	}

	private function pickGeniusHit($hits, $artist, $title) {
		$best = null;
		$bestScore = -1;
		foreach ($hits as $hit) {
			if (($hit['type'] ?? '') !== 'song') {
				continue;
			}
			$result = $hit['result'] ?? [];
			$hitTitle = $result['title'] ?? '';
			$hitArtist = $result['primary_artist']['name'] ?? '';
			$score = 0;
			if ($artist !== '' && $this->artistNamesMatch($artist, $hitArtist)) {
				$score += 5;
			} else if ($artist === '' && $hitArtist !== '' && stripos($title, $hitArtist) !== false) {
				$score += 5;
			}
			if (strcasecmp($hitTitle, $title) === 0) {
				$score += 4;
			} else if ($title !== '' && (stripos($hitTitle, $title) !== false || stripos($title, $hitTitle) !== false)) {
				$score += 2;
			}
			if ($score > $bestScore) {
				$bestScore = $score;
				$best = $result;
			}
		}
		return $best;
	}

	private function extractGeniusLyrics($html) {
		$html = (string)$html;
		if ($html === '') {
			return '';
		}
		$html = preg_replace('/<br\s*\/?>/i', "\n", $html);
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		$xpath = new DOMXPath($dom);
		foreach ($xpath->query('//*[@data-exclude-from-selection="true"]') as $node) {
			if ($node->parentNode) {
				$node->parentNode->removeChild($node);
			}
		}
		$nodes = $xpath->query('//*[@data-lyrics-container="true"]');
		if (!$nodes || $nodes->length === 0) {
			$nodes = $xpath->query('//*[contains(@class, "Lyrics__Container")]');
		}
		$parts = [];
		if ($nodes) {
			foreach ($nodes as $node) {
				$text = html_entity_decode($node->textContent, ENT_QUOTES, 'UTF-8');
				$text = preg_replace("/[ \t]+/", ' ', $text);
				$text = preg_replace("/\n{3,}/", "\n\n", trim($text));
				if ($text !== '') {
					$parts[] = $text;
				}
			}
		}
		return trim(implode("\n\n", $parts));
	}

	private function httpGet($url, $headers = []) {
		$defaultHeaders = [
			'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
		];
		$allHeaders = array_merge($defaultHeaders, $headers);
		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_TIMEOUT => 20,
				CURLOPT_HTTPHEADER => $allHeaders
			]);
			$body = curl_exec($ch);
			$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($body === false || $code >= 400) {
				return '';
			}
			return $body;
		}
		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => implode("\r\n", $allHeaders),
				'timeout' => 20,
				'follow_location' => 1
			]
		]);
		$body = @file_get_contents($url, false, $context);
		return $body === false ? '' : $body;
	}

	private function pathToUser($in) {
		$from = ['/hdd3/music/', '/'];
		$to = ['', ' - '];
		$out = str_replace($from, $to, $in);
		return $out;
	}

	private function lyricsButtonHtml($path, $name) {
		return '<button type="button" class="lyricsButton" data-path="' . $this->h($path) . '" data-name="' . $this->h($name) . '" aria-label="Show lyrics">L</button>';
	}

	private function h($in) {
		return htmlspecialchars((string)$in, ENT_QUOTES, 'UTF-8');
	}
	
}
