<?php

namespace Database\Seeders;

use App\Models\AsphaltEstimate;
use App\Models\AsphaltEstimateSegment;
use App\Models\Assembly;
use App\Models\AssemblyItem;
use App\Models\BankGuarantee;
use App\Models\BoqItem;
use App\Models\BuildingType;
use App\Models\ChangeOrder;
use App\Models\Client;
use App\Models\ClientType;
use App\Models\Company;
use App\Models\ComplianceDocument;
use App\Models\Consultation;
use App\Models\ContactType;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\DebitNote;
use App\Models\DebitNoteItem;
use App\Models\Document;
use App\Models\DocumentPin;
use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentMaintenanceLog;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lead;
use App\Models\Material;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentCertificate;
use App\Models\PaymentCertificateLine;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectUpdate;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QuickEstimate;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Rfi;
use App\Models\RfiMessage;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\RfqQuote;
use App\Models\RfqQuoteItem;
use App\Models\SafetyIncident;
use App\Models\ScheduleTask;
use App\Models\Selection;
use App\Models\SelectionOption;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\Submittal;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;
use App\Models\TakeoffPage;
use App\Models\TaxRate;
use App\Models\TimesheetEntry;
use App\Models\ToolboxTalk;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VendorBill;
use App\Models\VendorBillSubmission;
use App\Models\ZakatCalculation;
use App\Support\AsphaltEstimateCalc;
use Illuminate\Database\Seeder;

