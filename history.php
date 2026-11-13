<?php
require_once('_models/freal_model_history.php');

$entries = (new PlayHistory())->recent(50);

function h($in) {
	return htmlspecialchars((string)$in, ENT_QUOTES, 'UTF-8');
}

function songTitle($path) {
	$title = pathinfo($path, PATHINFO_FILENAME);
	return rawurldecode($title);
}

function folderLabel($path) {
	$dir = dirname($path);
	$dir = preg_replace('#^/hdd3/music/#', '', $dir);
	$dir = preg_replace('#^/hdd/repo/#', '', $dir);
	$dir = preg_replace('#^/repo/#', '', $dir);
	return rawurldecode(str_replace('/', ' / ', $dir));
}

function songHref($path) {
	$folder = dirname($path);
	return 'index.php?filepath=' . rawurlencode($folder) . '&selected=' . rawurlencode($path);
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Play History</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body {
	margin: 0;
	background: #333;
	color: #ddd;
	font-family: Arial, verdana, sans-serif;
}
.wrap {
	width: 95%;
	max-width: 720px;
	margin: 0 auto;
	padding: 16px 0 40px;
}
.back {
	color: #ddd;
	text-decoration: none;
	font-size: 16px;
}
h1 {
	font-size: 28px;
	margin: 16px 0 8px;
}
.historyItem {
	display: block;
	color: inherit;
	text-decoration: none;
	padding: 16px 4px;
	border-bottom: 1px solid #555;
}
.historyItem:active {
	background: #3D7EDB;
}
.historyTitle {
	font-size: 22px;
	line-height: 1.2;
}
.historyWhen,
.historyPath,
.historyClient {
	display: block;
	margin-top: 4px;
	font-size: 13px;
	line-height: 1.3;
	color: #aaa;
	word-break: break-all;
}
.empty {
	font-size: 18px;
	margin-top: 24px;
}
</style>
</head>
<body>
<div class="wrap">
	<a class="back" href="index.php">Back to player</a>
	<h1>Play History</h1>
	<?php if (!count($entries)) { ?>
		<p class="empty">No songs have been played yet.</p>
	<?php } else { ?>
		<?php foreach ($entries as $entry) {
			$when = strtotime($entry['time']);
			$label = $when ? date('M j, Y g:i A', $when) : $entry['time'];
		?>
		<a class="historyItem" href="<?= h(songHref($entry['path'])); ?>">
			<span class="historyTitle"><?= h(songTitle($entry['path'])); ?></span>
			<span class="historyWhen"><?= h($label); ?></span>
			<span class="historyPath"><?= h(folderLabel($entry['path'])); ?></span>
			<?php if (($entry['ip'] ?? '') !== '' || ($entry['agent'] ?? '') !== '') { ?>
			<span class="historyClient"><?= h(trim(($entry['ip'] ?? '') . ' ' . ($entry['agent'] ?? ''))); ?></span>
			<?php } ?>
		</a>
		<?php } ?>
	<?php } ?>
</div>
</body>
</html>
