<?php
error_reporting(E_PARSE);


$file = file('toscrape');
$sql = [];
$links = [];
foreach ($file as $line) {

$html = file_get_contents($line);

$dom = new DOMDocument();
$dom->loadHTML($html);

$table = $dom->getElementsByTagName('table')->item(2);

preg_match("/href=\"((.*?)\.mp3)\"/", $html, $matches);
$mp3 = 'https://www.incompetech.com' . $matches[1];


	$trs = $table->getElementsByTagName('tr');
	$tr = $trs->item(1);

	$tds = $tr->getElementsByTagName('td');
	$source = 'incompetech';
	$name = trim(array_shift(explode("\n", $tds->item(0)->nodeValue)));
	
	$path = '/hdd2/stock/incompetech/' . urlencode($name);
	$tempo = $tds->item(1)->nodeValue;
	$genre = $tds->item(2)->nodeValue;
	$length = $tds->item(3)->nodeValue;
	
	echo chr(10) . "('" . $source . "', '" . $line  . "', '" . $path . "', '" . $name
		 . "', '" . $tempo . "', '" . $genre . "', '" . $length . "', 1, NOW()),";


	$links[] = chr(10) . $mp3;	
	
}

echo implode($links);