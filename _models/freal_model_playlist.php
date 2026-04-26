<?php
class Playlist {

	private $db;
	
	public function __construct($db) {
		$this->db = $db;
	}
	
	public function getPlaylistByName($playlistName) {
		$query = "SELECT * FROM frealPlayer_playlists WHERE parentId in (
				SELECT id FROM frealPlayer_playlists WHERE name='" . $this->clean($playlistName) . "' 
				AND parentId=0) AND parentId>0";
		try {
			$stmt = $this->db->query($query) or die ('no query '. $this->db->error. chr(10).$query);
			$results = [];
			while($row = $stmt->fetch_assoc()){ 
				$results[] = $row;
			}
		} catch(Exception $e){
			error_log($e->getMessage());
		}
		return $results;
	}
	
	public function removeFromPlaylist($name) {
		$query = "DELETE FROM frealPlayer_playlists WHERE name='" . $this->clean($name) . "' LIMIT 1";
		try {
			$stmt = $this->db->query($query) or die ('no query '. $this->db->error. chr(10).$query);
		} catch(Exception $e){
			error_log($e->getMessage());
		}
	}
	
	public function searchPlaylist($srch) {
		$query = "SELECT * FROM frealPlayer_playlists WHERE name like '%" . $this->clean($srch) . "%' 
			AND parentId=0 AND deleted=0 ORDER BY name";
		try {
			$stmt = $this->db->query($query) or die ('no query '. $this->db->error. chr(10).$query);
			$results = [];
			while($row = $stmt->fetch_assoc()){ 
				$results[] = $row;
			}
		} catch(Exception $e){
			error_log($e->getMessage());
		}
		return $results;
	}
	
	public function savePlaylist($args=null) {
		if (trim($args['playlistName'])) {
			// entering new playlist name
			$query = "INSERT INTO frealPlayer_playlists 
				(parentId, name) VALUES (0,'" . $this->clean($args['playlistName']) . "')";
			try {
				$stmt = $this->db->query($query) or die ('no query '. $this->db->error. chr(10).$query);
				$playlistId = $this->db->insert_id;
			} catch(Exception $e){
				error_log($e->getMessage());
			}
		} else {
			$playlistId = $args['playlistSelect'];
		}
		$query = "INSERT INTO frealPlayer_playlists (parentId, name, path) VALUES 
				(" . $playlistId . ", '" . $this->clean($args['name']) . "','" . $args['path'] . "')";
		try {
			$stmt = $this->db->query($query) or die ('no query '. $this->db->error. chr(10).$query);
		} catch(Exception $e){
			error_log($e->getMessage());
		}
		return ['success' => true];
		
		
	}
	
	public function getPlaylists() {
		$query = "SELECT * FROM frealPlayer_playlists WHERE parentId=0 AND deleted=0 ORDER BY name";
		try {
			$stmt = $this->db->query($query) or die ('no query '. $this->db->error. chr(10).$query);
			$results = [];
			while($row = $stmt->fetch_assoc()){ 
				$results[] = $row;
			}
		} catch(Exception $e){
			error_log($e->getMessage());
		}
		return [
			'success' => true,
			'playlists' => $results
		];
		
		
	}
	
	private function clean($in)
	{
		return $this->db->real_escape_string($in);
	}
	


}

