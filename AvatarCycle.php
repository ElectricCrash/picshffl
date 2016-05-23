<?php
header('Content-Type: text/html; charset=utf-8');

//  Twitter Setup
// Create app and tokens at https://apps.twitter.com/
$auth = array(
	"consumer_key" => "RGOfgtsaZFB2bwxxhMTWQFMU1",
	"consumer_secret" => "4F8RCtnxqFhDB66bvCkw2u7LQknOuZ4CHmWyTQtvEVSLFE5CFy",
	"user_token" => "2544959155-jgzKZMwASGjjlO1QZdJDYdea4ZjEuhEEucjUNBq",
	"user_secret" => "bq5j31H7s46NlW8iCeGLLzZCchKqm1Oko6Nqar47TxCbi");

require_once "twitter.class.php";
$twitter = new Twitter($auth["consumer_key"], $auth["consumer_secret"], $auth["user_token"], $auth["user_secret"]);

// Absolute path to avatar folder
$dir    = 'C:\Users\Antonio\Twitter Avatars';
$avatarList = array_diff(scandir($dir), array('..', '.'));

$select = array_rand($avatarList);


$image = $dir . "/" . $avatarList[$select];
$type = pathinfo($image, PATHINFO_EXTENSION);
$data = file_get_contents($image);
$base64 = base64_encode($data);

try {
	
	//echo "Updating Avatar...\n";
	$update = $twitter->updateAvatar($base64);
	if(!$update) echo "Update failed.\n";
	
} catch(TwitterException $e) {
	echo "Error updating Avatar: ".$e->getMessage()."\n";
}

?>
