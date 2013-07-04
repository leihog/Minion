<?php

include dirname(__FILE__).'/bot/functions.php';
include dirname(__FILE__).'/bot/memory.php';

use Bot\Memory as Memory;

$mem = new Memory();
echo "Storing string 'testing 123'\n";
$mem->put('foobar', 'testing 123');
echo "value is ", $mem->get('foobar');

echo "\n\nStoring array\n";
$mem->put('foo[bar]', 10);
$value = $mem->get('foo[bar]');
echo "Value is $value\n";

echo "\n\nAssert that we can't alter the stored value by reference.\n",
	"Trying to set value to 666\n";
$value = 666;
echo "Value is still ". $mem->get('foo[bar]'), "\n";


echo "\n\nTrying to store a value using array syntax when base key exists and is not an array...\n";
echo ">>> \$mem->put('foobar[one]', 10);\n";
try {
	$mem->put('foobar[one]', 10);
} catch(Exception $e) {
	echo "Failed;\n\t", $e->getMessage();
}

echo "\n\nTesting increment/decrement\n";
echo "value after inc ", $mem->inc('karma[leihog]'), "\n";
echo "value after inc ", $mem->inc('karma[leihog]'), "\n";
echo "value after inc ", $mem->inc('karma[leihog]'), "\n";
echo "value after dec ", $mem->dec('karma[leihog]'), "\n";
echo "value is ", $mem->get('karma[leihog]'), "\n";
