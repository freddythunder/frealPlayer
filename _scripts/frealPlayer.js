/* freal player controls */
var frealPlayerSavedAt = '2026-09-07 2:57 PM';

var songId = 0;
var server = window.location.host;
var playlistData = {};
var currentSongData = null;
var artworkRequestId = 0;
var lastPositionStateAt = 0;
var mediaSessionHandlersBound = false;
var abLoopStart = 0;
var abLoopEnd = 0;
var abLoopActive = false;
var lyricsOverlayOpen = false;
var lyricsUserScrolling = false;
var lyricsUserScrollTimer = null;
var lyricsRequestId = 0;
var lyricsSongPath = '';
var lyricsSongName = '';
var lyricsActiveButton = null;

function getAudioElement() {
	return document.getElementById('myaudio');
}

function formatAbLoopTime(seconds) {
	let mins = Math.floor(seconds / 60);
	let secs = Math.floor(seconds % 60);
	let tenths = Math.floor((seconds % 1) * 10);
	return mins + ':' + String(secs).padStart(2, '0') + '.' + tenths;
}

function updateAbLoopStatus() {
	let status = $('#abLoopStatus');
	if (!$('#abLoopA').hasClass('active') && !$('#abLoopB').hasClass('active')) {
		status.text('');
		return;
	}
	let text = 'A: ' + formatAbLoopTime(abLoopStart);
	if ($('#abLoopB').hasClass('active')) {
		text += '  B: ' + formatAbLoopTime(abLoopEnd);
		if (abLoopActive) {
			text += ' (looping)';
		}
	}
	status.text(text);
}

function onAbLoopTimeUpdate() {
	let audio = getAudioElement();
	if (abLoopActive && audio.currentTime >= abLoopEnd) {
		audio.currentTime = abLoopStart;
	}
}

function stopAbLoopListener() {
	$(getAudioElement()).off('timeupdate.abloop', onAbLoopTimeUpdate);
}

function clearAbLoop() {
	abLoopStart = 0;
	abLoopEnd = 0;
	abLoopActive = false;
	$('#abLoopA, #abLoopB').removeClass('active');
	stopAbLoopListener();
	updateAbLoopStatus();
}

function startAbLoop() {
	abLoopActive = true;
	$('#abLoopB').addClass('active');
	stopAbLoopListener();
	$(getAudioElement()).on('timeupdate.abloop', onAbLoopTimeUpdate);
	updateAbLoopStatus();
}

function getFolderName(songPath) {
	if (!songPath) {
		return '';
	}
	let folderPath = songPath.replace(/\/[^/]+$/, '');
	return folderPath.split('/').pop() || '';
}

function getAlbumArtUrl(songPath) {
	if (!songPath) {
		return '';
	}
	return new URL('api.php?cmd=getAlbumArt&raw=1&path=' + encodeURIComponent(songPath), window.location.href).href;
}

function getAlbumName(data) {
	if (data && data.album) {
		return data.album;
	}
	return getFolderName(data && data.path);
}

function getArtistName(data) {
	let songPath = (data && data.path) || '';
	if (songPath) {
		try {
			songPath = decodeURIComponent(songPath);
		} catch (e) {}
		let parts = songPath.split('/').filter(function(part) {
			return part !== '';
		});
		let musicIndex = parts.indexOf('music');
		if (musicIndex !== -1 && parts[musicIndex + 1]) {
			return parts[musicIndex + 1];
		}
	}
	if (data && data.artist) {
		return data.artist;
	}
	return 'FrealPlayer';
}

function getAbsoluteUrl(path) {
	if (!path) {
		return '';
	}
	if (/^https?:\/\//i.test(path)) {
		return path;
	}
	return new URL(path, window.location.href).href;
}

function getVisibleSongs() {
	let songs = [];
	$('.songWrap').each(function() {
		let info = $(this).data('info');
		if (info && info.id != null && info.name) {
			songs.push(info);
		}
	});
	return songs;
}

function mediaSessionArtwork(artworkSrc) {
	if (!artworkSrc) {
		return [];
	}
	let artworkUrl = getAbsoluteUrl(artworkSrc);
	return [
		{ src: artworkUrl, sizes: '96x96', type: 'image/jpeg' },
		{ src: artworkUrl, sizes: '128x128', type: 'image/jpeg' },
		{ src: artworkUrl, sizes: '192x192', type: 'image/jpeg' },
		{ src: artworkUrl, sizes: '256x256', type: 'image/jpeg' },
		{ src: artworkUrl, sizes: '384x384', type: 'image/jpeg' },
		{ src: artworkUrl, sizes: '512x512', type: 'image/jpeg' }
	];
}

