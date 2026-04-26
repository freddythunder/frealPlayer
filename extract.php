<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// test Second Coming
// https://www.youtube.com/watch?v=vWCuXy7RVZc&list=PLHTo__bpnlYX4u_3tkQjDJn4ueOST5JLT&index=2
// thumbnail
// https://i.ytimg.com/vi/7nu5YLoaxHc/hqdefault.jpg

function studlyCase($in, $allowSpaces = true) {
	$pattern = $allowSpaces ? "/[^a-zA-Z0-9\s]/" : "/[^a-zA-Z0-9]/";
	return preg_replace($pattern, "", ucwords($in));
}

// if ($_SERVER['REQUEST_METHOD'] == "POST") {
	if ($_REQUEST['cmd'] ?? null) {
		$cmd = $_REQUEST['cmd'];
		$response = [];
		$response['success'] = true;
		$data = [];
		if ($cmd == 'getConfirmInfo') {
			// go get youtube html and read the title
			parse_str(substr($_REQUEST['url'], strpos($_REQUEST['url'], '?')+1), $vars);
			$playlist = ($vars['list'] ?? false);
			$data['playlist'] = $playlist;
			$band = ($_REQUEST['band'] ?? false);
			// check to see if band folder exists
			$data['band'] = $band;
			$data['bandpath'] = '/hdd3/music/' . studlyCase($band);
			$data['bandexists'] = file_exists($data['bandpath']);
			$album = ($_REQUEST['album'] ?? 'Unknown Album');
			$data['album'] = $album;
			$data['albumpath'] = '/hdd3/music/' . studlyCase($band) . '/' . studlyCase($album);
			$data['albumexists'] = file_exists($data['albumpath']);
			// check to see if album folder exists
			$song = ($vars['v'] ?? false);
			if ($song) {
				$data['song'] = $song;
				// try to get the image
				$url = 'https://i.ytimg.com/vi/' . $song . '/hqdefault.jpg';
				$image = file_get_contents($url);
				$filename = 'tmp/' . studlyCase($album, false) . '.jpg';
				file_put_contents($filename, $image);
				// turn image into square
				// convert in.png -gravity Center -extent 1:1 out.png
				$c = 'convert ' . $filename . ' -gravity Center -extent 1:1 ' . $filename;
				exec(escapeshellcmd($c));
				$data['img'] = $filename;
			}
			$response['data'] = $data;
			
		}
		if ($cmd == 'getPlaylist' || $cmd == 'getSong') {
			$albumpath = preg_replace("/[^a-zA-Z0-9\s\/]/", "", $_REQUEST['albumpath']);
			// create location for songs to live
			error_log('creating directory ' . $albumpath);
			mkdir($albumpath, 0775, true) or error_log('cannot create directory');
			error_log('going into directory ' . $albumpath);
			chdir($albumpath) or error_log('cannot enter directory');
			parse_str(substr($_REQUEST['url'], strpos($_REQUEST['url'], '?')+1), $vars);
			$playlist = ($vars['list'] ?? false);
			$song = ($vars['v'] ?? false);
			if ($cmd == 'getPlaylist') {
				// yt-dlp -x --audio-quality 0 --audio-format mp3 -o "%(playlist_index)03d.%(title)s.%(ext)s" -i $1
				$c = "yt-dlp -x --audio-quality 0 --audio-format mp3 -o \"%(playlist_index)03d.%(title)s.%(ext)s\" -i '$playlist' &";
				error_log('trying to get playlist with "' . $c . '"');
			} else {
				// yt-dlp -x --audio-quality 0 --audio-format mp3 -o "%(title)s.%(ext)s" $1
				$c = "yt-dlp -x --audio-quality 0 --audio-format mp3 -o \"%(title)s.%(ext)s\" '$song' &";
				error_log('trying to get song with "' . $c . '"');
			}
			// TODO how do I do this without escaping?
			exec($c);
			// exec(escapeshellcmd($c));
			error_log('background process run?');
			$thumb = str_replace(" ", "", preg_replace("/(.*)\//", "", $albumpath)) . ".jpg";
			$fromimage = '/hdd/repo/ftapps/frealPlayer/tmp/' . $thumb;
			$toimage = $albumpath . '/' . $thumb;
			error_log('moving image from ' . $fromimage . ' to ' . $toimage);
			rename($fromimage, $toimage) or error_log('cannot move image');
			
		}
		
	
		echo json_encode($response);
		die();
	}
