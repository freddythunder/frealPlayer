<?php

function ytdlpFirstExecutable($paths) {
	foreach ($paths as $path) {
		if ($path && is_executable($path)) {
			return $path;
		}
	}
	return '';
}

function ytdlpResolveBin() {
	$paths = [
		'/home/freddythunder/.local/bin/yt-dlp',
		'/usr/local/bin/yt-dlp',
		'/usr/bin/yt-dlp',
	];
	foreach (glob('/home/*/.local/bin/yt-dlp') ?: [] as $path) {
		array_unshift($paths, $path);
	}
	return ytdlpFirstExecutable($paths);
}

function ytdlpResolveDeno() {
	$paths = [
		'/home/freddythunder/.deno/bin/deno',
		'/usr/local/bin/deno',
		'/usr/bin/deno',
	];
	foreach (glob('/home/*/.deno/bin/deno') ?: [] as $path) {
		array_unshift($paths, $path);
	}
	return ytdlpFirstExecutable($paths);
}

function ytdlpTmpDir() {
	$tmpDir = realpath(__DIR__ . '/../tmp');
	if ($tmpDir === false) {
		$tmpDir = sys_get_temp_dir();
	}
	@mkdir($tmpDir . '/ytdlp-cache', 0775, true);
	return $tmpDir;
}

function ytdlpRunBackground($outputTemplate, $target, $extraFlags = [], $logName = 'ytdlp.log') {
	$ytDlp = ytdlpResolveBin();
	if ($ytDlp === '') {
		error_log('yt-dlp is not available on the server.');
		return false;
	}
	$tmpDir = ytdlpTmpDir();
	$logFile = $tmpDir . '/' . $logName;

	$cmd = escapeshellcmd($ytDlp);
	$deno = ytdlpResolveDeno();
	if ($deno !== '') {
		$cmd .= ' --js-runtimes ' . escapeshellarg('deno:' . $deno);
	}
	$cmd .= ' -x --audio-quality 0 --audio-format mp3 --no-update';
	foreach ($extraFlags as $flag) {
		$cmd .= ' ' . $flag;
	}
	$cmd .= ' -o ' . escapeshellarg($outputTemplate)
		. ' -- ' . escapeshellarg($target);
	$full = 'HOME=' . escapeshellarg($tmpDir)
		. ' XDG_CACHE_HOME=' . escapeshellarg($tmpDir . '/ytdlp-cache')
		. ' nohup ' . $cmd
		. ' >> ' . escapeshellarg($logFile)
		. ' 2>&1 &';
	error_log($full);
	exec($full);
	return true;
}
