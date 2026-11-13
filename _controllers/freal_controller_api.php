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
		require_once('_models/freal_model_history.php');
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

	private function logPlay() {
		$history = new PlayHistory();
		$ok = $history->log(
			$_REQUEST['path'] ?? '',
			$this->clientIp(),
			$_SERVER['HTTP_USER_AGENT'] ?? ''
		);
		echo json_encode(['success' => $ok]);
	}

	private function clientIp() {
		$forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
		if ($forwarded !== '') {
			$first = trim(explode(',', $forwarded)[0]);
			if (filter_var($first, FILTER_VALIDATE_IP)) {
				return $first;
			}
		}
		$remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
		return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
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
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
		$path = rawurldecode(trim((string)($_REQUEST['path'] ?? '')));
		$art = $this->findAlbumArt($path);
		$embedded = null;
		if ($art === '') {
			$embedded = $this->readEmbeddedArt($path);
		}
		$raw = isset($_REQUEST['raw']) && (string)$_REQUEST['raw'] === '1';
		if ($raw) {
			if ($art !== '' && is_file($art)) {
				$this->sendImageFile($art);
				return;
			}
			if ($embedded) {
				$this->sendImageBytes($embedded['mime'], $embedded['data']);
				return;
			}
			http_response_code(404);
			return;
		}
		echo json_encode([
			'success' => $art !== '' || $embedded !== null,
			'path' => $art
		]);
	}

	private function sendImageFile($file) {
		$this->sendImageHeaders($this->imageContentType($file), filesize($file));
		readfile($file);
	}

	private function sendImageBytes($mime, $data) {
		$this->sendImageHeaders($mime, strlen($data));
		echo $data;
	}

	private function sendImageHeaders($mime, $length) {
		header('Content-Type: ' . $mime);
		header('Content-Length: ' . $length);
		header('Cache-Control: public, max-age=86400');
		header('Access-Control-Allow-Origin: *');
	}

	private function imageContentType($file) {
		$ext = strtolower(pathinfo((string)$file, PATHINFO_EXTENSION));
		if ($ext === 'png') {
			return 'image/png';
		}
		if ($ext === 'gif') {
			return 'image/gif';
		}
		if ($ext === 'webp') {
			return 'image/webp';
		}
		return 'image/jpeg';
	}

	private function localMediaPath($songPath) {
		$path = (string)$songPath;
		if (strpos($path, '/repo/') === 0) {
			$path = '/hdd' . $path;
		}
		return $path;
	}

	private function allowedMediaRoots() {
		$roots = [];
		foreach (['/hdd3/music', '/hdd/repo'] as $root) {
			$real = realpath($root);
			if ($real !== false) {
				$roots[] = $real;
			}
		}
		return $roots;
	}

	private function pathIsAllowed($real) {
		if ($real === false || $real === '') {
			return false;
		}
		foreach ($this->allowedMediaRoots() as $root) {
			if ($real === $root || strpos($real, $root . DIRECTORY_SEPARATOR) === 0) {
				return true;
			}
		}
		return false;
	}

	private function findAlbumArt($songPath) {
		$path = $this->localMediaPath($songPath);
		if ($path === '') {
			return '';
		}
		if (is_file($path) || preg_match('/\.(mp3|flac|ogg|wav)$/i', $path)) {
			$start = dirname($path);
		} else {
			$start = $path;
		}
		$dir = realpath($start);
		if (!$this->pathIsAllowed($dir)) {
			return '';
		}
		$roots = $this->allowedMediaRoots();
		while ($this->pathIsAllowed($dir)) {
			if (!in_array($dir, $roots, true)) {
				$image = $this->firstImageInDir($dir);
				if ($image !== '') {
					return $image;
				}
			}
			if (in_array($dir, $roots, true)) {
				break;
			}
			$parent = dirname($dir);
			if ($parent === $dir) {
				break;
			}
			$dir = $parent;
		}
		return '';
	}

	private function firstImageInDir($dir) {
		$entries = @scandir($dir);
		if (!is_array($entries)) {
			return '';
		}
		foreach ($entries as $entry) {
			if ($entry === '' || $entry[0] === '.') {
				continue;
			}
			if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $entry)) {
				continue;
			}
			$file = $dir . '/' . $entry;
			if (is_file($file) && filesize($file) > 0) {
				return $file;
			}
		}
		return '';
	}

	private function readEmbeddedArt($songPath) {
		$path = $this->localMediaPath($songPath);
		$real = realpath($path);
		if (!$this->pathIsAllowed($real) || !is_file($real)) {
			return null;
		}
		$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
		if ($ext === 'flac') {
			return $this->readFlacPicture($real);
		}
		if ($ext === 'mp3') {
			return $this->readMp3Picture($real);
		}
		return null;
	}

	private function readFlacPicture($path) {
		$fh = fopen($path, 'rb');
		if (!$fh) {
			return null;
		}
		if (fread($fh, 4) !== 'fLaC') {
			fclose($fh);
			return null;
		}
		$picture = null;
		while (!feof($fh)) {
			$hdr = fread($fh, 4);
			if (strlen($hdr) < 4) {
				break;
			}
			$info = ord($hdr[0]);
			$last = ($info & 0x80) !== 0;
			$type = $info & 0x7F;
			$len = (ord($hdr[1]) << 16) | (ord($hdr[2]) << 8) | ord($hdr[3]);
			if ($len < 0 || $len > 8000000) {
				break;
			}
			if ($type === 6 && $len > 0) {
				$block = fread($fh, $len);
				$parsed = $this->parseFlacPictureBlock($block);
				if ($parsed && ($picture === null || (int)$parsed['type'] === 3)) {
					$picture = $parsed;
				}
				if ($picture && (int)$picture['type'] === 3) {
					break;
				}
			} else if ($len > 0 && fseek($fh, $len, SEEK_CUR) !== 0) {
				break;
			}
			if ($last) {
				break;
			}
		}
		fclose($fh);
		return $picture;
	}

	private function parseFlacPictureBlock($block) {
		$length = strlen($block);
		if ($length < 32) {
			return null;
		}
		$offset = 0;
		$type = $this->readBe32($block, $offset);
		$offset += 4;
		$mimeLen = $this->readBe32($block, $offset);
		$offset += 4;
		if ($mimeLen < 0 || $mimeLen > 128 || $offset + $mimeLen > $length) {
			return null;
		}
		$mime = substr($block, $offset, $mimeLen);
		$offset += $mimeLen;
		$descLen = $this->readBe32($block, $offset);
		$offset += 4;
		if ($descLen < 0 || $offset + $descLen + 20 > $length) {
			return null;
		}
		$offset += $descLen + 16;
		$dataLen = $this->readBe32($block, $offset);
		$offset += 4;
		if ($dataLen <= 0 || $offset + $dataLen > $length) {
			return null;
		}
		$data = substr($block, $offset, $dataLen);
		$mime = $this->normalizeImageMime($mime, $data);
		if ($mime === '') {
			return null;
		}
		return [
			'mime' => $mime,
			'data' => $data,
			'type' => $type
		];
	}

	private function readMp3Picture($path) {
		$fh = fopen($path, 'rb');
		if (!$fh) {
			return null;
		}
		$head = fread($fh, 10);
		if (strlen($head) < 10 || substr($head, 0, 3) !== 'ID3') {
			fclose($fh);
			return null;
		}
		$version = ord($head[3]);
		if ((ord($head[5]) & 0x80) !== 0) {
			fclose($fh);
			return null;
		}
		$end = 10 + $this->readSynchsafe($head, 6);
		$picture = null;
		if ($version === 2) {
			while (ftell($fh) + 6 <= $end) {
				$id = fread($fh, 3);
				$sizeBytes = fread($fh, 3);
				if (strlen($id) < 3 || $id === "\0\0\0") {
					break;
				}
				$frameSize = (ord($sizeBytes[0]) << 16) | (ord($sizeBytes[1]) << 8) | ord($sizeBytes[2]);
				if ($frameSize <= 0 || ftell($fh) + $frameSize > $end) {
					break;
				}
				if ($id === 'PIC') {
					$picture = $this->parsePicFrame(fread($fh, $frameSize));
					break;
				}
				fseek($fh, $frameSize, SEEK_CUR);
			}
		} else if ($version === 3 || $version === 4) {
			while (ftell($fh) + 10 <= $end) {
				$id = fread($fh, 4);
				$sizeBytes = fread($fh, 4);
				fread($fh, 2);
				if (strlen($id) < 4 || $id === "\0\0\0\0") {
					break;
				}
				$frameSize = $version === 4 ? $this->readSynchsafe($sizeBytes, 0) : $this->readBe32($sizeBytes, 0);
				if ($frameSize <= 0 || ftell($fh) + $frameSize > $end) {
					break;
				}
				if ($id === 'APIC') {
					$parsed = $this->parseApicFrame(fread($fh, $frameSize));
					if ($parsed && ($picture === null || (int)$parsed['type'] === 3)) {
						$picture = $parsed;
					}
					if ($picture && (int)$picture['type'] === 3) {
						break;
					}
					continue;
				}
				fseek($fh, $frameSize, SEEK_CUR);
			}
		}
		fclose($fh);
		return $picture;
	}

	private function parseApicFrame($frame) {
		$length = strlen($frame);
		if ($length < 4) {
			return null;
		}
		$encoding = ord($frame[0]);
		$mimeEnd = strpos($frame, "\0", 1);
		if ($mimeEnd === false) {
			return null;
		}
		$mime = substr($frame, 1, $mimeEnd - 1);
		$offset = $mimeEnd + 1;
		if ($offset >= $length) {
			return null;
		}
		$type = ord($frame[$offset]);
		$offset++;
		if ($encoding === 1 || $encoding === 2) {
			$term = $this->utf16Terminator($frame, $offset);
			if ($term === false) {
				return null;
			}
			$offset = $term + 2;
		} else {
			$term = strpos($frame, "\0", $offset);
			if ($term === false) {
				return null;
			}
			$offset = $term + 1;
		}
		if ($offset >= $length) {
			return null;
		}
		$data = substr($frame, $offset);
		$mime = $this->normalizeImageMime($mime, $data);
		if ($mime === '') {
			return null;
		}
		return [
			'mime' => $mime,
			'data' => $data,
			'type' => $type
		];
	}

	private function parsePicFrame($frame) {
		$length = strlen($frame);
		if ($length < 6) {
			return null;
		}
		$encoding = ord($frame[0]);
		$format = strtoupper(substr($frame, 1, 3));
		$type = ord($frame[4]);
		$offset = 5;
		if ($encoding === 1 || $encoding === 2) {
			$term = $this->utf16Terminator($frame, $offset);
			if ($term === false) {
				return null;
			}
			$offset = $term + 2;
		} else {
			$term = strpos($frame, "\0", $offset);
			if ($term === false) {
				return null;
			}
			$offset = $term + 1;
		}
		if ($offset >= $length) {
			return null;
		}
		$data = substr($frame, $offset);
		$mime = $format === 'PNG' ? 'image/png' : 'image/jpeg';
		$mime = $this->normalizeImageMime($mime, $data);
		if ($mime === '') {
			return null;
		}
		return [
			'mime' => $mime,
			'data' => $data,
			'type' => $type
		];
	}

	private function utf16Terminator($frame, $offset) {
		$term = strpos($frame, "\0\0", $offset);
		if ($term === false) {
			return false;
		}
		if ((($term - $offset) % 2) === 1) {
			$term = strpos($frame, "\0\0", $term + 1);
		}
		return $term;
	}

	private function normalizeImageMime($mime, $data) {
		$mime = strtolower(trim((string)$mime));
		if ($mime === 'image/jpg') {
			$mime = 'image/jpeg';
		}
		if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
			$mime = $this->sniffImageMime($data);
		}
		if ($mime === '' || $this->sniffImageMime($data) === '') {
			return '';
		}
		return $mime;
	}

	private function sniffImageMime($data) {
		if (strncmp($data, "\xFF\xD8\xFF", 3) === 0) {
			return 'image/jpeg';
		}
		if (strncmp($data, "\x89PNG\r\n\x1a\n", 8) === 0) {
			return 'image/png';
		}
		if (strncmp($data, 'GIF8', 4) === 0) {
			return 'image/gif';
		}
		if (strlen($data) >= 12 && strncmp($data, 'RIFF', 4) === 0 && substr($data, 8, 4) === 'WEBP') {
			return 'image/webp';
		}
		return '';
	}

	private function readBe32($bytes, $offset) {
		return (ord($bytes[$offset]) << 24) | (ord($bytes[$offset + 1]) << 16) | (ord($bytes[$offset + 2]) << 8) | ord($bytes[$offset + 3]);
	}

	private function readSynchsafe($bytes, $offset) {
		return ((ord($bytes[$offset]) & 0x7F) << 21)
			| ((ord($bytes[$offset + 1]) & 0x7F) << 14)
			| ((ord($bytes[$offset + 2]) & 0x7F) << 7)
			| (ord($bytes[$offset + 3]) & 0x7F);
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
		$title = $this->stripArtistPrefix($title, $artist);
		$response['title'] = $title;
		$response['artist'] = $artist;
		$defaultQuery = $artist !== '' ? trim($artist . ' ' . $title) : $title;
		$requestedQuery = trim((string)($_REQUEST['query'] ?? ''));
		$query = $requestedQuery !== '' ? $requestedQuery : $defaultQuery;
		$response['query'] = $query;
		$revisedQuery = $this->normalizeQueryKey($query) !== $this->normalizeQueryKey($defaultQuery);

		$cacheOnly = !empty($_REQUEST['cacheOnly']);
		$cached = $revisedQuery
			? $this->lyricsCacheGetByQuery($query)
			: $this->lyricsCacheGet($path, $artist, $title);
		if ($cached) {
			$cached['query'] = $query;
			$cached['cached'] = true;
			echo json_encode($cached);
			return;
		}
		if ($cacheOnly) {
			$response['cached'] = false;
			echo json_encode($response);
			return;
		}

		$key = (string)($_SERVER['GENKEY'] ?? getenv('GENKEY') ?: '');
		if ($key === '') {
			$response['msg'] = 'Lyrics API is not configured.';
			echo json_encode($response);
			return;
		}
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
		$best = $revisedQuery ? $this->firstGeniusSongHit($hits) : $this->pickGeniusHit($hits, $artist, $title);
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

		$lyrics = $this->lookupLrclibLyrics($response['artist'], $response['title']);
		if ($lyrics === '') {
			$pageHtml = $this->httpGet($response['url']);
			$lyrics = $this->extractGeniusLyrics($pageHtml);
		}
		if ($lyrics === '') {
			$response['msg'] = 'Found the song, but could not load the lyrics.';
			echo json_encode($response);
			return;
		}

		$response['success'] = true;
		$response['lyrics'] = $lyrics;
		$response['query'] = $query;
		if ($revisedQuery) {
			$this->lyricsCacheDeleteRows($path, []);
			$this->lyricsCacheSet($path, $response, $query);
		} else {
			$this->lyricsCacheSet($path, $response);
		}
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

	private function lyricsCacheGetByQuery($query) {
		$key = 'q:' . $this->normalizeQueryKey($query);
		if ($key === 'q:') {
			return null;
		}
		return $this->lyricsCacheGet('', '', '', $key);
	}

	private function normalizeQueryKey($query) {
		$value = strtolower(trim((string)$query));
		return preg_replace('/[^a-z0-9]+/', '', $value);
	}

	private function firstGeniusSongHit($hits) {
		foreach ($hits as $hit) {
			if (($hit['type'] ?? '') === 'song' && !empty($hit['result'])) {
				return $hit['result'];
			}
		}
		return null;
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

	private function lyricsCacheGet($path, $artist, $title, $keyOverride = null) {
		$db = $this->lyricsCacheDb();
		if (!$db) {
			return null;
		}
		$key = $keyOverride !== null ? $keyOverride : $this->lyricsCacheKey($artist, $title);
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

	private function lyricsCacheSet($path, $response, $queryKey = null) {
		$db = $this->lyricsCacheDb();
		if (!$db || empty($response['lyrics'])) {
			return;
		}
		$key = $queryKey !== null
			? 'q:' . $this->normalizeQueryKey($queryKey)
			: $this->lyricsCacheKey($response['artist'] ?? '', $response['title'] ?? '');
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
		$title = rawurldecode(trim((string)$title));
		$previous = '';
		while ($title !== $previous) {
			$previous = $title;
			$title = preg_replace('/^\d+[\s.\-_]+/', '', $title);
			$title = trim((string)$title);
		}
		$title = $this->stripEncapsulatedExtras($title);
		$title = preg_replace('/\s+/', ' ', $title);
		return trim((string)$title, " \t-–—");
	}

	private function stripArtistPrefix($title, $artist) {
		$title = trim((string)$title);
		$artist = trim((string)$artist);
		if ($title === '' || $artist === '') {
			return $title;
		}
		$stripped = preg_replace('/^' . preg_quote($artist, '/') . '\s*[-–—]\s+/iu', '', $title);
		return trim((string)$stripped);
	}

	private function stripEncapsulatedExtras($title) {
		$patterns = [
			'/\([^()]*\)/u',
			'/\[[^\[\]]*\]/u',
			'/\{[^{}]*\}/u',
			'/（[^（）]*）/u',
			'/［[^［］]*］/u',
			'/【[^【】]*】/u',
		];
		$changed = true;
		while ($changed) {
			$changed = false;
			foreach ($patterns as $pattern) {
				if (!preg_match($pattern, $title)) {
					continue;
				}
				$stripped = trim(preg_replace('/\s+/', ' ', preg_replace($pattern, ' ', $title)));
				if ($stripped !== '') {
					$title = $stripped;
					$changed = true;
					continue;
				}
				if (preg_match('/^(?:\(|\[|\{|（|［|【)\s*(.*?)\s*(?:\)|\]|\}|）|］|】)$/u', $title, $matches)) {
					$title = trim($matches[1]);
					$changed = true;
				}
			}
		}
		return trim(preg_replace('/\s+/', ' ', $title));
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

	private function lookupLrclibLyrics($artist, $title) {
		$artist = trim((string)$artist);
		$title = $this->cleanSongTitle($title);
		if ($title === '') {
			return '';
		}
		$headers = [
			'Accept: application/json',
			'User-Agent: frealPlayer/1.0 (personal lyrics lookup)'
		];
		if ($artist !== '') {
			$directUrl = 'https://lrclib.net/api/get?artist_name=' . rawurlencode($artist)
				. '&track_name=' . rawurlencode($title);
			$direct = json_decode($this->httpGet($directUrl, $headers), true);
			$lyrics = $this->lrclibLyricsFromSong($direct, $artist, $title);
			if ($lyrics !== '') {
				return $lyrics;
			}
		}
		$query = trim($artist . ' ' . $title);
		$rows = json_decode($this->httpGet('https://lrclib.net/api/search?q=' . rawurlencode($query), $headers), true);
		if (!is_array($rows)) {
			return '';
		}
		$bestLyrics = '';
		$bestScore = 0;
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$score = $this->scoreLrclibRow($row, $artist, $title);
			$lyrics = $this->lrclibLyricsFromSong($row, $artist, $title, false);
			if ($lyrics === '' || $score < $bestScore) {
				continue;
			}
			if ($score > $bestScore || strlen($lyrics) > strlen($bestLyrics)) {
				$bestScore = $score;
				$bestLyrics = $lyrics;
			}
		}
		$minScore = $artist !== '' ? 8 : 6;
		return $bestScore >= $minScore ? $bestLyrics : '';
	}

	private function lrclibLyricsFromSong($song, $artist, $title, $requireMatch = true) {
		if (!is_array($song) || !empty($song['instrumental'])) {
			return '';
		}
		if ($requireMatch && $this->scoreLrclibRow($song, $artist, $title) < ($artist !== '' ? 8 : 6)) {
			return '';
		}
		$plain = trim((string)($song['plainLyrics'] ?? ''));
		if ($plain !== '') {
			return $plain;
		}
		$synced = trim((string)($song['syncedLyrics'] ?? ''));
		if ($synced === '') {
			return '';
		}
		$lines = preg_split("/\r\n|\n|\r/", $synced);
		$plainLines = [];
		foreach ($lines as $line) {
			$plainLines[] = preg_replace('/^\[\d{1,2}:\d{2}(?:\.\d{1,3})?\]\s*/', '', $line);
		}
		return trim(implode("\n", $plainLines));
	}

	private function scoreLrclibRow($row, $artist, $title) {
		$normalize = function($value) {
			$value = strtolower(trim((string)$value));
			$value = preg_replace('/^the\s+/', '', $value);
			return preg_replace('/[^a-z0-9]+/', '', $value);
		};
		$hitTitle = $normalize($row['trackName'] ?? '');
		$wantTitle = $normalize($title);
		$score = 0;
		$hitArtist = (string)($row['artistName'] ?? '');
		if ($artist !== '' && $this->artistNamesMatch($artist, $hitArtist)) {
			$score += 5;
		}
		if ($wantTitle !== '' && $hitTitle === $wantTitle) {
			$score += 6;
		} else if ($wantTitle !== '' && $hitTitle !== '' && (strpos($hitTitle, $wantTitle) !== false || strpos($wantTitle, $hitTitle) !== false)) {
			$score += 3;
		}
		return $score;
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
		$hasUserAgent = false;
		foreach ($headers as $header) {
			if (stripos($header, 'User-Agent:') === 0) {
				$hasUserAgent = true;
				break;
			}
		}
		$allHeaders = $hasUserAgent ? $headers : array_merge($defaultHeaders, $headers);
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
