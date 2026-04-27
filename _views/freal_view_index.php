<?php

$mobile = true;
if (stripos($_SERVER['HTTP_USER_AGENT'], 'iphone')) {
	$mobile = true;
}
$debug = false;
?>
<!DOCTYPE html>
<html>
<head>
<title>fRealPlayer :: Knowatmsayin'?</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="_styles/styles.css?cache=<?= md5_file('_styles/styles.css'); ?>" type="text/css">
<link rel="icon" href="favicon.ico" type="image/x-icon">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="_scripts/frealPlayer.js?cache=<?= md5_file('_scripts/frealPlayer.js'); ?>"></script>
<style>

.width200 {
width:200px;
}
.repeat.active {
background-color:#0096FF;
color:white;
}
.mobileSearch {
	padding-top:30px;
}
.srch {
	width:90%;
	font-size:3em;
}
.inlineBlock {
	display:inline-block;
}
#controlWrapper {
	#width:150px;
}
.displayNone {
	display:none !important;
}
#myaudio {
	width:100%;
}
.width90 {
	width:90%;
}
.board {
	padding:20px;
	cursor:pointer;
	font-size:2em;
}
.dirWrap {
	border: 1px #000 solid;
    min-height: 80px;
    padding: 3px;
    cursor: pointer;
    margin-bottom:2px;
}
.dirWrapFirst {
	margin-top:20px;
	border-top:#000 3px solid;
}
.displayInline {
	display:inline;
}
.displayInline i {
	font-size:2em;
}
.playlistIcon {
	font-size: 40px;
    padding-top: 7px;
    cursor:pointer;
}
.song_name {
	line-height:2px;
}
.center {
	text-align:center;
}
.right {
	text-align:right;
	padding-top:10px;
}
#srchResults {
	overflow-y: auto;
	overflow-x: hidden;
	height: 600px;
	touch-action: pan-y;
}
.playlistWrapper {
	overflow: auto;
    height: 76%;
}
.playlistButton {
	font-size: 1.5em;
    padding: 10px;
}
.settingsCogButton {
	font-size: 1.5em;
	cursor: pointer;
	padding: 4px 8px;
}
.settingsPanel {
	font-size: 1em;
}
.settingsRow {
	margin-bottom: 28px;
}
.settingsPanel .form-check-input {
	transform: scale(3);
	transform-origin: right center;
}
.browse_wrapper {
	float:left;
	width:175px;
	padding:3px;
	min-height:225px;
	cursor:pointer;
}
.browse_thumb {
	width:100%;
	text-align:center;
	
}
</style>
</head>
<body>



<div id="wrapper">
<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvas">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasLabel">Offcanvas</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <h5>Add to playlist</h5>
    <select id="playlistSelect" class="form-control">
    	<option value=""></option>
    </select>
    <div class="center">OR</div>
    <input type="text" id="playlistName" class="form-control" placeholder="New Playlist">
    <div class="right">
    	<button type="button" class="btn btn-primary" id="addToPlaylistButton">Add To Playlist</button>
    </div>
    <div class="playlistWrapper" id="playlistWrapper">
    
    </div>
    
    
  </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="settingsOffcanvas">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Player Settings</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body settingsPanel">
    <div class="d-flex justify-content-between align-items-center settingsRow">
      <span>Dark Mode</span>
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" role="switch" id="darkModeToggle">
      </div>
    </div>
    <div class="d-flex justify-content-between align-items-center settingsRow">
      <span>Motorcycle Mode</span>
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" role="switch" id="motorcycleModeToggle" <?= ($_SESSION['motomode'] ?? null) ? 'checked' : ''; ?>>
      </div>
    </div>
  </div>
</div>



<header>
<div id="controlWrapper">
	<div class="row width90">
		<div class="col-md-1">
			<div class="inlineBlock controlButton <?= $mobile? 'displayNone' : null; ?>" onClick="getPrev()"><span class="fa fa-backward"></span></div>
		</div>
		<div class="col-md-10">
			<audio controls="controls" id="myaudio">Your browser no likey the audio thingy</audio>
		</div>
		<div class="col-md-1">
			<div class="inlineBlock controlButton <?= $mobile? 'displayNone' : null; ?>" onClick="getNext()"><span class="fa fa-forward"></span></div>
		</div>
	</div>
