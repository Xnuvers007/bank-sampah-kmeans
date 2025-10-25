# 🤝 Contributing to Bank Sampah Digital

Terima kasih telah tertarik untuk berkontribusi pada Bank Sampah Digital! Kami sangat menghargai kontribusi Anda dalam membantu mengembangkan sistem manajemen bank sampah yang lebih baik.

---

## 📋 Table of Contents
- [Code of Conduct](#-code-of-conduct)
- [Getting Started](#-getting-started)
- [Development Setup](#-development-setup)
- [Contributing Guidelines](#-contributing-guidelines)
- [Pull Request Process](#-pull-request-process)
- [Issue Guidelines](#-issue-guidelines)
- [Coding Standards](#-coding-standards)
- [Testing](#-testing)
- [Documentation](#-documentation)

---

## 🌟 Code of Conduct

Dengan berpartisipasi dalam proyek ini, Anda diharapkan untuk menjunjung tinggi kode etik kami:
- Bersikap hormat dan inklusif terhadap semua kontributor.
- Menggunakan bahasa yang sopan dan konstruktif.
- Fokus pada apa yang terbaik untuk komunitas.
- Menunjukkan empati terhadap anggota komunitas lainnya.

---

## 🚀 Getting Started

### Prerequisites
Pastikan Anda telah menginstal:
- **PHP** 7.4 atau lebih tinggi.
- **MySQL** 5.7 atau lebih tinggi.
- **Apache/Nginx** web server.
- **Composer** (untuk dependency management).
- **Git**.

---

## 🛠️ Development Setup

### 1. Fork Repository
1. Fork repository ini ke akun GitHub Anda.
2. Clone fork Anda ke local machine.

```bash
# Clone repository
git clone https://github.com/Xnuvers007/bank-sampah-kmeans.git
cd bank-sampah-kmeans
```

### 2. Setup Database
```bash
# Import database schema
mysql -u root -p < database/mydb.sql
```

### 3. Install Dependencies
```bash
# Install dependencies menggunakan Composer
composer install
```

### 4. Configure Environment
```bash
# Copy dan konfigurasi file environment
# see in database.php too || lihat di database.php juga
cp config/db.example.php config/db.php
cat config/db.php
cat config/database.php

# Edit db.php sesuai dengan setup lokal Anda
```

### 5. Jalankan Development Server
```bash
# Jalankan development server menggunakan PHP
php -S localhost:8000

# Atau gunakan Apache/Nginx sesuai setup Anda
systemctl start apache2 || systemctl start nginx
systemctl start mysql
```

---

## 🛠️ Contributing Guidelines

### Jenis Kontribusi yang Kami Terima
- 🐛 **Bug fixes**: Perbaikan bug yang ditemukan di aplikasi.
- ✨ **New features**: Penambahan fitur baru yang relevan.
- 📚 **Documentation improvements**: Perbaikan atau penambahan dokumentasi.
- 🎨 **UI/UX improvements**: Peningkatan desain antarmuka pengguna.
- 🔧 **Code refactoring**: Peningkatan kualitas kode tanpa mengubah fungsionalitas.

### Panduan Umum
1. Pastikan kode Anda mengikuti standar coding yang telah ditentukan.
2. Sertakan deskripsi yang jelas untuk setiap perubahan yang Anda buat.
3. Tambahkan komentar pada kode jika diperlukan untuk menjelaskan logika yang kompleks.
4. Pastikan semua perubahan telah diuji sebelum diajukan.

---

## 🔄 Pull Request Process

1. **Fork dan Clone**: Fork repository ini dan clone ke local machine Anda.
2. **Buat Branch Baru**: Buat branch baru untuk setiap fitur atau perbaikan.
   ```bash
   git checkout -b nama-branch-anda
   ```
3. **Commit Perubahan**: Commit perubahan Anda dengan pesan yang jelas.
   ```bash
   git commit -m "Deskripsi perubahan"
   ```
4. **Push ke Fork Anda**: Push branch Anda ke fork Anda di GitHub.
   ```bash
   git push origin nama-branch-anda
   ```
5. **Buat Pull Request**: Ajukan pull request ke branch `main` repository ini.

---

## 🐞 Issue Guidelines

- Jelaskan masalah dengan jelas dan detail.
- Sertakan langkah-langkah untuk mereproduksi masalah.
- Jika memungkinkan, sertakan screenshot atau log error.

---

## 🧹 Coding Standards

- Gunakan **PSR-12** sebagai standar coding untuk PHP.
- Gunakan nama variabel dan fungsi yang deskriptif.
- Hindari kode yang tidak digunakan (dead code).
- Format kode Anda sebelum commit.

---

## 🧪 Testing

- Pastikan semua fitur yang Anda tambahkan telah diuji.
- Gunakan PHPUnit untuk menulis dan menjalankan unit test.
- Jalankan semua test sebelum mengajukan pull request.

---

## 📚 Documentation

- Perbarui dokumentasi jika Anda menambahkan fitur baru.
- Dokumentasi harus jelas dan mudah dipahami.

---

Terima kasih atas kontribusi Anda! 🌟