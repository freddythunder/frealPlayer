<?php
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
		
		if (method_exists($this, $_REQUEST['cmd'])) {
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
		$srch = preg_replace("[^a-zA-Z0-9\s]", "", $_REQUEST['srch']);
		$bands = [];
		$songs = [];
		$playlists = [];
		$final = [];
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
		
		$html = '<div class="searchResultContainer">';
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
				$html .= '<div class="searchSong dopost" data-song="' . $song . '">' . basename($song);
				$html .= '<div class="tiny">' . $this->pathToUser($song) . '</div>';
				$html .= '</div>';
			}
		}
		
		
		
		$html .= '</div>';
		
		echo json_encode(['success' => true, 'html' => $html]);
		die();
	}
	
	private function pathToUser($in) {
		$from = ['/hdd3/music/', '/'];
		$to = ['', ' - '];
		$out = str_replace($from, $to, $in);
		return $out;
	}
	
}
