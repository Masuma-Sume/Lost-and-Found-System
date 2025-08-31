<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname="brac_lost_and_found";

//creating connection

$conn = new mysqli($servername, $username, $password);

//check connection
if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
	}
//else{
     //mysql_select_db($conn,$dbname);
    // echo "Connection successful";
	 //}
	 
	 
	 
?>	 
