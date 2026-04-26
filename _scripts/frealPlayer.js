/* freal player controls */

var songId = 0;
var server = window.location.host;
var playlistData = {};

function getSong(id){
	songId = id;
	var data = $('#song'+id).data('info');
	console.log(data);
	$('#myaudio').attr('src', '//'+server+data.path.replace(/'/g, "\\'") + '?cache=' + (new Date().getMilliseconds()));
	// $('#myaudio').attr('src', '//' + server + escape(data.path));
	document.getElementById('myaudio').play();
	$('#songplaying').html(data.id+'. '+data.name);
	// set all songs to white
	$('.songWrap').css('backgroundColor', '#FFF');
	// set playing song to green
	$('#song'+data.id).css('backgroundColor', '#8BF77E');
	// change the title to show in the car
	$('title').html(data.name);
	
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

$(document).ready(function(){

	getPlaylists();

	// set up continuous playing
	document.getElementById('myaudio').addEventListener('ended', function(){ 
		logMessage('Ended event listener fired');
		getNext(); 
	});
	
	// set up rating system
	$('.fa-star, .fa-star-o').on('click', function(i,v){
		rateSong($(this).data('id'), $(this).data('rate'));
	});
	
	$('#repeatButton').on('click', function(e) {
		if ($(this).hasClass('active')) {
			$(this).removeClass('active');
			
		} else {
			$(this).addClass('active');
		}
	});

	// deal with a hash
	if (window.location.hash !== '') {
		let hash = decodeURI(window.location.hash.replace(/#/, ''));
		hash = hash.replace(/'/g, '%27');
		let id = '';
		$('.songWrap').each(function(i, v) {
			let info = $(this).data('info');
			if (info.path.indexOf(hash) !== -1) {
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
	
	// breadcrumbs
	let filepath = new URLSearchParams(window.location.search).get('filepath');
	if (filepath) {
		let pieces = filepath.split('/');
		let album = pieces.pop();
		let band = pieces.pop();
		let breadcrumbs = '';
		let base = '//' + server + '/ftapps/frealPlayer/?cache=' + (new Date().getMilliseconds()) + '&filepath=/hdd3/music/';
		if (band == 'music') {
			breadcrumbs = '<a href="' + base + encodeURIComponent(album) + '">' + album + '</a>';
		} else {
			breadcrumbs = '<a href="' + base + encodeURIComponent(band) + '">' + band + '</a> > ' 
				+ '<a href="' + base + encodeURIComponent(band) + '/' + encodeURIComponent(album) + '">' + album + '</a>';
		}
		$('#breadcrumbs').html(breadcrumbs);
	}
	
	$(document).keypress(
		function(event){
    		if (event.which == '13') {
      			event.preventDefault();
   		 	}
		}
	);
	
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
	if ($(this).data('band')) {
		window.location='/ftapps/frealPlayer/?cache=' + (new Date().getTime()) + '&filepath=' + $(this).data('band');
	}
	if ($(this).data('song')) {
		window.location='/ftapps/frealPlayer/?cache=' + (new Date().getTime()) + '&filepath=' + $(this).data('song').replace(/\/([^\/]*)$/, '#$1');
	}
	if ($(this).data('playlist')) {
		window.location='/ftapps/frealPlayer/?cache=' + (new Date().getTime()) + '&playlist=' + $(this).data('playlist');
	}
	$('#srchResults').html('');
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
			location.reload();
		}
	})
});

$(document).on('click', '.motomode', function(e) {
	let url = new URL(window.location);
	let final = url.origin + url.pathname;
	final += url.search ? url.search + '&motomode=true' : '?motomode=true';
	final += url.hash ? url.hash : null;
	window.location=final;
});

$(document).on('click', '.motoStart', function(e) {
	document.getElementById('myaudio').play();
});

$(document).on('click', '.motoStop', function(e) {
	document.getElementById('myaudio').pause();
});

$(document).on('click', '.browse_wrapper', function(e) {
	window.location='/ftapps/frealPlayer/?cache=' + (new Date().getTime()) + '&filepath=' + $(this).data('dir');
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



