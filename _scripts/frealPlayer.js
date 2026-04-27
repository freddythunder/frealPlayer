/* freal player controls */

var songId = 0;
var server = window.location.host;
var playlistData = {};

function setNowPlayingArtwork(songPath) {
	let art = $('#nowPlayingArt');
	if (!songPath) {
		art.addClass('displayNone').attr('src', '');
		return;
	}
	let folderPath = songPath.replace(/\/[^/]+$/, '');
	let folderName = folderPath.split('/').pop() || '';
	let imageName = folderName.replace(/[^a-zA-Z]/g, '');
	if (!imageName) {
		art.addClass('displayNone').attr('src', '');
		return;
	}
	let imagePath = folderPath + '/' + imageName + '.jpg';
	art.off('load error');
	art.on('load', function() {
		art.removeClass('displayNone');
	});
	art.on('error', function() {
		art.addClass('displayNone');
	});
	art.attr('src', imagePath + '?cache=' + (new Date().getMilliseconds()));
}

function getSong(id){
	songId = id;
	var data = $('#song'+id).data('info');
	console.log(data);
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
	setNowPlayingArtwork(data.path);
	
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

function getSelectionTargetFromUrl() {
	let searchParams = new URLSearchParams(window.location.search);
	let selected = searchParams.get('selected');
	if (selected) {
		return selected.replace(/'/g, '%27');
	}
	if (window.location.hash !== '') {
		return decodeURI(window.location.hash.replace(/#/, '')).replace(/'/g, '%27');
	}
	let filepath = searchParams.get('filepath');
	if (filepath && /\.(mp3|flac|ogg|wav)$/i.test(filepath)) {
		return filepath.replace(/'/g, '%27');
	}
	return '';
}

function scrollToSelectedSong() {
	let targetPath = getSelectionTargetFromUrl();
	if (!targetPath) {
		return;
	}
	let id = '';
	$('.songWrap').each(function(i, v) {
		let info = $(this).data('info');
		if (info && info.path && info.path.indexOf(targetPath) !== -1) {
			id = info.id;
		}
	});
	if (id) {
		$('html,body').animate({
			scrollTop: $('#song' + id).offset().top - 250
		}, 200);
		$('#song' + id).css({ backgroundColor:'#FCFFCF' });
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

	getPlaylists();

	// set up continuous playing
	document.getElementById('myaudio').addEventListener('ended', function(){ 
		logMessage('Ended event listener fired');
		getNext(); 
	});
	
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
		}
	});
}

function applyDarkMode(isEnabled) {
	$('body').toggleClass('dark-mode', isEnabled);
	$('#darkModeToggle').prop('checked', isEnabled);
}

function initDarkMode() {
	let isEnabled = localStorage.getItem('frealPlayerDarkMode') === '1';
	applyDarkMode(isEnabled);
}



