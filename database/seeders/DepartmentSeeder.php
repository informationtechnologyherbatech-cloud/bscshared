<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $depts = [
            ['code' => 'OPS', 'name' => 'Operations', 'manager_title' => 'Operation Manager', 'description' => 'Operasional pabrik, rantai pasok dan kelancaran produksi herbal.', 'color' => '#059669', 'sort_order' => 1],
            ['code' => 'MKT', 'name' => 'Marketing & Sales', 'manager_title' => 'Marketing & Sales Manager', 'description' => 'Pemasaran produk herbal, penjualan kanal distributor dan penetrasi pasar.', 'color' => '#0284c7', 'sort_order' => 2],
            ['code' => 'FAT', 'name' => 'Finance, Accounting & Tax', 'manager_title' => 'Finance & Accounting Manager', 'description' => 'Pengelolaan keuangan, akuntansi, arus kas, dan perpajakan.', 'color' => '#10b981', 'sort_order' => 3],
            ['code' => 'PROD', 'name' => 'Production', 'manager_title' => 'Production Manager', 'description' => 'Pelaksanaan produksi obat bahan alam dan formulasi sediaan herbal.', 'color' => '#f59e0b', 'sort_order' => 4],
            ['code' => 'QC', 'name' => 'Quality Control', 'manager_title' => 'QC Manager', 'description' => 'Pengujian mutu bahan baku, produk antara, dan sediaan jadi.', 'color' => '#8b5cf6', 'sort_order' => 5],
            ['code' => 'QA', 'name' => 'Quality Assurance', 'manager_title' => 'QA Manager', 'description' => 'Penjaminan mutu CPOTB dan kepatuhan regulasi BPOM.', 'color' => '#ec4899', 'sort_order' => 6],
            ['code' => 'RND', 'name' => 'Research & Development', 'manager_title' => 'R&D Manager', 'description' => 'Riset ekstrak herbal dan inovasi produk formulasi baru.', 'color' => '#06b6d4', 'sort_order' => 7],
            ['code' => 'PROC', 'name' => 'Procurement', 'manager_title' => 'Procurement Manager', 'description' => 'Pengadaan bahan baku herbal, simplisia, dan bahan kemas.', 'color' => '#6366f1', 'sort_order' => 8],
            ['code' => 'HRGA', 'name' => 'Human Resources & General Affair', 'manager_title' => 'HR & GA Manager', 'description' => 'Pengembangan SDM, pelatihan, kepatuhan ketenagakerjaan dan fasilitas.', 'color' => '#64748b', 'sort_order' => 9],
            ['code' => 'ENG', 'name' => 'Engineering', 'manager_title' => 'Engineering Manager', 'description' => 'Pemeliharaan mesin produksi ekstraksi dan utilitas pabrik.', 'color' => '#d97706', 'sort_order' => 10],
            ['code' => 'IT', 'name' => 'Information Technology', 'manager_title' => 'IT Manager', 'description' => 'Infrastruktur teknologi, integrasi sistem Odoo ERP dan keamanan data.', 'color' => '#0ea5e9', 'sort_order' => 11],
            ['code' => 'LOG', 'name' => 'Logistics & Warehouse', 'manager_title' => 'Logistics Manager', 'description' => 'Gudang bahan baku, karantina, dan distribusi produk jadi.', 'color' => '#84cc16', 'sort_order' => 12],
        ];

        foreach ($depts as $d) {
            Department::updateOrCreate(['code' => $d['code']], $d);
        }
    }
}
