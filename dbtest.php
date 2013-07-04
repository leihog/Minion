<?php

$db = new \PDO('sqlite:' . 'examplebot/data/bot.db');
$update = $db->prepare("UPDATE memory SET value = ? WHERE key = ?");
$insert = $db->prepare("INSERT INTO memory (key, value) VALUES(?, ?)");

function storeKeyValue($key, $value)
{
	global $db, $update, $insert;

	$update->execute(array($value, $key));
	if ($update->rowCount()===0) {
		$insert->execute(array($key, $value));
	}
}

for($i=0;$i<10;$i++) {
	$data["foobar{$i}"] = 10 - $i;
}

foreach($data as $key => $value) {
	storeKeyValue($key, $value);
}
