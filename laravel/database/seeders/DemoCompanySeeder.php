<?php

namespace Database\Seeders;

use App\Models\BuildingType;
use App\Models\ChangeOrder;
use App\Models\Client;
use App\Models\ClientType;
use App\Models\Company;
use App\Models\ComplianceDocument;
use App\Models\Consultation;
use App\Models\ContactType;
use App\Models\Document;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lead;
use App\Models\Material;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\QuickEstimate;
use App\Models\ScheduleTask;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;
use App\Models\TaxRate;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        if (Company::where('email', 'demo@buildxact-saudi.local')->exists()) {
            $this->command?->info('Demo company already exists.');
            return;
        }

        $proPlan = Plan::where('slug', 'professional')->firstOrFail();

        $company = Company::create([
            'name' => 'Al Rashid Construction Co.',
            'name_ar' => 'شركة الراشد للمقاولات',
            'email' => 'demo@buildxact-saudi.local',
            'phone' => '+966 50 123 4567',
            'city' => 'Riyadh',
            'status' => 'active',
            'plan_id' => $proPlan->id,
            'trial_ends_at' => now()->addDays(14),
            'cr_number' => '1010123456',
            'vat_number' => '300012345600003',
            'address' => 'King Fahd Road, Riyadh 12345',
            'default_markup_percent' => 15,
            'default_retention_percent' => 10,
            'contractor_classification' => 'first',
            'contractor_classification_number' => 'CC-2024-8891',
            'building_number' => '7512',
            'street_name' => 'King Fahd Road',
            'district' => 'Al Olaya',
            'postal_code' => '12333',
            'additional_number' => '4521',
        ]);

        $owner = User::create([
            'company_id' => $company->id,
            'name' => 'Ahmed Al Rashid',
            'email' => 'owner@buildxact-saudi.local',
            'password' => 'Demo@12345',
            'role' => 'owner',
            'status' => 'active',
        ]);

        User::create([
            'company_id' => $company->id,
            'name' => 'Fatimah Al Zahrani',
            'email' => 'estimator@buildxact-saudi.local',
            'password' => 'Demo@12345',
            'role' => 'estimator',
            'status' => 'active',
        ]);

        User::create([
            'company_id' => $company->id,
            'name' => 'Mohammed Al Qahtani',
            'email' => 'accountant@buildxact-saudi.local',
            'password' => 'Demo@12345',
            'role' => 'accountant',
            'status' => 'active',
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $proPlan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'current_period_end' => now()->addDays(30),
        ]);

        Payment::create([
            'company_id' => $company->id,
            'amount' => 449,
            'currency' => 'SAR',
            'method' => 'mada',
            'reference' => 'PMT-1001',
            'status' => 'paid',
        ]);

        // ---------------- Clients ----------------
        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Jeddah Heights Development',
            'name_ar' => 'تطوير مرتفعات جدة',
            'email' => 'contact@jeddahheights.sa',
            'phone' => '+966 55 987 6543',
            'address' => 'Jeddah, Saudi Arabia',
        ]);

        $client2 = Client::create([
            'company_id' => $company->id,
            'name' => 'Nasser Al Otaibi',
            'name_ar' => 'ناصر العتيبي',
            'email' => 'nasser.otaibi@example.com',
            'phone' => '+966 56 234 5678',
            'address' => 'Al Nakheel District, Riyadh',
        ]);

        // ---------------- Projects ----------------
        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Villa Renovation - Al Nakheel',
            'name_ar' => 'تجديد فيلا - النخيل',
            'description' => 'Full renovation of a 600 sqm villa including MEP works.',
            'description_ar' => 'تجديد كامل لفيلا مساحتها 600 متر مربع يشمل الأعمال الكهروميكانيكية.',
            'status' => 'in_progress',
            'budget' => 350000,
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(80),
        ]);

        $project2 = Project::create([
            'company_id' => $company->id,
            'client_id' => $client2->id,
            'name' => 'New Build - Al Malqa Residence',
            'name_ar' => 'بناء جديد - مسكن الملقا',
            'description' => 'Ground-up construction of a 450 sqm two-story family home.',
            'description_ar' => 'بناء منزل عائلي جديد بمساحة 450 متر مربع من دورين.',
            'status' => 'planning',
            'budget' => 620000,
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(280),
        ]);

        // ---------------- Estimate ----------------
        $estimate = Estimate::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'client_id' => $client->id,
            'title' => 'Villa Renovation Estimate',
            'title_ar' => 'تسعيرة تجديد الفيلا',
            'status' => 'accepted',
            'total' => 350000,
            'share_token' => bin2hex(random_bytes(20)),
        ]);

        $items = [
            ['Demolition & site prep', 'الهدم وتجهيز الموقع', 1, 25000],
            ['Structural & MEP works', 'الأعمال الإنشائية والكهروميكانيكية', 1, 150000],
            ['Finishing materials', 'مواد التشطيب', 1, 100000],
            ['Labor & supervision', 'العمالة والإشراف', 1, 75000],
        ];
        foreach ($items as [$desc, $descAr, $qty, $cost]) {
            EstimateItem::create([
                'estimate_id' => $estimate->id, 'description' => $desc, 'description_ar' => $descAr,
                'qty' => $qty, 'unit_cost' => $cost, 'total' => $qty * $cost,
            ]);
        }

        $estimate2 = Estimate::create([
            'company_id' => $company->id,
            'project_id' => $project2->id,
            'client_id' => $client2->id,
            'title' => 'Al Malqa Residence — Preliminary Estimate',
            'title_ar' => 'مسكن الملقا — تسعيرة أولية',
            'status' => 'sent',
            'total' => 620000,
            'share_token' => bin2hex(random_bytes(20)),
        ]);
        EstimateItem::create([
            'estimate_id' => $estimate2->id, 'description' => 'Foundation & structural shell', 'description_ar' => 'الأساسات والهيكل الإنشائي',
            'qty' => 1, 'unit_cost' => 280000, 'total' => 280000,
        ]);
        EstimateItem::create([
            'estimate_id' => $estimate2->id, 'description' => 'MEP rough-in & finishing', 'description_ar' => 'الأعمال الكهروميكانيكية والتشطيب',
            'qty' => 1, 'unit_cost' => 340000, 'total' => 340000,
        ]);

        // ---------------- Invoices ----------------
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-1001',
            'status' => 'paid',
            'total' => 100000,
            'vat_rate' => 15,
            'vat_amount' => 13043.48,
            'due_date' => now()->subDays(5),
            'share_token' => bin2hex(random_bytes(20)),
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Mobilization payment (30%)',
            'description_ar' => 'دفعة التعبئة (30%)', 'qty' => 1, 'unit_price' => 86956.52, 'total' => 86956.52,
        ]);

        $invoice2 = Invoice::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-1002',
            'status' => 'unpaid',
            'total' => 57500,
            'vat_rate' => 15,
            'vat_amount' => 7500,
            'due_date' => now()->addDays(14),
            'share_token' => bin2hex(random_bytes(20)),
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice2->id, 'description' => 'Structural works progress payment',
            'description_ar' => 'دفعة تقدم الأعمال الإنشائية', 'qty' => 1, 'unit_price' => 50000, 'total' => 50000,
        ]);

        // ---------------- Schedule ----------------
        $tasks = [
            ['Site demolition', 'هدم الموقع', -8, -2, 'done'],
            ['Electrical rough-in', 'الأعمال الكهربائية الأولية', -1, 14, 'in_progress'],
            ['Plumbing rough-in', 'أعمال السباكة الأولية', 2, 16, 'pending'],
            ['Interior finishing', 'التشطيبات الداخلية', 20, 60, 'pending'],
        ];
        foreach ($tasks as [$title, $titleAr, $startOffset, $endOffset, $status]) {
            ScheduleTask::create([
                'company_id' => $company->id, 'project_id' => $project->id,
                'title' => $title, 'title_ar' => $titleAr,
                'start_date' => now()->addDays($startOffset), 'end_date' => now()->addDays($endOffset),
                'status' => $status,
            ]);
        }
        ScheduleTask::create([
            'company_id' => $company->id, 'project_id' => $project2->id,
            'title' => 'Site survey & permits', 'title_ar' => 'المسح الموقعي والتصاريح',
            'start_date' => now()->addDays(20), 'end_date' => now()->addDays(35), 'status' => 'pending',
        ]);

        // ---------------- Change orders & photos ----------------
        ChangeOrder::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'title' => 'Upgrade kitchen countertops to marble', 'title_ar' => 'ترقية أسطح المطبخ إلى الرخام',
            'description' => 'Client requested a marble upgrade over the originally quoted granite.',
            'description_ar' => 'طلب العميل ترقية الرخام بدلاً من الجرانيت الموصوف أصلاً في العرض.',
            'amount' => 12500, 'status' => 'approved', 'approved_at' => now()->subDays(3),
        ]);
        ChangeOrder::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'title' => 'Add exterior lighting package', 'title_ar' => 'إضافة باقة الإنارة الخارجية',
            'description' => 'Additional landscape and facade lighting not in original scope.',
            'description_ar' => 'إنارة إضافية للواجهة والحديقة لم تكن ضمن النطاق الأصلي.',
            'amount' => 8200, 'status' => 'pending',
        ]);

        ProjectPhoto::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'uploaded_by' => $owner->id,
            'caption' => 'Demolition complete — ready for structural works', 'file_path' => '/assets/img/demo/site-photo-1.jpg',
            'taken_on' => now()->subDays(2),
        ]);
        ProjectPhoto::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'uploaded_by' => $owner->id,
            'caption' => 'Electrical conduit rough-in, ground floor', 'file_path' => '/assets/img/demo/site-photo-2.jpg',
            'taken_on' => now()->subDay(),
        ]);

        // ---------------- Suppliers & materials ----------------
        $supplier1 = Supplier::create([
            'company_id' => $company->id, 'name' => 'Saudi Building Materials Co.', 'name_ar' => 'الشركة السعودية لمواد البناء',
            'contact_name' => 'Khalid Al Harbi', 'email' => 'sales@sbmc.sa', 'phone' => '+966 11 456 7890',
            'address' => 'Industrial City, Riyadh', 'category' => 'General Materials',
        ]);
        $supplier2 = Supplier::create([
            'company_id' => $company->id, 'name' => 'Gulf Ceramics & Tiles', 'name_ar' => 'بلاط وسيراميك الخليج',
            'contact_name' => 'Sara Al Dossary', 'email' => 'info@gulfceramics.sa', 'phone' => '+966 12 345 6789',
            'address' => 'Jeddah Industrial Area', 'category' => 'Finishing',
        ]);

        $materials = [
            [$supplier1->id, 'CEM-42.5', 'Cement 42.5N (50kg bag)', 'أسمنت 42.5 (كيس 50 كجم)', 'Concrete', 'bag', 18.50, 15.00, 3.50],
            [$supplier1->id, 'REBAR-12', 'Rebar 12mm (ton)', 'حديد تسليح 12مم (طن)', 'Steel', 'ton', 2850.00, 2700.00, 150.00],
            [$supplier1->id, 'BLK-STD', 'Concrete Block (standard)', 'بلوك خرساني قياسي', 'Masonry', 'unit', 3.20, 2.80, 0.40],
            [$supplier2->id, 'TILE-CER-60', 'Ceramic Floor Tile 60x60', 'بلاط أرضيات سيراميك 60×60', 'Finishing', 'sqm', 45.00, 38.00, 7.00],
            [$supplier2->id, 'MARB-KIT', 'Marble Countertop Slab', 'ألواح رخام لأسطح المطابخ', 'Finishing', 'sqm', 320.00, 280.00, 40.00],
        ];
        foreach ($materials as [$supplierId, $sku, $name, $nameAr, $category, $unit, $unitCost, $materialCost, $laborCost]) {
            Material::create([
                'company_id' => $company->id, 'supplier_id' => $supplierId, 'sku' => $sku,
                'name' => $name, 'name_ar' => $nameAr, 'category' => $category, 'unit' => $unit,
                'unit_cost' => $unitCost, 'material_cost' => $materialCost, 'labor_cost' => $laborCost,
            ]);
        }

        // ---------------- Documents ----------------
        Document::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'uploaded_by' => $owner->id,
            'name' => 'Villa Renovation - Signed Contract', 'name_ar' => 'تجديد الفيلا - العقد الموقع',
            'file_path' => '/assets/docs/demo/villa-contract.pdf', 'file_type' => 'application/pdf', 'file_size' => 245000,
        ]);
        Document::create([
            'company_id' => $company->id, 'project_id' => null, 'uploaded_by' => $owner->id,
            'name' => 'Commercial Registration Certificate', 'name_ar' => 'شهادة السجل التجاري',
            'file_path' => '/assets/docs/demo/cr-certificate.pdf', 'file_type' => 'application/pdf', 'file_size' => 180000,
        ]);

        // ---------------- Compliance documents ----------------
        ComplianceDocument::create([
            'company_id' => $company->id, 'doc_type' => 'cr', 'name' => 'Commercial Registration', 'name_ar' => 'السجل التجاري',
            'document_number' => '1010123456', 'expiry_date' => now()->addDays(200)->format('Y-m-d'),
        ]);
        ComplianceDocument::create([
            'company_id' => $company->id, 'doc_type' => 'zakat', 'name' => 'Zakat Certificate', 'name_ar' => 'شهادة الزكاة',
            'document_number' => 'ZKT-88213', 'expiry_date' => now()->addDays(25)->format('Y-m-d'),
        ]);
        ComplianceDocument::create([
            'company_id' => $company->id, 'doc_type' => 'gosi', 'name' => 'GOSI Certificate of Good Standing', 'name_ar' => 'شهادة حسن السير من التأمينات',
            'document_number' => 'GOSI-441829', 'expiry_date' => now()->addDays(90)->format('Y-m-d'),
        ]);

        // ---------------- Leads ----------------
        Lead::create([
            'company_id' => $company->id, 'name' => 'Abdullah Al Ghamdi', 'company_name' => 'Al Ghamdi Holdings',
            'company_name_ar' => 'مجموعة الغامدي القابضة', 'email' => 'abdullah@alghamdi-holdings.sa', 'phone' => '+966 54 111 2222',
            'source' => 'website', 'status' => 'qualified', 'estimated_value' => 480000,
            'notes' => 'Interested in a 3-villa compound in Al Yasmin district.', 'assigned_to' => $owner->id,
        ]);
        Lead::create([
            'company_id' => $company->id, 'name' => 'Reem Al Subaie', 'company_name' => null, 'company_name_ar' => null,
            'email' => 'reem.subaie@example.com', 'phone' => '+966 53 222 3333',
            'source' => 'referral', 'status' => 'new', 'estimated_value' => 95000,
            'notes' => 'Kitchen and bathroom renovation for an existing apartment.',
        ]);
        Lead::create([
            'company_id' => $company->id, 'name' => 'Turki Al Mutairi', 'company_name' => 'Al Mutairi Trading Est.',
            'company_name_ar' => 'مؤسسة المطيري التجارية', 'email' => 'turki@almutairi-trading.sa', 'phone' => '+966 50 444 5555',
            'source' => 'quick_estimate', 'status' => 'contacted', 'estimated_value' => 210000,
            'notes' => 'Requested a quick estimate for a warehouse extension.',
        ]);

        // ---------------- Quick estimate (in-app saved quote) ----------------
        QuickEstimate::create([
            'company_id' => $company->id, 'client_id' => $client2->id, 'project_name' => 'Al Malqa Residence — sqm pricing check',
            'region_id' => 1, 'foundation_id' => 1, 'total_area' => 450, 'discount_percent' => 0,
            'addons_json' => json_encode([]), 'subtotal' => 540000, 'vat_amount' => 81000, 'total' => 621000,
            'lang' => 'en', 'contact_name' => 'Nasser Al Otaibi', 'contact_email' => 'nasser.otaibi@example.com',
            'status' => 'converted',
        ]);

        // ---------------- Digital takeoff ----------------
        $takeoff = Takeoff::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'name' => 'Ground Floor Plan — Villa Renovation',
            'scale_px_per_unit' => 12.5, 'scale_unit' => 'm',
        ]);
        TakeoffMeasurement::create([
            'takeoff_id' => $takeoff->id, 'type' => 'area', 'label' => 'Living room floor area',
            'points_json' => json_encode([[10, 10], [200, 10], [200, 150], [10, 150]]),
            'value' => 42.5, 'unit' => 'sqm', 'unit_cost' => 45.00, 'total_cost' => 1912.50,
        ]);
        TakeoffMeasurement::create([
            'takeoff_id' => $takeoff->id, 'type' => 'linear', 'label' => 'Perimeter wall — north wing',
            'points_json' => json_encode([[10, 10], [200, 10]]),
            'value' => 28.0, 'unit' => 'lm', 'unit_cost' => 65.00, 'total_cost' => 1820.00,
        ]);

        // ---------------- Consultation ----------------
        Consultation::create([
            'company_id' => $company->id, 'requested_by' => $owner->id, 'type' => 'chat', 'status' => 'completed',
            'topic' => 'ZATCA Phase 2 onboarding guidance', 'notes' => 'Need help understanding the CSR/CSID onboarding steps.',
            'assigned_engineer' => 'Eng. Yousef Al Harthi', 'preferred_date' => now()->subDays(4)->format('Y-m-d'),
            'scheduled_at' => now()->subDays(4),
        ]);

        // ---------------- Business setup lookups ----------------
        foreach (['Single Family Residential', 'Villa', 'Duplex', 'Apartment Building', 'Commercial', 'Industrial', 'Renovation / Remodel'] as $i => $name) {
            BuildingType::create(['company_id' => $company->id, 'name' => $name, 'sort_order' => $i]);
        }
        foreach (['Client', 'Subcontractor', 'Supplier', 'Consultant', 'Architect', 'Government / Municipality'] as $i => $name) {
            ContactType::create(['company_id' => $company->id, 'name' => $name, 'sort_order' => $i]);
        }
        foreach (['Individual Homeowner', 'Real Estate Developer', 'Government Entity', 'Commercial Business', 'Property Management Company'] as $i => $name) {
            ClientType::create(['company_id' => $company->id, 'name' => $name, 'sort_order' => $i]);
        }
        $units = [
            ['sqm', 'Square meter'], ['m3', 'Cubic meter'], ['lm', 'Linear meter'], ['each', 'Each'],
            ['lot', 'Lot / Job'], ['hr', 'Hour'], ['ton', 'Ton'], ['point', 'Point (electrical/plumbing)'], ['kg', 'Kilogram'],
        ];
        foreach ($units as $i => [$code, $name]) {
            UnitOfMeasure::create(['company_id' => $company->id, 'code' => $code, 'name' => $name, 'sort_order' => $i]);
        }
        TaxRate::create(['company_id' => $company->id, 'name' => 'Standard VAT', 'name_ar' => 'ضريبة القيمة المضافة القياسية', 'rate_percent' => 15, 'is_default' => true, 'sort_order' => 0]);
        TaxRate::create(['company_id' => $company->id, 'name' => 'Zero-rated', 'name_ar' => 'معفاة من الضريبة', 'rate_percent' => 0, 'is_default' => false, 'sort_order' => 1]);

        $this->command?->info('Demo company seeded: owner@buildxact-saudi.local / Demo@12345 (plus estimator@ and accountant@ teammates, same password)');
    }
}
