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