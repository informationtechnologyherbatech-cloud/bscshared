# 📘 Pedoman Standar Dokumentasi API & Postman Collection
## Super Apps Balanced Scorecard (BSC Hop 4) — PT Herbatech Innopharma

---

## 📑 Informasi Dokumen
| Atribut | Keterangan |
| :--- | :--- |
| **Nama Proyek** | Super Apps BSC Hop 4 - Integrasi & Staging Gateway |
| **Organisasi** | PT Herbatech Innopharma |
| **Versi Dokumen** | 1.0.0 |
| **Format Standar** | Postman Collection Schema v2.1.0 |
| **Target Pengguna** | Backend Developer, Frontend Developer, System Integrator, QA Engineer |

---

## 1. Pendahuluan & Tujuan
Dokumen ini disusun sebagai pedoman baku dalam merancang, menyusun, mendokumentasikan, dan menguji API (*Application Programming Interface*) sistem **Super Apps BSC** menggunakan **Postman**. 

### Tujuan Utama:
1. **Konsistensi**: Memastikan seluruh *endpoint* memiliki struktur dokumentasi, penamaan, dan format payload yang seragam.
2. **Efisiensi Integrasi**: Mempermudah integrasi lintas modul (Finance ERP, HRIS, Logistik, Produksi, dan Quality Control).
3. **Auditabilitas & Idempotensi**: Menjamin setiap transaksi masuk (*inbound payload*) dapat dilacak (*staging log*) dan aman dari duplikasi (*idempotent*).
4. **Otomatisasi Pengujian**: Menyediakan *test scripts* dan *assertions* otomatis di setiap request Postman.

---

## 2. Standar Struktur Folder Postman Collection

Susun Postman Collection dengan hierarki modular berdasarkan domain fungsional BSC:

```text
📁 [Super Apps BSC - Hop 4 API] (Collection Root)
 ├── 📄 Collection Overview & Quickstart Guide
 ├── 📁 01. Authentication & System Health
 │    ├── [POST] /api/v1/auth/token             -> Generate Gateway Token
 │    └── [GET]  /api/v1/health                 -> System Health & Ping Status
 ├── 📁 02. Level 2 - Finance Integration (CoA & Rasio)
 │    ├── [POST] /api/v1/bsc/sync/finance-coa   -> Inbound Jurnal CoA Finance
 │    └── [GET]  /api/v1/bsc/finance/ratios     -> Get Realtime 7 Rasio Keuangan
 ├── 📁 03. Level 3 - Department Objectives (KPI Departemen)
 │    ├── [POST] /api/v1/bsc/sync/dept-kpi      -> Inbound KPI Capaian Departemen
 │    ├── [GET]  /api/v1/bsc/objectives         -> Get KPI List by Department
 │    └── [PUT]  /api/v1/bsc/objectives/:id     -> Update Target & Actual KPI
 ├── 📁 04. Level 4 - Action Plans (Program Kerja Mitigasi)
 │    ├── [GET]  /api/v1/bsc/action-plans       -> Get Program Kerja by Status
 │    └── [POST] /api/v1/bsc/action-plans/sync  -> Sync Progress & Evidence URL
 ├── 📁 05. Staging Pipeline & Audit Logs
 │    ├── [POST] /api/v1/bsc/staging/batch-csv  -> Mass Inbound Upload (CSV/JSON)
 │    └── [GET]  /api/v1/bsc/staging/logs       -> Query Staging Logs & Idempotency
```

---

## 3. Konfigurasi Postman Environment & Variables

Dilarang keras melakukan *hardcoding* URL host, kredensial, atau parameter dinamis di dalam URL request. Gunakan **Postman Environment** dengan standar variabel berikut:

