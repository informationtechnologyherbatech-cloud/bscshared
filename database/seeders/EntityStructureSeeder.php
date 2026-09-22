<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\WorkUnit;
use Illuminate\Database\Seeder;

/**
 * Struktur awal empat entitas di bawah holding Erhanesia Mulia Corpora, beserta
 * unit kerja masing-masing.
 *
 * Idempoten: aman dijalankan berulang dan tidak pernah menimpa perubahan yang
 * sudah dibuat pengguna lewat menu Unit Kerja — baris yang sudah ada dibiarkan.
 */
class EntityStructureSeeder extends Seeder
{
    /**
     * Unit kerja manufaktur, dari katalog 11 departemen pada
     * docs/dokumentasi-integrasi-hris-finance.md. Dipakai bersama oleh ketiga
     * entitas manufaktur sebagai titik awal; tiap entitas dapat mengubahnya.
     */
    private const UNIT_MANUFAKTUR = [
        ['OPS', 'Operasional Pabrik', 'Operasional'],
        ['SCM', 'Supply Chain Management', 'Operasional'],
        ['PRC', 'Procurement / Pengadaan', 'Operasional'],
        ['PRO', 'Produksi & Engineering', 'Operasional'],
        ['RND', 'Research & Development', 'Operasional'],
        ['QLT', 'Quality Management', 'Mutu'],
        ['QC', 'Quality Control', 'Mutu'],
        ['QA', 'Quality Assurance', 'Mutu'],
        ['GA', 'General Affair & Legal', 'Business Support'],
        ['HRD', 'Human Capital / HRD', 'Business Support'],
        ['FIN', 'Finance, Accounting & Tax', 'Business Support'],
    ];

    /**
     * Unit kerja Erdigma, dari sheet "Asumsi" bagian E pada
     * Cascading_Revenue_Rasio_KPI_Erdigma_2026.xlsx — 3 stream + business support.
     */
    private const UNIT_ERDIGMA = [
        ['MFG', 'Manufacture — Herbatech (GM Factory)', 'Upstream', 'GM Factory → CEO', 'Manajemen terpisah dari Erdigma; pemasok HPP — diperlakukan hanya sebagai Kontributor'],
        ['PDV', 'Product Development (+ Category Product Development)', 'Midstream EICK', 'GM EICK', 'Pengembangan produk bersama maklon'],
        ['SCM', 'Supply Chain (Demand Planning, Fulfillment Center, Inventory Control, Last Mile Delivery)', 'Midstream EICK', 'GM EICK', 'Demand planning dengan manufacture, kontrol target produksi, warehouse, last mile'],
        ['CMP', 'Compliance & Regulatory', 'Midstream EICK', 'GM EICK', 'Menertibkan iklan overclaim, permainan harga, pemalsu'],
        ['OFD', 'Offline Distribution', 'Midstream EICK', 'GM EICK', 'Modern Trade lokal & national account'],
        ['PTN', 'EDX Partner', 'Midstream EICK', 'GM EICK', 'Mencari partner yang membangun model bisnis serupa di platform digital & menjual produk'],
        ['SOC', 'Social Commerce — BU Brand', 'Downstream EDX & EDM', 'CBO', 'Seluruh social media kecuali TikTok; satu BU per brand'],
        ['TTC', 'TikTok Commerce — BU Brand', 'Downstream EDX & EDM', 'CBO', 'TikTok Shop termasuk affiliate & live; satu BU per brand'],
        ['ECO', 'E-Commerce (lintas brand, kecuali TikTok Shop)', 'Downstream EDX & EDM', 'CBO', 'Marketplace, satu unit lintas brand'],
        ['CXP', 'Customer Experience', 'Downstream EDX & EDM', 'CBO', 'Customer acquisition & CRM di channel social commerce'],
        ['BMK', 'Brand Marketing', 'Downstream EDX & EDM', 'CBO', 'Aktivasi brand di level awareness'],
        ['FAT', 'Finance, Accounting & Tax', 'Business Support', 'CFO', 'Enabler keuangan: pembukuan, pajak, kas, penagihan'],
        ['LGL', 'Legal', 'Business Support', 'CFO', 'Enabler hukum: kontrak, perizinan, perlindungan merek'],
        ['HRG', 'HR & GA', 'Business Support', 'CEO', 'Enabler SDM & umum: rekrutmen, payroll, fasilitas'],
        ['ERS', 'Erspace', 'Business Support', 'CEO', 'Enabler (lingkup diisi sesuai fungsi aktual Erspace)'],
        ['SEC', 'Secretary', 'Business Support', 'CEO', 'Enabler kesekretariatan & korporasi'],
        ['DIT', 'Data & IT', 'Business Support', 'CBO', 'Enabler data & sistem'],
    ];

    /**
     * Empat entitas operasional, urut seperti disebutkan manajemen.
     *
     * @return array<int, array{code: string, name: string, legal_name: string, industry: string}>
     */
    public static function entities(): array
    {
        return [
            ['code' => 'HERBAEMAS', 'name' => 'Herbaemas', 'legal_name' => 'PT Herba Emas Wahidatama', 'industry' => Entity::MANUFAKTUR],
            ['code' => 'HERBATECH', 'name' => 'Herbatech', 'legal_name' => 'PT Herbatech Innopharma Industry', 'industry' => Entity::MANUFAKTUR],
            ['code' => 'AEJ', 'name' => 'AEJ', 'legal_name' => 'PT Abithama Emas Juara', 'industry' => Entity::MANUFAKTUR],
            ['code' => 'ERDIGMA', 'name' => 'Erdigma', 'legal_name' => 'PT Erhanesia Digima Mukitama', 'industry' => Entity::DIGITAL_MARKETING],
        ];
    }

    public function run(): void
    {
        foreach (self::entities() as $urutan => $data) {
            $entity = Entity::firstOrCreate(
                ['code' => $data['code']],
                $data + ['sort' => $urutan + 1, 'is_active' => true]
            );

            $katalog = $data['industry'] === Entity::DIGITAL_MARKETING
                ? self::UNIT_ERDIGMA
                : self::UNIT_MANUFAKTUR;

            foreach ($katalog as $i => $unit) {
                WorkUnit::withoutGlobalScopes()->firstOrCreate(
                    ['entity_id' => $entity->id, 'code' => $unit[0]],
                    [
                        'name' => $unit[1],
                        'stream' => $unit[2] ?? null,
                        'reports_to' => $unit[3] ?? null,
                        'scope' => $unit[4] ?? null,
                        'is_active' => true,
                        'sort' => $i + 1,
                    ]
                );
            }
        }
    }
}
