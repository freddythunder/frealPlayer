<?php

session_start();
class Freal
{
	private $bs;
	private $dbi;
	
	private $stockAudioModel;
	private $songList;
	private $groupData;
	private $dirs;
	
	private $playlist;
	protected $isPlaylist;
	protected $firstRun;
	protected $browseHTML;
	
	public function __construct()
	{
		// get bootstrap
		require_once('_copy/bootstrap.php');
		require_once('_models/freal_model_playlist.php');
		// echo "<pre>"; print_r($_REQUEST); die();
		// connect to database
		$this->bs = new Bootstrap();
		$this->dbi = $this->bs->dbi;
		// get stuff from database
		$this->stockAudioModel = new StockAudio($this->dbi);
		$this->playlist = new Playlist($this->dbi);
		$this->isPlaylist = false;
		
		if (isset($_REQUEST['motomode'])) {
			if ($_SESSION['motomode'] ?? null) {
				$_SESSION['motomode'] = false;
			} else {
				$_SESSION['motomode'] = true;
			}
		}
		
		// for rating songs:
		if (isset($_REQUEST['cmd']) && $_REQUEST['cmd'] == 'rateSong') {
			$this->rateSong();
			return;
		}
		
		if (isset($_REQUEST['cmd']) && $_REQUEST['cmd'] == 'saveNotes') {
			$this->setNotes();
			return;
		}
		
		$this->groupData = $this->stockAudioModel->getGroupData();

		$this->dirs = [];
// 		$sting = [];
// 		exec('find /hdd3/music -type d', $directories);
// 		foreach ($directories as $directory) {
// 			$files = glob($directory . '/*.{ogg,mp3,flac}', GLOB_BRACE);
// 			if (count($files) && stripos($directory, 'edm') === false) {
// 				if (stripos($directory, 'sting') !== false) {
// 					$sting[] = $directory;
// 				} else {
// 					$this->dirs[] = $directory;
// 				}
// 			}
// 		}
// 		
// 		sort($this->dirs);
// 		$this->dirs[] = '-------------';
// 		$this->dirs = array_merge($this->dirs, $sting);

		$this->firstRun = false;		
		if (isset($_REQUEST['filepath']) && $_REQUEST['filepath']) {
			$this->songList = $this->stockAudioModel->getFileAudioList($_REQUEST['filepath']);
	
		} else if (isset($_REQUEST['dirs']) && $_REQUEST['dirs']) {
			$this->songList = $this->stockAudioModel->getFileAudioList($_REQUEST['dirs']);
		} else if ($_REQUEST['playlist'] ?? null) {
			$this->songList = [];
			$playlistSongs = $this->playlist->getPlaylistByName($_REQUEST['playlist']);
			$id = 0;
			foreach ($playlistSongs as $playlistSong) {
				$this->songList[] = [
					'id' => ++$id,
					'source' => $playlistSong['path'],
					'path' => $playlistSong['path'],
					'name' => $playlistSong['name'],
					'type' => 'song'
				];
			}
			$this->isPlaylist = true;
			
		} else {

			$source = isset($_REQUEST['source']) ? $_REQUEST['source'] : "incompetech";
			$name = isset($_REQUEST['srch']) ? $_REQUEST['srch'] : '';
			$genre = isset($_REQUEST['genre']) ? $_REQUEST['genre'] : ''; 
			// change this later if it ever goes to a real server...
			$rating = isset($_REQUEST['rating']) ? $_REQUEST['rating'] : '';
			$order = 'album, track, name';
			$this->songList = $this->stockAudioModel->getAudioList($source, $name, $genre, $rating, $order);
			$this->firstRun = true;

			// gather browse by band
			$this->browseHTML = '';
			exec('find /hdd3/music -type d -maxdepth 1', $directories);
			foreach ($directories as $dir) {
				if (in_array($dir, ['/hdd3/music'])) {
					continue;
				}
				$cmd = 'find "' . $dir . '" -type f -iname "*.jpg" | head -n 1';
				$result = [];
				exec($cmd, $result); // or die (chr(10) . 'no exec ' . $cmd);
				if (count($result) && filesize($result[0]) > 0) {
					$this->browseHTML .= '<div class="browse_wrapper" data-dir="' . $dir . '">';
					$img = '<img src="' . $result[0] . '" class="browse_thumb">';
					$this->browseHTML .= $img . '<br><small>' . basename($dir) . '</small></div>';
					
				}
			}
			$this->browseHTML .= '<br clear="all">';
		}

		require_once('_views/freal_view_index.php');
		
		
	}

	public function rateSong() 
	{
		$result = $this->stockAudioModel->setSongRate($_REQUEST);
		$response = ['msg' => $result];
		echo json_encode($response);
	}
	
	public function setNotes()
	{
		$this->stockAudioModel->setNotes($_REQUEST);
		$response = ['msg' => $result];
		echo json_encode($response);
	}


}
