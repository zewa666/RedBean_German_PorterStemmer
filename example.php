<?php
header('Content-Type: text/html; charset=utf-8');

// require RedBean
require_once('rb.php');

// require the plugin
require_once('RedBean_German_PorterStemmer.php');


R::setup('mysql:host=localhost;dbname=redbeandemo',
         'root','');

// create some demo data
$word = R::dispense('word');
$word->name = "Gehen ist nicht Laufen";
R::store($word);

$word = R::dispense('word');
$word->name = "Er geht hinaus auf die Straße";
R::store($word);

$word = R::dispense('word');
$word->name = "Bibliothekare und Bibliothekarinnen beschäftigen sich mit der Auswahl,
               Beschaffung, Katalogisierung, Erschließung, Verwaltung und Erhaltung
               von gedruckten, elektronischen und handschriftlichen Informationsmaterialien";
R::store($word);

$word = R::dispense('word');
$word->name = "Es war ein großes Abenteuer";
R::store($word);


// now lets search for some of them
echo "<h3>Suche nach: bibliotheken</h3>";
$result = R::stemmedSearch($word, "name", "bibliotheken");
foreach($result as $bean){
  echo $bean->id . " " . $bean->name . "<br />";
}

echo "<h3>Suche nach: gehen</h3>";
$result = R::stemmedSearch($word, "name", "gehen");
foreach($result as $bean){
  echo $bean->id . " " . $bean->name . "<br />";
}

echo "<h3>Suche nach: abenteuerlich</h3>";
$result = R::stemmedSearch($word, "name", "abenteuerlich");
foreach($result as $bean){
  echo $bean->id . " " . $bean->name . "<br />";
}

// you can also search with multiple words, which get stemmed separately
echo "<h3>Suche nach: abenteuerliche Bibliothekaren gehen Schwimmen</h3>";
$result = R::stemmedSearch($word, "name", "abenteuerliche Bibliothekaren gehen Schwimmen");
foreach($result as $bean){
  echo $bean->id . " " . $bean->name . "<br />";
}

// to simply stem without search use this
var_dump(R::stem("neuerliche Ausschreitungen"));