class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $existingCompany = Company::where('email', 'demo@buildxact-saudi.local')->first();
        if ($existingCompany) {
            $this->backfillExtendedDemoData($existingCompany);
            $this->seedSecondDemoCompany();

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
            'hourly_rate' => 150,
        ]);

        $estimator = User::create([
            'company_id' => $company->id,
            'name' => 'Fatimah Al Zahrani',
            'email' => 'estimator@buildxact-saudi.local',
            'password' => 'Demo@12345',
            'role' => 'estimator',
            'status' => 'active',
            'hourly_rate' => 95,
        ]);

        $accountant = User::create([
            'company_id' => $company->id,
            'name' => 'Mohammed Al Qahtani',
            'email' => 'accountant@buildxact-saudi.local',
            'password' => 'Demo@12345',
            'role' => 'accountant',
            'status' => 'active',
            'hourly_rate' => 85,
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

        // ---------------- Bank guarantees & bonds ----------------
        BankGuarantee::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'type' => 'performance_bond', 'bank_name' => 'Al Rajhi Bank', 'guarantee_number' => 'PB-2024-5521',
            'amount' => 17500, 'issue_date' => now()->subDays(10)->format('Y-m-d'),
            'expiry_date' => now()->addDays(20)->format('Y-m-d'), 'status' => 'active',
        ]);
        BankGuarantee::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'type' => 'advance_payment', 'bank_name' => 'Riyad Bank', 'guarantee_number' => 'APG-2024-1187',
            'amount' => 35000, 'issue_date' => now()->subDays(10)->format('Y-m-d'),
            'expiry_date' => now()->addDays(150)->format('Y-m-d'), 'status' => 'active',
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

        // A broad, representative slice of the shared material library (see
        // database/data/material_library_items.php) spanning most catalog categories,
        // rather than a handful of hand-typed rows, so the demo shows a realistic library.
        // Roughly a third are attributed to each of the two demo suppliers; the rest are
        // left unassigned, same as a real company wouldn't source every material from one place.
        $librarySkus = [
            'CEM-425' => $supplier1->id, 'REBAR-12' => $supplier1->id, 'RMX-C25' => $supplier1->id,
            'BLK-CONC20' => $supplier1->id, 'BLK-AAC' => $supplier1->id,
            'PVC-110' => $supplier1->id, 'PPR-20' => $supplier1->id,
            'CABLE-25' => $supplier1->id, 'MCB-20A' => $supplier1->id,
            'SAND-FINE' => $supplier1->id, 'AGG-CRUSH20' => $supplier1->id,
            'TILE-CER60' => $supplier2->id, 'TILE-POR80' => $supplier2->id, 'MARBLE-SLAB' => $supplier2->id,
            'WC-WALLHUNG' => $supplier2->id, 'BASIN-COUNTER' => $supplier2->id, 'MIXER-SHOWER' => $supplier2->id,
            'KITCHENSINK-SS' => $supplier2->id, 'GLASS-TEMP10' => $supplier2->id, 'ALUCOMPOSITE' => $supplier2->id,
            'WATERHEATER-50' => null, 'LED-DOWNLIGHT' => null, 'AC-SPLIT18' => null, 'AC-SPLIT24' => null,
            'PAINT-EMLINT' => null, 'PAINT-EMLEXT' => null, 'DOOR-FLUSHINT' => null, 'WIN-ALUSLD' => null,
            'WPMEMB-BIT' => null, 'INSUL-XPS5' => null, 'ALUPROFILE-WIN' => null,
        ];
        $libraryBySku = collect(require database_path('data/material_library_items.php'))->keyBy('sku');
        foreach ($librarySkus as $sku => $supplierId) {
            $li = $libraryBySku[$sku] ?? null;
            if (! $li) {
                continue;
            }
            $materialCost = (float) $li['material_cost'];
            $laborCost = (float) ($li['labor_cost'] ?? 0);
            Material::create([
                'company_id' => $company->id, 'supplier_id' => $supplierId, 'sku' => $li['sku'],
                'name' => $li['name'], 'name_ar' => $li['name_ar'] ?? null, 'category' => $li['category'], 'unit' => $li['unit'],
                'unit_cost' => $materialCost + $laborCost, 'material_cost' => $materialCost, 'labor_cost' => $laborCost,
            ]);
        }

        // ---------------- Documents ----------------
        Document::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'uploaded_by' => $owner->id,
            'name' => 'Villa Renovation - Signed Contract', 'name_ar' => 'تجديد الفيلا - العقد الموقع',
            'file_path' => '/assets/docs/demo/villa-contract.pdf', 'file_type' => 'application/pdf', 'file_size' => 38593,
        ]);
        Document::create([
            'company_id' => $company->id, 'project_id' => null, 'uploaded_by' => $owner->id,
            'name' => 'Commercial Registration Certificate', 'name_ar' => 'شهادة السجل التجاري',
            'file_path' => '/assets/docs/demo/cr-certificate.pdf', 'file_type' => 'application/pdf', 'file_size' => 36892,
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

        // ==================================================================
        // Rich, cross-feature demo data: a 3rd client/project (asphalt/road
        // job), plus coverage for every feature shipped since the data above
        // was first written. Client and supplier portal logins are enabled
        // at the very end, once every record they need to show is in place.
        // ==================================================================

        $this->seedExtendedDemoData($company, $owner, $estimator, $accountant, $client, $project, $project2, $invoice, $invoice2, $supplier1, $supplier2);

        $this->command?->info(implode("\n", [
            'Demo company seeded.',
            '',
            'Company team logins (app):',
            '  Owner:      owner@buildxact-saudi.local / Demo@12345',
            '  Estimator:  estimator@buildxact-saudi.local / Demo@12345',
            '  Accountant: accountant@buildxact-saudi.local / Demo@12345',
            '',
            'Client portal login (/portal/login):',
            '  Email:    contact@jeddahheights.sa',
            '  Password: Demo@12345',
            '',
            'Supplier/vendor portal login (/supplier-portal/login):',
            '  Email:    sales@sbmc.sa',
            '  Password: Demo@12345',
        ]));

        $this->seedSecondDemoCompany();
    }

    /**
     * Runs when the demo company already exists (e.g. it was seeded before the extended
     * demo data below was added to this seeder). Looks up the fixed rows the base seeding
     * above always creates with the same known emails/names, then runs the exact same
     * extended-data method a fresh install would get — so re-running `db:seed` on an
     * already-seeded install backfills the new data instead of silently no-op'ing.
     * Guarded on AsphaltEstimate existence so it only backfills once.
     */
    private function backfillExtendedDemoData(Company $company): void
    {
        if (AsphaltEstimate::where('company_id', $company->id)->exists()) {
            $this->command?->info('Demo company already fully seeded (including extended data).');

            return;
        }

        $owner = User::where('company_id', $company->id)->where('role', 'owner')->firstOrFail();
        $estimator = User::where('company_id', $company->id)->where('role', 'estimator')->firstOrFail();
        $accountant = User::where('company_id', $company->id)->where('role', 'accountant')->firstOrFail();
        $client = Client::where('company_id', $company->id)->where('email', 'contact@jeddahheights.sa')->firstOrFail();
        $project = Project::where('company_id', $company->id)->where('name', 'Villa Renovation - Al Nakheel')->firstOrFail();
        $project2 = Project::where('company_id', $company->id)->where('name', 'New Build - Al Malqa Residence')->firstOrFail();
        $invoice = Invoice::where('company_id', $company->id)->where('invoice_number', 'INV-1001')->firstOrFail();
        $invoice2 = Invoice::where('company_id', $company->id)->where('invoice_number', 'INV-1002')->firstOrFail();
        $supplier1 = Supplier::where('company_id', $company->id)->where('email', 'sales@sbmc.sa')->firstOrFail();
        $supplier2 = Supplier::where('company_id', $company->id)->where('email', 'info@gulfceramics.sa')->firstOrFail();

        $this->seedExtendedDemoData($company, $owner, $estimator, $accountant, $client, $project, $project2, $invoice, $invoice2, $supplier1, $supplier2);

        $this->command?->info(implode("\n", [
            'Backfilled extended demo data (asphalt estimate, RFQ bid leveling, purchase orders,',
            'subcontracts, vendor bills, payment certificates, RFI/submittals, equipment, safety,',
            'timesheets, recurring invoice, credit/debit notes, zakat, selections, assemblies,',
            'extra takeoff page, project updates, notifications) into the existing demo company.',
            '',
            'Client portal login (/portal/login):',
            '  Email:    contact@jeddahheights.sa',
            '  Password: Demo@12345',
            '',
            'Supplier/vendor portal login (/supplier-portal/login):',
            '  Email:    sales@sbmc.sa',
            '  Password: Demo@12345',
        ]));
    }

    /**
     * Everything beyond the original baseline demo data above: a 3rd client/project (a
     * road/asphalt job), plus at least one example of every feature shipped since —
     * asphalt estimating, RFQ bid leveling, purchase orders, subcontracts, vendor bill
     * submissions, payment certificates, RFI/submittals, equipment, safety, timesheets,
     * recurring invoices, credit/debit notes, zakat, selections, assemblies, a 2nd
     * takeoff page, project updates, and notifications — then enables the client and
     * supplier portal logins once their data exists to show.
     */
    private function seedExtendedDemoData(
        Company $company,
        User $owner,
        User $estimator,
        User $accountant,
        Client $client,
        Project $project,
        Project $project2,
        Invoice $invoice,
        Invoice $invoice2,
        Supplier $supplier1,
        Supplier $supplier2,
    ): void {
        // ---------------- A 3rd client & a road/asphalt project ----------------
        $client3 = Client::create([
            'company_id' => $company->id,
            'name' => 'Eastern Province Development Authority',
            'name_ar' => 'هيئة تطوير المنطقة الشرقية',
            'email' => 'projects@epda-demo.sa',
            'phone' => '+966 13 456 7890',
            'address' => 'Al Khobar, Eastern Province',
        ]);

        $project3 = Project::create([
            'company_id' => $company->id,
            'client_id' => $client3->id,
            'name' => 'Al Khobar Corniche Road Improvement',
            'name_ar' => 'تحسين طريق كورنيش الخبر',
            'city' => 'Al Khobar',
            'description' => 'Resurfacing and widening of a 1.2km corniche road section, including two side cross-streets.',
            'description_ar' => 'إعادة رصف وتوسعة قطاع من طريق الكورنيش بطول 1.2 كم يشمل شارعين جانبيين متقاطعين.',
            'status' => 'in_progress',
            'budget' => 2700000,
            'start_date' => now()->subDays(25),
            'end_date' => now()->addDays(95),
        ]);

        // ---------------- A 3rd supplier (for the RFQ below) ----------------
        $supplier3 = Supplier::create([
            'company_id' => $company->id, 'name' => 'Riyadh Ready-Mix & Rebar Trading', 'name_ar' => 'الرياض للخرسانة الجاهزة وحديد التسليح',
            'contact_name' => 'Majed Al Shammari', 'email' => 'sales@riyadh-readymix.sa', 'phone' => '+966 11 789 0123',
            'address' => 'Second Industrial City, Riyadh', 'category' => 'Concrete & Rebar',
        ]);

        // ---------------- Asphalt / Road Estimate ----------------
        $segmentInputs = [
            [
                'name' => 'Main Corniche Road', 'sort_order' => 0, 'is_main_road' => true, 'plot_rows' => 0, 'plot_cols' => 0,
                'length_m' => 1200, 'width_m' => 14, 'median_width_m' => 2,
                'subbase_thickness_mm' => 200, 'subbase_unit_cost_per_m3' => 45,
                'base_course_thickness_mm' => 150, 'base_course_unit_cost_per_m3' => 60,
                'asphalt_binder_thickness_mm' => 60, 'asphalt_wearing_thickness_mm' => 40,
                'asphalt_density_kg_per_m3' => 2400, 'asphalt_unit_cost_per_ton' => 320,
            ],
            [
                'name' => 'Cross Street — Palm Avenue', 'sort_order' => 1, 'is_main_road' => false, 'plot_rows' => 2, 'plot_cols' => 6,
                'length_m' => 180, 'width_m' => 10, 'median_width_m' => 0,
                'subbase_thickness_mm' => 180, 'subbase_unit_cost_per_m3' => 45,
                'base_course_thickness_mm' => 120, 'base_course_unit_cost_per_m3' => 60,
                'asphalt_binder_thickness_mm' => 50, 'asphalt_wearing_thickness_mm' => 40,
                'asphalt_density_kg_per_m3' => 2400, 'asphalt_unit_cost_per_ton' => 320,
            ],
            [
                'name' => 'Cross Street — Corniche Access 2', 'sort_order' => 2, 'is_main_road' => false, 'plot_rows' => 0, 'plot_cols' => 0,
                'length_m' => 150, 'width_m' => 9, 'median_width_m' => 0,
                'subbase_thickness_mm' => 180, 'subbase_unit_cost_per_m3' => 45,
                'base_course_thickness_mm' => 120, 'base_course_unit_cost_per_m3' => 60,
                'asphalt_binder_thickness_mm' => 50, 'asphalt_wearing_thickness_mm' => 40,
                'asphalt_density_kg_per_m3' => 2400, 'asphalt_unit_cost_per_ton' => 320,
            ],
        ];
        $otherCosts = [
            'transport_cost_per_ton' => 18,
            'paver_daily_rate' => 3500, 'paver_days' => 12,
            'roller_daily_rate' => 1800, 'roller_days' => 12,
            'labor_crew_size' => 10, 'labor_days' => 14,
            'labor_daily_rate_per_worker' => 120, 'labor_food_daily_rate_per_worker' => 25,
            'surveyor_fee' => 8000, 'lab_testing_fee' => 6500, 'mobilization_fee' => 15000,
        ];
        $computedSegments = array_map(fn (array $s) => AsphaltEstimateCalc::computeSegment($s), $segmentInputs);
        $asphaltTotals = AsphaltEstimateCalc::computeTotals($computedSegments, $otherCosts, 15, 15);
        $asphaltCachedTotals = array_intersect_key($asphaltTotals, array_flip([
            'segments_subtotal', 'other_costs_subtotal', 'subtotal', 'total_asphalt_tons', 'total_area_m2',
            'markup_amount', 'tax_amount', 'total',
        ]));

        $asphaltEstimate = AsphaltEstimate::create(array_merge($otherCosts, [
            'company_id' => $company->id, 'project_id' => $project3->id, 'client_id' => $client3->id,
            'name' => 'Al Khobar Corniche Road Improvement — Road Works', 'city' => 'Al Khobar',
            'status' => 'approved',
            'site_width_m' => 420, 'site_length_m' => 60,
            'notes' => 'Includes main corniche carriageway plus two signalized cross-street tie-ins.',
            'markup_percent' => 15, 'tax_percent' => 15,
        ], $asphaltCachedTotals));

        foreach ($segmentInputs as $i => $input) {
            AsphaltEstimateSegment::create(array_merge($input, $computedSegments[$i], [
                'asphalt_estimate_id' => $asphaltEstimate->id,
            ]));
        }

        // ---------------- RFQ bid leveling ----------------
        $rfq = Rfq::create([
            'company_id' => $company->id, 'project_id' => $project2->id,
            'title' => 'Rebar & Ready-Mix Concrete Supply — Al Malqa Residence',
            'status' => 'quoted', 'due_date' => now()->addDays(10)->format('Y-m-d'),
            'created_by' => $owner->id,
            'notes' => 'Foundation and structural shell package — please quote itemized.',
        ]);
        $rfqItemInputs = [
            ['description' => 'Ready-mix concrete C25', 'qty' => 180, 'unit' => 'm3'],
            ['description' => 'Rebar 12mm deformed bar', 'qty' => 9000, 'unit' => 'kg'],
            ['description' => 'Concrete blocks 20cm', 'qty' => 4000, 'unit' => 'each'],
        ];
        $rfqItems = [];
        foreach ($rfqItemInputs as $it) {
            $rfqItems[] = RfqItem::create(array_merge($it, ['rfq_id' => $rfq->id]));
        }

        $quote1 = RfqQuote::create([
            'rfq_id' => $rfq->id, 'supplier_id' => $supplier1->id, 'total_amount' => 121400,
            'lead_time_days' => 10, 'notes' => 'Can deliver in 2 batches.', 'is_awarded' => false,
            'submitted_at' => now()->subDays(3)->format('Y-m-d'),
        ]);
        $quote1Prices = [265.00, 3.30, 11.00];
        foreach ($rfqItems as $i => $item) {
            RfqQuoteItem::create(['rfq_quote_id' => $quote1->id, 'rfq_item_id' => $item->id, 'unit_price' => $quote1Prices[$i], 'total' => round($item->qty * $quote1Prices[$i], 2)]);
        }

        $quote2 = RfqQuote::create([
            'rfq_id' => $rfq->id, 'supplier_id' => $supplier3->id, 'total_amount' => 115050,
            'lead_time_days' => 7, 'notes' => 'Best price — standing supply agreement available.', 'is_awarded' => true,
            'submitted_at' => now()->subDays(2)->format('Y-m-d'),
        ]);
        $quote2Prices = [255.00, 3.15, 10.20];
        foreach ($rfqItems as $i => $item) {
            RfqQuoteItem::create(['rfq_quote_id' => $quote2->id, 'rfq_item_id' => $item->id, 'unit_price' => $quote2Prices[$i], 'total' => round($item->qty * $quote2Prices[$i], 2)]);
        }

        // ---------------- Purchase orders ----------------
        $po1 = PurchaseOrder::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier1->id,
            'po_number' => 'PO-1001', 'status' => 'issued', 'issue_date' => now()->subDays(6)->format('Y-m-d'),
            'expected_delivery_date' => now()->addDays(4)->format('Y-m-d'),
            'notes' => 'Deliver to site — Villa Renovation, Al Nakheel.',
            'subtotal' => 15850, 'vat_amount' => 2377.50, 'total' => 18227.50,
            'currency' => 'SAR', 'exchange_rate_to_sar' => 1.0, 'acknowledged_at' => now()->subDays(5),
        ]);
        PurchaseOrderItem::create(['purchase_order_id' => $po1->id, 'description' => 'Cement 42.5N (50kg bags)', 'qty' => 500, 'unit_price' => 18.50, 'total' => 9250]);
        PurchaseOrderItem::create(['purchase_order_id' => $po1->id, 'description' => 'Rebar 12mm deformed bar', 'qty' => 2000, 'unit_price' => 3.30, 'total' => 6600]);

        $po2 = PurchaseOrder::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier1->id,
            'po_number' => 'PO-1002', 'status' => 'draft',
            'notes' => 'Draft — pending internal approval before issuing.',
            'subtotal' => 3200, 'vat_amount' => 480, 'total' => 3680,
            'currency' => 'SAR', 'exchange_rate_to_sar' => 1.0,
        ]);
        PurchaseOrderItem::create(['purchase_order_id' => $po2->id, 'description' => 'Sand (fine, washed)', 'qty' => 40, 'unit_price' => 80, 'total' => 3200]);

        $po3 = PurchaseOrder::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier2->id,
            'po_number' => 'PO-1003', 'status' => 'issued', 'issue_date' => now()->subDays(2)->format('Y-m-d'),
            'expected_delivery_date' => now()->addDays(20)->format('Y-m-d'),
            'notes' => 'Imported porcelain tiles — sea freight, Jeddah Islamic Port.',
            'subtotal' => 6600, 'vat_amount' => 990, 'total' => 7590,
            'currency' => 'USD', 'exchange_rate_to_sar' => 3.75,
        ]);
        PurchaseOrderItem::create(['purchase_order_id' => $po3->id, 'description' => 'Porcelain tiles 80x80cm, polished', 'qty' => 300, 'unit_price' => 22.00, 'total' => 6600]);

        // ---------------- Subcontracts ----------------
        $subcontract1 = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier1->id,
            'title' => 'Structural & MEP Rough-in Subcontract',
            'description' => 'Supply and install structural rebar/formwork and MEP first-fix for the villa renovation.',
            'contract_value' => 95000, 'retention_percent' => 10, 'status' => 'active',
            'start_date' => now()->subDays(8)->format('Y-m-d'), 'end_date' => now()->addDays(70)->format('Y-m-d'),
            'currency' => 'SAR', 'exchange_rate_to_sar' => 1.0,
        ]);
        SubcontractPayment::create([
            'company_id' => $company->id, 'subcontract_id' => $subcontract1->id, 'payment_number' => 1,
            'payment_date' => now()->subDay()->format('Y-m-d'), 'status' => 'certified',
            'cumulative_value' => 28500, 'previous_cumulative_value' => 0, 'this_period_value' => 28500,
            'retention_percent' => 10, 'retention_amount' => 2850, 'net_payable' => 25650,
            'created_by' => $owner->id, 'certified_by' => $owner->id, 'certified_at' => now()->subDay(),
        ]);

        Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project3->id, 'supplier_id' => $supplier1->id,
            'title' => 'Road Base & Sub-base Earthworks Subcontract',
            'description' => 'Excavation, grading and compacted sub-base/base-course placement for the corniche road package.',
            'contract_value' => 310000, 'retention_percent' => 10, 'status' => 'active',
            'start_date' => now()->subDays(20)->format('Y-m-d'), 'end_date' => now()->addDays(60)->format('Y-m-d'),
            'currency' => 'SAR', 'exchange_rate_to_sar' => 1.0,
        ]);

        // ---------------- Vendor bills & a pending supplier-submitted bill ----------------
        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier1->id,
            'purchase_order_id' => $po1->id, 'category' => 'material', 'description' => 'Cement & rebar delivery — PO-1001',
            'amount' => 18227.50, 'bill_date' => now()->subDays(4)->format('Y-m-d'), 'reference' => 'SBMC-INV-7741', 'status' => 'paid',
        ]);
        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'category' => 'labor',
            'description' => 'Demolition crew — week 1-2 wages', 'amount' => 14000,
            'bill_date' => now()->subDays(6)->format('Y-m-d'), 'reference' => 'PAYROLL-W1-2', 'status' => 'paid',
        ]);
        VendorBillSubmission::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier1->id,
            'purchase_order_id' => $po1->id, 'category' => 'material',
            'description' => 'Additional cement top-up delivery (50 bags) — same PO', 'amount' => 925,
            'bill_date' => now()->subDay()->format('Y-m-d'), 'reference' => 'SBMC-INV-7812', 'status' => 'pending',
        ]);

        // ---------------- BOQ & Payment Certificates (IPC) ----------------
        $boqInputs = [
            ['item_number' => '1.1', 'section_title' => 'Demolition & Site Prep', 'section_title_ar' => 'الهدم وتجهيز الموقع', 'description' => 'Demolition of existing partitions & finishes', 'description_ar' => 'هدم الحواجز والتشطيبات الحالية', 'uom' => 'lot', 'qty' => 1, 'unit_price' => 25000],
            ['item_number' => '2.1', 'section_title' => 'Structural & MEP', 'section_title_ar' => 'الأعمال الإنشائية والكهروميكانيكية', 'description' => 'Structural rebar & formwork', 'description_ar' => 'حديد التسليح والقوالب الإنشائية', 'uom' => 'lot', 'qty' => 1, 'unit_price' => 95000],
            ['item_number' => '2.2', 'section_title' => 'Structural & MEP', 'section_title_ar' => 'الأعمال الإنشائية والكهروميكانيكية', 'description' => 'Electrical & plumbing first fix', 'description_ar' => 'الأعمال الكهربائية والسباكة الأولية', 'uom' => 'lot', 'qty' => 1, 'unit_price' => 55000],
            ['item_number' => '3.1', 'section_title' => 'Finishes', 'section_title_ar' => 'التشطيبات', 'description' => 'Flooring, painting & fixtures', 'description_ar' => 'الأرضيات والدهانات والتجهيزات', 'uom' => 'lot', 'qty' => 1, 'unit_price' => 100000],
            ['item_number' => '4.1', 'section_title' => 'Labor & Supervision', 'section_title_ar' => 'العمالة والإشراف', 'description' => 'Site supervision & labor', 'description_ar' => 'الإشراف الموقعي والعمالة', 'uom' => 'lot', 'qty' => 1, 'unit_price' => 75000],
        ];
        $boqItems = [];
        foreach ($boqInputs as $i => $b) {
            $boqItems[] = BoqItem::create(array_merge($b, [
                'company_id' => $company->id, 'project_id' => $project->id,
                'total' => $b['qty'] * $b['unit_price'], 'sort_order' => $i,
            ]));
        }

        $certificate1 = PaymentCertificate::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'certificate_number' => 1,
            'certificate_date' => now()->subDays(20)->format('Y-m-d'),
            'period_from' => now()->subDays(30)->format('Y-m-d'), 'period_to' => now()->subDays(20)->format('Y-m-d'),
            'status' => 'certified', 'retention_percent' => 10, 'retention_amount' => 8750,
            'gross_amount' => 87500, 'net_payable' => 78750, 'cumulative_certified' => 87500,
            'created_by' => $owner->id, 'certified_by' => $owner->id, 'certified_at' => now()->subDays(19),
            'signed_at' => now()->subDays(18), 'signed_by_name' => 'Faisal Al Jeddawi (Client Representative)',
        ]);
        $certificate2 = PaymentCertificate::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'certificate_number' => 2,
            'certificate_date' => now()->format('Y-m-d'),
            'period_from' => now()->subDays(19)->format('Y-m-d'), 'period_to' => now()->format('Y-m-d'),
            'status' => 'draft', 'retention_percent' => 10, 'retention_amount' => 8750,
            'gross_amount' => 87500, 'net_payable' => 78750, 'cumulative_certified' => 175000,
            'created_by' => $owner->id,
        ]);
        foreach ([[$certificate1, 0.00, 0.25], [$certificate2, 0.25, 0.50]] as [$certificate, $prevFraction, $cumFraction]) {
            foreach ($boqItems as $boqItem) {
                PaymentCertificateLine::create([
                    'payment_certificate_id' => $certificate->id, 'boq_item_id' => $boqItem->id,
                    'description' => $boqItem->description, 'description_ar' => $boqItem->description_ar, 'uom' => $boqItem->uom,
                    'contract_qty' => $boqItem->qty, 'contract_unit_price' => $boqItem->unit_price, 'contract_total' => $boqItem->total,
                    'previous_cumulative_qty' => round($boqItem->qty * $prevFraction, 2), 'cumulative_qty' => round($boqItem->qty * $cumFraction, 2),
                    'this_period_qty' => round($boqItem->qty * ($cumFraction - $prevFraction), 2),
                    'this_period_value' => round($boqItem->total * ($cumFraction - $prevFraction), 2),
                ]);
            }
        }

        // ---------------- RFI & Submittals ----------------
        $planDocument = Document::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'uploaded_by' => $owner->id,
            'name' => 'Villa Renovation - Ground Floor Plan', 'name_ar' => 'تجديد الفيلا - مخطط الدور الأرضي',
            'file_path' => '/assets/img/demo/takeoff-ground-floor.jpg', 'file_type' => 'image/jpeg', 'file_size' => 512340,
        ]);

        $rfi1 = Rfi::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'rfi_number' => 1,
            'subject' => 'Kitchen countertop material confirmation',
            'question' => 'Please confirm the approved marble slab type/color before fabrication is ordered.',
            'raised_by' => $estimator->id, 'assigned_to' => $owner->id, 'status' => 'answered',
            'due_date' => now()->subDays(5)->format('Y-m-d'),
        ]);
        RfiMessage::create(['rfi_id' => $rfi1->id, 'sender_id' => $estimator->id, 'sender_name' => $estimator->name, 'message' => 'Client requested marble upgrade via change order — which slab/color should we order?']);
        RfiMessage::create(['rfi_id' => $rfi1->id, 'sender_id' => $owner->id, 'sender_name' => $owner->name, 'message' => 'Confirmed: Carrara White, as discussed with the client on site.']);

        $rfi2 = Rfi::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'rfi_number' => 2,
            'subject' => 'Electrical panel location clarification',
            'question' => 'Drawing shows the new distribution panel in the hallway; client prefers the utility room. Please confirm before first-fix.',
            'raised_by' => $estimator->id, 'assigned_to' => $owner->id, 'status' => 'open',
            'due_date' => now()->addDays(5)->format('Y-m-d'), 'document_id' => $planDocument->id,
        ]);
        Rfi::create([
            'company_id' => $company->id, 'project_id' => $project3->id, 'rfi_number' => 1,
            'subject' => 'Road marking paint specification',
            'question' => 'Please confirm thermoplastic vs. chlorinated rubber paint for the lane markings per municipality spec.',
            'raised_by' => $owner->id, 'status' => 'closed', 'due_date' => now()->subDays(10)->format('Y-m-d'),
        ]);

        DocumentPin::create([
            'document_id' => $planDocument->id, 'company_id' => $company->id, 'x' => 62.500, 'y' => 38.200,
            'pinnable_type' => Rfi::class, 'pinnable_id' => $rfi2->id, 'created_by' => $owner->id, 'label' => 'RFI-002',
        ]);

        Submittal::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'submittal_number' => 1,
            'title' => 'Marble countertop sample submission', 'spec_section' => '12 36 00', 'status' => 'approved',
            'submitted_by' => $estimator->id, 'reviewed_by' => $owner->id, 'reviewed_at' => now()->subDays(2),
            'due_date' => now()->subDays(3)->format('Y-m-d'),
        ]);
        Submittal::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'submittal_number' => 2,
            'title' => 'Paint color sample board', 'spec_section' => '09 91 00', 'status' => 'under_review',
            'submitted_by' => $estimator->id, 'due_date' => now()->addDays(4)->format('Y-m-d'),
        ]);
        Submittal::create([
            'company_id' => $company->id, 'project_id' => $project3->id, 'submittal_number' => 1,
            'title' => 'Asphalt mix design report', 'spec_section' => '32 12 16', 'status' => 'approved',
            'submitted_by' => $owner->id, 'reviewed_by' => $owner->id, 'reviewed_at' => now()->subDays(7),
            'due_date' => now()->subDays(12)->format('Y-m-d'),
        ]);

        // ---------------- Equipment & fleet ----------------
        $equip1 = Equipment::create(['company_id' => $company->id, 'name' => 'Excavator — CAT 320', 'name_ar' => 'حفارة - كاتربيلر 320', 'asset_number' => 'EQ-1001', 'category' => 'Excavator', 'ownership_type' => 'owned', 'purchase_date' => now()->subYears(2)->format('Y-m-d'), 'purchase_cost' => 480000, 'status' => 'in_use']);
        Equipment::create(['company_id' => $company->id, 'name' => 'Concrete Mixer Truck', 'name_ar' => 'شاحنة خلاطة خرسانة', 'asset_number' => 'EQ-1002', 'category' => 'Concrete Mixer', 'ownership_type' => 'rented', 'rental_cost_per_day' => 650, 'status' => 'available', 'supplier_id' => $supplier1->id]);
        $equip3 = Equipment::create(['company_id' => $company->id, 'name' => 'Tandem Vibratory Roller', 'name_ar' => 'مدحلة اهتزازية مزدوجة', 'asset_number' => 'EQ-1003', 'category' => 'Roller', 'ownership_type' => 'rented', 'rental_cost_per_day' => 900, 'status' => 'in_use']);

        EquipmentAssignment::create(['company_id' => $company->id, 'equipment_id' => $equip1->id, 'project_id' => $project3->id, 'assigned_date' => now()->subDays(20)->format('Y-m-d'), 'notes' => 'Earthworks & sub-base excavation.']);
        EquipmentAssignment::create(['company_id' => $company->id, 'equipment_id' => $equip3->id, 'project_id' => $project3->id, 'assigned_date' => now()->subDays(5)->format('Y-m-d'), 'notes' => 'Base-course compaction.']);
        EquipmentAssignment::create(['company_id' => $company->id, 'equipment_id' => $equip1->id, 'project_id' => $project->id, 'assigned_date' => now()->subDays(60)->format('Y-m-d'), 'returned_date' => now()->subDays(10)->format('Y-m-d'), 'notes' => 'Used for initial demolition debris handling.']);
        EquipmentMaintenanceLog::create(['company_id' => $company->id, 'equipment_id' => $equip1->id, 'maintenance_date' => now()->subDays(30)->format('Y-m-d'), 'description' => '500-hour service — oil, filters, hydraulic check', 'cost' => 3200, 'performed_by' => 'CAT Dealer Service Center', 'next_due_date' => now()->addDays(60)->format('Y-m-d')]);

        // ---------------- Safety (HSE) ----------------
        SafetyIncident::create(['company_id' => $company->id, 'project_id' => $project->id, 'incident_number' => 1, 'incident_date' => now()->subDays(12)->format('Y-m-d'), 'incident_type' => 'Near Miss', 'severity' => 'low', 'description' => 'Scaffolding plank shifted during demolition works; no injury, area cordoned off immediately.', 'location' => 'Ground floor, east wing', 'reported_by' => $owner->id, 'corrective_action' => 'Re-secured scaffold clamps and added a daily pre-use inspection checklist.', 'status' => 'closed']);
        SafetyIncident::create(['company_id' => $company->id, 'project_id' => $project3->id, 'incident_number' => 1, 'incident_date' => now()->subDays(4)->format('Y-m-d'), 'incident_type' => 'First Aid', 'severity' => 'low', 'description' => 'Minor hand cut while handling rebar; treated on site with the first aid kit.', 'location' => 'Cross Street — Palm Avenue', 'injured_person_name' => 'Site laborer (subcontractor crew)', 'reported_by' => $owner->id, 'corrective_action' => 'Reissued cut-resistant gloves to all rebar crew members.', 'status' => 'closed']);
        ToolboxTalk::create(['company_id' => $company->id, 'project_id' => $project->id, 'talk_date' => now()->subDays(9)->format('Y-m-d'), 'topic' => 'Safe scaffold use & fall protection', 'conducted_by' => $owner->id, 'attendee_count' => 8, 'notes' => 'Reviewed scaffold inspection checklist and harness use for upper-floor works.']);
        ToolboxTalk::create(['company_id' => $company->id, 'project_id' => $project3->id, 'talk_date' => now()->subDays(18)->format('Y-m-d'), 'topic' => 'Traffic management & road-work signage', 'conducted_by' => $owner->id, 'attendee_count' => 14, 'notes' => 'Reviewed lane closure signage plan and high-visibility PPE requirements for roadside works.']);

        // ---------------- Timesheets ----------------
        foreach (range(1, 5) as $dayOffset) {
            TimesheetEntry::create(['company_id' => $company->id, 'project_id' => $project->id, 'user_id' => $owner->id, 'work_date' => now()->subDays($dayOffset)->format('Y-m-d'), 'hours' => 8, 'hourly_rate_snapshot' => $owner->hourly_rate, 'cost' => 8 * $owner->hourly_rate, 'logged_by' => $owner->id]);
            TimesheetEntry::create(['company_id' => $company->id, 'project_id' => $project->id, 'user_id' => $estimator->id, 'work_date' => now()->subDays($dayOffset)->format('Y-m-d'), 'hours' => 6, 'hourly_rate_snapshot' => $estimator->hourly_rate, 'cost' => 6 * $estimator->hourly_rate, 'logged_by' => $estimator->id]);
        }

        // ---------------- Recurring invoice ----------------
        $recurringInvoice = RecurringInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id,
            'title' => 'Monthly Facilities Maintenance Retainer', 'frequency' => 'monthly',
            'next_run_date' => now()->addDays(9)->format('Y-m-d'), 'last_generated_at' => now()->subDays(21),
            'is_active' => true, 'apply_vat' => true, 'retention_percent' => 0, 'due_days' => 14,
        ]);
        RecurringInvoiceItem::create(['recurring_invoice_id' => $recurringInvoice->id, 'description' => 'Monthly facilities maintenance & minor repairs', 'description_ar' => 'الصيانة الشهرية للمرافق والإصلاحات الطفيفة', 'qty' => 1, 'unit_price' => 4500]);

        // ---------------- Credit & debit notes ----------------
        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice2->id, 'client_id' => $client->id,
            'note_number' => 'CN-1001', 'issue_date' => now()->subDays(2)->format('Y-m-d'),
            'reason' => 'Quantity correction on structural works progress billing.',
            'status' => 'issued', 'subtotal' => 5000, 'vat_rate' => 15, 'vat_amount' => 750, 'total' => 5750,
        ]);
        CreditNoteItem::create(['credit_note_id' => $creditNote->id, 'description' => 'Structural works progress payment — quantity correction', 'description_ar' => 'دفعة تقدم الأعمال الإنشائية - تصحيح الكمية', 'qty' => 1, 'unit_price' => 5000, 'total' => 5000]);

        $debitNote = DebitNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'note_number' => 'DN-1001', 'issue_date' => now()->subDay()->format('Y-m-d'),
            'reason' => 'Additional mobilization costs not captured in the original invoice.',
            'status' => 'issued', 'subtotal' => 3000, 'vat_rate' => 15, 'vat_amount' => 450, 'total' => 3450,
        ]);
        DebitNoteItem::create(['debit_note_id' => $debitNote->id, 'description' => 'Additional mobilization costs', 'description_ar' => 'تكاليف تعبئة إضافية', 'qty' => 1, 'unit_price' => 3000, 'total' => 3000]);

        // ---------------- Zakat calculation ----------------
        ZakatCalculation::create([
            'company_id' => $company->id, 'created_by' => $owner->id,
            'period_end_date' => now()->subDays(45)->format('Y-m-d'), 'rate_type' => 'hijri',
            'equity_amount' => 1200000, 'long_term_liabilities' => 150000, 'net_fixed_assets' => 480000, 'other_deductions' => 50000,
            'zakat_base' => 820000, 'zakat_due' => 20500,
            'notes' => 'Based on FY1446H audited financials provided by the company accountant.',
        ]);

        // ---------------- Selections (client-facing finish allowances) ----------------
        $selection1 = Selection::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'category' => 'Kitchen',
            'name' => 'Kitchen Countertop', 'name_ar' => 'سطح المطبخ',
            'description' => 'Allowance for kitchen countertop material and fabrication.', 'description_ar' => 'مخصص لمادة سطح المطبخ والتصنيع.',
            'allowance_amount' => 3000, 'status' => 'client_selected', 'due_date' => now()->addDays(15)->format('Y-m-d'),
        ]);
        SelectionOption::create(['selection_id' => $selection1->id, 'name' => 'Granite — Standard Grey', 'name_ar' => 'جرانيت - رمادي قياسي', 'price' => 2800, 'sort_order' => 0, 'is_chosen' => false]);
        SelectionOption::create(['selection_id' => $selection1->id, 'name' => 'Marble — Carrara White', 'name_ar' => 'رخام - كارارا أبيض', 'price' => 4200, 'sort_order' => 1, 'is_chosen' => true]);
        SelectionOption::create(['selection_id' => $selection1->id, 'name' => 'Quartz — Engineered Stone', 'name_ar' => 'كوارتز - حجر هندسي', 'price' => 3500, 'sort_order' => 2, 'is_chosen' => false]);

        $selection2 = Selection::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'category' => 'Flooring',
            'name' => 'Living Room Flooring', 'name_ar' => 'أرضية غرفة المعيشة',
            'description' => 'Allowance for the main living area floor finish.', 'description_ar' => 'مخصص لتشطيب أرضية منطقة المعيشة الرئيسية.',
            'allowance_amount' => 2500, 'status' => 'pending', 'due_date' => now()->addDays(25)->format('Y-m-d'),
        ]);
        SelectionOption::create(['selection_id' => $selection2->id, 'name' => 'Ceramic Tile 60x60', 'name_ar' => 'بلاط سيراميك 60×60', 'price' => 2200, 'sort_order' => 0, 'is_chosen' => false]);
        SelectionOption::create(['selection_id' => $selection2->id, 'name' => 'Porcelain Tile 80x80', 'name_ar' => 'بلاط بورسلين 80×80', 'price' => 2900, 'sort_order' => 1, 'is_chosen' => false]);

        // ---------------- Estimate assemblies ----------------
        $assembly1 = Assembly::create(['company_id' => $company->id, 'name' => 'Standard Interior Block Wall (Finished)', 'name_ar' => 'جدار بلوك داخلي قياسي (منتهي)', 'base_uom' => 'sqm', 'description' => 'Block wall with plaster and paint finish, both sides.', 'is_active' => true]);
        AssemblyItem::create(['assembly_id' => $assembly1->id, 'description' => 'Concrete block 20cm', 'description_ar' => 'بلوك خرساني 20 سم', 'item_type' => 'material', 'uom' => 'each', 'qty_per_unit' => 12.5, 'unit_cost' => 3.80, 'sort_order' => 0]);
        AssemblyItem::create(['assembly_id' => $assembly1->id, 'description' => 'Cement plaster, both faces', 'description_ar' => 'لياسة إسمنتية على الوجهين', 'item_type' => 'material', 'uom' => 'sqm', 'qty_per_unit' => 2, 'unit_cost' => 12.00, 'sort_order' => 1]);
        AssemblyItem::create(['assembly_id' => $assembly1->id, 'description' => 'Paint, two coats', 'description_ar' => 'دهان طبقتين', 'item_type' => 'material', 'uom' => 'sqm', 'qty_per_unit' => 2, 'unit_cost' => 8.50, 'sort_order' => 2]);
        AssemblyItem::create(['assembly_id' => $assembly1->id, 'description' => 'Mason & labor', 'description_ar' => 'عامل بناء وأجرة', 'item_type' => 'labor', 'uom' => 'sqm', 'qty_per_unit' => 1, 'unit_cost' => 35.00, 'sort_order' => 3]);

        $assembly2 = Assembly::create(['company_id' => $company->id, 'name' => 'Standard Ceramic Floor Finish', 'name_ar' => 'تشطيب أرضية سيراميك قياسي', 'base_uom' => 'sqm', 'description' => 'Ceramic tile floor finish including screed, adhesive and grout.', 'is_active' => true]);
        AssemblyItem::create(['assembly_id' => $assembly2->id, 'description' => 'Ceramic floor tile 60x60', 'description_ar' => 'بلاط أرضية سيراميك 60×60', 'item_type' => 'material', 'uom' => 'sqm', 'qty_per_unit' => 1.05, 'unit_cost' => 45.00, 'sort_order' => 0]);
        AssemblyItem::create(['assembly_id' => $assembly2->id, 'description' => 'Sand/cement screed bed', 'description_ar' => 'طبقة تسوية رملية إسمنتية', 'item_type' => 'material', 'uom' => 'sqm', 'qty_per_unit' => 1, 'unit_cost' => 15.00, 'sort_order' => 1]);
        AssemblyItem::create(['assembly_id' => $assembly2->id, 'description' => 'Tile layer labor', 'description_ar' => 'عامل تركيب البلاط', 'item_type' => 'labor', 'uom' => 'sqm', 'qty_per_unit' => 1, 'unit_cost' => 20.00, 'sort_order' => 2]);

        // ---------------- Takeoff: assign the existing page, add a 2nd page ----------------
        $takeoff = Takeoff::where('company_id', $company->id)->where('project_id', $project->id)->firstOrFail();
        $takeoffPage1 = TakeoffPage::create(['takeoff_id' => $takeoff->id, 'page_number' => 1, 'image_path' => '/assets/img/demo/takeoff-ground-floor.jpg', 'scale_px_per_unit' => 12.5, 'scale_unit' => 'm']);
        TakeoffMeasurement::where('takeoff_id', $takeoff->id)->update(['takeoff_page_id' => $takeoffPage1->id]);

        $takeoffPage2 = TakeoffPage::create(['takeoff_id' => $takeoff->id, 'page_number' => 2, 'image_path' => '/assets/img/demo/takeoff-first-floor.jpg', 'scale_px_per_unit' => 12.5, 'scale_unit' => 'm']);
        TakeoffMeasurement::create(['takeoff_id' => $takeoff->id, 'takeoff_page_id' => $takeoffPage2->id, 'type' => 'area', 'label' => 'Master bedroom floor area', 'points_json' => json_encode([[20, 20], [180, 20], [180, 140], [20, 140]]), 'value' => 28.0, 'unit' => 'sqm', 'unit_cost' => 45.00, 'total_cost' => 1260.00]);
        TakeoffMeasurement::create(['takeoff_id' => $takeoff->id, 'takeoff_page_id' => $takeoffPage2->id, 'type' => 'linear', 'label' => 'Corridor partition wall', 'points_json' => json_encode([[20, 150], [180, 150]]), 'value' => 16.0, 'unit' => 'lm', 'unit_cost' => 65.00, 'total_cost' => 1040.00, 'assembly_id' => $assembly1->id]);
        TakeoffMeasurement::create(['takeoff_id' => $takeoff->id, 'takeoff_page_id' => $takeoffPage2->id, 'type' => 'count', 'label' => 'Door openings', 'points_json' => json_encode([[50, 50]]), 'value' => 6, 'unit' => 'each', 'unit_cost' => 850.00, 'total_cost' => 5100.00]);

        // ---------------- Project updates (feeds the client portal) ----------------
        ProjectUpdate::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'title' => 'Structural works progressing on schedule',
            'body' => "Demolition is complete and structural rebar/formwork is now underway on the ground floor. Electrical and plumbing rough-in has started in parallel. We remain on track for the interior finishing phase to begin in about three weeks.\n\nNext milestone: structural concrete pour, expected within 10 days.",
            'source' => 'ai_draft', 'created_by' => $owner->id, 'published_at' => now()->subDays(2),
        ]);
        ProjectUpdate::create([
            'company_id' => $company->id, 'project_id' => $project->id,
            'title' => 'Marble countertop selection confirmed',
            'body' => 'Thank you for confirming the Carrara White marble upgrade. Fabrication has been ordered and is expected on site in 3 weeks, ahead of the kitchen installation phase.',
            'source' => 'manual', 'created_by' => $owner->id, 'published_at' => null,
        ]);

        // ---------------- Notifications ----------------
        Notification::create(['company_id' => $company->id, 'type' => 'invoice_paid', 'title' => 'Invoice INV-1001 paid', 'message' => 'Jeddah Heights Development paid invoice INV-1001 (100,000 SAR).', 'link' => '/app/invoices/'.$invoice->id, 'read_at' => now()->subDays(4)]);
        Notification::create(['company_id' => $company->id, 'type' => 'change_order_approved', 'title' => 'Change order approved', 'message' => 'Client approved "Upgrade kitchen countertops to marble" (+12,500 SAR).', 'link' => '/app/projects/'.$project->id, 'read_at' => null]);
        Notification::create(['company_id' => $company->id, 'type' => 'rfq_quote_received', 'title' => 'New RFQ quote received', 'message' => 'Riyadh Ready-Mix & Rebar Trading submitted a quote for "Rebar & Ready-Mix Concrete Supply".', 'link' => '/app/rfqs/'.$rfq->id, 'read_at' => null]);

        // ==================================================================
        // Additional volume: 2 more clients/projects, more estimates/invoices,
        // a 2nd asphalt estimate, a 2nd RFQ, more POs/subcontracts/vendor bills,
        // a 3rd payment certificate, more RFIs/submittals/equipment/safety, more
        // timesheets, another recurring invoice + credit/debit note pair, a
        // selection, an assembly, and leads — so lists aren't sparse with just
        // one or two of everything.
        // ==================================================================

        // ---------------- 2 more clients & projects ----------------
        $client4 = Client::create([
            'company_id' => $company->id, 'name' => 'Al Faisaliah Commercial Developers', 'name_ar' => 'الفيصلية للتطوير التجاري',
            'email' => 'projects@alfaisaliah-dev.sa', 'phone' => '+966 11 234 9900', 'address' => 'Al Olaya, Riyadh',
        ]);
        $client5 = Client::create([
            'company_id' => $company->id, 'name' => 'Mansour Al Dosari', 'name_ar' => 'منصور الدوسري',
            'email' => 'mansour.dosari@example.com', 'phone' => '+966 58 765 4321', 'address' => 'Al Yasmin District, Riyadh',
        ]);

        $project4 = Project::create([
            'company_id' => $company->id, 'client_id' => $client4->id,
            'name' => 'Al Faisaliah Office Fit-Out', 'name_ar' => 'تجهيز مكاتب الفيصلية',
            'description' => 'Category-A office fit-out for a 1,200 sqm floor, including partitions, ceilings and MEP.',
            'description_ar' => 'تجهيز مكاتب من الفئة أ لطابق مساحته 1200 متر مربع يشمل الحواجز والأسقف والأعمال الكهروميكانيكية.',
            'status' => 'completed', 'budget' => 480000,
            'start_date' => now()->subDays(140), 'end_date' => now()->subDays(20),
        ]);
        $project5 = Project::create([
            'company_id' => $company->id, 'client_id' => $client5->id,
            'name' => 'Al Dosari Apartment Building', 'name_ar' => 'عمارة الدوسري السكنية',
            'description' => 'An 8-unit, 4-story apartment building with ground-floor parking.',
            'description_ar' => 'عمارة سكنية من 8 وحدات على 4 طوابق مع موقف سيارات بالدور الأرضي.',
            'status' => 'on_hold', 'budget' => 1450000,
            'start_date' => now()->subDays(60), 'end_date' => now()->addDays(240),
        ]);

        // ---------------- More estimates ----------------
        $estimate3 = Estimate::create([
            'company_id' => $company->id, 'project_id' => $project4->id, 'client_id' => $client4->id,
            'title' => 'Al Faisaliah Office Fit-Out — Revised Estimate', 'title_ar' => 'تجهيز مكاتب الفيصلية — تسعيرة منقحة',
            'status' => 'declined', 'total' => 520000, 'share_token' => bin2hex(random_bytes(20)),
        ]);
        EstimateItem::create(['estimate_id' => $estimate3->id, 'description' => 'Partitions, ceilings & flooring', 'description_ar' => 'الحواجز والأسقف والأرضيات', 'qty' => 1, 'unit_cost' => 320000, 'total' => 320000]);
        EstimateItem::create(['estimate_id' => $estimate3->id, 'description' => 'MEP & lighting upgrade', 'description_ar' => 'ترقية الأعمال الكهروميكانيكية والإنارة', 'qty' => 1, 'unit_cost' => 200000, 'total' => 200000]);

        $estimate4 = Estimate::create([
            'company_id' => $company->id, 'project_id' => $project5->id, 'client_id' => $client5->id,
            'title' => 'Al Dosari Apartment Building — Draft Estimate', 'title_ar' => 'عمارة الدوسري — تسعيرة أولية',
            'status' => 'draft', 'total' => 1450000, 'share_token' => bin2hex(random_bytes(20)),
        ]);
        EstimateItem::create(['estimate_id' => $estimate4->id, 'description' => 'Structural shell, 4 floors', 'description_ar' => 'الهيكل الإنشائي، 4 طوابق', 'qty' => 1, 'unit_cost' => 820000, 'total' => 820000]);
        EstimateItem::create(['estimate_id' => $estimate4->id, 'description' => 'MEP, finishes & external works', 'description_ar' => 'الأعمال الكهروميكانيكية والتشطيبات والأعمال الخارجية', 'qty' => 1, 'unit_cost' => 630000, 'total' => 630000]);

        $estimate5 = Estimate::create([
            'company_id' => $company->id, 'project_id' => $project3->id, 'client_id' => $client3->id,
            'title' => 'Road Signage & Line Marking Estimate', 'title_ar' => 'تسعيرة اللافتات وعلامات الطرق',
            'status' => 'accepted', 'total' => 95000, 'share_token' => bin2hex(random_bytes(20)),
        ]);
        EstimateItem::create(['estimate_id' => $estimate5->id, 'description' => 'Thermoplastic lane markings', 'description_ar' => 'علامات الحارات الثرموبلاستيك', 'qty' => 1, 'unit_cost' => 55000, 'total' => 55000]);
        EstimateItem::create(['estimate_id' => $estimate5->id, 'description' => 'Traffic & directional signage', 'description_ar' => 'لافتات المرور والإرشاد', 'qty' => 1, 'unit_cost' => 40000, 'total' => 40000]);

        // ---------------- More invoices ----------------
        $invoice3 = Invoice::create([
            'company_id' => $company->id, 'project_id' => $project4->id, 'client_id' => $client4->id,
            'invoice_number' => 'INV-1003', 'status' => 'draft', 'total' => 48000, 'vat_rate' => 15, 'vat_amount' => 6260.87,
            'due_date' => now()->addDays(21), 'share_token' => bin2hex(random_bytes(20)),
        ]);
        InvoiceItem::create(['invoice_id' => $invoice3->id, 'description' => 'Final account — fit-out works', 'description_ar' => 'الحساب الختامي - أعمال التجهيز', 'qty' => 1, 'unit_price' => 41739.13, 'total' => 41739.13]);

        $invoice4 = Invoice::create([
            'company_id' => $company->id, 'project_id' => $project5->id, 'client_id' => $client5->id,
            'invoice_number' => 'INV-1004', 'status' => 'overdue', 'total' => 72500, 'vat_rate' => 15, 'vat_amount' => 9456.52,
            'due_date' => now()->subDays(18), 'share_token' => bin2hex(random_bytes(20)),
        ]);
        InvoiceItem::create(['invoice_id' => $invoice4->id, 'description' => 'Structural shell — progress payment', 'description_ar' => 'الهيكل الإنشائي - دفعة تقدم الأعمال', 'qty' => 1, 'unit_price' => 63043.48, 'total' => 63043.48]);

        $invoice5 = Invoice::create([
            'company_id' => $company->id, 'project_id' => $project3->id, 'client_id' => $client3->id,
            'invoice_number' => 'INV-1005', 'status' => 'paid', 'total' => 850000, 'vat_rate' => 15, 'vat_amount' => 110869.57,
            'due_date' => now()->subDays(8), 'share_token' => bin2hex(random_bytes(20)),
        ]);
        InvoiceItem::create(['invoice_id' => $invoice5->id, 'description' => 'Road works — milestone 1 payment', 'description_ar' => 'أعمال الطريق - دفعة المرحلة الأولى', 'qty' => 1, 'unit_price' => 739130.43, 'total' => 739130.43]);

        // ---------------- 2nd Asphalt Estimate: apartment parking apron ----------------
        $segment2Inputs = [[
            'name' => 'Ground-Floor Parking Apron', 'sort_order' => 0, 'is_main_road' => true, 'plot_rows' => 3, 'plot_cols' => 8,
            'length_m' => 40, 'width_m' => 22, 'median_width_m' => 0,
            'subbase_thickness_mm' => 150, 'subbase_unit_cost_per_m3' => 42,
            'base_course_thickness_mm' => 100, 'base_course_unit_cost_per_m3' => 55,
            'asphalt_binder_thickness_mm' => 40, 'asphalt_wearing_thickness_mm' => 30,
            'asphalt_density_kg_per_m3' => 2400, 'asphalt_unit_cost_per_ton' => 320,
        ]];
        $otherCosts2 = [
            'transport_cost_per_ton' => 18,
            'paver_daily_rate' => 3500, 'paver_days' => 2,
            'roller_daily_rate' => 1800, 'roller_days' => 2,
            'labor_crew_size' => 6, 'labor_days' => 3,
            'labor_daily_rate_per_worker' => 120, 'labor_food_daily_rate_per_worker' => 25,
            'surveyor_fee' => 2500, 'lab_testing_fee' => 1800, 'mobilization_fee' => 4000,
        ];
        $computedSegments2 = array_map(fn (array $s) => AsphaltEstimateCalc::computeSegment($s), $segment2Inputs);
        $asphaltTotals2 = AsphaltEstimateCalc::computeTotals($computedSegments2, $otherCosts2, 15, 15);
        $asphaltCachedTotals2 = array_intersect_key($asphaltTotals2, array_flip([
            'segments_subtotal', 'other_costs_subtotal', 'subtotal', 'total_asphalt_tons', 'total_area_m2',
            'markup_amount', 'tax_amount', 'total',
        ]));
        $asphaltEstimate2 = AsphaltEstimate::create(array_merge($otherCosts2, [
            'company_id' => $company->id, 'project_id' => $project5->id, 'client_id' => $client5->id,
            'name' => 'Al Dosari Apartment Building — Parking Apron', 'city' => 'Riyadh',
            'status' => 'draft', 'site_width_m' => 22, 'site_length_m' => 40,
            'notes' => 'Ground-floor parking apron paving, to follow structural shell completion.',
            'markup_percent' => 15, 'tax_percent' => 15,
        ], $asphaltCachedTotals2));
        foreach ($segment2Inputs as $i => $input) {
            AsphaltEstimateSegment::create(array_merge($input, $computedSegments2[$i], ['asphalt_estimate_id' => $asphaltEstimate2->id]));
        }

        // ---------------- A 4th supplier & a 2nd RFQ bid leveling ----------------
        $supplier4 = Supplier::create([
            'company_id' => $company->id, 'name' => 'Gulf HVAC Systems', 'name_ar' => 'أنظمة التكييف الخليجية',
            'contact_name' => 'Yasser Al Qurashi', 'email' => 'sales@gulfhvac.sa', 'phone' => '+966 12 678 9012',
            'address' => 'Jeddah Industrial City', 'category' => 'HVAC & MEP',
        ]);

        $rfq2 = Rfq::create([
            'company_id' => $company->id, 'project_id' => $project4->id,
            'title' => 'HVAC Split Units & Ductwork — Al Faisaliah Office Fit-Out',
            'status' => 'quoted', 'due_date' => now()->subDays(100)->format('Y-m-d'),
            'created_by' => $owner->id, 'notes' => 'Office floor HVAC package — please quote itemized, installed.',
        ]);
        $rfq2ItemInputs = [
            ['description' => 'Ducted split AC unit 24,000 BTU', 'qty' => 10, 'unit' => 'each'],
            ['description' => 'Galvanized ductwork', 'qty' => 180, 'unit' => 'lm'],
            ['description' => 'Diffusers & grilles', 'qty' => 40, 'unit' => 'each'],
        ];
        $rfq2Items = [];
        foreach ($rfq2ItemInputs as $it) {
            $rfq2Items[] = RfqItem::create(array_merge($it, ['rfq_id' => $rfq2->id]));
        }
        $rfq2Quote1 = RfqQuote::create(['rfq_id' => $rfq2->id, 'supplier_id' => $supplier4->id, 'total_amount' => 68400, 'lead_time_days' => 14, 'notes' => 'Includes commissioning.', 'is_awarded' => true, 'submitted_at' => now()->subDays(105)->format('Y-m-d')]);
        $rfq2Quote1Prices = [5200.00, 95.00, 110.00];
        foreach ($rfq2Items as $i => $item) {
            RfqQuoteItem::create(['rfq_quote_id' => $rfq2Quote1->id, 'rfq_item_id' => $item->id, 'unit_price' => $rfq2Quote1Prices[$i], 'total' => round($item->qty * $rfq2Quote1Prices[$i], 2)]);
        }
        $rfq2Quote2 = RfqQuote::create(['rfq_id' => $rfq2->id, 'supplier_id' => $supplier2->id, 'total_amount' => 74200, 'lead_time_days' => 10, 'notes' => 'Faster lead time, premium brand units.', 'is_awarded' => false, 'submitted_at' => now()->subDays(104)->format('Y-m-d')]);
        $rfq2Quote2Prices = [5650.00, 102.00, 125.00];
        foreach ($rfq2Items as $i => $item) {
            RfqQuoteItem::create(['rfq_quote_id' => $rfq2Quote2->id, 'rfq_item_id' => $item->id, 'unit_price' => $rfq2Quote2Prices[$i], 'total' => round($item->qty * $rfq2Quote2Prices[$i], 2)]);
        }

        // ---------------- More purchase orders ----------------
        $po4 = PurchaseOrder::create([
            'company_id' => $company->id, 'project_id' => $project4->id, 'supplier_id' => $supplier4->id,
            'po_number' => 'PO-1004', 'status' => 'issued', 'issue_date' => now()->subDays(110)->format('Y-m-d'),
            'expected_delivery_date' => now()->subDays(95)->format('Y-m-d'),
            'notes' => 'HVAC package per awarded RFQ quote.',
            'subtotal' => 68400, 'vat_amount' => 10260, 'total' => 78660,
            'currency' => 'SAR', 'exchange_rate_to_sar' => 1.0, 'acknowledged_at' => now()->subDays(108),
        ]);
        PurchaseOrderItem::create(['purchase_order_id' => $po4->id, 'description' => 'Ducted split AC unit 24,000 BTU', 'qty' => 10, 'unit_price' => 5200.00, 'total' => 52000]);

        $po5 = PurchaseOrder::create([
            'company_id' => $company->id, 'project_id' => $project5->id, 'supplier_id' => $supplier1->id,
            'po_number' => 'PO-1005', 'status' => 'draft',
            'notes' => 'Structural materials for shell — pending site mobilization.',
            'subtotal' => 42000, 'vat_amount' => 6300, 'total' => 48300,
            'currency' => 'SAR', 'exchange_rate_to_sar' => 1.0,
        ]);
        PurchaseOrderItem::create(['purchase_order_id' => $po5->id, 'description' => 'Ready-mix concrete C30', 'qty' => 120, 'unit_price' => 290.00, 'total' => 34800]);
        PurchaseOrderItem::create(['purchase_order_id' => $po5->id, 'description' => 'Rebar 16mm deformed bar (ton)', 'qty' => 2.5, 'unit_price' => 2880.00, 'total' => 7200]);

        // ---------------- A 3rd subcontract ----------------
        Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project4->id, 'supplier_id' => $supplier4->id,
            'title' => 'HVAC Supply & Install Subcontract',
            'description' => 'Supply, installation and commissioning of the office floor HVAC package.',
            'contract_value' => 78660, 'retention_percent' => 5, 'status' => 'completed',
            'start_date' => now()->subDays(108)->format('Y-m-d'), 'end_date' => now()->subDays(25)->format('Y-m-d'),
            'currency' => 'SAR', 'exchange_rate_to_sar' => 1.0,
        ]);

        // ---------------- More vendor bills & another pending submission ----------------
        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project4->id, 'supplier_id' => $supplier4->id,
            'purchase_order_id' => $po4->id, 'category' => 'material', 'description' => 'HVAC package delivery & install — PO-1004',
            'amount' => 78660, 'bill_date' => now()->subDays(100)->format('Y-m-d'), 'reference' => 'GHS-INV-2201', 'status' => 'paid',
        ]);
        VendorBill::create([
            'company_id' => $company->id, 'project_id' => $project3->id, 'category' => 'equipment',
            'description' => 'Roller & paver rental — weeks 1-2', 'amount' => 22000,
            'bill_date' => now()->subDays(15)->format('Y-m-d'), 'reference' => 'EQ-RENT-0091', 'status' => 'paid',
        ]);
        VendorBillSubmission::create([
            'company_id' => $company->id, 'project_id' => $project3->id, 'supplier_id' => $supplier3->id,
            'category' => 'material', 'description' => 'Ready-mix concrete delivery — road base works', 'amount' => 48500,
            'bill_date' => now()->subDay()->format('Y-m-d'), 'reference' => 'RRM-INV-3390', 'status' => 'pending',
        ]);

        // ---------------- A 3rd payment certificate (road project) ----------------
        $boq3Inputs = [
            ['item_number' => '1.1', 'section_title' => 'Earthworks & Paving', 'section_title_ar' => 'الأعمال الترابية والرصف', 'description' => 'Sub-base & base course placement', 'description_ar' => 'طبقات الأساس والقاعدة', 'uom' => 'm2', 'qty' => 16800, 'unit_price' => 55],
            ['item_number' => '1.2', 'section_title' => 'Earthworks & Paving', 'section_title_ar' => 'الأعمال الترابية والرصف', 'description' => 'Asphalt wearing course', 'description_ar' => 'طبقة الأسفلت السطحية', 'uom' => 'm2', 'qty' => 16800, 'unit_price' => 48],
        ];
        $boq3Items = [];
        foreach ($boq3Inputs as $i => $b) {
            $boq3Items[] = BoqItem::create(array_merge($b, ['company_id' => $company->id, 'project_id' => $project3->id, 'total' => $b['qty'] * $b['unit_price'], 'sort_order' => $i]));
        }
        $boq3Total = array_sum(array_map(fn ($b) => $b->total, $boq3Items));
        $cert3Fraction = 0.4;
        $cert3Gross = round($boq3Total * $cert3Fraction, 2);
        $cert3Retention = round($cert3Gross * 0.10, 2);
        $certificate3 = PaymentCertificate::create([
            'company_id' => $company->id, 'project_id' => $project3->id, 'certificate_number' => 1,
            'certificate_date' => now()->subDays(10)->format('Y-m-d'),
            'period_from' => now()->subDays(25)->format('Y-m-d'), 'period_to' => now()->subDays(10)->format('Y-m-d'),
            'status' => 'certified', 'retention_percent' => 10, 'retention_amount' => $cert3Retention,
            'gross_amount' => $cert3Gross, 'net_payable' => $cert3Gross - $cert3Retention, 'cumulative_certified' => $cert3Gross,
            'created_by' => $owner->id, 'certified_by' => $owner->id, 'certified_at' => now()->subDays(9),
        ]);
        foreach ($boq3Items as $boqItem) {
            PaymentCertificateLine::create([
                'payment_certificate_id' => $certificate3->id, 'boq_item_id' => $boqItem->id,
                'description' => $boqItem->description, 'description_ar' => $boqItem->description_ar, 'uom' => $boqItem->uom,
                'contract_qty' => $boqItem->qty, 'contract_unit_price' => $boqItem->unit_price, 'contract_total' => $boqItem->total,
                'previous_cumulative_qty' => 0, 'cumulative_qty' => round($boqItem->qty * $cert3Fraction, 2),
                'this_period_qty' => round($boqItem->qty * $cert3Fraction, 2), 'this_period_value' => round($boqItem->total * $cert3Fraction, 2),
            ]);
        }

        // ---------------- More RFIs & submittals ----------------
        Rfi::create([
            'company_id' => $company->id, 'project_id' => $project4->id, 'rfi_number' => 1,
            'subject' => 'Ceiling grid layout near structural beam',
            'question' => 'Structural beam conflicts with the proposed suspended ceiling grid — please confirm bulkhead treatment.',
            'raised_by' => $estimator->id, 'assigned_to' => $owner->id, 'status' => 'closed',
            'due_date' => now()->subDays(130)->format('Y-m-d'),
        ]);
        Rfi::create([
            'company_id' => $company->id, 'project_id' => $project5->id, 'rfi_number' => 1,
            'subject' => 'Parking apron drainage slope',
            'question' => 'Please confirm the required cross-fall for the parking apron given the adjacent municipal storm drain level.',
            'raised_by' => $owner->id, 'status' => 'open', 'due_date' => now()->addDays(20)->format('Y-m-d'),
        ]);
        Rfi::create([
            'company_id' => $company->id, 'project_id' => $project3->id, 'rfi_number' => 2,
            'subject' => 'Cross-street signal timing coordination',
            'question' => 'Requesting confirmation from the municipality traffic department on signal timing during the cross-street tie-in works.',
            'raised_by' => $owner->id, 'status' => 'answered', 'due_date' => now()->subDays(6)->format('Y-m-d'),
        ]);

        Submittal::create([
            'company_id' => $company->id, 'project_id' => $project4->id, 'submittal_number' => 1,
            'title' => 'Suspended ceiling tile & grid sample', 'spec_section' => '09 51 00', 'status' => 'approved',
            'submitted_by' => $estimator->id, 'reviewed_by' => $owner->id, 'reviewed_at' => now()->subDays(125),
            'due_date' => now()->subDays(130)->format('Y-m-d'),
        ]);
        Submittal::create([
            'company_id' => $company->id, 'project_id' => $project5->id, 'submittal_number' => 1,
            'title' => 'Structural rebar shop drawings', 'spec_section' => '03 21 00', 'status' => 'under_review',
            'submitted_by' => $owner->id, 'due_date' => now()->addDays(10)->format('Y-m-d'),
        ]);
        Submittal::create([
            'company_id' => $company->id, 'project_id' => $project3->id, 'submittal_number' => 2,
            'title' => 'Traffic signage specification sheet', 'spec_section' => '10 14 00', 'status' => 'approved',
            'submitted_by' => $owner->id, 'reviewed_by' => $owner->id, 'reviewed_at' => now()->subDays(5),
            'due_date' => now()->subDays(8)->format('Y-m-d'),
        ]);

        // ---------------- More equipment ----------------
        $equip4 = Equipment::create(['company_id' => $company->id, 'name' => 'Tower Crane — Potain MDT 178', 'name_ar' => 'رافعة برجية - بوتان MDT 178', 'asset_number' => 'EQ-1004', 'category' => 'Crane', 'ownership_type' => 'rented', 'rental_cost_per_day' => 2200, 'status' => 'in_use']);
        $equip5 = Equipment::create(['company_id' => $company->id, 'name' => 'Backhoe Loader — JCB 3CX', 'name_ar' => 'حفارة جرافة - جي سي بي 3CX', 'asset_number' => 'EQ-1005', 'category' => 'Backhoe Loader', 'ownership_type' => 'owned', 'purchase_date' => now()->subYears(3)->format('Y-m-d'), 'purchase_cost' => 310000, 'status' => 'available']);
        Equipment::create(['company_id' => $company->id, 'name' => 'Scaffolding Set (500 sqm)', 'name_ar' => 'طقم سقالات (500 م2)', 'asset_number' => 'EQ-1006', 'category' => 'Scaffolding', 'ownership_type' => 'rented', 'rental_cost_per_day' => 450, 'status' => 'in_use']);

        EquipmentAssignment::create(['company_id' => $company->id, 'equipment_id' => $equip4->id, 'project_id' => $project5->id, 'assigned_date' => now()->subDays(55)->format('Y-m-d'), 'notes' => 'Structural shell erection, floors 1-4.']);
        EquipmentAssignment::create(['company_id' => $company->id, 'equipment_id' => $equip5->id, 'project_id' => $project4->id, 'assigned_date' => now()->subDays(135)->format('Y-m-d'), 'returned_date' => now()->subDays(120)->format('Y-m-d'), 'notes' => 'Site clearance before fit-out mobilization.']);

        // ---------------- More safety (HSE) ----------------
        SafetyIncident::create(['company_id' => $company->id, 'project_id' => $project5->id, 'incident_number' => 1, 'incident_date' => now()->subDays(30)->format('Y-m-d'), 'incident_type' => 'Near Miss', 'severity' => 'medium', 'description' => 'Tower crane load swung near the site boundary wall in high wind; works paused until wind speed dropped.', 'location' => 'Site perimeter, east side', 'reported_by' => $owner->id, 'corrective_action' => 'Introduced a wind-speed cutoff procedure for crane lifts above 40 km/h.', 'status' => 'closed']);
        ToolboxTalk::create(['company_id' => $company->id, 'project_id' => $project5->id, 'talk_date' => now()->subDays(50)->format('Y-m-d'), 'topic' => 'Crane lift planning & exclusion zones', 'conducted_by' => $owner->id, 'attendee_count' => 10, 'notes' => 'Reviewed tower crane exclusion zone marking and banksman signaling.']);

        // ---------------- More timesheets ----------------
        foreach (range(1, 5) as $dayOffset) {
            TimesheetEntry::create(['company_id' => $company->id, 'project_id' => $project3->id, 'user_id' => $owner->id, 'work_date' => now()->subDays($dayOffset + 5)->format('Y-m-d'), 'hours' => 8, 'hourly_rate_snapshot' => $owner->hourly_rate, 'cost' => 8 * $owner->hourly_rate, 'logged_by' => $owner->id]);
            TimesheetEntry::create(['company_id' => $company->id, 'project_id' => $project3->id, 'user_id' => $accountant->id, 'work_date' => now()->subDays($dayOffset + 5)->format('Y-m-d'), 'hours' => 4, 'hourly_rate_snapshot' => $accountant->hourly_rate, 'cost' => 4 * $accountant->hourly_rate, 'logged_by' => $accountant->id]);
        }

        // ---------------- Another recurring invoice ----------------
        $recurringInvoice2 = RecurringInvoice::create([
            'company_id' => $company->id, 'client_id' => $client4->id,
            'title' => 'Quarterly Office HVAC Maintenance Contract', 'frequency' => 'quarterly',
            'next_run_date' => now()->addDays(40)->format('Y-m-d'), 'last_generated_at' => now()->subDays(50),
            'is_active' => true, 'apply_vat' => true, 'retention_percent' => 0, 'due_days' => 30,
        ]);
        RecurringInvoiceItem::create(['recurring_invoice_id' => $recurringInvoice2->id, 'description' => 'Quarterly HVAC preventive maintenance', 'description_ar' => 'صيانة وقائية ربع سنوية لأنظمة التكييف', 'qty' => 1, 'unit_price' => 6500]);

        // ---------------- Another credit/debit note pair ----------------
        $creditNote2 = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice4->id, 'client_id' => $client5->id,
            'note_number' => 'CN-1002', 'issue_date' => now()->subDays(5)->format('Y-m-d'),
            'reason' => 'Billing correction after re-measurement of completed shell works.',
            'status' => 'issued', 'subtotal' => 4200, 'vat_rate' => 15, 'vat_amount' => 630, 'total' => 4830,
        ]);
        CreditNoteItem::create(['credit_note_id' => $creditNote2->id, 'description' => 'Structural works — re-measurement correction', 'description_ar' => 'الأعمال الإنشائية - تصحيح بعد إعادة القياس', 'qty' => 1, 'unit_price' => 4200, 'total' => 4200]);

        $debitNote2 = DebitNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice3->id, 'client_id' => $client4->id,
            'note_number' => 'DN-1002', 'issue_date' => now()->subDays(3)->format('Y-m-d'),
            'reason' => 'Additional after-hours work requested by the client.',
            'status' => 'issued', 'subtotal' => 2500, 'vat_rate' => 15, 'vat_amount' => 375, 'total' => 2875,
        ]);
        DebitNoteItem::create(['debit_note_id' => $debitNote2->id, 'description' => 'After-hours labor surcharge', 'description_ar' => 'رسوم إضافية للعمل خارج أوقات الدوام', 'qty' => 1, 'unit_price' => 2500, 'total' => 2500]);

        // ---------------- A 3rd selection ----------------
        $selection3 = Selection::create([
            'company_id' => $company->id, 'project_id' => $project5->id, 'category' => 'Exterior',
            'name' => 'Facade Cladding', 'name_ar' => 'كسوة الواجهة',
            'description' => 'Allowance for the main facade cladding finish.', 'description_ar' => 'مخصص لتشطيب كسوة الواجهة الرئيسية.',
            'allowance_amount' => 18000, 'status' => 'pending', 'due_date' => now()->addDays(60)->format('Y-m-d'),
        ]);
        SelectionOption::create(['selection_id' => $selection3->id, 'name' => 'Painted Render Finish', 'name_ar' => 'تشطيب لياسة مدهونة', 'price' => 15000, 'sort_order' => 0, 'is_chosen' => false]);
        SelectionOption::create(['selection_id' => $selection3->id, 'name' => 'Aluminum Composite Panel Cladding', 'name_ar' => 'كسوة ألواح ألمنيوم مركبة', 'price' => 24000, 'sort_order' => 1, 'is_chosen' => false]);

        // ---------------- A 3rd assembly ----------------
        $assembly3 = Assembly::create(['company_id' => $company->id, 'name' => 'Suspended Gypsum Ceiling (Painted)', 'name_ar' => 'سقف جبسي معلق (مدهون)', 'base_uom' => 'sqm', 'description' => 'Suspended gypsum board ceiling with skim coat and paint finish.', 'is_active' => true]);
        AssemblyItem::create(['assembly_id' => $assembly3->id, 'description' => 'Gypsum board ceiling system', 'description_ar' => 'نظام سقف ألواح جبسية', 'item_type' => 'material', 'uom' => 'sqm', 'qty_per_unit' => 1, 'unit_cost' => 38.00, 'sort_order' => 0]);
        AssemblyItem::create(['assembly_id' => $assembly3->id, 'description' => 'Skim coat & paint, two coats', 'description_ar' => 'معجون وطلاء طبقتين', 'item_type' => 'material', 'uom' => 'sqm', 'qty_per_unit' => 1, 'unit_cost' => 9.00, 'sort_order' => 1]);
        AssemblyItem::create(['assembly_id' => $assembly3->id, 'description' => 'Ceiling installer labor', 'description_ar' => 'عامل تركيب الأسقف', 'item_type' => 'labor', 'uom' => 'sqm', 'qty_per_unit' => 1, 'unit_cost' => 22.00, 'sort_order' => 2]);

        // ---------------- More leads ----------------
        Lead::create([
            'company_id' => $company->id, 'name' => 'Lama Al Harbi', 'company_name' => null, 'company_name_ar' => null,
            'email' => 'lama.harbi@example.com', 'phone' => '+966 59 333 4444',
            'source' => 'website', 'status' => 'new', 'estimated_value' => 160000,
            'notes' => 'Interested in a majlis & garden landscaping extension.',
        ]);
        Lead::create([
            'company_id' => $company->id, 'name' => 'Saeed Al Shehri', 'company_name' => 'Al Shehri Real Estate',
            'company_name_ar' => 'الشهري العقارية', 'email' => 'saeed@alshehri-re.sa', 'phone' => '+966 56 777 8888',
            'source' => 'referral', 'status' => 'qualified', 'estimated_value' => 950000,
            'notes' => 'Potential multi-unit residential compound in Jeddah — awaiting land survey.',
        ]);

        // ---------------- Enable client & supplier portal logins ----------------
        // Client model casts `password` as 'hashed' — assigning a plain string here is
        // auto-hashed on save, same as Client/SupplierController::enablePortal() in the app.
        $client->update(['portal_enabled' => true, 'password' => 'Demo@12345']);
        $supplier1->update(['portal_enabled' => true, 'password' => 'Demo@12345']);
    }

    /**
     * A second, smaller demo company on a different plan tier (Enterprise, vs the first
     * company's Professional) — a southern-region earthworks/infrastructure specialist —
     * so the demo shows multi-tenancy and plan variety. Mirrors the first company's
     * create-or-backfill idempotency pattern exactly: guarded on its own email, and on
     * AsphaltEstimate existence for the "exists but incomplete" backfill case.
     */
    private function seedSecondDemoCompany(): void
    {
        $company2 = Company::where('email', 'demo2@buildxact-saudi.local')->first();
        if ($company2 && AsphaltEstimate::where('company_id', $company2->id)->exists()) {
            $this->command?->info('Second demo company already fully seeded.');

            return;
        }

        $enterprisePlan = Plan::where('slug', 'enterprise')->firstOrFail();

        if (! $company2) {
            $company2 = Company::create([
                'name' => 'Jazan Earthworks & Infrastructure Co.', 'name_ar' => 'جازان للأعمال الترابية والبنية التحتية',
                'email' => 'demo2@buildxact-saudi.local', 'phone' => '+966 17 321 6540', 'city' => 'Jizan',
                'status' => 'active', 'plan_id' => $enterprisePlan->id, 'trial_ends_at' => now()->addDays(14),
                'cr_number' => '5910087654', 'vat_number' => '300098765400003',
                'address' => 'Industrial Road, Jizan 82723', 'default_markup_percent' => 12, 'default_retention_percent' => 10,
            ]);

            $owner2 = User::create([
                'company_id' => $company2->id, 'name' => 'Hassan Al Maliki', 'email' => 'owner@jazan-earthworks-demo.local',
                'password' => 'Demo@12345', 'role' => 'owner', 'status' => 'active', 'hourly_rate' => 130,
            ]);

            Subscription::create(['company_id' => $company2->id, 'plan_id' => $enterprisePlan->id, 'billing_cycle' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addDays(300)]);
            Payment::create(['company_id' => $company2->id, 'amount' => 8990, 'currency' => 'SAR', 'method' => 'bank_transfer', 'reference' => 'PMT-2001', 'status' => 'paid']);
        } else {
            $owner2 = User::where('company_id', $company2->id)->where('role', 'owner')->firstOrFail();
        }

        $client2a = Client::create([
            'company_id' => $company2->id, 'name' => 'Jizan Municipality — Roads Department', 'name_ar' => 'أمانة منطقة جازان — إدارة الطرق',
            'email' => 'roads@jizan-municipality-demo.sa', 'phone' => '+966 17 222 3344', 'address' => 'Jizan City Center',
        ]);
        $client2b = Client::create([
            'company_id' => $company2->id, 'name' => 'Farasan Logistics Co.', 'name_ar' => 'فرسان للخدمات اللوجستية',
            'email' => 'contracts@farasan-logistics-demo.sa', 'phone' => '+966 17 555 1122', 'address' => 'Jizan Port Area',
        ]);

        $project2a = Project::create([
            'company_id' => $company2->id, 'client_id' => $client2a->id,
            'name' => 'Jizan Industrial Road Rehabilitation', 'name_ar' => 'إعادة تأهيل طريق جازان الصناعي',
            'city' => 'Jizan',
            'description' => 'Full-depth rehabilitation of a 2.5km industrial access road, including drainage upgrades.',
            'description_ar' => 'إعادة تأهيل كامل لطريق صناعي بطول 2.5 كم يشمل تحسين الصرف.',
            'status' => 'in_progress', 'budget' => 4200000,
            'start_date' => now()->subDays(40), 'end_date' => now()->addDays(150),
        ]);
        $project2b = Project::create([
            'company_id' => $company2->id, 'client_id' => $client2b->id,
            'name' => 'Farasan Logistics Yard Earthworks', 'name_ar' => 'الأعمال الترابية لساحة فرسان اللوجستية',
            'city' => 'Jizan',
            'description' => 'Grading, compaction and drainage for a new 8,000 sqm logistics storage yard.',
            'description_ar' => 'تسوية ودمك وصرف لساحة تخزين لوجستية جديدة بمساحة 8000 متر مربع.',
            'status' => 'planning', 'budget' => 980000,
            'start_date' => now()->addDays(15), 'end_date' => now()->addDays(120),
        ]);

        $supplier2a = Supplier::create([
            'company_id' => $company2->id, 'name' => 'Southern Region Aggregates Co.', 'name_ar' => 'شركة ركام المنطقة الجنوبية',
            'contact_name' => 'Nawaf Al Amri', 'email' => 'sales@sra-aggregates-demo.sa', 'phone' => '+966 17 445 6677',
            'address' => 'Jizan Industrial City', 'category' => 'Aggregates',
        ]);
        $supplier2b = Supplier::create([
            'company_id' => $company2->id, 'name' => 'Red Sea Asphalt Plant', 'name_ar' => 'مصنع أسفلت البحر الأحمر',
            'contact_name' => 'Ibrahim Al Zahrani', 'email' => 'plant@redseaasphalt-demo.sa', 'phone' => '+966 17 889 0011',
            'address' => 'Jizan Port Road', 'category' => 'Asphalt',
        ]);

        // A small representative slice of the shared material library (see
        // the first company's material-seeding block above for the full pattern).
        $libraryBySku2 = collect(require database_path('data/material_library_items.php'))->keyBy('sku');
        foreach (['AGG-CRUSH20' => $supplier2a->id, 'SAND-FINE' => $supplier2a->id, 'RMX-C25' => $supplier2b->id] as $sku => $supplierId) {
            $li = $libraryBySku2[$sku] ?? null;
            if (! $li) {
                continue;
            }
            $materialCost = (float) $li['material_cost'];
            $laborCost = (float) ($li['labor_cost'] ?? 0);
            Material::create([
                'company_id' => $company2->id, 'supplier_id' => $supplierId, 'sku' => $li['sku'],
                'name' => $li['name'], 'name_ar' => $li['name_ar'] ?? null, 'category' => $li['category'], 'unit' => $li['unit'],
                'unit_cost' => $materialCost + $laborCost, 'material_cost' => $materialCost, 'labor_cost' => $laborCost,
            ]);
        }

        $estimate2a = Estimate::create([
            'company_id' => $company2->id, 'project_id' => $project2b->id, 'client_id' => $client2b->id,
            'title' => 'Farasan Logistics Yard — Earthworks Estimate', 'title_ar' => 'ساحة فرسان اللوجستية — تسعيرة الأعمال الترابية',
            'status' => 'sent', 'total' => 980000, 'share_token' => bin2hex(random_bytes(20)),
        ]);
        EstimateItem::create(['estimate_id' => $estimate2a->id, 'description' => 'Site grading & compaction', 'description_ar' => 'تسوية ودمك الموقع', 'qty' => 1, 'unit_cost' => 620000, 'total' => 620000]);
        EstimateItem::create(['estimate_id' => $estimate2a->id, 'description' => 'Drainage & stormwater works', 'description_ar' => 'أعمال الصرف ومياه الأمطار', 'qty' => 1, 'unit_cost' => 360000, 'total' => 360000]);

        $invoice2a = Invoice::create([
            'company_id' => $company2->id, 'project_id' => $project2a->id, 'client_id' => $client2a->id,
            'invoice_number' => 'JZ-INV-2001', 'status' => 'paid', 'total' => 1200000, 'vat_rate' => 15, 'vat_amount' => 156521.74,
            'due_date' => now()->subDays(12), 'share_token' => bin2hex(random_bytes(20)),
        ]);
        InvoiceItem::create(['invoice_id' => $invoice2a->id, 'description' => 'Mobilization & early works payment', 'description_ar' => 'دفعة التعبئة والأعمال المبكرة', 'qty' => 1, 'unit_price' => 1043478.26, 'total' => 1043478.26]);

        // ---------------- Asphalt estimate ----------------
        $jzSegmentInputs = [[
            'name' => 'Industrial Access Road', 'sort_order' => 0, 'is_main_road' => true, 'plot_rows' => 0, 'plot_cols' => 0,
            'length_m' => 2500, 'width_m' => 12, 'median_width_m' => 0,
            'subbase_thickness_mm' => 200, 'subbase_unit_cost_per_m3' => 40,
            'base_course_thickness_mm' => 150, 'base_course_unit_cost_per_m3' => 55,
            'asphalt_binder_thickness_mm' => 60, 'asphalt_wearing_thickness_mm' => 40,
            'asphalt_density_kg_per_m3' => 2400, 'asphalt_unit_cost_per_ton' => 300,
        ]];
        $jzOtherCosts = [
            'transport_cost_per_ton' => 15,
            'paver_daily_rate' => 3200, 'paver_days' => 20,
            'roller_daily_rate' => 1700, 'roller_days' => 20,
            'labor_crew_size' => 12, 'labor_days' => 25,
            'labor_daily_rate_per_worker' => 110, 'labor_food_daily_rate_per_worker' => 25,
            'surveyor_fee' => 10000, 'lab_testing_fee' => 8500, 'mobilization_fee' => 20000,
        ];
        $jzComputedSegments = array_map(fn (array $s) => AsphaltEstimateCalc::computeSegment($s), $jzSegmentInputs);
        $jzTotals = AsphaltEstimateCalc::computeTotals($jzComputedSegments, $jzOtherCosts, 12, 15);
        $jzCachedTotals = array_intersect_key($jzTotals, array_flip([
            'segments_subtotal', 'other_costs_subtotal', 'subtotal', 'total_asphalt_tons', 'total_area_m2',
            'markup_amount', 'tax_amount', 'total',
        ]));
        $jzAsphaltEstimate = AsphaltEstimate::create(array_merge($jzOtherCosts, [
            'company_id' => $company2->id, 'project_id' => $project2a->id, 'client_id' => $client2a->id,
            'name' => 'Jizan Industrial Road Rehabilitation — Road Works', 'city' => 'Jizan',
            'status' => 'approved', 'site_width_m' => 12, 'site_length_m' => 2500,
            'notes' => 'Full-depth rehabilitation with drainage tie-ins at 4 culvert crossings.',
            'markup_percent' => 12, 'tax_percent' => 15,
        ], $jzCachedTotals));
        foreach ($jzSegmentInputs as $i => $input) {
            AsphaltEstimateSegment::create(array_merge($input, $jzComputedSegments[$i], ['asphalt_estimate_id' => $jzAsphaltEstimate->id]));
        }

        // ---------------- Enable portal logins ----------------
        $client2a->update(['portal_enabled' => true, 'password' => 'Demo@12345']);
        $supplier2a->update(['portal_enabled' => true, 'password' => 'Demo@12345']);

        $this->command?->info(implode("\n", [
            '',
            'Second demo company seeded: Jazan Earthworks & Infrastructure Co. (Enterprise plan).',
            '  Owner: owner@jazan-earthworks-demo.local / Demo@12345',
            '',
            'Client portal login (/portal/login):',
            '  Email:    roads@jizan-municipality-demo.sa',
            '  Password: Demo@12345',
            '',
            'Supplier/vendor portal login (/supplier-portal/login):',
            '  Email:    sales@sra-aggregates-demo.sa',
            '  Password: Demo@12345',
        ]));
    }
}
