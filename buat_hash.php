<?php

/*
    buat_hash.php
    Script sederhana untuk membuat hash password menggunakan password_hash()
    Gunakan ini untuk menghasilkan hash password yang aman untuk pengguna baru atau mengubah password.
*/

// HASH UNTUK ADMIN
$passwordAdmin = 'admin';
$hashAdmin = password_hash($passwordAdmin, PASSWORD_DEFAULT);
echo "Hash untuk 'admin': " . $hashAdmin . "<br>";

// HASH UNTUK SISWA1
$passwordSiswa = 'siswa1';
$hashSiswa = password_hash($passwordSiswa, PASSWORD_DEFAULT);
echo "Hash untuk 'siswa1': " . $hashSiswa . "<br>";

// HASH UNTUK SISWA2
$passwordSiswa2 = 'siswa2';
$hashSiswa2 = password_hash($passwordSiswa2, PASSWORD_DEFAULT);
echo "Hash untuk 'siswa2': " . $hashSiswa2 . "<br>";

$passwordSiswa3 = 'siswa3';
$hashSiswa3 = password_hash($passwordSiswa3, PASSWORD_DEFAULT);
echo "Hash untuk 'siswa3': " . $hashSiswa3 . "<br>";

?>