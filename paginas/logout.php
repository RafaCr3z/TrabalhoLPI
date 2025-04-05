<?php
session_start();
include '../basedados/basedados.h';

session_destroy();
header("Location: index.php");
exit();
?>
