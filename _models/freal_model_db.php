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
	
	public function getFileAudioList($path) {
		$results = [];
		$i = 0;
		$path = rtrim($path, '/');
		$files = glob($path . '/*.{flac,mp3,ogg,wav}', GLOB_BRACE);

		foreach ($files as $file) {
			$path = str_replace("/hdd/repo/", "/repo/", $path);
			$results[] = [
				'id' => ++$i,
				'source' => $path . '/'	. $file,
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
		
		$dirs = glob($path . '/*', GLOB_ONLYDIR);
		foreach ($dirs as $file) {
			$results[] = [
				'id' => ++$i,
				'source' => $path . '/'	. $file,
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