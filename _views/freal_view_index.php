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
	overflow: auto;
    height: 600px;
}
.playlistWrapper {
	overflow: auto;
    height: 76%;
}
.playlistButton {
	font-size: 1.5em;
    padding: 10px;
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
<div id="songplaying"></div>
<div id="songSelection">
	<form name="asdf" action="" method="POST">
	<?php if ($mobile) { ?>
	<div class="p-1">
		<span id="breadcrumbs"></span>
	</div>
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
<?php 
	$firstdir = true;
	$incompetech = false; // set this to TRUE for stock music database
	if (count($this->songList)) { 
	foreach ($this->songList as $song){ 


		if (($song['type'] ?? null) === 'song' || $incompetech) { 
		$song['path'] = str_replace("/hdd/repo/", "/repo/", $song['path']); ?>
		<div id="song<?=$song['id'];?>" class="songWrap" data-info='<?=json_encode($song, JSON_HEX_APOS);?>'>
			<div class="d-flex">
				<div class="p-1">
					<?php 
					@$thumb = str_replace(basename($song['path']), "", $song['path']) 
						. str_replace(" ", "", array_pop(array_filter(explode("/", str_replace(basename($song['path']), "", $song['path']))))) . '.jpg'; 
					// error_log('Thumb: ' . $thumb);
					if (file_exists($thumb)) { ?>
					<img src="<?= $thumb; ?>" width="62">
					<?php } ?>
				</div>
				<div class="flex-fill p-1">
					<div class="container">
						<div class="d-flex">

							<div class="song_name" onClick="getSong(<?=$song['id'];?>)">
								<span class="name"><?=$song['name'];?></span><br>
								<span class="source"><?=$song['path'] ?? $song['artist'];?> <?= $song['album'] ? ' :: ' . $song['album'] : ''; ?></span><br>
								<?php if (!$mobile) { ?>
								<span class="source"><input type="text" class="width100" value="<?=$song['notes'];?>" data-id="<?= $song['id']; ?>" onclick="event.stopPropagation()" onblur="saveNotes(this)"></span>
								<?php } ?>
								<?php if ($song['notes'] ?? null) { ?>
								<p><em><?= $song['notes']; ?></em></p>
								<?php } ?>
							</div>
					
							<div class="song_info">
								<?php // $song['length']; $song['genre']; $song['id']; ?>
							</div>
					
							<?php if (!$mobile) { ?>
							<div class="song_rating" id="songrate<?= $song['id']; ?>">
								<input type="text" value="https://www.tacofever.com/<?= $song['source']; ?>" onclick="this.select()">
								<br>
								<i class="fa fa-star<?=$song['rating']>=1?null:'-o';?>" data-id="<?=$song['id']?>" data-rate="1"></i>
								<i class="fa fa-star<?=$song['rating']>=2?null:'-o';?>" data-id="<?=$song['id']?>" data-rate="2"></i>
								<i class="fa fa-star<?=$song['rating']>=3?null:'-o';?>" data-id="<?=$song['id']?>" data-rate="3"></i>
								<i class="fa fa-star<?=$song['rating']>=4?null:'-o';?>" data-id="<?=$song['id']?>" data-rate="4"></i>
								<i class="fa fa-star<?=$song['rating']>=5?null:'-o';?>" data-id="<?=$song['id']?>" data-rate="5"></i>
							</div>
							<?php } else { ?>
							<div class="">
								<?php if (!$mobile) { ?>
									<span class="fa fa-clipboard board" onclick="navigator.clipboard.writeText('https://www.tacofever.com<?= $song['path']; ?>')"></span></button>
								<?php } else { 
									if (!$this->isPlaylist) { ?>
									<span class="fa fa-plus-circle playlistIcon" data-bs-toggle="offcanvas" data-bs-target="#offcanvas"></span>
								<?php } else { ?>
									<span class="fa fa-trash deleteFromPlaylist"></span>
								<?php }
								} ?>
							</div>
							<?php } ?>
					
						</div>
					</div>
				</div>
			</div>
		</div>
		
		
		<?php } else if (($song['type'] ?? null) === 'directory') { 
			if ($firstdir) {
				$class = 'dirWrapFirst';
				$firstdir = false;
			} else {
				$class = '';
			}
		?>
		<div class="dirWrap dopost <?= $class; ?>" data-band="<?= $song['path']; ?>" data-info='<?= json_encode($song, JSON_HEX_APOS);?>'>
			<div class="d-flex">
				<div class="p-1">
					<?php 
					$thumb = $song['path'] . '/' . str_replace(" ", "", basename($song['name'])) . '.jpg';
					if (file_exists($thumb)) { ?>
					<img src="<?= $thumb; ?>" width="62">
					<?php } ?>
				</div>
				<div class="flex-fill p-1">
					<div class="displayInline">
						<i class="fas fa-record-vinyl"></i>
					</div>
					<div class="displayInline">
						<span class="name"><?= basename($song['name']); ?></span><br>
						<span class="source"><?= $song['path']; ?></span>
					</div>
				</div>
			</div>
		</div>
		<?php } ?>
<?php } 
	} else { ?>
		<p>Sorry Charlie, you gots no results...</p>
<?php } 
	if ($this->firstRun) { ?>
	<h5>Browse Music</h5>
	<?= $this->browseHTML; ?>
	
	
	<?php }
?>

</div>
<div class="fa-solid fa-motorcycle playlistIcon motomode"></div>
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
