<?php
// /lib/KMeans.php

class KMeans {
    protected $data = [];
    protected $centroids = [];
    protected $clusters = [];
    protected $k;

    public function __construct($k) {
        if ($k <= 0) {
            throw new Exception("Jumlah klaster (k) harus lebih besar dari 0");
        }
        $this->k = (int) $k;
    }

    /**
     * Memuat data yang akan di-cluster.
     * Data harus berupa array 2D, misal: [[10, 5], [12, 8], [9, 3]]
     * $originalData opsional untuk menyimpan data asli (misal: id_nasabah)
     */
    public function loadData(array $data, array $originalData = []) {
        $this->data = [];
        $i = 0;
        foreach ($data as $point) {
            if (!is_array($point) || count($point) < 1) {
                throw new Exception("Data point harus berupa array numerik");
            }
            // Simpan data point dan data aslinya (ID)
            $this->data[$i] = [
                'point' => $point,
                'original' => $originalData[$i] ?? null
            ];
            $i++;
        }
    }

    /**
     * Menjalankan proses clustering.
     */
    public function run($maxIterations = 100) {
        if (empty($this->data)) {
            throw new Exception("Data belum dimuat. Gunakan loadData().");
        }
        if (count($this->data) < $this->k) {
            throw new Exception("Jumlah data ( " . count($this->data) . " ) lebih sedikit dari jumlah klaster ( " . $this->k . " ).");
        }

        $this->initializeCentroids();

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            // 1. Assign setiap data point ke centroid terdekat
            $this->assignClusters();

            // 2. Hitung ulang posisi centroid berdasarkan rata-rata data point di klaster tsb
            $newCentroids = $this->calculateNewCentroids();

            // 3. Cek konvergensi (jika centroid tidak berubah)
            if ($this->centroids === $newCentroids) {
                break; // Proses selesai
            }

            $this->centroids = $newCentroids;
        }

        return $this->getClusters();
    }

    /**
     * Mendapatkan hasil akhir clustering
     */
    public function getClusters() {
        $result = [];
        for ($i = 0; $i < $this->k; $i++) {
            $result[$i] = [
                'centroid' => $this->centroids[$i],
                'data_points' => []
            ];
        }

        foreach ($this->clusters as $dataIndex => $clusterIndex) {
            $result[$clusterIndex]['data_points'][] = $this->data[$dataIndex];
        }
        return $result;
    }

    // --- Fungsi Internal ---

    protected function initializeCentroids() {
        // Ambil K data point pertama secara acak sebagai centroid awal
        $indices = array_rand($this->data, $this->k);
        if (!is_array($indices)) $indices = [$indices]; // Handle jika k=1

        $this->centroids = [];
        foreach ($indices as $index) {
            $this->centroids[] = $this->data[$index]['point'];
        }
    }

    protected function assignClusters() {
        $this->clusters = [];
        foreach ($this->data as $dataIndex => $data) {
            $point = $data['point'];
            $bestCluster = -1;
            $minDistance = INF;

            foreach ($this->centroids as $clusterIndex => $centroid) {
                $distance = $this->euclideanDistance($point, $centroid);
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $bestCluster = $clusterIndex;
                }
            }
            $this->clusters[$dataIndex] = $bestCluster;
        }
    }

    protected function calculateNewCentroids() {
        $newCentroids = [];
        $clusterSums = array_fill(0, $this->k, array_fill(0, count($this->data[0]['point']), 0));
        $clusterCounts = array_fill(0, $this->k, 0);

        foreach ($this->clusters as $dataIndex => $clusterIndex) {
            $point = $this->data[$dataIndex]['point'];
            foreach ($point as $dimension => $value) {
                $clusterSums[$clusterIndex][$dimension] += $value;
            }
            $clusterCounts[$clusterIndex]++;
        }

        for ($i = 0; $i < $this->k; $i++) {
            if ($clusterCounts[$i] > 0) {
                // Hitung rata-rata
                $newCentroids[$i] = array_map(function($sum) use ($clusterCounts, $i) {
                    return $sum / $clusterCounts[$i];
                }, $clusterSums[$i]);
            } else {
                // Jika klaster kosong, jangan ubah centroid-nya (atau bisa di-inisialisasi ulang)
                $newCentroids[$i] = $this->centroids[$i];
            }
        }
        return $newCentroids;
    }

    /**
     * Menghitung Jarak Euclidean (standar K-Means)
     * antara 2 titik (array)
     */
    protected function euclideanDistance(array $point1, array $point2) {
        $sum = 0;
        foreach ($point1 as $dimension => $value) {
            $sum += pow($value - $point2[$dimension], 2);
        }
        return sqrt($sum);
    }
}
?>