<?php
	$dbhost = 'localhost';
	$dbuser = 'root';
	$dbpass = '';
	$dbname = 'FelixBus';

	$conn = mysqli_connect($dbhost, $dbuser, $dbpass);
	if (!$conn)
		die('Falha tecnica: '. mysqli_error($conn));

	mysqli_select_db($conn, $dbname);
?>