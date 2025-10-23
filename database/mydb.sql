CREATE DATABASE db_bank_sampah_bu;
USE db_bank_sampah_bu;

-- 1. Tabel untuk pengguna (admin & nasabah)
CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Gunakan password_hash() di PHP
    role ENUM('admin', 'nasabah') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabel data nasabah (siswa)
CREATE TABLE nasabah (
    id_nasabah INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL UNIQUE, -- Foreign key ke tabel users
    nis VARCHAR(20) UNIQUE,
    nama_lengkap VARCHAR(100) NOT NULL,
    kelas VARCHAR(50),
    saldo BIGINT NOT NULL DEFAULT 0,
    -- Ini untuk menyimpan hasil clustering
    id_klaster INT DEFAULT NULL,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
);

-- 3. Tabel jenis sampah
CREATE TABLE jenis_sampah (
    id_sampah INT AUTO_INCREMENT PRIMARY KEY,
    nama_sampah VARCHAR(100) NOT NULL,
    satuan VARCHAR(10) NOT NULL, -- (misal: 'kg', 'pcs')
    harga_beli BIGINT NOT NULL -- Harga yg diterima nasabah per satuan
);

-- 4. Tabel transaksi setoran
CREATE TABLE transaksi_setor (
    id_setor INT AUTO_INCREMENT PRIMARY KEY,
    id_nasabah INT NOT NULL,
    id_sampah INT NOT NULL,
    berat DECIMAL(10, 2) NOT NULL, -- Berat/jumlah dalam satuan
    total_harga BIGINT NOT NULL,   -- (berat * harga_beli)
    tanggal_setor TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dicatat_oleh INT NOT NULL,     -- id_user admin yang mencatat
    FOREIGN KEY (id_nasabah) REFERENCES nasabah(id_nasabah),
    FOREIGN KEY (id_sampah) REFERENCES jenis_sampah(id_sampah),
    FOREIGN KEY (dicatat_oleh) REFERENCES users(id_user)
);

-- 5. Tabel transaksi penarikan saldo
CREATE TABLE transaksi_tarik (
    id_tarik INT AUTO_INCREMENT PRIMARY KEY,
    id_nasabah INT NOT NULL,
    jumlah_tarik BIGINT NOT NULL,
    catatan TEXT,
    tanggal_tarik TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dicatat_oleh INT NOT NULL,     -- id_user admin yang mencatat
    FOREIGN KEY (id_nasabah) REFERENCES nasabah(id_nasabah),
    FOREIGN KEY (dicatat_oleh) REFERENCES users(id_user)
);

ALTER TABLE transaksi_tarik ADD COLUMN catatan TEXT AFTER jumlah_tarik;

-- 6. Tabel untuk menyimpan definisi klaster (opsional, tapi bagus)
CREATE TABLE klaster_info (
    id_klaster INT PRIMARY KEY,
    nama_klaster VARCHAR(50) NOT NULL, -- Misal: 'Sangat Aktif', 'Kurang Aktif'
    deskripsi TEXT
);

CREATE TABLE IF NOT EXISTS pengepul (
    id_pengepul INT AUTO_INCREMENT PRIMARY KEY,
    nama_pengepul VARCHAR(100) NOT NULL,
    kontak_pengepul VARCHAR(50)
);

DROP TABLE IF EXISTS transaksi_jual;
CREATE TABLE transaksi_jual (
    id_jual INT AUTO_INCREMENT PRIMARY KEY,
    id_sampah INT NOT NULL,
    id_pengepul INT,
    berat DECIMAL(10, 2) NOT NULL,
    harga_jual_per_kg BIGINT NOT NULL,
    total_pendapatan BIGINT NOT NULL,
    tanggal_jual TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dicatat_oleh INT NOT NULL,
    FOREIGN KEY (id_sampah) REFERENCES jenis_sampah(id_sampah),
    FOREIGN KEY (id_pengepul) REFERENCES pengepul(id_pengepul),
    FOREIGN KEY (dicatat_oleh) REFERENCES users(id_user)
);