### 3.1 Daftar Environment Variables
| Variable Key | Type | Deskripsi | Contoh Nilai (Local / Staging) |
| :--- | :--- | :--- | :--- |
| `baseUrl` | `default` | URL protokol & host backend API | `http://127.0.0.1:8000` / `http://sharedbsc.test` |
| `apiVersion` | `default` | Versi rilis API | `v1` |
| `apiKey` | `secret` | Kunci autentikasi API Gateway | `bsc_live_secret_key_2026_hop4` |
| `authToken` | `secret` | JWT / Bearer Token hasil login | `{{dynamic_token}}` |
| `activePeriod` | `default` | Periode pembukuan BSC aktif | `2026-08` |
| `idempotencyKey` | `default` | Kunci unik anti-duplikasi transaksi | `IDEMP-FIN-20260904-120000` |

---

## 4. Standar Header & Protokol Keamanan

Setiap request HTTP wajib memuat *header* standar berikut:

### 4.1 Header Wajib (Universal)
| Header Key | Value | Wajib? | Penjelasan |
| :--- | :--- | :---: | :--- |
| `Content-Type` | `application/json` | **Ya** | Tipe format body request |
| `Accept` | `application/json` | **Ya** | Memastikan response berformat JSON |
| `X-API-KEY` | `{{apiKey}}` | **Ya** | Autentikasi Gateway BSC Hop 4 |
| `X-Idempotency-Key` | `{{idempotencyKey}}` | **Kondisional** | **Wajib untuk semua metode `POST` dan `PUT`** |

---

## 5. Spesifikasi Detail Endpoint & Format Payload

Berikut adalah spesifikasi standar untuk setiap endpoint utama sistem Super Apps BSC:

---

### 5.1 [POST] Inbound Finance CoA Sync
* **Path**: `{{baseUrl}}/api/{{apiVersion}}/bsc/sync/finance-coa`
* **Metode**: `POST`
* **Deskripsi**: Menerima 8 saldo akun buku besar (CoA) dari ERP Finance untuk mengkalkulasi otomatis 7 Rasio Keuangan Utama (Current Ratio, DER, ITO, NPM, ROE, Rev/Emp, dll.) serta memperbarui Apex Score.

#### Request Headers:
```http
Content-Type: application/json
Accept: application/json
X-API-KEY: {{apiKey}}
X-Idempotency-Key: IDEMP-FIN-202608-001
```

#### Request Body (JSON):
```json
{
  "period": "2026-08",
  "dept_code": "FIN",
  "source_version": 1,
  "coa_data": {
    "sales": 96000.00,
    "hpp": 57600.00,
    "opex": 23400.00,
    "kas_bank": 12500.00,
    "piutang": 9800.00,
    "persediaan": 14200.00,
    "hutang_usaha": 8200.00,
    "modal_ekuitas": 73300.00
  }
}
```

#### Response Contoh 1: `200 OK` (Sukses)
```json
{
  "success": true,
  "status_code": 200,
  "message": "Data Finance CoA berhasil diterima dan 7 rasio keuangan berhasil diperbarui.",
  "data": {
    "period": "2026-08",
    "idempotency_key": "IDEMP-FIN-202608-001",
    "staging_status": "SCORED",
    "financial_summary": {
      "net_profit": 15000.00,
      "calculated_ratios": {
        "current_ratio": { "value": 4.45, "target": 2.00, "achievement_pct": 100.0, "status": "Tercapai" },
        "debt_to_equity": { "value": 0.11, "target": 0.50, "achievement_pct": 100.0, "status": "Tercapai" },
        "inventory_turnover": { "value": 4.06, "target": 5.00, "achievement_pct": 81.20, "status": "Waspada" },
        "net_profit_margin": { "value": 15.63, "target": 10.00, "achievement_pct": 100.0, "status": "Tercapai" },
        "return_on_equity": { "value": 20.46, "target": 15.00, "achievement_pct": 100.0, "status": "Tercapai" },
        "revenue_per_employee": { "value": 960.00, "target": 1000.00, "achievement_pct": 96.00, "status": "Waspada" }
      }
    }
  },
  "timestamp": "2026-09-04T12:00:00+07:00"
}
```

