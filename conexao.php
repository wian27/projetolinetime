<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "projetoline";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 
$id = 0 ;
$nome = $_POST['nome'];
$ultimonome = $_POST['ultimonome'];
$email = $_POST ['email']; 
$senha  =  $_POST['senha'];
$cidade = $_POST ['cidade'];




$sql = "INSERT INTO usuarios (nome, ultimonome, email, senha, cidade)
VALUES ('$nome', '$ultimonome', '$email', ' $senha', '$cidade')";


if ($conn->query($sql) === TRUE) {
    echo "New record created successfully";

} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>