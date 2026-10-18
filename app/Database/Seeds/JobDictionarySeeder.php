<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

final class JobDictionarySeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->db->tableExists('skills_dictionary') || ! $this->db->tableExists('title_dictionary')) {
            throw new RuntimeException('Jalankan migration Job Matching sebelum mengisi kamus.');
        }

        $this->seedSkills();
        $this->seedTitles();
    }

    private function seedSkills(): void
    {
        $groups = [
            'programming' => [
                'PHP|php programming',
                'Python|pemrograman python',
                'Java|pemrograman java',
                'JavaScript|javascript programming|js',
                'TypeScript|typescript programming|ts',
                'C#|c sharp',
                'C++|cpp|c plus plus',
                'Go|golang',
                'Ruby|ruby programming',
                'Swift|swift programming',
                'Kotlin|kotlin programming',
                'SQL|structured query language',
                'HTML|html markup',
                'CSS|cascading style sheets',
                'Laravel|framework laravel',
                'CodeIgniter|framework codeigniter',
                'React|reactjs',
                'Vue.js|vuejs|vue',
                'Node.js|nodejs',
                'REST API|api rest|restful api',
                'GraphQL|graphql api',
                'Git|version control git',
                'Docker|container docker',
                'Linux|sistem operasi linux',
                'Unit testing|pengujian unit',
                'Object-oriented programming|pemrograman berorientasi objek|oop',
                'WordPress|wordpress development',
                'Django|framework django',
                'Spring Boot|framework spring',
                'ASP.NET|dotnet|asp net',
                'Flutter|flutter development',
                'React Native|react native development',
            ],
            'data' => [
                'Data analysis|analisis data',
                'Data visualization|visualisasi data',
                'Data cleaning|pembersihan data',
                'Data entry|entri data',
                'Data modeling|pemodelan data',
                'Data mining|penggalian data',
                'Data warehousing|gudang data',
                'Business intelligence|intelijen bisnis|bi',
                'Machine learning|pembelajaran mesin',
                'Deep learning|pembelajaran mendalam',
                'Statistics|statistika',
                'Probability|probabilitas',
                'ETL|extract transform load',
                'Power BI|microsoft power bi',
                'Tableau|tableau software',
                'Looker Studio|google data studio',
                'Google Analytics|ga4|google analytics 4',
                'Microsoft Excel|ms excel|excel',
                'R programming|bahasa r',
                'Apache Spark|spark data',
                'BigQuery|google bigquery',
                'PostgreSQL|postgres|postgresql database',
                'MySQL|mysql database',
                'MongoDB|mongodb database',
            ],
            'design' => [
                'UI design|desain ui|user interface design',
                'UX design|desain ux|user experience design',
                'Product design|desain produk digital',
                'Graphic design|desain grafis',
                'Visual design|desain visual',
                'Interaction design|desain interaksi',
                'Wireframing|pembuatan wireframe',
                'Prototyping|pembuatan prototipe',
                'Design systems|sistem desain',
                'Figma|figma design',
                'Adobe Photoshop|photoshop',
                'Adobe Illustrator|illustrator',
                'Adobe InDesign|indesign',
                'Adobe XD|adobe experience design',
                'Canva|canva design',
                'Typography|tipografi',
                'User research|riset pengguna',
                'Usability testing|uji kegunaan',
            ],
            'digital_marketing' => [
                'Digital marketing|pemasaran digital',
                'Search engine optimization|optimasi mesin pencari|seo',
                'Search engine marketing|pemasaran mesin pencari|sem',
                'Content marketing|pemasaran konten',
                'Social media marketing|pemasaran media sosial',
                'Email marketing|pemasaran email',
                'Performance marketing|pemasaran kinerja',
                'Affiliate marketing|pemasaran afiliasi',
                'Marketing analytics|analitik pemasaran',
                'Copywriting|penulisan naskah iklan',
                'Content writing|penulisan konten',
                'Campaign management|pengelolaan kampanye',
                'Brand management|pengelolaan merek',
                'Market research|riset pasar',
                'Google Ads|google adwords',
                'Meta Ads|facebook ads|instagram ads',
                'TikTok Ads|iklan tiktok',
                'Search Console|google search console',
                'WordPress content management|pengelolaan konten wordpress',
                'Marketing automation|otomasi pemasaran',
            ],
            'sales' => [
                'Sales|penjualan',
                'B2B sales|penjualan antarperusahaan',
                'B2C sales|penjualan konsumen',
                'Lead generation|pencarian prospek',
                'Sales prospecting|prospecting penjualan',
                'Negotiation|negosiasi',
                'Sales forecasting|peramalan penjualan',
                'Account management|pengelolaan akun pelanggan',
                'Key account management|pengelolaan akun utama',
                'Customer relationship management|pengelolaan hubungan pelanggan|crm',
                'Salesforce|salesforce crm',
                'HubSpot CRM|hubspot',
                'Pipeline management|pengelolaan pipeline',
                'Business development|pengembangan bisnis',
                'Retail sales|penjualan ritel',
                'Merchandising|penataan produk dagang',
                'Upselling|penjualan tambahan',
                'Cross-selling|penjualan silang',
            ],
            'finance_accounting' => [
                'Accounting|akuntansi',
                'Financial accounting|akuntansi keuangan',
                'Management accounting|akuntansi manajemen',
                'Bookkeeping|pembukuan',
                'Accounts payable|utang usaha',
                'Accounts receivable|piutang usaha',
                'Bank reconciliation|rekonsiliasi bank',
                'Financial reporting|pelaporan keuangan',
                'Financial analysis|analisis keuangan',
                'Budgeting|penyusunan anggaran',
                'Forecasting|peramalan keuangan',
                'Tax compliance|kepatuhan pajak',
                'Tax reporting|pelaporan pajak',
                'Payroll accounting|akuntansi penggajian',
                'Cost accounting|akuntansi biaya',
                'Internal audit|audit internal',
                'External audit|audit eksternal',
                'Risk management|manajemen risiko',
                'SAP Finance|sap fi',
                'QuickBooks|quickbooks accounting',
                'Accurate accounting software|software accurate|accurate',
                'Xero accounting|xero',
            ],
            'administration' => [
                'Office administration|administrasi perkantoran',
                'Document management|pengelolaan dokumen',
                'Records management|pengelolaan arsip',
                'Filing|pengarsipan',
                'Data verification|verifikasi data',
                'Correspondence|korespondensi',
                'Calendar management|pengelolaan kalender',
                'Meeting coordination|koordinasi rapat',
                'Travel coordination|koordinasi perjalanan dinas',
                'Inventory administration|administrasi inventaris',
                'Procurement administration|administrasi pengadaan',
                'Report writing|penyusunan laporan',
                'Minute taking|pencatatan notulen',
                'Office management|manajemen kantor',
                'Administrative support|dukungan administrasi',
                'Database administration|administrasi basis data',
                'Document control|pengendalian dokumen',
                'Records retention|retensi arsip',
            ],
            'human_resources' => [
                'Human resources|sumber daya manusia|hr',
                'Recruitment|rekrutmen',
                'Talent acquisition|akuisisi talenta',
                'Interviewing|wawancara kerja',
                'Applicant tracking systems|sistem pelacakan pelamar|ats',
                'Onboarding|orientasi karyawan',
                'Employee relations|hubungan karyawan',
                'Performance management|manajemen kinerja',
                'Learning and development|pembelajaran dan pengembangan|l d',
                'Training delivery|pelaksanaan pelatihan',
                'Compensation and benefits|kompensasi dan tunjangan',
                'Payroll administration|administrasi penggajian',
                'HR information systems|sistem informasi sdm|hris',
                'Workforce planning|perencanaan tenaga kerja',
                'Labor law compliance|kepatuhan hukum ketenagakerjaan',
                'Job evaluation|evaluasi jabatan',
                'Employee engagement|keterlibatan karyawan',
            ],
            'operations' => [
                'Operations management|manajemen operasional',
                'Process improvement|perbaikan proses',
                'Business process mapping|pemetaan proses bisnis',
                'Project management|manajemen proyek',
                'Agile methodology|metodologi agile',
                'Scrum|scrum framework',
                'Lean management|manajemen lean',
                'Six Sigma|six sigma methodology',
                'Quality assurance|jaminan mutu|qa',
                'Quality control|pengendalian mutu|qc',
                'Supply chain management|manajemen rantai pasok',
                'Logistics coordination|koordinasi logistik',
                'Warehouse management|manajemen gudang',
                'Vendor management|manajemen vendor',
                'SOP development|penyusunan sop|standard operating procedures',
                'KPI reporting|pelaporan indikator kinerja',
                'Resource planning|perencanaan sumber daya',
                'Business continuity planning|perencanaan kelangsungan bisnis',
            ],
            'customer_service' => [
                'Customer service|layanan pelanggan',
                'Customer support|dukungan pelanggan',
                'Call center operations|operasional pusat panggilan',
                'Complaint handling|penanganan keluhan',
                'Help desk support|dukungan helpdesk',
                'Technical support|dukungan teknis',
                'Ticketing systems|sistem tiket layanan',
                'Zendesk|zendesk support',
                'Service level agreements|perjanjian tingkat layanan|sla',
                'Customer satisfaction|kepuasan pelanggan',
                'Customer retention|retensi pelanggan',
                'Conflict resolution|penyelesaian konflik',
                'Active listening|mendengarkan aktif',
                'Service recovery|pemulihan layanan',
                'Live chat support|dukungan obrolan langsung',
            ],
            'engineering' => [
                'Mechanical engineering|teknik mesin',
                'Electrical engineering|teknik elektro',
                'Civil engineering|teknik sipil',
                'Industrial engineering|teknik industri',
                'Chemical engineering|teknik kimia',
                'Mechatronics|mekatronika',
                'Computer networking|jaringan komputer',
                'Network administration|administrasi jaringan',
                'System administration|administrasi sistem',
                'Cybersecurity|keamanan siber',
                'Occupational health and safety|keselamatan dan kesehatan kerja|k3',
                'AutoCAD|autocad drafting',
                'SolidWorks|solidworks cad',
                'Preventive maintenance|pemeliharaan preventif',
                'Electrical installation|instalasi listrik',
                'Technical drawing|gambar teknik',
                'Building information modeling|pemodelan informasi bangunan|bim',
                'Environmental management|manajemen lingkungan',
            ],
            'languages' => [
                'Indonesian language|bahasa indonesia',
                'English language|bahasa inggris',
                'Mandarin Chinese|bahasa mandarin',
                'Japanese language|bahasa jepang',
                'Korean language|bahasa korea',
                'Arabic language|bahasa arab',
                'French language|bahasa prancis',
                'German language|bahasa jerman',
                'Spanish language|bahasa spanyol',
                'Business English|bahasa inggris bisnis',
            ],
            'office_tools' => [
                'Microsoft Word|ms word|word',
                'Microsoft PowerPoint|ms powerpoint|powerpoint',
                'Microsoft Outlook|ms outlook|outlook',
                'Microsoft Access|ms access|access database',
                'Google Workspace|google workspace tools|g suite',
                'Google Sheets|google spreadsheet',
                'Google Docs|google documents',
                'Google Slides|google presentation',
                'Microsoft Teams|ms teams',
                'Zoom|zoom meetings',
                'Slack|slack messaging',
                'Trello|trello boards',
                'Asana|asana project management',
                'Jira|jira software',
                'SharePoint|microsoft sharepoint',
                'Microsoft 365|office 365|m365',
            ],
        ];

        $rows = [];
        foreach ($groups as $category => $items) {
            foreach ($items as $item) {
                $aliases = array_values(array_filter(array_map('trim', explode('|', $item))));
                $canonical = array_shift($aliases);
                $rows[] = [
                    'id'        => $this->uuid(),
                    'canonical' => $canonical,
                    'aliases'   => $this->json(array_values(array_unique($aliases))),
                    'category'  => $category,
                    'weight'    => 1,
                ];
            }
        }

        $rows = $this->uniqueRows($rows, 'canonical');
        $existing = $this->existingCanonicals('skills_dictionary', 'canonical', array_column($rows, 'canonical'));
        $rows = array_values(array_filter($rows, static fn (array $row): bool => ! isset($existing[$row['canonical']])));
        if ($rows !== []) {
            $this->db->table('skills_dictionary')->insertBatch($rows);
        }
    }

    private function seedTitles(): void
    {
        $groups = [
            'administration' => [
                'Staf Administrasi|admin|office administrator|staf administrasi dan keuangan',
                'Asisten Administrasi|administrative assistant|admin assistant',
                'Resepsionis|receptionist|front desk officer',
                'Sekretaris|secretary|executive secretary',
                'Asisten Eksekutif|executive assistant|personal assistant',
                'Petugas Entri Data|data entry clerk|data entry operator',
                'Petugas Arsip|archivist|records clerk',
                'Koordinator Kantor|office coordinator|office administrator',
                'Manajer Kantor|office manager|administration manager',
                'Petugas Dokumen|document controller|document control officer',
                'Staf Operasional Administrasi|administrative operations staff|operations administrator',
                'Asisten Proyek|project assistant|project administrator',
                'Koordinator Administrasi|administrative coordinator|admin coordinator',
                'Staf Pengadaan|procurement staff|purchasing administrator',
                'Petugas Inventaris|inventory clerk|inventory administrator',
            ],
            'accounting' => [
                'Staf Akuntansi|accounting staff|accountant assistant',
                'Akuntan|accountant|professional accountant',
                'Akuntan Publik|public accountant|external accountant',
                'Auditor Internal|internal auditor|internal audit officer',
                'Auditor Eksternal|external auditor|audit associate',
                'Staf Pajak|tax staff|tax accountant',
                'Konsultan Pajak|tax consultant|tax advisor',
                'Staf Piutang|accounts receivable clerk|ar staff',
                'Staf Utang|accounts payable clerk|ap staff',
                'Pembukuan|bookkeeper|bookkeeping clerk',
                'Manajer Akuntansi|accounting manager|accounting lead',
                'Kepala Keuangan dan Akuntansi|finance and accounting manager|head of finance and accounting',
                'Analis Biaya|cost analyst|cost accountant',
                'Staf Penggajian|payroll officer|payroll accountant',
                'Analis Pelaporan Keuangan|financial reporting analyst|reporting accountant',
            ],
            'finance' => [
                'Analis Keuangan|financial analyst|finance analyst',
                'Manajer Keuangan|finance manager|financial manager',
                'Kepala Keuangan|finance director|head of finance',
                'Analis Kredit|credit analyst|credit risk analyst',
                'Petugas Kredit|credit officer|loan officer',
                'Analis Investasi|investment analyst|investment associate',
                'Manajer Perbendaharaan|treasury manager|treasury officer',
                'Staf Perbendaharaan|treasury staff|treasury analyst',
                'Analis Risiko|risk analyst|financial risk analyst',
                'Perencana Keuangan|financial planner|personal financial advisor',
                'Analis Anggaran|budget analyst|budget officer',
                'Kepala Akuntansi|chief accountant|chief accounting officer',
            ],
            'technology' => [
                'Pengembang Perangkat Lunak|software developer|software engineer',
                'Pengembang Backend|backend developer|back end engineer',
                'Pengembang Frontend|frontend developer|front end engineer',
                'Pengembang Full Stack|full stack developer|fullstack engineer',
                'Pengembang PHP|php developer|php programmer',
                'Pengembang Java|java developer|java programmer',
                'Pengembang Python|python developer|python programmer',
                'Pengembang Aplikasi Seluler|mobile app developer|mobile application engineer',
                'Pengembang iOS|ios developer|swift developer',
                'Pengembang Android|android developer|android engineer',
                'Pengembang Web|web developer|web programmer',
                'Insinyur DevOps|devops engineer|site reliability engineer',
                'Administrator Sistem|system administrator|sysadmin',
                'Administrator Jaringan|network administrator|network engineer',
                'Analis Keamanan Siber|cybersecurity analyst|security analyst',
                'Analis Dukungan TI|it support analyst|help desk analyst',
                'Manajer Proyek TI|it project manager|technology project manager',
                'Arsitek Solusi|solution architect|solutions architect',
                'Insinyur Perangkat Lunak QA|qa engineer|software test engineer',
                'Penguji Perangkat Lunak|software tester|quality assurance tester',
            ],
            'data' => [
                'Analis Data|data analyst|business data analyst',
                'Ilmuwan Data|data scientist|data science analyst',
                'Insinyur Data|data engineer|big data engineer',
                'Analis Intelijen Bisnis|business intelligence analyst|bi analyst',
                'Pengembang Intelijen Bisnis|business intelligence developer|bi developer',
                'Analis Basis Data|database analyst|database developer',
                'Administrator Basis Data|database administrator|dba',
                'Analis Riset|research analyst|quantitative analyst',
                'Analis Pelaporan|reporting analyst|management reporting analyst',
                'Analis Operasional|operations analyst|business operations analyst',
                'Analis Pemasaran|marketing analyst|marketing data analyst',
                'Analis Rantai Pasok|supply chain analyst|logistics analyst',
            ],
            'design' => [
                'Desainer Grafis|graphic designer|visual designer',
                'Desainer UI|ui designer|user interface designer',
                'Desainer UX|ux designer|user experience designer',
                'Desainer Produk|product designer|digital product designer',
                'Desainer Web|web designer|website designer',
                'Desainer Interior|interior designer|interior architect',
                'Ilustrator|illustrator|digital illustrator',
                'Desainer Konten|content designer|digital content designer',
                'Desainer Merek|brand designer|visual identity designer',
                'Peneliti UX|ux researcher|user researcher',
                'Direktur Kreatif|creative director|head of creative',
                'Animator|animator|motion graphics artist',
            ],
            'marketing' => [
                'Spesialis Pemasaran Digital|digital marketing specialist|digital marketer',
                'Manajer Pemasaran Digital|digital marketing manager|online marketing manager',
                'Spesialis SEO|seo specialist|search engine optimization specialist',
                'Spesialis SEM|sem specialist|paid search specialist',
                'Manajer Media Sosial|social media manager|social media lead',
                'Spesialis Media Sosial|social media specialist|social media officer',
                'Penulis Konten|content writer|web content writer',
                'Manajer Konten|content manager|content marketing manager',
                'Spesialis Pemasaran Konten|content marketing specialist|content marketer',
                'Penulis Naskah Iklan|copywriter|advertising copywriter',
                'Manajer Merek|brand manager|brand marketing manager',
                'Manajer Kampanye|campaign manager|marketing campaign manager',
                'Spesialis Iklan Digital|paid media specialist|performance marketing specialist',
                'Spesialis Hubungan Masyarakat|public relations specialist|pr officer',
            ],
            'sales' => [
                'Staf Penjualan|sales representative|sales executive',
                'Eksekutif Penjualan|sales executive|sales account executive',
                'Manajer Penjualan|sales manager|sales team manager',
                'Manajer Akun|account manager|client account manager',
                'Manajer Akun Utama|key account manager|strategic account manager',
                'Pengembang Bisnis|business development executive|business development officer',
                'Manajer Pengembangan Bisnis|business development manager|bd manager',
                'Konsultan Penjualan|sales consultant|sales advisor',
                'Pramuniaga|retail sales associate|store sales assistant',
                'Kasir|cashier|retail cashier',
                'Supervisor Penjualan|sales supervisor|sales team leader',
                'Manajer Toko|store manager|retail manager',
            ],
            'human_resources' => [
                'Staf Sumber Daya Manusia|human resources staff|hr officer',
                'Spesialis Rekrutmen|recruitment specialist|talent acquisition specialist',
                'Perekrut|recruiter|talent recruiter',
                'Manajer Sumber Daya Manusia|human resources manager|hr manager',
                'Manajer Rekrutmen|recruitment manager|talent acquisition manager',
                'Spesialis Pengembangan Organisasi|organizational development specialist|od specialist',
                'Spesialis Penggajian|payroll specialist|payroll officer',
                'Spesialis Kompensasi dan Tunjangan|compensation and benefits specialist|c and b specialist',
                'Spesialis Pembelajaran dan Pengembangan|learning and development specialist|training specialist',
                'Mitra Bisnis SDM|human resources business partner|hrbp',
                'Administrator SDM|human resources administrator|hr administrator',
                'Manajer Hubungan Industrial|industrial relations manager|employee relations manager',
            ],
            'customer_service' => [
                'Perwakilan Layanan Pelanggan|customer service representative|customer care agent',
                'Agen Pusat Panggilan|call center agent|contact center representative',
                'Manajer Layanan Pelanggan|customer service manager|customer care manager',
                'Spesialis Dukungan Pelanggan|customer support specialist|customer support agent',
                'Petugas Dukungan Teknis|technical support officer|technical support specialist',
                'Petugas Helpdesk|helpdesk officer|service desk analyst',
                'Manajer Pengalaman Pelanggan|customer experience manager|cx manager',
                'Koordinator Layanan Pelanggan|customer service coordinator|customer care coordinator',
                'Petugas Pengaduan|complaint officer|complaints handler',
                'Petugas Reservasi|reservation agent|booking agent',
            ],
            'operations' => [
                'Manajer Operasional|operations manager|business operations manager',
                'Staf Operasional|operations staff|operations associate',
                'Analis Operasional|operations analyst|business operations analyst',
                'Supervisor Operasional|operations supervisor|operations team leader',
                'Manajer Proyek|project manager|project lead',
                'Koordinator Proyek|project coordinator|project officer',
                'Manajer Rantai Pasok|supply chain manager|supply chain lead',
                'Staf Logistik|logistics staff|logistics coordinator',
                'Manajer Gudang|warehouse manager|distribution center manager',
                'Staf Pengendalian Mutu|quality control staff|quality inspector',
                'Manajer Mutu|quality manager|quality assurance manager',
                'Analis Proses Bisnis|business process analyst|process improvement analyst',
            ],
            'engineering' => [
                'Insinyur Sipil|civil engineer|structural engineer',
                'Insinyur Mesin|mechanical engineer|mechanical design engineer',
                'Insinyur Elektro|electrical engineer|electrical design engineer',
                'Insinyur Industri|industrial engineer|manufacturing engineer',
                'Insinyur Kimia|chemical engineer|process engineer',
                'Insinyur Lingkungan|environmental engineer|environmental specialist',
                'Insinyur Keselamatan Kerja|occupational health and safety officer|hse officer',
                'Drafter|cad drafter|technical draftsman',
                'Pengawas Lapangan|site supervisor|field engineer',
                'Manajer Konstruksi|construction manager|construction project manager',
                'Teknisi Pemeliharaan|maintenance technician|service technician',
                'Teknisi Listrik|electrical technician|electrician',
                'Arsitek|architect|building architect',
                'Surveyor|land surveyor|quantity surveyor',
            ],
            'education' => [
                'Guru Sekolah Dasar|elementary school teacher|primary teacher',
                'Guru Sekolah Menengah|secondary school teacher|high school teacher',
                'Dosen|university lecturer|college instructor',
                'Tutor|private tutor|academic tutor',
                'Instruktur Pelatihan|training instructor|corporate trainer',
                'Konselor Pendidikan|school counselor|education counselor',
                'Kepala Sekolah|school principal|head teacher',
                'Staf Administrasi Pendidikan|education administrator|school administrator',
            ],
            'healthcare' => [
                'Perawat|nurse|registered nurse',
                'Dokter Umum|general practitioner|primary care physician',
                'Apoteker|pharmacist|registered pharmacist',
                'Asisten Apoteker|pharmacy assistant|pharmacy technician',
                'Bidan|midwife|registered midwife',
                'Analis Laboratorium Medis|medical laboratory technologist|medical lab analyst',
                'Ahli Gizi|nutritionist|dietitian',
                'Fisioterapis|physiotherapist|physical therapist',
            ],
            'logistics' => [
                'Kurir|courier|delivery driver',
                'Pengemudi|driver|professional driver',
                'Petugas Ekspedisi|shipping clerk|dispatch officer',
                'Koordinator Transportasi|transportation coordinator|fleet coordinator',
                'Petugas Pembelian|buyer|purchasing officer',
                'Manajer Pengadaan|procurement manager|purchasing manager',
                'Petugas Rantai Pasok|supply chain officer|supply chain associate',
                'Petugas Bea Cukai|customs officer|customs clearance specialist',
            ],
            'hospitality' => [
                'Staf Hotel|hotel staff|hospitality associate',
                'Resepsionis Hotel|hotel receptionist|front office agent',
                'Manajer Hotel|hotel manager|hospitality manager',
                'Koki|chef|cook',
                'Pelayan Restoran|waiter|food service attendant',
                'Manajer Restoran|restaurant manager|food and beverage manager',
                'Agen Perjalanan|travel agent|travel consultant',
                'Pemandu Wisata|tour guide|tour leader',
            ],
            'legal' => [
                'Staf Hukum|legal staff|legal officer',
                'Pengacara|lawyer|attorney',
                'Paralegal|paralegal assistant|legal assistant',
                'Konsultan Hukum|legal consultant|legal advisor',
                'Sekretaris Hukum|legal secretary|law office secretary',
                'Kepala Kepatuhan|compliance officer|compliance manager',
            ],
            'media' => [
                'Fotografer|photographer|professional photographer',
                'Videografer|videographer|video producer',
                'Editor Video|video editor|post production editor',
                'Jurnalis|journalist|news reporter',
                'Penyiar|broadcaster|radio presenter',
                'Produser Konten|content producer|digital producer',
            ],
        ];

        $rows = [];
        foreach ($groups as $family => $items) {
            foreach ($items as $item) {
                $aliases = array_values(array_filter(array_map('trim', explode('|', $item))));
                $canonical = array_shift($aliases);
                $rows[] = [
                    'id'              => $this->uuid(),
                    'canonical_title' => $canonical,
                    'aliases'         => $this->json(array_values(array_unique($aliases))),
                    'job_family'      => $family,
                    'seniority_hint'  => null,
                ];
            }
        }

        $rows = $this->uniqueRows($rows, 'canonical_title');
        $existing = $this->existingCanonicals('title_dictionary', 'canonical_title', array_column($rows, 'canonical_title'));
        $rows = array_values(array_filter($rows, static fn (array $row): bool => ! isset($existing[$row['canonical_title']])));
        if ($rows !== []) {
            $this->db->table('title_dictionary')->insertBatch($rows);
        }
    }

    /**
     * @param list<string> $canonicals
     *
     * @return array<string, true>
     */
    private function existingCanonicals(string $table, string $column, array $canonicals): array
    {
        $existing = [];
        foreach (array_chunk($canonicals, 500) as $chunk) {
            foreach ($this->db->table($table)->select($column)->whereIn($column, $chunk)->get()->getResultArray() as $row) {
                $existing[(string) $row[$column]] = true;
            }
        }

        return $existing;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function uniqueRows(array $rows, string $column): array
    {
        $unique = [];
        foreach ($rows as $row) {
            $unique[$row[$column]] ??= $row;
        }

        return array_values($unique);
    }

    /**
     * @param array<mixed> $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
