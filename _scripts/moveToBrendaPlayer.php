<?php

/**
 * Notes to Freddy:
 * This script ran in the browser to get the DB login from env vars so it won't run command line
 * Update the band and album to match frealPlayer /hdd3/music/[band]/[album] for this to work
 * Should be easy if you keep the format
 * you suck.
 */

$band = "Ashton Becker And Dente";
$album = "Along The Road";

require_once('../../bs2.php');
$F = new FtappsIncludes();
$db = $F;
$query = "use tunes";
$db->prepare($query);
$db->execute();

// get directory to transfer
$dir = "/hdd3/music/$band/$album";

if (!file_exists($dir)) {
    die('path does not exist');
}


// copy thumbnail to tunes repo
$thumbname = str_replace(" ", "", $album).".jpg";
$thumb = $dir."/".$thumbname;
$thumbpath = '/hdd/repo/ftapps/tunes/assets/images/'.$thumbname;
copy($thumb, $thumbpath) or die ('no copy');

// create album query
// userId, band, name, thumnb
$userId = 2; // brenda
$query = "INSERT INTO tunes_album (userId, band, name, thumb) VALUES (?,?,?,?)";
$params = [
    $userId,
    $band,
    $album,
    $thumbname
];
try {
    $db->prepare($query);
    $db->execute($params);
} catch (PDOException $e) {
    die($e->getMessage());
}
$albumId = $db->getInsertId();


// create songs query
$files = glob($dir."/*.mp3");
$i=1;
echo "<pre>";
foreach ($files as $file) {
    $query = "INSERT INTO tunes_songs (albumId, name, position, path) VALUES (?,?,?,?)";
    $params = [
        $albumId,
        str_replace(".mp3", "", basename($file)),
        $i++,
        $file
    ];
    try {
        $db->prepare($query);
        $db->execute($params);
    } catch (PDOException $e) {
        die($e->getMessage());
    }
}

echo "done";


