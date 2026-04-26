<?php

$root = "/hdd3/music/";

$ch = "find $root -type d";
$results = [];
$k = 0;
exec(escapeshellcmd($ch), $results);
$excluded = [
	'/hdd3/music/',
	'/hdd3/music/Van Halen/OICU81',
	'/hdd3/music/Sting'
];
// I do not know if I can get this login back / 100 per month
// Used it up Jan 31, can't look it up.  Does it reset tomorrow?  Or Feburary 31st?
$key = '55e3658edc953845ab9681381db01f7e151af29b6e36ec6120aedc81ab049c1c';
// $key = '5589420d3eb3cee52fd6c0f59c17ada415df12cbacedd2552c92cff06b86f32c';

foreach ($results as $dir) {
	if (stripos($dir, 'sting') !== false) {
		continue;
	}
	if (!in_array($dir, $excluded)) {
		// go through each directory and see if there's an album image
		$pieces = explode('/', str_replace($root, '', $dir));
		$album = array_pop($pieces);
		$band = array_pop($pieces);
		$thumb = str_replace(" ", "", ($album ?? $band)) . '.jpg';
		$thumblocation = $dir . '/' . $thumb;
		if (!file_exists($thumblocation)) {
			echo chr(10) . "Working on $band / $album";
			echo chr(10) . "Looking for $thumblocation";
			// there is no album image, so use the API and go get one
			$term = rawurlencode($band . ' ' . $album);
			echo chr(10) . "No thumbnail; requesting for \"$term\"";
			$url = "https://serpapi.com/search?q=$term&tbm=isch&ijn=0&imgar=s&api_key=$key";
			$data = json_decode(file_get_contents($url), true) or die ('json fail');
			for ($i=0; $i<5; $i++) {
				// log the first 5 results just in case
				$url = $data['images_results'][$i]['original'];
				if (!$url) {
					// print_r($data);
					echo(chr(10) . ' Something went wrong - no URL returned');
				}
				if ($i===0) {
					// write the thumbnail
					file_put_contents($thumblocation, file_get_contents($url));
					echo chr(10) . "Writing thumbnail";
					// bother to resize?
					$cmd = "convert \"$thumblocation\" -resize 300x300 \"$thumblocation\"";
					echo chr(10) . "Resizing thumbnail";
					exec(escapeshellcmd($cmd));
				}
				file_put_contents('thumbnail.log', $thumblocation . chr(9) . $url, FILE_APPEND);
			}
			if ($k++==5) {
				// die(chr(10));
			}
		}
	}
	
}
