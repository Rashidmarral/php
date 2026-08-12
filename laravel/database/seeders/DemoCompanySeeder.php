<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ScheduleTask;
use App\Models\Subscription;
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
            'email' => 'demo@buildxact-saudi.local',
            'phone' => '+966 50 123 4567',
            'city' => 'Riyadh',
            'status' => 'active',
            'plan_id' => $proPlan->id,
            'trial_ends_at' => now()->addDays(14),
        ]);

        User::create([
            'company_id' => $company->id,
            'name' => 'Ahmed Al Rashid',
            'email' => 'owner@buildxact-saudi.local',
            'password' => 'Demo@12345',
            'role' => 'owner',
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

        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Jeddah Heights Development',
            'name_ar' => 'تطوير مرتفعات جدة',
            'email' => 'contact@jeddahheights.sa',
            'phone' => '+966 55 987 6543',
            'address' => 'Jeddah, Saudi Arabia',
        ]);

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

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-1001',
            'status' => 'paid',
            'total' => 100000,
            'due_date' => now()->subDays(5),
            'share_token' => bin2hex(random_bytes(20)),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Mobilization payment (30%)',
            'description_ar' => 'دفعة التعبئة (30%)', 'qty' => 1, 'unit_price' => 100000, 'total' => 100000,
        ]);

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

        $this->command?->info('Demo company seeded: owner@buildxact-saudi.local / Demo@12345');
    }
}
