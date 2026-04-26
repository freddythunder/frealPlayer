<?php

//$m3u = json_encode(explode("\n", file_get_contents('/hdd2/stock/incompetech/incompetech.m3u')), true);
$path = "//".$_SERVER['SERVER_NAME']."/hdd2/stock/incompetech/";
require_once('../bootstrap.php');
$bs = new Bootstrap();
$dbi = $bs->dbi;
$query = "SELECT * FROM incompetech";
if(isset($_GET['genre'])){
	$query .= " WHERE genre LIKE '%".preg_replace('/[^A-Za-z]/', '', $_GET['genre'])."%'";
}
$stmt = $dbi->query($query) or die ('no query '.$dbi->error);
$songs = [];
while($row = $stmt->fetch_assoc()){
	$songs[] = $path.rawurlencode($row['name']).'.mp3';
}
$m3u = json_encode($songs);

?>
<!DOCTYPE html>
<html>
<head>
<title>fRealPlayer :: Knowatmsayin'?</title>
<link rel="stylesheet" href="../library/font-awesome/css/font-awesome.min.css" type="text/css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<style>
html, body {
margin:0;
padding:0;
}
#songplaying {
border:1px #C00 solid;
padding:3px;
}
header {
position:fixed;
z-index:5;
background-color:#FFF;
width:100%;
padding:20px;
}
#songlist {
padding:100px 20px;

}
</style>
</head>
<body>
<header>
<audio controls="controls" id="myaudio">
	Your browser no likey the audio thingy
</audio>&nbsp;
<input type="button" value="Back" onClick="getPrev()">&nbsp;<input type="button" value="Next" onClick="getNext()"><br>
<div id="songplaying"></div>
</header>
<div id="songlist"></div>
<script>
var songs = <?php print_r($m3u); ?>;
var num = 0;
var data = "";
for(i in songs){
  data += i+'. <div id="song'+i+'"><a href="#" onClick="gotoSong('+i+');return false;">'+songs[i]+'</a></div>';
}
$('#songlist').html(data);
function getNext(){
 num++;
 $('#myaudio').attr('src', songs[num]);
 document.getElementById('myaudio').play();
 $('#songplaying').html(num+'. '+cleanIt(songs[num]));
 //$('#myaudio').play();
 //var myaudio = new Audio(songs[num]);
 //myaudio.play();
}
function cleanIt(input){
	var loc = input.lastIndexOf('/');
	return unescape(input.substring(loc+1));
}
function gotoSong(newnum){
 num=newnum-1;
 getNext();
}
function getPrev(){
 num -= 2;
 getNext();
}
document.getElementById('myaudio').addEventListener('ended', function(){ getNext(); });

</script>




</body>
</html>