#### Response Contoh 2: `422 Unprocessable Entity` (Validasi Gagal)
```json
{
  "success": false,
  "status_code": 422,
  "error": "Unprocessable Entity",
  "message": "Validasi input payload gagal.",
  "errors": {
    "coa_data.sales": [
      "Nilai penjualan (sales) wajib diisi dan berupa angka numerik positif."
    ],
    "period": [
      "Format periode harus sesuai pola YYYY-MM."
    ]
  }
}
```

---

### 5.2 [POST] Inbound Department KPI Objective Sync
* **Path**: `{{baseUrl}}/api/{{apiVersion}}/bsc/sync/dept-kpi`
* **Metode**: `POST`
* **Deskripsi**: Menerima data realisasi KPI operasional dari masing-masing departemen (PROD, QA, MKT, LOG, HRD, IT, dll.).

#### Request Body (JSON):
```json
{
  "period": "2026-08",
  "dept_code": "PROD",
  "kpi_code": "KPI-PROD-001",
  "kpi_name": "Output Efisiensi Produksi Obat Herbal",
  "target": 98.00,
  "actual": 98.50,
  "unit": "%",
  "evidence_url": "https://docs.herbatech.co.id/qc/2026-08-ba-produksi.pdf",
  "notes": "Pencapaian batch Agustus sesuai standar GMP"
}
```

#### Response Contoh: `201 Created`
```json
{
  "success": true,
  "status_code": 201,
  "message": "KPI Departemen PROD berhasil disinkronkan dan diskor.",
  "data": {
    "id": 14,
    "period": "2026-08",
    "dept_code": "PROD",
    "kpi_code": "KPI-PROD-001",
    "achievement_pct": 100.00,
    "status": "Tercapai",
    "idempotency_key": "IDEMP-PROD-20260904-124000"
  },
  "timestamp": "2026-09-04T12:40:00+07:00"
}
```

---

### 5.3 [GET] Query Staging & Audit Logs
* **Path**: `{{baseUrl}}/api/{{apiVersion}}/bsc/staging/logs`
* **Metode**: `GET`
* **Query Parameters**:
  * `period` *(optional)*: `2026-08`
  * `dept_code` *(optional)*: `FIN`
  * `status` *(optional)*: `SCORED` / `PENDING` / `ERROR`
  * `page` *(optional)*: `1`

#### Response Contoh: `200 OK`
```json
{
  "success": true,
  "status_code": 200,
  "data": {
    "current_page": 1,
    "total_records": 128,
    "logs": [
      {
        "id": 1,
        "period": "2026-08",
        "dept_code": "FIN",
        "idempotency_key": "IDEMP-FIN-COA-20260904-120000",
        "status": "SCORED",
        "source_version": 1,
        "message": "Penerimaan Data Finance ERP Berhasil Disinkronkan.",
        "created_at": "2026-09-04 12:00:00"
      }
    ]
  }
}
```

---

## 6. Standar Postman Automation Scripts

Gunakan script otomatis pada Postman untuk mempermudah eksekusi dan validasi berulang:

### 6.1 Collection Level: Pre-Request Script
Script ini secara otomatis menghasilkan `idempotencyKey` unik berbasis *timestamp* dan *random hash* sebelum request dikirim:

```javascript
// Otomatisasi generate Idempotency Key jika belum ada
if (!pm.environment.get("idempotencyKey") || pm.environment.get("idempotencyKey").startsWith("IDEMP-AUTO")) {
    const timestamp = new Date().toISOString().replace(/[-:T.Z]/g, "").substring(0, 14);
    const randomHex = Math.random().toString(36).substring(2, 8).toUpperCase();
    const dynamicKey = `IDEMP-AUTO-${timestamp}-${randomHex}`;
    
    pm.environment.set("idempotencyKey", dynamicKey);
    console.log(`[Pre-Request] Generated Idempotency-Key: ${dynamicKey}`);
}
```