</div>
<div id="nowPlayingMeta" class="d-flex align-items-center">
	<img src="" id="nowPlayingArt" class="displayNone" alt="Album art">
	<div class="nowPlayingText">
		<div id="songplaying"></div>
		<div id="breadcrumbs"></div>
	</div>
	<?php if ($mobile) { ?>
	<span class="fa fa-cog settingsCogButton" data-bs-toggle="offcanvas" data-bs-target="#settingsOffcanvas" aria-label="Open player settings"></span>
	<?php } ?>
</div>
<div id="songSelection">
	<form name="asdf" action="" method="POST">
	<?php if ($mobile) { ?>
	<div class="mobileSearch d-flex">
		<div class="">
			<input type="text" class="srch" name="srch" id="srch">
			<input type="hidden" name="mobile" value="1">
		</div>
	</div>
	<?php if ($_SESSION['motomode'] ?? null) { ?>
	<div class="d-flex" id="motoButtons">
		<div class="motoStart"><i class="fa-solid fa-play"></i></div>
		<div class="motoStop"><i class="fa-solid fa-pause"></i></div>
	</div>
	<?php } ?>
	<div id="srchResults" class="displayNone"></div>
	<?php } else { ?>
	<input type="text" name="filepath" value="<?= $_REQUEST['filepath'] ?? ''; ?>" placeholder="File Path">
	<select name="dirs" class="width200" onchange="this.form.submit()">
		<option value="">-- Directory --</option>
		<?php foreach($this->dirs as $dir) { ?>
		<option value="<?= $dir; ?>"<?= isset($_REQUEST['dirs']) && $source==$_REQUEST['dirs'] ? ' selected="SELECTED"' : null; ?>><?= str_replace("/hdd3/music/", "", $dir); ?></option>
		<?php } ?>
	</select>
	<select name="source">
		<option value="">-- Source --</option>
		<?php foreach(explode(",", $this->groupData['sources']) as $source){ ?>
		<option value="<?= $source; ?>"<?= isset($_REQUEST['source']) && $source==$_REQUEST['source'] ? ' selected="SELECTED"' : null; ?>><?= ucwords($source); ?></option>
		<?php } ?>
	</select>
	<select name="genre">
		<option value="">-- Genre --</option>
		<?php foreach(explode(",", $this->groupData['genres']) as $genre){ ?>
		<option value="<?= $genre; ?>"<?= isset($_REQUEST['genre']) && $genre==$_REQUEST['genre'] ? ' selected="SELECTED"' : null; ?>><?= ucwords($genre); ?></option>
		<?php } ?>
	</select>
	<select name="rating">
		<option value="">-- Rating --</option>
		<option value="1"<?= isset($_REQUEST['rating']) && $_REQUEST['rating']==1 ? ' selected="SELECTED"' : null; ?>>1 star</option>
		<option value="2"<?= isset($_REQUEST['rating']) && $_REQUEST['rating']==2 ? ' selected="SELECTED"' : null; ?>>2 star</option>
		<option value="3"<?= isset($_REQUEST['rating']) && $_REQUEST['rating']==3 ? ' selected="SELECTED"' : null; ?>>3 star</option>
		<option value="4"<?= isset($_REQUEST['rating']) && $_REQUEST['rating']==4 ? ' selected="SELECTED"' : null; ?>>4 star</option>
		<option value="5"<?= isset($_REQUEST['rating']) && $_REQUEST['rating']==5 ? ' selected="SELECTED"' : null; ?>>5 star</option>
	</select>
	<input type="text" name="srch" value="<?= $_REQUEST['srch'] ?? ''; ?>" placeholder="Search">
	<button type="submit">Go</button>
	<button type="button" class="repeat" id="repeatButton">Repeat</button>
	<?php } ?>
	</form>
</div>
</header>

<div id="songlist">
<?php require('_views/freal_view_songlist.php'); ?>

</div>
</div>
<style>
.debugwrapper {
	position: fixed;
    bottom: 0;
    width: 100%;
    margin: auto;
    padding: 2px;
    background-color:#FFF;
}
.debug {
	max-height:200px;
	font-size:.8em;
	height:200px;
}



</style>
<div class="debugwrapper <?= $debug ? null : 'displayNone'; ?>">
	<div class="debughandle">DEBUG</div>
	<div class="debug">
	
	</debug>
</div>
<script>

</script>



</body>
</html>
