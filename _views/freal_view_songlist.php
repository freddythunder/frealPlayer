<?php
$firstdir = true;
$incompetech = false; // set this to TRUE for stock music database
if (count($this->songList)) {
	foreach ($this->songList as $song) {
		if (($song['type'] ?? null) === 'song' || $incompetech) {
			$song['path'] = str_replace("/hdd/repo/", "/repo/", $song['path']); ?>
			<div id="song<?=$song['id'];?>" class="songWrap" data-info='<?=json_encode($song, JSON_HEX_APOS);?>'>
				<div class="d-flex">
					<div class="p-1 songThumb" onClick="getSong(<?=$song['id'];?>)">
						<?php
						@$thumb = str_replace(basename($song['path']), "", $song['path'])
							. str_replace(" ", "", array_pop(array_filter(explode("/", str_replace(basename($song['path']), "", $song['path']))))) . '.jpg';
						if (file_exists($thumb)) { ?>
						<img src="<?= $thumb; ?>" width="62">
						<?php } ?>
					</div>
					<div class="flex-fill p-1 songContent" onClick="getSong(<?=$song['id'];?>)">
						<div class="songContentInner">
							<div class="songTopRow">
								<div class="song_name">
									<span class="name"><?=$song['name'];?></span><br>
									<?php if (!$mobile) { ?>
									<span class="source"><input type="text" class="width100" value="<?=$song['notes'];?>" data-id="<?= $song['id']; ?>" onclick="event.stopPropagation()" onblur="saveNotes(this)"></span>
									<?php } ?>
									<?php if ($song['notes'] ?? null) { ?>
									<p><em><?= $song['notes']; ?></em></p>
									<?php } ?>
								</div>

								<div class="song_info"></div>

								<?php if (!$mobile) { ?>
								<div class="song_rating" id="songrate<?= $song['id']; ?>" onclick="event.stopPropagation()">
									<input type="text" value="https://www.tacofever.com/<?= $song['source']; ?>" onclick="event.stopPropagation(); this.select()">
									<br>
									<i class="fa fa-star<?=$song['rating']>=1?null:'-o';?>" data-id="<?=$song['id']?>" data-rate="1"></i>
									<i class="fa fa-star<?=$song['rating']>=2?null:'-o';?>" data-id="<?=$song['id']?>" data-rate="2"></i>
									<i class="fa fa-star<?=$song['rating']>=3?null:'-o';?>" data-id="<?=$song['id']?>" data-rate="3"></i>
									<i class="fa fa-star<?=$song['rating']>=4?null:'-o';?>" data-id="<?=$song['id']?>" data-rate="4"></i>
									<i class="fa fa-star<?=$song['rating']>=5?null:'-o';?>" data-id="<?=$song['id']?>" data-rate="5"></i>
								</div>
								<?php } else { ?>
								<div class="songActions" onclick="event.stopPropagation()">
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
							<div class="songBottomRow">
								<span class="source songPath"><?=$song['path'] ?? $song['artist'];?> <?= $song['album'] ? ' :: ' . $song['album'] : ''; ?></span>
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