// }
?>
<html>
<head>
	<title>Extract Songs or Playlist into Player</title>
	<meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	
<style>
	.displayNone 
	{
		display: none;
	}
	.red {
		color: #C00;
	}
	.green {
		color: #0C0;
	}
</style>
</head>
<body>
	<div class="container">
		<h2>Extract from YouTube</h2>
		<input type="text" class="form-control" id="url" placeholder="Enter in YouTube URL">
		<input type="text" class="form-control mt-2" id="band" placeholder="Enter Band Name">
		<input type="text" class="form-control mt-2" id="album" placeholder="Enter Album Name">
		<input type="hidden" id="albumpath" value="">
		<button type="button" class="btn btn-primary mt-2" id="startButton">Start</button>
		<hr>
		<div class="confirmInfoWrapper displayNone">
			<div class="d-flex">
				<div class="p-2" id="confirmImage"></div>
				<div class="p-2 flex-fill" id="confirmInfo"></div>
			</div>
		</div>
	</div>
	
	<script>
	$(document).on('click', '#getPlaylist', function(e) {
		$.ajax({
			url: 'extract.php',
			dataType: 'json',
			method: 'POST',
			data: {
				cmd: 'getPlaylist',
				url: $('#url').val(),
				band: $('#band').val(),
				album: $('#album').val(),
				albumpath: $('#albumpath').val(),
				image: $('#albumimage').attr('src')
			},
			success: function(msg) {
				alert('playlist process started');
			}
		});
	});
	
	$(document).on('click', '#getSonglist', function(e) {
		if (!$('#albumpath').val()) {
			alert('no album path');
			return;
		}
		$.ajax({
			url: 'extract.php',
			dataType: 'json',
			method: 'POST',
			data: {
				cmd: 'getSong',
				url: $('#url').val(),
				band: $('#band').val(),
				album: $('#album').val(),
				albumpath: $('#albumpath').val(),
				image: $('#albumimage').attr('src')
			},
			success: function(msg) {
				
			}
		});
	});
	
	$('#startButton').on('click', function(e) {
		// url entered
		if (!$('#url').val()) {
			alert('You must enter a link here');
			return;
		}
		// get and display type, title and thumbnail
		$.ajax({
			url: 'extract.php',
			dataType: 'json',
			method: 'POST',
			data: {
				cmd: 'getConfirmInfo',
				url: $('#url').val(),
				band: $('#band').val(),
				album: $('#album').val()
			},
			success: function(msg) {
				if (msg.success) {
					console.log(msg);
					let data = msg.data;
					$('#band').val(data.band);
					$('#album').val(data.album);
					$('#albumpath').val(data.albumpath);
					
					let html = 'The path to put the new music will be ' + data.albumpath;
					html += '<br><strong>' + data.bandpath + '</strong> ';
					html += data.bandexists ? '<span class="green">exists</span>' : '<span class="red">will be created</span>';
					html += '<br><strong>' + data.albumpath + '</strong> ';
					html += data.albumexists ? '<span class="green">exists (check to make sure)</span>' : '<span class="red">will be created</span>';
					if (data.img) {
						$('#confirmImage').html('<img id="albumimage" src="' + data.img + '">');
					}
					if (data.playlist) {
						html += '<br><button type="button" class="btn btn-primary mt-2" id="getPlaylist">Get Entire Playlist</button>';
					}
					html += '<br><button type="button" class="btn btn-primary mt-2" id="getSong">Get Song</button>';
					$('#confirmInfo').html(html);
					$('.confirmInfoWrapper').removeClass('displayNone');
				}
			}
		});
	});
	
	
	</script>
	




</body>
</html>


