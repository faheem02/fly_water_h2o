<?php
$conn = mysqli_connect("localhost", "root", "", "fly_water_h2o");

if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}

$software_name = "Fly Water H2O";
$company_name = "Fly Water H2O";
$owner_name = "Muhammad Saqib";
$owner_address = "78 Canal Avenue, Rahim Yar Khan";
$owner_phone = "0317-6759600";
?>
