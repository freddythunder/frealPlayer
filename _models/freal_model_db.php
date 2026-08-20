<?php
class StockAudio
{
	private $db;
	
	public function __construct($db)
	{
		$this->db = $db;
	}
	
	public function getAudioList($source="", $name="", $genre="", $rating="", $order="name")
	{
		$query = "SELECT * FROM stock_audio ";
		$where = [];
		if($source){
			$where[] = "source='".$this->clean($source)."'";
		}
		if($name){
			$where[] = "(name like '%".$this->clean($name)."%' || notes like '%" . $this->clean($name) . "%')";
		}
		if($genre){
			$where[] = "genre='".$this->clean($genre)."'";
		}
		if($rating){
			$where[] = "rating=" . $this->clean($rating);
		}

		if(count($where)){
			$where = "WHERE ".implode(" && ", $where);
			$query .= $where;
		}

		$query .= " ORDER BY ".$this->clean($order);

		try {
			$stmt = $this->db->query($query) or die ('no query '. $this->db->error. chr(10).$query);
			$results = [];
			while($row = $stmt->fetch_assoc()){ 
				$results[] = $row;
			}
			return $results;
		} catch(Exception $e){
			die($e->getMessage() . '<br>' . $query);
		}
		
	}

	public function searchStockAudio($srch, $limit = 100)
	{
		$srch = trim((string)$srch);
		if ($srch === '') {
			return [];
		}
		$term = $this->clean($srch);
		$limit = max(1, min(500, (int)$limit));
		$query = "SELECT id, source, path, name, genre, notes
			FROM stock_audio
			WHERE name LIKE '%{$term}%'
				OR notes LIKE '%{$term}%'
				OR genre LIKE '%{$term}%'
				OR source LIKE '%{$term}%'
				OR path LIKE '%{$term}%'
			ORDER BY name
			LIMIT {$limit}";
		try {
			$stmt = $this->db->query($query) or die('no query '.$this->db->error.chr(10).$query);
			$results = [];
			while ($row = $stmt->fetch_assoc()) {
				$row['path'] = rawurldecode((string)($row['path'] ?? ''));
				$results[] = $row;
			}
			return $results;
		} catch (Exception $e) {
			die($e->getMessage() . '<br>' . $query);
		}
	}

	public function getAudioById($id)
	{
		$id = (int)$id;
		if ($id < 1) {
			return [];
		}
		$query = "SELECT * FROM stock_audio WHERE id={$id} LIMIT 1";
		try {
			$stmt = $this->db->query($query) or die('no query '.$this->db->error.chr(10).$query);
			$row = $stmt->fetch_assoc();
			if (!$row) {
				return [];
			}
			$row['path'] = rawurldecode((string)($row['path'] ?? ''));
			$row['type'] = 'song';
			return [$row];
		} catch (Exception $e) {
			die($e->getMessage() . '<br>' . $query);
		}
	}
	
	public function getFileAudioList($path) {
		$results = [];
		$i = 0;
		$path = rawurldecode(trim((string)$path));
		if ($path === '') {
			return $results;
		}
		$path = rtrim($path, '/');
		$isFileRequest = is_file($path);
		$files = $isFileRequest ? [$path] : glob($path . '/*.{flac,mp3,ogg,wav}', GLOB_BRACE);

		foreach ($files as $file) {
			$results[] = [
				'id' => ++$i,
				'source' => str_replace("/hdd/repo/", "/repo/", $file),
				'link' => '',
				'path' => str_replace("'", "%27", $file),
				'track' => '',
				'name' => str_replace('.'.pathinfo($file, PATHINFO_EXTENSION), '', basename($file)),
				'artist' => '',
				'album' => '',
				'produced' => '',
				'tempo' => '',
				'genre' => '',
				'length' => '',
				'acquired' => '',
				'notes' => '',
				'rating' => '',
				'updated' => '',
				'type' => 'song'
			];
		}
		
		$dirs = $isFileRequest ? [] : glob($path . '/*', GLOB_ONLYDIR);
		foreach ($dirs as $file) {
			$results[] = [
				'id' => ++$i,
				'source' => str_replace("/hdd/repo/", "/repo/", $file),
				'link' => '',
				'path' => str_replace("'", "%27", $file),
				'track' => '',
				'name' => str_replace('.'.pathinfo($file, PATHINFO_EXTENSION), '', basename($file)),
				'artist' => '',
				'album' => '',
				'produced' => '',
				'tempo' => '',
				'genre' => '',
				'length' => '',
				'acquired' => '',
				'notes' => '',
				'rating' => '',
				'updated' => '',
				'type' => 'directory'
			];
		}
		
		return $results;
	}
	
	public function setSongRate($data)
	{
		$query = "update stock_audio set rating=" . $this->clean($data['rate']) . " 
					where id=" . $this->clean($data['id']) . " limit 1";
		if ($stmt = $this->db->query($query)) {
			return true;
		} else {
			return $this->db->error . chr(10) . $query;
		}
	}
	
	public function setNotes($data)
	{
		$query = "update stock_audio set notes='" . $this->clean($data['notes']) . "' 
			where id=" . $this->clean($data['id']) . " limit 1";
		if ($stmt = $this->db->query($query)) {
			return true;
		} else {
			return $this->db->error . chr(10) . $query;
		}
	}
	
	public function getGroupData()
	{
		$query = "select group_concat(DISTINCT(source) order by source) as sources, 
			group_concat(DISTINCT(genre) order by genre) as genres from stock_audio";
		try {
			$stmt = $this->db->query($query) or die ('no query '. $this->db->error. chr(10).$query);
			$results = $stmt->fetch_assoc();
			return $results;
		} catch(Exception $e){
			die($e->getMessage());
		}
	}
	
	private function clean($in)
	{
		return $this->db->real_escape_string($in);
	}

}