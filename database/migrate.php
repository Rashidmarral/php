<?php

/**
 * Creates all tables (idempotent) and, on first run, seeds default
 * subscription plans, a super admin account, and a demo company with
 * sample data so the platform is immediately explorable.
 *
 * Usage: php database/migrate.php [--seed-demo]
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$pdo = Database::pdo();
$driver = Database::driver();

$id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
$ts = $driver === 'sqlite' ? "TEXT DEFAULT (datetime('now'))" : 'DATETIME DEFAULT CURRENT_TIMESTAMP';
$engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

$statements = [];

$statements[] = "CREATE TABLE IF NOT EXISTS plans (
    id {$id},
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    tagline VARCHAR(255),
    price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_yearly DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'SAR',
    max_users INT NOT NULL DEFAULT 5,
    max_projects INT NOT NULL DEFAULT 10,
    features TEXT,
    is_active INT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS companies (
    id {$id},
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30),
    city VARCHAR(100),
    cr_number VARCHAR(50),
    vat_number VARCHAR(50),
    status VARCHAR(20) NOT NULL DEFAULT 'trial',
    plan_id INT,
    trial_ends_at TEXT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS users (
    id {$id},
    company_id INT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'owner',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS subscriptions (
    id {$id},
    company_id INT NOT NULL,
    plan_id INT NOT NULL,
    billing_cycle VARCHAR(10) NOT NULL DEFAULT 'monthly',
    status VARCHAR(20) NOT NULL DEFAULT 'trialing',
    current_period_end TEXT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS payments (
    id {$id},
    company_id INT NOT NULL,
    subscription_id INT,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'SAR',
    method VARCHAR(30) NOT NULL DEFAULT 'manual',
    reference VARCHAR(100),
    status VARCHAR(20) NOT NULL DEFAULT 'paid',
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS clients (
    id {$id},
    company_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    address VARCHAR(255),
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS projects (
    id {$id},
    company_id INT NOT NULL,
    client_id INT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'planning',
    budget DECIMAL(12,2) NOT NULL DEFAULT 0,
    start_date TEXT,
    end_date TEXT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS estimates (
    id {$id},
    company_id INT NOT NULL,
    project_id INT,
    client_id INT,
    title VARCHAR(150) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS estimate_items (
    id {$id},
    estimate_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS project_photos (
    id {$id},
    company_id INT NOT NULL,
    project_id INT NOT NULL,
    uploaded_by INT,
    caption VARCHAR(255),
    file_path VARCHAR(255) NOT NULL,
    taken_on TEXT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS change_orders (
    id {$id},
    company_id INT NOT NULL,
    project_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at {$ts},
    approved_at TEXT
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS invoices (
    id {$id},
    company_id INT NOT NULL,
    project_id INT,
    client_id INT,
    invoice_number VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date TEXT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS invoice_items (
    id {$id},
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS schedule_tasks (
    id {$id},
    company_id INT NOT NULL,
    project_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    start_date TEXT,
    end_date TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    assigned_to INT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) PRIMARY KEY,
    value TEXT
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS quick_estimate_regions (
    id {$id},
    name_en VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    price_per_sqm DECIMAL(10,2) NOT NULL DEFAULT 0,
    multiplier DECIMAL(5,2) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    is_active INT NOT NULL DEFAULT 1
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS quick_estimate_foundations (
    id {$id},
    name_en VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description_en VARCHAR(255),
    description_ar VARCHAR(255),
    price_per_sqm DECIMAL(10,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_active INT NOT NULL DEFAULT 1
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS quick_estimate_addons (
    id {$id},
    name_en VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    description_en VARCHAR(255),
    description_ar VARCHAR(255),
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit_type VARCHAR(20) NOT NULL DEFAULT 'sqm',
    is_pro INT NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_active INT NOT NULL DEFAULT 1
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS quick_estimates (
    id {$id},
    project_name VARCHAR(150),
    region_id INT,
    foundation_id INT,
    total_area DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    addons_json TEXT,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    lang VARCHAR(2) NOT NULL DEFAULT 'en',
    contact_name VARCHAR(150),
    contact_email VARCHAR(150),
    contact_phone VARCHAR(30),
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS takeoffs (
    id {$id},
    company_id INT NOT NULL,
    project_id INT,
    name VARCHAR(150) NOT NULL,
    plan_image_path VARCHAR(255),
    scale_px_per_unit DECIMAL(12,6) NOT NULL DEFAULT 1,
    scale_unit VARCHAR(10) NOT NULL DEFAULT 'm',
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS takeoff_measurements (
    id {$id},
    takeoff_id INT NOT NULL,
    type VARCHAR(10) NOT NULL,
    label VARCHAR(150) NOT NULL,
    points_json TEXT,
    value DECIMAL(12,3) NOT NULL DEFAULT 0,
    unit VARCHAR(10) NOT NULL DEFAULT 'm',
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS suppliers (
    id {$id},
    company_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(150),
    email VARCHAR(150),
    phone VARCHAR(30),
    address VARCHAR(255),
    category VARCHAR(100),
    notes TEXT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS materials (
    id {$id},
    company_id INT NOT NULL,
    supplier_id INT,
    sku VARCHAR(60),
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100),
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255),
    created_at {$ts},
    updated_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS documents (
    id {$id},
    company_id INT NOT NULL,
    project_id INT,
    uploaded_by INT,
    name VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size INT NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS pages (
    id {$id},
    slug VARCHAR(150) NOT NULL UNIQUE,
    title_en VARCHAR(200) NOT NULL,
    title_ar VARCHAR(200) NOT NULL,
    content_en TEXT,
    content_ar TEXT,
    meta_description_en VARCHAR(255),
    meta_description_ar VARCHAR(255),
    nav_label_en VARCHAR(80),
    nav_label_ar VARCHAR(80),
    show_in_nav INT NOT NULL DEFAULT 0,
    show_in_footer INT NOT NULL DEFAULT 0,
    nav_order INT NOT NULL DEFAULT 0,
    is_published INT NOT NULL DEFAULT 1,
    created_at {$ts},
    updated_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS translations (
    id {$id},
    locale VARCHAR(5) NOT NULL,
    translation_key VARCHAR(190) NOT NULL,
    value TEXT,
    updated_at {$ts},
    UNIQUE(locale, translation_key)
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS leads (
    id {$id},
    company_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    company_name VARCHAR(150),
    email VARCHAR(150),
    phone VARCHAR(30),
    source VARCHAR(50) NOT NULL DEFAULT 'other',
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    estimated_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT,
    assigned_to INT,
    converted_client_id INT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS building_types (
    id {$id},
    company_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS contact_types (
    id {$id},
    company_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS client_types (
    id {$id},
    company_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS units_of_measure (
    id {$id},
    company_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS tax_rates (
    id {$id},
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    rate_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    is_default INT NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS estimate_templates (
    id {$id},
    name_en VARCHAR(150) NOT NULL,
    name_ar VARCHAR(150) NOT NULL,
    description_en VARCHAR(255),
    description_ar VARCHAR(255),
    building_type VARCHAR(100),
    icon VARCHAR(10) NOT NULL DEFAULT '🏗️',
    is_active INT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS estimate_template_items (
    id {$id},
    template_id INT NOT NULL,
    section_number VARCHAR(10) NOT NULL DEFAULT '1.0',
    section_title_en VARCHAR(150) NOT NULL,
    section_title_ar VARCHAR(150) NOT NULL,
    item_number VARCHAR(10) NOT NULL DEFAULT '1.1',
    description_en VARCHAR(255) NOT NULL,
    description_ar VARCHAR(255) NOT NULL,
    item_type VARCHAR(10) NOT NULL DEFAULT 'material',
    default_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
    uom VARCHAR(20) NOT NULL DEFAULT 'each',
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS invoice_payments (
    id {$id},
    invoice_id INT NOT NULL,
    company_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'SAR',
    method VARCHAR(30) NOT NULL DEFAULT 'moyasar',
    reference VARCHAR(100),
    status VARCHAR(20) NOT NULL DEFAULT 'paid',
    payer_name VARCHAR(150),
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS password_resets (
    id {$id},
    email VARCHAR(150) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    used_at TEXT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS compliance_documents (
    id {$id},
    company_id INT NOT NULL,
    doc_type VARCHAR(30) NOT NULL DEFAULT 'other',
    name VARCHAR(150) NOT NULL,
    document_number VARCHAR(100),
    expiry_date TEXT,
    file_path VARCHAR(255),
    notes VARCHAR(255),
    reminder_sent_at TEXT,
    created_at {$ts}
){$engine}";

foreach ($statements as $sql) {
    $pdo->exec($sql);
}

echo "Tables created/verified using driver: {$driver}\n";

// ---- Schema evolution: add columns to tables that may already exist from an earlier install ----
function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
{
    if ($driver === 'sqlite') {
        $rows = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
        foreach ($rows as $row) {
            if (strcasecmp($row['name'], $column) === 0) {
                return true;
            }
        }
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetch()['c'] > 0;
}

function addColumnIfMissing(PDO $pdo, string $driver, string $table, string $column, string $definition): void
{
    if (columnExists($pdo, $driver, $table, $column)) {
        return;
    }
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    echo "Added column {$table}.{$column}\n";
}

addColumnIfMissing($pdo, $driver, 'companies', 'logo_path', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'companies', 'address', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'companies', 'default_markup_percent', "DECIMAL(5,2) NOT NULL DEFAULT 0");
addColumnIfMissing($pdo, $driver, 'companies', 'default_retention_percent', "DECIMAL(5,2) NOT NULL DEFAULT 0");
addColumnIfMissing($pdo, $driver, 'companies', 'contractor_classification', 'VARCHAR(20)');
addColumnIfMissing($pdo, $driver, 'companies', 'contractor_classification_number', 'VARCHAR(50)');
addColumnIfMissing($pdo, $driver, 'companies', 'client_portal_enabled', "INT NOT NULL DEFAULT 1");
addColumnIfMissing($pdo, $driver, 'companies', 'price_sync_url', 'VARCHAR(500)');
addColumnIfMissing($pdo, $driver, 'companies', 'price_sync_last_at', 'TEXT');

addColumnIfMissing($pdo, $driver, 'companies', 'name_ar', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'companies', 'building_number', 'VARCHAR(10)');
addColumnIfMissing($pdo, $driver, 'companies', 'street_name', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'companies', 'district', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'companies', 'postal_code', 'VARCHAR(10)');
addColumnIfMissing($pdo, $driver, 'companies', 'additional_number', 'VARCHAR(10)');
addColumnIfMissing($pdo, $driver, 'companies', 'country_code', "VARCHAR(2) NOT NULL DEFAULT 'SA'");
addColumnIfMissing($pdo, $driver, 'companies', 'cr_document_path', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'companies', 'vat_document_path', 'VARCHAR(255)');

addColumnIfMissing($pdo, $driver, 'materials', 'material_cost', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
addColumnIfMissing($pdo, $driver, 'materials', 'labor_cost', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
// Backfill: existing rows had their full price in unit_cost with no labor split — treat it all as material cost.
$pdo->exec("UPDATE materials SET material_cost = unit_cost WHERE material_cost = 0 AND labor_cost = 0 AND unit_cost <> 0");

addColumnIfMissing($pdo, $driver, 'clients', 'password_hash', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'clients', 'portal_enabled', 'INT NOT NULL DEFAULT 0');

addColumnIfMissing($pdo, $driver, 'invoices', 'vat_rate', 'DECIMAL(5,2)');
addColumnIfMissing($pdo, $driver, 'invoices', 'vat_amount', "DECIMAL(12,2) NOT NULL DEFAULT 0");

addColumnIfMissing($pdo, $driver, 'plans', 'feature_flags', 'TEXT');

addColumnIfMissing($pdo, $driver, 'companies', 'zatca_environment', "VARCHAR(20) NOT NULL DEFAULT 'sandbox'");
addColumnIfMissing($pdo, $driver, 'companies', 'zatca_status', "VARCHAR(20) NOT NULL DEFAULT 'not_started'");
addColumnIfMissing($pdo, $driver, 'companies', 'zatca_csr', 'TEXT');
addColumnIfMissing($pdo, $driver, 'companies', 'zatca_private_key', 'TEXT');
addColumnIfMissing($pdo, $driver, 'companies', 'zatca_compliance_csid', 'TEXT');
addColumnIfMissing($pdo, $driver, 'companies', 'zatca_compliance_secret', 'TEXT');
addColumnIfMissing($pdo, $driver, 'companies', 'zatca_production_csid', 'TEXT');
addColumnIfMissing($pdo, $driver, 'companies', 'zatca_production_secret', 'TEXT');
addColumnIfMissing($pdo, $driver, 'companies', 'zatca_last_icv', 'INT NOT NULL DEFAULT 0');
addColumnIfMissing($pdo, $driver, 'companies', 'zatca_last_invoice_hash', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'companies', 'zatca_last_error', 'TEXT');

addColumnIfMissing($pdo, $driver, 'invoices', 'zatca_uuid', 'VARCHAR(64)');
addColumnIfMissing($pdo, $driver, 'invoices', 'zatca_icv', 'INT');
addColumnIfMissing($pdo, $driver, 'invoices', 'zatca_hash', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'invoices', 'zatca_previous_hash', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'invoices', 'zatca_status', "VARCHAR(20) NOT NULL DEFAULT 'not_submitted'");
addColumnIfMissing($pdo, $driver, 'invoices', 'zatca_submitted_at', 'TEXT');
addColumnIfMissing($pdo, $driver, 'invoices', 'zatca_response', 'TEXT');

addColumnIfMissing($pdo, $driver, 'quick_estimates', 'company_id', 'INT');
addColumnIfMissing($pdo, $driver, 'quick_estimates', 'client_id', 'INT');

addColumnIfMissing($pdo, $driver, 'estimates', 'share_token', 'VARCHAR(64)');
addColumnIfMissing($pdo, $driver, 'estimates', 'signed_at', 'TEXT');
addColumnIfMissing($pdo, $driver, 'estimates', 'signed_by_name', 'VARCHAR(150)');
addColumnIfMissing($pdo, $driver, 'estimates', 'signature_data', 'TEXT');
addColumnIfMissing($pdo, $driver, 'estimates', 'signed_ip', 'VARCHAR(45)');
addColumnIfMissing($pdo, $driver, 'invoices', 'share_token', 'VARCHAR(64)');
addColumnIfMissing($pdo, $driver, 'invoices', 'retention_percent', 'DECIMAL(5,2) NOT NULL DEFAULT 0');
addColumnIfMissing($pdo, $driver, 'invoices', 'retention_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
addColumnIfMissing($pdo, $driver, 'invoices', 'retention_released', 'INT NOT NULL DEFAULT 0');
addColumnIfMissing($pdo, $driver, 'invoices', 'retention_released_at', 'TEXT');

// Backfill share tokens for any rows created before this feature existed.
foreach (['estimates', 'invoices'] as $table) {
    $missing = $pdo->query("SELECT id FROM {$table} WHERE share_token IS NULL OR share_token = ''")->fetchAll();
    $upd = $pdo->prepare("UPDATE {$table} SET share_token = ? WHERE id = ?");
    foreach ($missing as $row) {
        $upd->execute([bin2hex(random_bytes(20)), $row['id']]);
    }
}

addColumnIfMissing($pdo, $driver, 'payments', 'proof_file_path', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'payments', 'reviewed_by', 'INT');
addColumnIfMissing($pdo, $driver, 'payments', 'reviewed_at', 'TEXT');
addColumnIfMissing($pdo, $driver, 'payments', 'plan_id', 'INT');
addColumnIfMissing($pdo, $driver, 'payments', 'billing_cycle', "VARCHAR(10)");

addColumnIfMissing($pdo, $driver, 'estimates', 'building_type', 'VARCHAR(100)');
addColumnIfMissing($pdo, $driver, 'estimates', 'job_address', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'estimates', 'template_id', 'INT');
addColumnIfMissing($pdo, $driver, 'estimates', 'source', "VARCHAR(20) NOT NULL DEFAULT 'blank'");

addColumnIfMissing($pdo, $driver, 'estimate_items', 'item_type', "VARCHAR(10) NOT NULL DEFAULT 'material'");
addColumnIfMissing($pdo, $driver, 'estimate_items', 'uom', "VARCHAR(20) NOT NULL DEFAULT 'each'");
addColumnIfMissing($pdo, $driver, 'estimate_items', 'section_title', 'VARCHAR(150)');

addColumnIfMissing($pdo, $driver, 'estimate_templates', 'is_default_choice', 'INT NOT NULL DEFAULT 0');

addColumnIfMissing($pdo, $driver, 'companies', 'moyasar_publishable_key', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'companies', 'moyasar_secret_key', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'companies', 'moyasar_enabled', 'INT NOT NULL DEFAULT 0');
addColumnIfMissing($pdo, $driver, 'companies', 'trial_reminder_sent_at', 'TEXT');

addColumnIfMissing($pdo, $driver, 'subscriptions', 'moyasar_card_token', 'VARCHAR(255)');
addColumnIfMissing($pdo, $driver, 'subscriptions', 'retry_count', 'INT NOT NULL DEFAULT 0');

// ---- Seed default plans (idempotent by slug) ----
$defaultPlans = [
    [
        'slug' => 'starter',
        'name' => 'Starter',
        'tagline' => 'For small contractors getting organized',
        'price_monthly' => 199,
        'price_yearly' => 1990,
        'max_users' => 3,
        'max_projects' => 10,
        'features' => json_encode([
            'Unlimited estimates & quotes',
            'Up to 10 active projects',
            '3 team members',
            'Client & invoice management',
            'Email support',
        ]),
        'sort_order' => 1,
    ],
    [
        'slug' => 'professional',
        'name' => 'Professional',
        'tagline' => 'For growing contracting businesses',
        'price_monthly' => 449,
        'price_yearly' => 4490,
        'max_users' => 15,
        'max_projects' => 50,
        'features' => json_encode([
            'Everything in Starter',
            'Up to 50 active projects',
            '15 team members',
            'Job costing & budget tracking',
            'Project scheduling',
            'Priority support',
        ]),
        'sort_order' => 2,
    ],
    [
        'slug' => 'enterprise',
        'name' => 'Enterprise',
        'tagline' => 'For large contractors & developers',
        'price_monthly' => 899,
        'price_yearly' => 8990,
        'max_users' => 999,
        'max_projects' => 999,
        'features' => json_encode([
            'Everything in Professional',
            'Unlimited projects & users',
            'Multi-branch support',
            'Dedicated account manager',
            'ZATCA-ready VAT invoicing',
            'Custom onboarding',
        ]),
        'sort_order' => 3,
    ],
];

$checkPlan = $pdo->prepare('SELECT id FROM plans WHERE slug = ?');
$insertPlan = $pdo->prepare('INSERT INTO plans (slug, name, tagline, price_monthly, price_yearly, currency, max_users, max_projects, features, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');

foreach ($defaultPlans as $p) {
    $checkPlan->execute([$p['slug']]);
    if ($checkPlan->fetch()) {
        continue;
    }
    $insertPlan->execute([
        $p['slug'], $p['name'], $p['tagline'], $p['price_monthly'], $p['price_yearly'],
        'SAR', $p['max_users'], $p['max_projects'], $p['features'], $p['sort_order'],
    ]);
}
echo "Default plans seeded.\n";

// ---- Seed default feature flags onto existing plans (idempotent: only fills blanks) ----
$defaultFlagsByPlan = [
    'starter' => [
        'takeoff' => false, 'suppliers' => true, 'materials' => true, 'documents' => true, 'reports' => false,
        'client_portal' => false, 'integrations' => false, 'zatca_phase2' => false, 'leads' => true,
        'compliance' => true, 'change_orders' => false, 'project_photos' => true, 'quick_estimate' => true,
        'ai_estimate_generator' => false, 'estimate_templates' => true, 'online_invoice_payments' => false,
    ],
    'professional' => [
        'takeoff' => true, 'suppliers' => true, 'materials' => true, 'documents' => true, 'reports' => true,
        'client_portal' => true, 'integrations' => true, 'zatca_phase2' => false, 'leads' => true,
        'compliance' => true, 'change_orders' => true, 'project_photos' => true, 'quick_estimate' => true,
        'ai_estimate_generator' => true, 'estimate_templates' => true, 'online_invoice_payments' => true,
    ],
    'enterprise' => [
        'takeoff' => true, 'suppliers' => true, 'materials' => true, 'documents' => true, 'reports' => true,
        'client_portal' => true, 'integrations' => true, 'zatca_phase2' => true, 'leads' => true,
        'compliance' => true, 'change_orders' => true, 'project_photos' => true, 'quick_estimate' => true,
        'ai_estimate_generator' => true, 'estimate_templates' => true, 'online_invoice_payments' => true,
    ],
];
$planRows = $pdo->query('SELECT id, slug, feature_flags FROM plans')->fetchAll();
$updateFlags = $pdo->prepare('UPDATE plans SET feature_flags = ? WHERE id = ?');
foreach ($planRows as $row) {
    $defaults = $defaultFlagsByPlan[$row['slug']] ?? $defaultFlagsByPlan['starter'];
    $existing = !empty($row['feature_flags']) ? (json_decode((string) $row['feature_flags'], true) ?: []) : [];
    // Merge in any flag keys the stored JSON is missing (e.g. new modules added after this plan
    // was first seeded) without touching flags an admin has already toggled by hand.
    $merged = $existing + $defaults;
    if ($merged !== $existing) {
        $updateFlags->execute([json_encode($merged), $row['id']]);
    }
}
echo "Plan feature flags seeded.\n";

// ZATCA Phase 2 (Fatoora reporting) moved from Enterprise-only to also included in Professional.
$pdo->exec("UPDATE plans SET feature_flags = REPLACE(feature_flags, '\"zatca_phase2\":false', '\"zatca_phase2\":true') WHERE slug = 'professional' AND feature_flags LIKE '%\"zatca_phase2\":false%'");

// ---- Call out ZATCA compliance explicitly in plan feature copy (idempotent: skips a plan that already mentions it) ----
$zatcaFeatureLine = [
    'starter' => 'ZATCA Phase 1 QR code invoicing',
    'professional' => 'ZATCA Phase 1 + Phase 2 (Fatoora) e-invoicing',
    'enterprise' => 'ZATCA Phase 1 + Phase 2 (Fatoora) e-invoicing, fully managed',
];
$selectPlanFeatures = $pdo->prepare('SELECT id, features FROM plans WHERE slug = ?');
$updatePlanFeatures = $pdo->prepare('UPDATE plans SET features = ? WHERE id = ?');
foreach ($zatcaFeatureLine as $slug => $line) {
    $selectPlanFeatures->execute([$slug]);
    $plan = $selectPlanFeatures->fetch();
    if (!$plan) {
        continue;
    }
    $features = json_decode((string) $plan['features'], true) ?: [];
    $alreadyMentioned = false;
    foreach ($features as $f) {
        if (stripos((string) $f, 'zatca') !== false) {
            $alreadyMentioned = true;
            break;
        }
    }
    if (!$alreadyMentioned) {
        $features[] = $line;
        $updatePlanFeatures->execute([json_encode($features), $plan['id']]);
    }
}
echo "Plan ZATCA feature copy updated.\n";

// ---- Call out newer platform capabilities in plan feature copy (idempotent: skips a plan that already mentions a given line) ----
$newFeatureLinesByPlan = [
    'starter' => [
        'WhatsApp sharing for estimates & invoices',
        'Client e-signature on estimates',
        'Change orders & retention tracking',
        'Site photo diary per project',
        'Saudi tax invoice PDF layout',
    ],
    'professional' => [
        'Everything in Starter',
    ],
    'enterprise' => [
        'Everything in Professional',
    ],
];
$selectPlanFeatures2 = $pdo->prepare('SELECT id, features FROM plans WHERE slug = ?');
$updatePlanFeatures2 = $pdo->prepare('UPDATE plans SET features = ? WHERE id = ?');
foreach ($newFeatureLinesByPlan as $slug => $lines) {
    $selectPlanFeatures2->execute([$slug]);
    $plan = $selectPlanFeatures2->fetch();
    if (!$plan) {
        continue;
    }
    $features = json_decode((string) $plan['features'], true) ?: [];
    $changed = false;
    foreach ($lines as $line) {
        $alreadyMentioned = false;
        foreach ($features as $f) {
            if (stripos((string) $f, $line) !== false) {
                $alreadyMentioned = true;
                break;
            }
        }
        if (!$alreadyMentioned) {
            $features[] = $line;
            $changed = true;
        }
    }
    if ($changed) {
        $updatePlanFeatures2->execute([json_encode($features), $plan['id']]);
    }
}
echo "Plan feature copy updated with newer platform capabilities.\n";

// ---- Call out the estimate template library, AI generator, leads, business setup & roles (idempotent) ----
$newFeatureLinesByPlan2 = [
    'starter' => [
        '12 built-in estimate templates',
        'AI Estimate Generator',
        'Role-based team permissions',
        'Leads pipeline',
        'Business Setup (building types, tax rates & more)',
    ],
    'professional' => [
        'Everything in Starter',
    ],
    'enterprise' => [
        'Everything in Professional',
    ],
];
$selectPlanFeatures3 = $pdo->prepare('SELECT id, features FROM plans WHERE slug = ?');
$updatePlanFeatures3 = $pdo->prepare('UPDATE plans SET features = ? WHERE id = ?');
foreach ($newFeatureLinesByPlan2 as $slug => $lines) {
    $selectPlanFeatures3->execute([$slug]);
    $plan = $selectPlanFeatures3->fetch();
    if (!$plan) {
        continue;
    }
    $features = json_decode((string) $plan['features'], true) ?: [];
    $changed = false;
    foreach ($lines as $line) {
        $alreadyMentioned = false;
        foreach ($features as $f) {
            if (stripos((string) $f, $line) !== false) {
                $alreadyMentioned = true;
                break;
            }
        }
        if (!$alreadyMentioned) {
            $features[] = $line;
            $changed = true;
        }
    }
    if ($changed) {
        $updatePlanFeatures3->execute([json_encode($features), $plan['id']]);
    }
}
echo "Plan feature copy updated with template library, AI generator, leads & business setup.\n";

// ---- Seed super admin account ----
$checkUser = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$checkUser->execute(['admin@buildxact-saudi.local']);
if (!$checkUser->fetch()) {
    $pdo->prepare('INSERT INTO users (company_id, name, email, password_hash, role, status) VALUES (NULL, ?, ?, ?, ?, ?)')
        ->execute(['Platform Admin', 'admin@buildxact-saudi.local', password_hash('Admin@12345', PASSWORD_DEFAULT), 'super_admin', 'active']);
    echo "Super admin seeded: admin@buildxact-saudi.local / Admin@12345\n";
} else {
    echo "Super admin already exists.\n";
}

// ---- Seed default platform settings (idempotent) ----
$defaultSettings = [
    'trial_days' => '14',
    'vat_rate' => '15',
    'currency' => 'SAR',
    'site_name' => 'BuildXact Saudi',
    'support_email' => 'support@buildxact-saudi.local',
    'support_phone' => '+966 11 234 5678',
    'bank_name' => '',
    'bank_account_name' => '',
    'bank_iban' => '',
    'bank_account_number' => '',
    'bank_transfer_enabled' => '1',
    'moyasar_publishable_key' => '',
    'moyasar_secret_key' => '',
    'moyasar_enabled' => '0',
    'platform_legal_name_en' => 'BuildXact Saudi',
    'platform_legal_name_ar' => '',
    'platform_vat_number' => '',
    'platform_cr_number' => '',
    'platform_building_number' => '',
    'platform_street_name' => '',
    'platform_district' => '',
    'platform_city' => 'Riyadh',
    'platform_postal_code' => '',
    'platform_additional_number' => '',
    'platform_logo_path' => '',
    'platform_cr_document_path' => '',
    'platform_vat_document_path' => '',
    'whatsapp_enabled' => '0',
    'whatsapp_phone_number_id' => '',
    'whatsapp_access_token' => '',
    'smtp_enabled' => '0',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_from_email' => '',
    'smtp_from_name' => 'BuildXact Saudi',
];
$checkSetting = $pdo->prepare('SELECT `key` FROM settings WHERE `key` = ?');
$insertSetting = $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?)');
foreach ($defaultSettings as $key => $value) {
    $checkSetting->execute([$key]);
    if (!$checkSetting->fetch()) {
        $insertSetting->execute([$key, $value]);
    }
}
echo "Default settings seeded.\n";

// ---- Seed Quick Estimate calculator data (idempotent) ----
if ((int) $pdo->query('SELECT COUNT(*) AS c FROM quick_estimate_regions')->fetch()['c'] === 0) {
    $regions = [
        ['Riyadh (Central Region)', 'الرياض (المنطقة الوسطى)', 100, 1.00, 1],
        ['Jeddah (Western Region)', 'جدة (المنطقة الغربية)', 112, 1.12, 2],
        ['Dammam (Eastern Region)', 'الدمام (المنطقة الشرقية)', 92, 0.92, 3],
        ['Makkah (Holy City)', 'مكة المكرمة (المدينة المقدسة)', 108, 1.08, 4],
        ['Madinah (Holy City)', 'المدينة المنورة (المدينة المقدسة)', 105, 1.05, 5],
    ];
    $ins = $pdo->prepare('INSERT INTO quick_estimate_regions (name_en, name_ar, price_per_sqm, multiplier, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    foreach ($regions as $r) {
        $ins->execute($r);
    }
    echo "Quick estimate regions seeded.\n";
}

if ((int) $pdo->query('SELECT COUNT(*) AS c FROM quick_estimate_foundations')->fetch()['c'] === 0) {
    $foundations = [
        ['Regular Foundation', 'أساسات عادية', 'Standard reinforced concrete', 'خرسانة مسلحة قياسية', 550, 1],
        ['Raft Foundation', 'أساسات حصيرة', 'Reinforced concrete raft', 'حصيرة خرسانية مسلحة', 700, 2],
    ];
    $ins = $pdo->prepare('INSERT INTO quick_estimate_foundations (name_en, name_ar, description_en, description_ar, price_per_sqm, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
    foreach ($foundations as $f) {
        $ins->execute($f);
    }
    echo "Quick estimate foundation types seeded.\n";
}

if ((int) $pdo->query('SELECT COUNT(*) AS c FROM quick_estimate_addons')->fetch()['c'] === 0) {
    $addons = [
        ['Water Tank', 'خزان مياه', 'Water storage tank with fittings', 'خزان مياه مع التوصيلات', 40, 'ton', 0, 1],
        ['Fencing', 'سياج', 'Perimeter fencing', 'سياج محيطي', 143, 'sqm', 0, 2],
        ['Guard Room', 'غرفة حارس', 'Security guard room with basic finishing', 'غرفة حارس مع تشطيب أساسي', 30, 'sqm', 0, 3],
        ['Sewage Tank', 'خزان صرف صحي', 'Sewage tank with connections', 'خزان صرف صحي مع التوصيلات', 35, 'sqm', 0, 4],
        ['Interior Paint', 'دهان داخلي وخارجي', 'Full interior and exterior painting', 'دهان داخلي وخارجي كامل', 90, 'sqm', 0, 5],
        ['Landscaping', 'لياسة', 'Interior and exterior finishing', 'تشطيب داخلي وخارجي', 73, 'sqm', 0, 6],
        ['Plumbing Works', 'أعمال صحية', 'Complete plumbing works', 'أعمال صحية كاملة', 132, 'sqm', 0, 7],
        ['Electrical Works', 'أعمال كهربائية', 'Complete electrical works', 'تمديدات كهربائية كاملة', 135, 'sqm', 0, 8],
        ['Aluminum Works', 'أعمال ألمنيوم', 'Windows, doors and railings', 'نوافذ وأبواب ودرابزين', 60, 'sqm', 0, 9],
        ['Gypsum Ceiling', 'تشطيب الأسقف', 'Suspended gypsum ceiling', 'أسقف جبسية معلقة', 85, 'sqm', 0, 10],
        ['Roof Insulation', 'عزل السطح', 'Waterproofing and thermal insulation', 'عزل مائي وحراري', 43, 'sqm', 0, 11],
        ['WPC Cladding', 'أبواب WPC', 'Weather-resistant WPC cladding', 'كسوة WPC مقاومة للعوامل الجوية', 50, 'sqm', 0, 12],
        ['Site Survey', 'مسح', 'Topographic site survey', 'مسح طبوغرافي للموقع', 90, 'sqm', 1, 13],
        ['Central AC System', 'تكييف مركزي', 'Central air conditioning ductwork', 'نظام تكييف مركزي بالدكت', 800, 'sqm', 1, 14],
        ['Swimming Pool', 'مسبح', 'Standard residential swimming pool', 'مسبح سكني قياسي', 380, 'unit', 1, 15],
    ];
    $ins = $pdo->prepare('INSERT INTO quick_estimate_addons (name_en, name_ar, description_en, description_ar, unit_price, unit_type, is_pro, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
    foreach ($addons as $a) {
        $ins->execute($a);
    }
    echo "Quick estimate add-ons seeded.\n";
}

if ((int) $pdo->query('SELECT COUNT(*) AS c FROM estimate_templates')->fetch()['c'] === 0) {
    $templates = require __DIR__ . '/seed_estimate_templates.php';
    $insTemplate = $pdo->prepare('INSERT INTO estimate_templates (name_en, name_ar, description_en, description_ar, building_type, icon, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)');
    $insItem = $pdo->prepare('INSERT INTO estimate_template_items (template_id, section_number, section_title_en, section_title_ar, item_number, description_en, description_ar, item_type, default_qty, uom, unit_cost, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    foreach ($templates as $tIndex => $tpl) {
        $insTemplate->execute([$tpl['name_en'], $tpl['name_ar'], $tpl['description_en'], $tpl['description_ar'], $tpl['building_type'], $tpl['icon'], $tIndex]);
        $templateId = (int) $pdo->lastInsertId();

        $itemSort = 0;
        foreach ($tpl['sections'] as $section) {
            foreach ($section['items'] as $item) {
                $insItem->execute([
                    $templateId, $section['no'], $section['title_en'], $section['title_ar'], $item['no'],
                    $item['en'], $item['ar'], $item['type'], $item['qty'], $item['uom'], $item['cost'], $itemSort++,
                ]);
            }
        }
    }
    echo count($templates) . " estimate templates seeded.\n";
}

// ---- Ensure exactly one estimate template is marked as the account-wide default ----
if ((int) $pdo->query('SELECT COUNT(*) AS c FROM estimate_templates WHERE is_default_choice = 1')->fetch()['c'] === 0) {
    $first = $pdo->query('SELECT id FROM estimate_templates WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1')->fetch();
    if ($first) {
        $pdo->prepare('UPDATE estimate_templates SET is_default_choice = 1 WHERE id = ?')->execute([$first['id']]);
    }
}

// ---- Optional demo company with sample data ----
if (in_array('--seed-demo', $argv, true)) {
    $checkCompany = $pdo->prepare('SELECT id FROM companies WHERE email = ?');
    $checkCompany->execute(['demo@buildxact-saudi.local']);
    $existing = $checkCompany->fetch();

    if (!$existing) {
        $proPlan = $pdo->query("SELECT id FROM plans WHERE slug = 'professional'")->fetch();

        $pdo->prepare('INSERT INTO companies (name, email, phone, city, status, plan_id, trial_ends_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute(['Al Rashid Construction Co.', 'demo@buildxact-saudi.local', '+966 50 123 4567', 'Riyadh', 'active', $proPlan['id'], date('Y-m-d', strtotime('+14 days'))]);
        $companyId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO users (company_id, name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$companyId, 'Ahmed Al Rashid', 'owner@buildxact-saudi.local', password_hash('Demo@12345', PASSWORD_DEFAULT), 'owner', 'active']);

        $pdo->prepare('INSERT INTO subscriptions (company_id, plan_id, billing_cycle, status, current_period_end) VALUES (?, ?, ?, ?, ?)')
            ->execute([$companyId, $proPlan['id'], 'monthly', 'active', date('Y-m-d', strtotime('+30 days'))]);

        $pdo->prepare('INSERT INTO payments (company_id, amount, currency, method, reference, status) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$companyId, 449, 'SAR', 'mada', 'PMT-1001', 'paid']);

        $pdo->prepare('INSERT INTO clients (company_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)')
            ->execute([$companyId, 'Jeddah Heights Development', 'contact@jeddahheights.sa', '+966 55 987 6543', 'Jeddah, Saudi Arabia']);
        $clientId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO projects (company_id, client_id, name, description, status, budget, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$companyId, $clientId, 'Villa Renovation - Al Nakheel', 'Full renovation of a 600 sqm villa including MEP works.', 'in_progress', 350000, date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('+80 days'))]);
        $projectId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO estimates (company_id, project_id, client_id, title, status, total) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$companyId, $projectId, $clientId, 'Villa Renovation Estimate', 'accepted', 350000]);
        $estimateId = (int) $pdo->lastInsertId();

        $items = [
            ['Demolition & site prep', 1, 25000],
            ['Structural & MEP works', 1, 150000],
            ['Finishing materials', 1, 100000],
            ['Labor & supervision', 1, 75000],
        ];
        foreach ($items as [$desc, $qty, $cost]) {
            $pdo->prepare('INSERT INTO estimate_items (estimate_id, description, qty, unit_cost, total) VALUES (?, ?, ?, ?, ?)')
                ->execute([$estimateId, $desc, $qty, $cost, $qty * $cost]);
        }

        $pdo->prepare('INSERT INTO invoices (company_id, project_id, client_id, invoice_number, status, total, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$companyId, $projectId, $clientId, 'INV-1001', 'paid', 100000, date('Y-m-d', strtotime('-5 days'))]);
        $invoiceId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, qty, unit_price, total) VALUES (?, ?, ?, ?, ?)')
            ->execute([$invoiceId, 'Mobilization payment (30%)', 1, 100000, 100000]);

        $tasks = [
            ['Site demolition', -8, -2, 'done'],
            ['Electrical rough-in', -1, 14, 'in_progress'],
            ['Plumbing rough-in', 2, 16, 'pending'],
            ['Interior finishing', 20, 60, 'pending'],
        ];
        foreach ($tasks as [$title, $startOffset, $endOffset, $status]) {
            $pdo->prepare('INSERT INTO schedule_tasks (company_id, project_id, title, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$companyId, $projectId, $title, date('Y-m-d', strtotime("{$startOffset} days")), date('Y-m-d', strtotime("{$endOffset} days")), $status]);
        }

        echo "Demo company seeded: owner@buildxact-saudi.local / Demo@12345\n";
    } else {
        echo "Demo company already exists.\n";
    }
}

echo "Migration complete.\n";