CREATE TABLE penjualan (
    id_penjualan INT AUTO_INCREMENT PRIMARY KEY, -- ID unik untuk setiap penjualan
    id_sampah INT NOT NULL,                      -- ID jenis sampah yang dijual
    id_pengepul INT NOT NULL,                    -- ID pengepul yang membeli sampah
    berat DECIMAL(10, 2) NOT NULL,               -- Berat sampah yang dijual (kg)
    harga_jual_per_kg BIGINT NOT NULL,           -- Harga jual per kg
    total_pendapatan BIGINT NOT NULL,            -- Total pendapatan (berat * harga_jual_per_kg)
    tanggal_jual TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Tanggal penjualan
    FOREIGN KEY (id_sampah) REFERENCES jenis_sampah(id_sampah), -- Relasi ke tabel jenis_sampah
    FOREIGN KEY (id_pengepul) REFERENCES pengepul(id_pengepul)  -- Relasi ke tabel pengepul
);

-- Masukkan data awal
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$E.qR3Ea/fR2jW9Y8qK1XkOp1q.Pf4.5Y5.F3.Z6.Z8.X3.Y4.Z5.X6', 'admin'), -- Ganti password ini!
('siswa1', '$2y$10$W.qR3Ea/fR2jW9Y8qK1XkOp1q.Pf4.5Y5.F3.Z6.Z8.X3.Y4.Z5.X7', 'nasabah'); -- Buat dgn password_hash()

INSERT INTO nasabah (id_user, nis, nama_lengkap, kelas) VALUES
(2, '1001', 'Budi Santoso', 'SMP 7A');

INSERT INTO jenis_sampah (nama_sampah, satuan, harga_beli) VALUES
('Botol Plastik', 'kg', 3000),
('Kardus', 'kg', 1500),
('Kertas HVS', 'kg', 2000);

INSERT INTO klaster_info (id_klaster, nama_klaster, deskripsi) VALUES
(0, 'Klaster 0: Kurang Aktif', 'Nasabah dengan frekuensi dan volume setoran rendah.'),
(1, 'Klaster 1: Cukup Aktif', 'Nasabah dengan frekuensi dan volume setoran sedang.'),
(2, 'Klaster 2: Sangat Aktif', 'Nasabah dengan frekuensi dan volume setoran tinggi.');

INSERT INTO pengepul (nama_pengepul, kontak_pengepul) 
VALUES ('Pengepul A - Pak Budi', '08123456789')
ON DUPLICATE KEY UPDATE kontak_pengepul = VALUES(kontak_pengepul);

-- 1. Hapus data lama (jika ada) agar tidak bentrok
DELETE FROM nasabah WHERE nis = '1001';
DELETE FROM users WHERE username IN ('admin', 'siswa1');

-- 2. Masukkan 'admin' dengan password Base64 'YWRtaW4=' (admin)
INSERT INTO users (username, password, role) VALUES
('admin', 'YWRtaW4=', 'admin');

-- 3. Masukkan 'siswa1' dengan password Base64 'c2lzd2Ex' (siswa1)
INSERT INTO users (username, password, role) VALUES
('siswa1', 'c2lzd2Ex', 'nasabah');

-- 4. Hubungkan 'siswa1' ke data nasabahnya
-- (Ini penting agar login siswa1 tidak error)
INSERT INTO nasabah (id_user, nis, nama_lengkap, kelas) VALUES
(
    (SELECT id_user FROM users WHERE username = 'siswa1'), 
    '1001', 
    'Budi Santoso', 
    'SMP 7A'
);

select * from users, nasabah, jenis_sampah, klaster_info;

-- Mengubah password menjadi plain text 'admin'
UPDATE users 
SET password = 'admin' 
WHERE username = 'admin';

-- Mengubah password menjadi plain text 'siswa1'
UPDATE users 
SET password = 'siswa1' 
WHERE username = 'siswa1';