function songListChapterInfo(artwork) {
	return getVisibleSongs().slice(0, 80).map(function(song) {
		let chapter = {
			title: song.id + '. ' + song.name,
			startTime: 1000000 + Number(song.id)
		};
		if (artwork && artwork.length) {
			chapter.artwork = artwork;
		}
		return chapter;
	});
}

function playSongFromChapterSeek(seekTime) {
	if (typeof seekTime !== 'number' || seekTime < 1000000) {
		return false;
	}
	let id = Math.round(seekTime - 1000000);
	if (!id || !$('#song' + id).length) {
		return false;
	}
	getSong(id);
	return true;
}

function updateMediaSession(data, artworkSrc) {
	if (!('mediaSession' in navigator) || typeof MediaMetadata === 'undefined' || !data) {
		return;
	}
	let artwork = mediaSessionArtwork(artworkSrc);
	let metadata = {
		title: data.name || '',
		artist: getArtistName(data),
		album: getAlbumName(data)
	};
	if (artwork.length) {
		metadata.artwork = artwork;
	}
	let chapters = songListChapterInfo(artwork);
	if (chapters.length) {
		metadata.chapterInfo = chapters;
	}
	try {
		navigator.mediaSession.metadata = new MediaMetadata(metadata);
	} catch (e) {
		delete metadata.chapterInfo;
		try {
			navigator.mediaSession.metadata = new MediaMetadata(metadata);
		} catch (err) {}
	}
}

function resetMediaPositionState() {
	if (!('mediaSession' in navigator) || !navigator.mediaSession.setPositionState) {
		return;
	}
	try {
		navigator.mediaSession.setPositionState(null);
	} catch (e) {}
	lastPositionStateAt = 0;
}

function updateMediaPositionState(force) {
	if (!('mediaSession' in navigator) || !navigator.mediaSession.setPositionState) {
		return;
	}
	let audio = getAudioElement();
	if (!audio) {
		return;
	}
	let duration = audio.duration;
	let position = audio.currentTime || 0;
	if (!duration || !isFinite(duration) || duration <= 0) {
		resetMediaPositionState();
		return;
	}
	if (position > duration) {
		position = duration;
	}
	if (!force && (Date.now() - lastPositionStateAt) < 1000) {
		return;
	}
	try {
		navigator.mediaSession.setPositionState({
			duration: duration,
			playbackRate: audio.playbackRate || 1,
			position: position
		});
		lastPositionStateAt = Date.now();
	} catch (e) {}
}

function setMediaPlaybackState(state) {
	if (!('mediaSession' in navigator)) {
		return;
	}
	navigator.mediaSession.playbackState = state;
}

function bindMediaSessionAction(action, handler) {
	if (!('mediaSession' in navigator)) {
		return;
	}
	try {
		navigator.mediaSession.setActionHandler(action, handler);
	} catch (e) {}
}

function initMediaSessionHandlers() {
	if (mediaSessionHandlersBound || !('mediaSession' in navigator)) {
		return;
	}
	mediaSessionHandlersBound = true;
	bindMediaSessionAction('play', function() {
		getAudioElement().play();
	});
	bindMediaSessionAction('pause', function() {
		getAudioElement().pause();
	});
	bindMediaSessionAction('previoustrack', function() {
		getPrev();
	});
	bindMediaSessionAction('nexttrack', function() {
		getNext();
	});
	bindMediaSessionAction('seekto', function(details) {
		if (details && playSongFromChapterSeek(details.seekTime)) {
			return;
		}
		let audio = getAudioElement();
		if (details && details.fastSeek && audio.fastSeek) {
			audio.fastSeek(details.seekTime);
		} else if (details) {
			audio.currentTime = details.seekTime;
		}
		updateMediaPositionState(true);
	});
	bindMediaSessionAction('seekbackward', function(details) {
		let audio = getAudioElement();
		audio.currentTime = Math.max(0, audio.currentTime - ((details && details.seekOffset) || 10));
		updateMediaPositionState(true);
	});
	bindMediaSessionAction('seekforward', function(details) {
		let audio = getAudioElement();
		let offset = (details && details.seekOffset) || 10;
		let nextTime = audio.currentTime + offset;
		if (audio.duration && isFinite(audio.duration)) {
			nextTime = Math.min(audio.duration, nextTime);
		}
		audio.currentTime = nextTime;
		updateMediaPositionState(true);
	});
	bindMediaSessionAction('stop', function() {
		let audio = getAudioElement();
		audio.pause();
		audio.currentTime = 0;
		setMediaPlaybackState('none');
		resetMediaPositionState();
	});
}