### 6.2 Request Level: Tests / Assertions Script
Tambahkan pada tab **Tests** untuk setiap request:

```javascript
// Test 1: Validasi Status Code Sukses
pm.test("Status response sukses (200 atau 201)", function () {
    pm.expect(pm.response.code).to.be.oneOf([200, 201]);
});

// Test 2: Validasi Response Time SLA (< 1000ms)
pm.test("SLA Response time di bawah 1000ms", function () {
    pm.expect(pm.response.responseTime).to.be.below(1000);
});

// Test 3: Validasi Struktur Standard Response JSON
pm.test("Response memiliki root key success, status_code, data, timestamp", function () {
    const json = pm.response.json();
    pm.expect(json).to.have.property("success");
    pm.expect(json).to.have.property("status_code");
    pm.expect(json).to.have.property("data");
    pm.expect(json).to.have.property("timestamp");
    pm.expect(json.success).to.be.true;
});
```

---

## 7. Format Standar Status & Kode Error API

Seluruh balasan API wajib mengikuti format error envelope standar:

| HTTP Code | Label | Deskripsi |
| :---: | :--- | :--- |
| `200` | **OK** | Permintaan berhasil diproses. |
| `201` | **Created** | Data baru berhasil dibuat & diskor. |
| `400` | **Bad Request** | Struktur JSON rusak atau header wajib tidak disertakan. |
| `401` | **Unauthorized** | Kunci `X-API-KEY` tidak disertakan atau tidak valid. |
| `403` | **Forbidden** | Pengguna tidak memiliki izin (*permission*) untuk mengakses resource. |
| `422` | **Unprocessable Entity** | Validasi aturan bisnis gagal (contoh: periode salah, saldo minus). |
| `429` | **Too Many Requests** | Melebihi batas *rate-limiting* API Gateway. |
| `500` | **Internal Server Error** | Terjadi kesalahan runtime internal server backend. |

---

## 8. Checklist Verifikasi Sebelum Publikasi Postman

Sebelum membagikan Collection kepada tim atau menerbitkan dokumentasi web (*Publish Docs*):

- [ ] **Variabel Teruji**: Seluruh endpoint menggunakan `{{baseUrl}}` dan tidak ada URL lokal `localhost` yang *hardcoded*.
- [ ] **Kunci Rahasia Terlindungi**: Pastikan nilai *Initial Value* untuk `apiKey` atau token rahasia pada Environment dalam keadaan kosong / dummy saat dibagikan.
- [ ] **Deskripsi Lengkap**: Semua folder dan request memiliki deskripsi fungsi bisnis yang jelas dalam format Markdown.
- [ ] **Contoh Response Lengkap**: Minimal terdapat 1 contoh response sukses (`200`/`201`) dan 1 contoh response error (`422`/`401`) per request.
- [ ] **Collection Runner Passed**: Eksekusi seluruh Collection menggunakan *Runner* dan pastikan 100% test assertions berstatus *Passed*.

---

## 9. Prosedur Ekspor & Distribusi Collection

1. **Ekspor File Collection JSON**:
   * Klik kanan nama Collection `[Super Apps BSC - Hop 4 API]` &rarr; Klik **Export**.
   * Pilih format **Collection v2.1 (recommended)** &rarr; Simpan file sebagai `BSC_SuperApps_Hop4_API.postman_collection.json`.
2. **Ekspor Environment JSON**:
   * Masuk ke tab **Environments** &rarr; Pilih Environment `BSC-Staging` / `BSC-Local`.
   * Klik titik tiga (**...**) &rarr; Pilih **Export** &rarr; Simpan file `BSC_Environment.postman_environment.json`.
3. **Dokumentasi Web Interaktif (Postman Web Docs)**:
   * Klik nama Collection &rarr; Klik tombol **View Documentation** pada panel kanan &rarr; Klik **Publish**.
   * Dapatkan *Public / Team Workspace Documentation URL* untuk dibagikan kepada seluruh developer.
