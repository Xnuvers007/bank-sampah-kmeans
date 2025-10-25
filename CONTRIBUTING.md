# 🤝 Contributing to Bank Sampah Digital

Terima kasih telah tertarik untuk berkontribusi pada Bank Sampah Digital! Kami sangat menghargai kontribusi Anda dalam membantu mengembangkan sistem manajemen bank sampah yang lebih baik.

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

## 🌟 Code of Conduct

Dengan berpartisipasi dalam proyek ini, Anda diharapkan untuk menjunjung tinggi kode etik kami:
- Bersikap hormat dan inklusif terhadap semua kontributor
- Menggunakan bahasa yang sopan dan konstruktif
- Fokus pada apa yang terbaik untuk komunitas
- Menunjukkan empati terhadap anggota komunitas lainnya

## 🚀 Getting Started

### Prerequisites
Pastikan Anda telah menginstall:
- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Apache/Nginx web server
- Composer (untuk dependency management)
- Git

### 1. Fork Repository
1. Fork repository ini ke akun GitHub Anda
2. Clone fork Anda ke local machine

### 2. Development Setup
```bash
# Clone repository
git clone https://github.com/Xnuvers007/bank-sampah-kmeans.git
cd bank-sampah-kmeans

# Setup database
mysql -u root -p < database/mydb.sql

# Install dependencies (jika menggunakan Composer)
composer install

# Copy dan configure environment file
cp config/db.example.php config/db.php
# Edit db.php sesuai dengan setup local Anda

# Setup virtual host atau jalankan development server
php -S localhost:8000
```

## 🛠️ Contributing Guidelines

### Jenis Kontribusi yang Kami Terima
- 🐛 Bug fixes
- ✨ New features
- 📚 Documentation improvements
- 🎨 UI/UX improvements
- 🔧 Code refactoring