function bindMediaSessionAudioEvents() {
	let audio = getAudioElement();
	if (!audio || audio.dataset.mediaSessionBound) {
		return;
	}
	audio.dataset.mediaSessionBound = '1';
	audio.addEventListener('loadstart', function() {
		resetMediaPositionState();
	});
	audio.addEventListener('loadedmetadata', function() {
		resetMediaPositionState();
		updateMediaPositionState(true);
	});
	audio.addEventListener('durationchange', function() {
		updateMediaPositionState(true);
	});
	audio.addEventListener('seeked', function() {
		updateMediaPositionState(true);
	});
	audio.addEventListener('play', function() {
		setMediaPlaybackState('playing');
		updateMediaPositionState(true);
	});
	audio.addEventListener('pause', function() {
		setMediaPlaybackState('paused');
		updateMediaPositionState(true);
	});
	audio.addEventListener('timeupdate', function() {
		updateMediaPositionState(false);
	});
}

function setPageIcon(imageUrl) {
	let href = imageUrl || new URL('favicon.ico', window.location.href).href;
	let type = imageUrl ? 'image/jpeg' : 'image/x-icon';
	document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]').forEach(function(link) {
		if (link.parentNode) {
			link.parentNode.removeChild(link);
		}
	});
	[
		{ rel: 'icon' },
		{ rel: 'shortcut icon' },
		{ rel: 'apple-touch-icon', sizes: '180x180' },
		{ rel: 'apple-touch-icon', sizes: '192x192' }
	].forEach(function(spec) {
		let link = document.createElement('link');
		link.rel = spec.rel;
		link.type = type;
		link.href = href;
		if (spec.sizes) {
			link.setAttribute('sizes', spec.sizes);
		}
		document.head.appendChild(link);
	});
}

function setNowPlayingArtwork(songPath) {
	let art = $('#nowPlayingArt');
	let requestId = ++artworkRequestId;
	let imagePath = getAlbumArtUrl(songPath);
	art.off('load error');
	art.addClass('displayNone').attr('src', '');
	if (!imagePath) {
		setPageIcon('');
		updateMediaSession(currentSongData, '');
		return;
	}
	art.on('load', function() {
		if (requestId !== artworkRequestId) {
			return;
		}
		art.removeClass('displayNone');
		setPageIcon(imagePath);
		updateMediaSession(currentSongData, imagePath);
	});
	art.on('error', function() {
		if (requestId !== artworkRequestId) {
			return;
		}
		art.addClass('displayNone').attr('src', '');
		setPageIcon('');
		updateMediaSession(currentSongData, '');
	});
	art.attr('src', imagePath);
}

function getSong(id){
	songId = id;
	var data = $('#song'+id).data('info');
	console.log(data);
	currentSongData = data;
	resetMediaPositionState();
	$('#myaudio').attr('src', '//'+server+data.path.replace(/'/g, "\\'") + '?cache=' + (new Date().getMilliseconds()));
	// $('#myaudio').attr('src', '//' + server + escape(data.path));
	document.getElementById('myaudio').play();
	$('#songplaying').html(data.id+'. '+data.name);
	let songBg = $('body').hasClass('dark-mode') ? '#3a3a3a' : '#FFF';
	// set all songs to white
	$('.songWrap').css('backgroundColor', songBg);
	// set playing song to green
	let activeSongBg = $('body').hasClass('dark-mode') ? '#3D7EDB' : '#8BF77E';
	$('#song'+data.id).css('backgroundColor', activeSongBg);
	// change the title to show in the car
	$('title').html(data.name);
	updateMediaSession(data, '');
	setNowPlayingArtwork(data.path);
	setMediaPlaybackState('playing');
	clearAbLoop();
	
}
/* not sequencital */
function getNext(){
	if ($('#repeatButton').hasClass('active')) {
		document.getElementById('myaudio').currentTime = 0;
		document.getElementById('myaudio').play();
	} else {
		songId = $('#song'+songId).next().data('info').id;
		logMessage('getNext() fired with songID: ' + songId);
		getSong(songId);
	}
}

function getPrev(){
	songId = $('#song'+songId).prev().data('info').id;
	getSong(songId);
}

function logMessage(msg) {
return;
	let debug = document.querySelector('.debug');
	debug.innerHTML = msg + '<br>' + debug.innerHTML;
}
window.onerror = (msg, src, line, col, err) => {
	let html = '<span class="red">' + msg + ' / ' + err + '</span>';
	logMessage(html);
}

function normalizeSongPath(path) {
	if (!path) {
		return '';
	}
	let value = String(path);
	try {
		value = decodeURIComponent(value);
	} catch (e) {}
	value = value.replace(/\+/g, ' ');
	value = value.replace(/'/g, '%27');
	value = value.replace(/\/hdd\/repo\//g, '/repo/');
	return value;
}

function songPathMatches(songPath, targetPath) {
	let song = normalizeSongPath(songPath);
	let target = normalizeSongPath(targetPath);
	if (!song || !target) {
		return false;
	}
	if (song === target || song.indexOf(target) !== -1 || target.indexOf(song) !== -1) {
		return true;
	}
	let songBase = song.split('/').pop();
	let targetBase = target.split('/').pop();
	return !!(songBase && targetBase && songBase === targetBase);
}

function getSelectionTargetFromUrl() {
	let searchParams = new URLSearchParams(window.location.search);
	let selected = searchParams.get('selected');
	if (selected) {
		return selected;
	}
	if (window.location.hash !== '') {
		try {
			return decodeURI(window.location.hash.replace(/#/, ''));
		} catch (e) {
			return window.location.hash.replace(/#/, '');
		}
	}
	let filepath = searchParams.get('filepath');
	if (filepath && /\.(mp3|flac|ogg|wav)$/i.test(filepath)) {
		return filepath;
	}
	return '';
}

function scrollToSelectedSong() {
	let targetPath = getSelectionTargetFromUrl();
	if (!targetPath) {
		return;
	}
	let id = '';
	$('.songWrap').each(function() {
		let info = $(this).data('info');
		if (info && songPathMatches(info.path, targetPath)) {
			id = info.id;
		}
	});
	if (id) {
		let song = $('#song' + id);
		if (!song.length || !song.offset()) {
			return;
		}
		$('html,body').animate({
			scrollTop: song.offset().top - 250
		}, 200);
		song.css({ backgroundColor:'#FCFFCF' });
	}
}

function renderBreadcrumbs() {
	let searchParams = new URLSearchParams(window.location.search);
	let playlist = searchParams.get('playlist');
	let filepath = searchParams.get('filepath');
	if (playlist) {
		$('#breadcrumbs').html('<span>' + playlist + '</span>');
		return;
	}
	if (!filepath) {
		$('#breadcrumbs').html('');
		return;
	}
	let normalizedPath = filepath.replace(/\/+$/, '');
	if (/\.(mp3|flac|ogg|wav)$/i.test(normalizedPath)) {
		normalizedPath = normalizedPath.replace(/\/[^/]+$/, '');
	}
	let pieces = normalizedPath.split('/').filter(function(part) {
		return part !== '';
	});
	if (!pieces.length) {
		$('#breadcrumbs').html('');
		return;
	}
	let musicIndex = pieces.indexOf('music');
	if (musicIndex === -1 || musicIndex >= pieces.length - 1) {
		$('#breadcrumbs').html('');
		return;
	}
	let pathParts = pieces.slice(musicIndex + 1);
	let currentPath = '/hdd3/music';
	let crumbs = [];
	$.each(pathParts, function(i, part) {
		currentPath += '/' + part;
		let href = window.location.pathname + '?filepath=' + encodeURIComponent(currentPath);
		crumbs.push('<a href="' + href + '" class="dopost" data-band="' + currentPath + '">' + part + '</a>');
	});
	$('#breadcrumbs').html(crumbs.join(' > '));
}

$(document).ready(function(){
	initDarkMode();
	$('#jsVersionStamp').text(frealPlayerSavedAt ? 'JS ' + frealPlayerSavedAt : '');

	getPlaylists();

	// set up continuous playing
	document.getElementById('myaudio').addEventListener('ended', function(){ 
		logMessage('Ended event listener fired');
		getNext(); 
	});
	initMediaSessionHandlers();
	bindMediaSessionAudioEvents();
	bindLyricsOverlayEvents();
	
	// set up rating system
	$('#repeatButton').on('click', function(e) {
		if ($(this).hasClass('active')) {
			$(this).removeClass('active');
			
		} else {
			$(this).addClass('active');
		}
	});

	scrollToSelectedSong();
	renderBreadcrumbs();
	
	$(document).keypress(
		function(event){
    		if (event.which == '13') {
				if ($(event.target).closest('#addNewSongForm').length) {
					return;
				}
      			event.preventDefault();
   		 	}
		}
	);

	$('#songSelection form').on('submit', function(e) {
		e.preventDefault();
		loadSongList($(this).serializeArray());
	});
	
});

$(document).on('click', '.fa-star, .fa-star-o', function(i,v){
	rateSong($(this).data('id'), $(this).data('rate'));
});

$(document).on('keyup', '#srch', function(e) {
	
	if ($(this).val().length >= 2) {
		$.ajax({
			url: 'api.php',
			method: 'POST',
			dataType: 'json',
			data: {
				cmd: 'doSearch',
				srch: $(this).val()
			},
			success: function(msg) {
				if (msg.html) {
					$('#srchResults').html(msg.html).removeClass('displayNone');
					$('#motoButtons').addClass('displayNone');
				} else {
					$('#srchResults').html('').addClass('displayNone');
					$('#motoButtons').removeClass('displayNone');
				}
			}
		});
	} else {
		$('#motoButtons').removeClass('displayNone');
		$('#srchResults').html('').addClass('displayNone');
	}
});

$(document).on('click', '.dopost', function(e) {
	e.preventDefault();
	let isSearchResultClick = $(this).closest('#srchResults').length > 0;
	if ($(this).data('band')) {
		loadSongList({
			filepath: $(this).data('band')
		});
	}
	if ($(this).data('song')) {
		let songPath = $(this).data('song');
		loadSongList({
			filepath: songPath.replace(/\/[^/]+$/, ''),
			selected: songPath
		});
	}
	if ($(this).data('playlist')) {
		loadSongList({
			playlist: $(this).data('playlist')
		});
	}
	if ($(this).data('stockId')) {
		loadSongList({
			stockid: $(this).data('stockId')
		});
	}
	$('#srchResults').html('').addClass('displayNone');
	$('#motoButtons').removeClass('displayNone');
	if (isSearchResultClick) {
		$('#srch').val('');
	}
});

$(document).on('click', '#addToPlaylistButton', function(e) {
	$.ajax({
		url: 'api.php',
		dataType: 'json',
		data: {
			cmd: 'savePlaylist',
			playlistName: $('#playlistName').val(),
			playlistSelect: $('#playlistSelect').val(),
			name: playlistData.name,
			path: playlistData.path
		},
		success: function(msg) {
			$('#offcanvas').offcanvas('hide');
			getPlaylists();
		}
	})
});

$(document).on('click', '.playlistIcon', function(e) {
	playlistData = $(this).closest('.songWrap').data('info');
});

$(document).on('click', '.deleteFromPlaylist', function(e) {
	songData = $(this).closest('.songWrap').data('info');
	$.ajax({
		url: 'api.php',
		dataType: 'json',
		data: {
			cmd: 'removeFromPlaylist',
			name: songData.name
		},
		success: function(msg) {
			loadSongList({
				playlist: new URLSearchParams(window.location.search).get('playlist') || ''
			});
		}
	})
});

$(document).on('change', '#motorcycleModeToggle', function() {
	let isEnabled = $(this).is(':checked');
	let url = new URL(window.location);
	url.searchParams.set('motomode', isEnabled ? '1' : '0');
	window.location = url.toString();
});

$(document).on('change', '#stockAudioModeToggle', function() {
	let isEnabled = $(this).is(':checked');
	let url = new URL(window.location);
	url.searchParams.set('stockmode', isEnabled ? '1' : '0');
	window.location = url.toString();
});

$(document).on('click', '#abLoopA', function() {
	let audio = getAudioElement();
	if ($(this).hasClass('active')) {
		clearAbLoop();
		return;
	}
	abLoopStart = audio.currentTime;
	$(this).addClass('active');
	if (abLoopActive) {
		abLoopActive = false;
		$('#abLoopB').removeClass('active');
		stopAbLoopListener();
	}
	updateAbLoopStatus();
});

$(document).on('click', '#abLoopB', function() {
	let audio = getAudioElement();
	if ($(this).hasClass('active') && abLoopActive) {
		clearAbLoop();
		return;
	}
	if (!$('#abLoopA').hasClass('active')) {
		return;
	}
	abLoopEnd = audio.currentTime;
	if (abLoopEnd <= abLoopStart) {
		return;
	}
	startAbLoop();
});

$(document).on('click', '.motoStart', function(e) {
	document.getElementById('myaudio').play();
});

$(document).on('click', '.motoStop', function(e) {
	document.getElementById('myaudio').pause();
});

$(document).on('click', '.browse_wrapper', function(e) {
	e.preventDefault();
	loadSongList({
		filepath: $(this).data('dir')
	});
});

$(document).on('change', '#darkModeToggle', function() {
	let isEnabled = $(this).is(':checked');
	applyDarkMode(isEnabled);
	localStorage.setItem('frealPlayerDarkMode', isEnabled ? '1' : '0');
});

function getPlaylists() {
	$.ajax({
		url: 'api.php',
		dataType: 'json',
		data: {
			cmd: 'getPlaylists'
		},
		success: function(msg) {
			$('#playlistSelect option').remove();
			$('#playlistSelect').append($('<option>', { 
				value: '',
				text: ' -- Select Playlist -- ' 
			}));
			let html = '';
			$(msg.playlists).each(function(i,v) {
				$('#playlistSelect').append($('<option>', {
					value: v.id,
					text: v.name
				}));
				html += '<div class="playlistButton dopost" data-playlist="' + v.name + '">' + v.name + '</div>';
			});
			if (html) {
				$('#playlistWrapper').html(html);
			}
		}
	})
}

function saveNotes(elm) {
	if ($(elm).val() !== '') {
		$.ajax({
			url: 'index.php',
			dataType: 'json',
			data: {
				cmd: 'saveNotes',
				id: $(elm).data('id'),
				notes: $(elm).val()
			},
			success: function(msg) {
				$(elm).css('backgroundColor', '#CFC');
				$(elm).animate({ backgroundColor: '#FFF' }, 400, function(e) {
					$(elm).attr('style', '');
				});
			}
		});
	}
}

function rateSong(id, rate){
	$.ajax({
		url: 'index.php',
		dataType: 'json',
		data: {
			cmd: 'rateSong',
			id: id,
			rate: rate
		},
		success: function(msg) {
			$('#songrate' + id + ' i').each(function(i,v){
				$(this).removeClass('fa-star').addClass('fa-star-o');
				if($(this).data('rate') <= rate) {
					$(this).addClass('fa-star').removeClass('fa-star-o');
				}
			});
		}
	});
}

function loadSongList(params) {
	let requestData = {
		ajax: 1,
		cache: new Date().getTime()
	};
	if (Array.isArray(params)) {
		$.each(params, function(i, field) {
			requestData[field.name] = field.value;
		});
	} else {
		requestData = Object.assign(requestData, params || {});
	}
	$.ajax({
		url: 'index.php',
		method: 'GET',
		data: requestData,
		success: function(html) {
			$('#songlist').html(html);
			let urlParams = new URLSearchParams();
			$.each(requestData, function(key, value) {
				if (key === 'ajax' || key === 'cache' || value === '' || value == null) {
					return;
				}
				urlParams.set(key, value);
			});
			let nextUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
			window.history.replaceState({}, '', nextUrl);
			renderBreadcrumbs();
			scrollToSelectedSong();
			if (currentSongData) {
				let art = $('#nowPlayingArt');
				updateMediaSession(currentSongData, art.hasClass('displayNone') ? '' : art.attr('src'));
			}
		}
	});
}

function getCurrentFilepath() {
	let searchParams = new URLSearchParams(window.location.search);
	let filepath = searchParams.get('filepath') || '';
	if (!filepath) {
		return '';
	}
	if (/\.(mp3|flac|ogg|wav)$/i.test(filepath)) {
		filepath = filepath.replace(/\/[^/]+$/, '');
	}
	return filepath.replace(/\/+$/, '');
}

function setAddSongStatus(message, isError) {
	let status = $('#addSongStatus');
	status.text(message);
	status.toggleClass('addSongStatusError', !!isError);
	status.toggleClass('addSongStatusOk', !isError && !!message);
}

$(document).on('submit', '#addNewSongForm', function(e) {
	e.preventDefault();
	let url = $.trim($('#addSongUrl').val() || '');
	let filename = $.trim($('#addSongFilename').val() || '');
	let filepath = getCurrentFilepath();
	if (!url) {
		setAddSongStatus('Enter a YouTube URL.', true);
		return;
	}
	if (filename) {
		if (!/^[a-zA-Z0-9\s._-]+$/.test(filename)) {
			setAddSongStatus('Filename may only contain letters, numbers, spaces, dashes, underscores, and periods.', true);
			return;
		}
		if (!/\.mp3$/i.test(filename)) {
			filename += '.mp3';
			$('#addSongFilename').val(filename);
		}
	}
	if (!filepath) {
		setAddSongStatus('Open a music folder first so the song has a place to save.', true);
		return;
	}
	let submitButton = $('#addSongSubmit');
	submitButton.prop('disabled', true);
	setAddSongStatus('Starting download...', false);
	$.ajax({
		url: 'api.php',
		method: 'POST',
		dataType: 'json',
		data: {
			cmd: 'addNewSong',
			url: url,
			filename: filename,
			filepath: filepath
		},
		success: function(msg) {
			if (msg && msg.success) {
				setAddSongStatus(msg.msg || 'Download started in the background.', false);
				$('#addSongUrl').val('');
				$('#addSongFilename').val('');
			} else {
				setAddSongStatus((msg && msg.msg) || 'Could not start the download.', true);
			}
		},
		error: function() {
			setAddSongStatus('Could not start the download.', true);
		},
		complete: function() {
			submitButton.prop('disabled', false);
		}
	});
});

function setLyricsDeleteVisible(show) {
	$('#lyricsOverlayDelete').toggleClass('displayNone', !show);
}

function setLyricsRetryVisible(show) {
	$('#lyricsOverlayRetry').toggleClass('displayNone', !show);
}

function setLyricsOverlayStatus(message) {
	$('#lyricsOverlayStatus').text(message || '');
	$('#lyricsOverlayText').text('');
}

function loadLyricsForOverlay(path, name, button) {
	let requestId = ++lyricsRequestId;
	setLyricsRetryVisible(false);
	setLyricsDeleteVisible(false);
	setLyricsOverlayStatus('Loading lyrics...');
	if (button) {
		setLyricsButtonLoading(button, true);
	}
	$.ajax({
		url: 'api.php',
		method: 'POST',
		dataType: 'json',
		data: {
			cmd: 'getLyrics',
			path: path || '',
			name: name || ''
		},
		success: function(msg) {
			if (requestId !== lyricsRequestId) {
				return;
			}
			if (msg && msg.title) {
				$('#lyricsOverlayTitle').text(msg.title);
			}
			if (msg && msg.artist) {
				$('#lyricsOverlayArtist').text(msg.artist);
			}
			if (msg && msg.success && msg.lyrics) {
				$('#lyricsOverlayStatus').text('');
				$('#lyricsOverlayText').text(msg.lyrics);
				setLyricsRetryVisible(false);
				setLyricsDeleteVisible(true);
				syncLyricsScroll();
				return;
			}
			setLyricsDeleteVisible(false);
			setLyricsRetryVisible(true);
			setLyricsOverlayStatus((msg && msg.msg) || 'Could not load lyrics.');
		},
		error: function() {
			if (requestId !== lyricsRequestId) {
				return;
			}
			setLyricsDeleteVisible(false);
			setLyricsRetryVisible(true);
			setLyricsOverlayStatus('Could not load lyrics.');
		},
		complete: function() {
			if (requestId !== lyricsRequestId) {
				return;
			}
			if (button) {
				setLyricsButtonLoading(button, false);
			}
		}
	});
}

function setLyricsButtonLoading(button, isLoading) {
	if (!button) {
		return;
	}
	let $button = $(button);
	if (isLoading) {
		if (!$button.data('lyricsLabel')) {
			$button.data('lyricsLabel', $button.html());
		}
		lyricsActiveButton = button;
		$button.prop('disabled', true)
			.addClass('lyricsButtonLoading')
			.html('<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>');
		return;
	}
	$button.prop('disabled', false)
		.removeClass('lyricsButtonLoading')
		.html($button.data('lyricsLabel') || 'L');
	$button.removeData('lyricsLabel');
	if (lyricsActiveButton === button) {
		lyricsActiveButton = null;
	}
}

function syncLyricsScroll() {
	if (!lyricsOverlayOpen || lyricsUserScrolling) {
		return;
	}
	let audio = getAudioElement();
	let panel = document.getElementById('lyricsOverlayBody');
	if (!audio || !panel) {
		return;
	}
	let duration = audio.duration;
	if (!duration || !isFinite(duration) || duration <= 0) {
		return;
	}
	let maxScroll = panel.scrollHeight - panel.clientHeight;
	if (maxScroll <= 0) {
		return;
	}
	panel.scrollTop = maxScroll * (audio.currentTime / duration);
}

function pauseLyricsAutoScroll() {
	lyricsUserScrolling = true;
	if (lyricsUserScrollTimer) {
		clearTimeout(lyricsUserScrollTimer);
	}
	lyricsUserScrollTimer = setTimeout(function() {
		lyricsUserScrolling = false;
		syncLyricsScroll();
	}, 4000);
}

function closeLyricsOverlay() {
	lyricsOverlayOpen = false;
	lyricsRequestId += 1;
	lyricsUserScrolling = false;
	if (lyricsUserScrollTimer) {
		clearTimeout(lyricsUserScrollTimer);
		lyricsUserScrollTimer = null;
	}
	$('#lyricsOverlay').addClass('displayNone').attr('aria-hidden', 'true');
	$('html, body').removeClass('lyricsOverlayOpen');
	$(getAudioElement()).off('timeupdate.lyrics');
	setLyricsButtonLoading(lyricsActiveButton, false);
	setLyricsDeleteVisible(false);
	setLyricsRetryVisible(false);
}

function openLyricsOverlay(path, name, button) {
	let overlay = document.getElementById('lyricsOverlay');
	if (!overlay) {
		return;
	}
	lyricsOverlayOpen = true;
	lyricsUserScrolling = false;
	lyricsSongPath = path || '';
	lyricsSongName = name || '';
	setLyricsButtonLoading(lyricsActiveButton, false);
	$('#lyricsOverlayTitle').text(name || 'Lyrics');
	$('#lyricsOverlayArtist').text(getArtistName({ path: path, name: name }));
	$(overlay).removeClass('displayNone').attr('aria-hidden', 'false');
	$('html, body').addClass('lyricsOverlayOpen');
	let panel = document.getElementById('lyricsOverlayBody');
	if (panel) {
		panel.scrollTop = 0;
	}
	$(getAudioElement()).off('timeupdate.lyrics').on('timeupdate.lyrics', syncLyricsScroll);
	loadLyricsForOverlay(path, name, button);
}

function bindLyricsOverlayEvents() {
	let panel = document.getElementById('lyricsOverlayBody');
	if (!panel || panel.dataset.lyricsBound) {
		return;
	}
	panel.dataset.lyricsBound = '1';
	panel.addEventListener('touchstart', pauseLyricsAutoScroll, { passive: true });
	panel.addEventListener('wheel', pauseLyricsAutoScroll, { passive: true });
}

document.addEventListener('click', function(e) {
	let button = e.target.closest('.lyricsButton');
	if (!button) {
		return;
	}
	e.preventDefault();
	e.stopPropagation();
	if (button.disabled || button.classList.contains('lyricsButtonLoading')) {
		return;
	}
	let path = button.getAttribute('data-path') || '';
	let name = button.getAttribute('data-name') || '';
	let wrap = button.closest('.songWrap');
	if (wrap) {
		let info = $(wrap).data('info') || {};
		path = path || info.path || '';
		name = name || info.name || '';
	}
	openLyricsOverlay(path, name, button);
}, true);

$(document).on('click', '#lyricsOverlayClose', function(e) {
	e.preventDefault();
	closeLyricsOverlay();
});

$(document).on('click', '#lyricsOverlayRetry', function(e) {
	e.preventDefault();
	loadLyricsForOverlay(lyricsSongPath, lyricsSongName, null);
});

$(document).on('click', '#lyricsOverlayDelete', function(e) {
	e.preventDefault();
	if (!lyricsSongPath && !lyricsSongName) {
		return;
	}
	if (!window.confirm('Remove these lyrics from the cache?')) {
		return;
	}
	let deleteButton = $(this);
	deleteButton.prop('disabled', true);
	$.ajax({
		url: 'api.php',
		method: 'POST',
		dataType: 'json',
		data: {
			cmd: 'deleteLyricsCache',
			path: lyricsSongPath,
			name: lyricsSongName
		},
		success: function(msg) {
			if (msg && msg.success) {
				setLyricsDeleteVisible(false);
				setLyricsRetryVisible(true);
				setLyricsOverlayStatus(msg.msg || 'Lyrics removed from cache.');
				return;
			}
			setLyricsOverlayStatus((msg && msg.msg) || 'Could not remove lyrics from cache.');
		},
		error: function() {
			setLyricsOverlayStatus('Could not remove lyrics from cache.');
		},
		complete: function() {
			deleteButton.prop('disabled', false);
		}
	});
});

$(document).on('keydown', function(e) {
	if (e.key === 'Escape' && lyricsOverlayOpen) {
		closeLyricsOverlay();
	}
});

function applyDarkMode(isEnabled) {
	$('body').toggleClass('dark-mode', isEnabled);
	$('#darkModeToggle').prop('checked', isEnabled);
}

function initDarkMode() {
	let isEnabled = localStorage.getItem('frealPlayerDarkMode') === '1';
	applyDarkMode(isEnabled);
}



