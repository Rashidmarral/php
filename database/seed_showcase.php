<?php

/**
 * Adds a rich, realistic dataset on top of the base demo company — more suppliers, a full
 * material/labor pricing library, more clients and projects at different stages, estimates,
 * invoices (some paid/unpaid/overdue, with retention on a couple), change orders, and a few
 * public Quick Estimate leads — so the platform looks like an active company's real account
 * when showing it to someone.
 *
 * Run this AFTER `php database/migrate.php --seed-demo` (it needs the demo company to already
 * exist). Safe to re-run: every insert checks for an existing row by name first.
 *
 * Usage: php database/seed_showcase.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$pdo = Database::pdo();

$company = $pdo->query("SELECT * FROM companies WHERE email = 'demo@buildxact-saudi.local'")->fetch(PDO::FETCH_ASSOC);
if (!$company) {
    echo "Demo company not found — run `php database/migrate.php --seed-demo` first.\n";
    exit(1);
}
$companyId = (int) $company['id'];

function findOrInsert(PDO $pdo, string $table, string $companyCol, int $companyId, string $nameCol, string $name, array $insertData): int
{
    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE {$companyCol} = ? AND {$nameCol} = ?");
    $stmt->execute([$companyId, $name]);
    if ($row = $stmt->fetch()) {
        return (int) $row['id'];
    }
    $columns = array_keys($insertData);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $pdo->prepare("INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES ({$placeholders})")->execute(array_values($insertData));
    return (int) $pdo->lastInsertId();
}

// ---- Company profile polish (only fills in currently-blank fields) ----
if (empty($company['name_ar'])) {
    $pdo->prepare("UPDATE companies SET
        name_ar = ?, cr_number = ?, vat_number = ?, building_number = ?, street_name = ?,
        district = ?, postal_code = ?, additional_number = ?, contractor_classification = ?,
        contractor_classification_number = ?, default_markup_percent = ?, default_retention_percent = ?
        WHERE id = ?")
        ->execute(['شركة الراشد للمقاولات', '1010456789', '300456789100003', '7512', 'King Fahd Road',
            'Al Olaya', '12211', '9421', '2', 'MC-224477', 15, 5, $companyId]);
    echo "Company profile filled in.\n";
}

// ---- Suppliers ----
$suppliers = [
    ['Zamil AC', 'Faisal Al Zamil', 'sales@zamilac.com', '+966 11 265 0000', 'Dammam, Saudi Arabia', 'HVAC'],
    ['Hadeed Saudi Iron & Steel', 'Omar Al Hadeed', 'sales@hadeed.com.sa', '+966 13 341 2000', 'Jubail, Saudi Arabia', 'Steel'],
    ['Saudi Readymix', 'Khalid Al Otaibi', 'orders@saudireadymix.com', '+966 11 456 7890', 'Riyadh, Saudi Arabia', 'Concrete'],
    ['Jotun Saudi', 'Layla Al Harbi', 'info@jotun.com.sa', '+966 12 636 1000', 'Jeddah, Saudi Arabia', 'Paint'],
    ['Sika Saudi Arabia', 'Yousef Al Qahtani', 'sales@sa.sika.com', '+966 11 217 9700', 'Riyadh, Saudi Arabia', 'Waterproofing'],
    ['USG Boral', 'Nasser Al Dosari', 'sales@usgboral.com', '+966 13 340 8000', 'Dammam, Saudi Arabia', 'Drywall'],
    ['Rak Ceramics KSA', 'Fahad Al Mutairi', 'sales@rakceramics.com', '+966 11 265 5000', 'Riyadh, Saudi Arabia', 'Flooring'],
    ['Alupco', 'Bandar Al Shammari', 'info@alupco.com', '+966 13 812 0000', 'Dammam, Saudi Arabia', 'Windows'],
    ['ITCO Industries', 'Saad Al Ghamdi', 'sales@itco.com.sa', '+966 11 265 8000', 'Riyadh, Saudi Arabia', 'Framing'],
    ['Riyadh Stone', 'Mohammed Al Anazi', 'sales@riyadhstone.com', '+966 11 494 0000', 'Riyadh, Saudi Arabia', 'Flooring'],
    ['Saudi Cables Company', 'Turki Al Rasheed', 'sales@saudicables.com', '+966 11 265 9000', 'Riyadh, Saudi Arabia', 'Electrical'],
    ['Saudi Block', 'Abdulaziz Al Fayez', 'orders@saudiblock.com', '+966 11 265 1000', 'Riyadh, Saudi Arabia', 'Masonry'],
];
$supplierIds = [];
foreach ($suppliers as [$name, $contact, $email, $phone, $address, $category]) {
    $supplierIds[$name] = findOrInsert($pdo, 'suppliers', 'company_id', $companyId, 'name', $name, [
        'company_id' => $companyId, 'name' => $name, 'contact_name' => $contact, 'email' => $email,
        'phone' => $phone, 'address' => $address, 'category' => $category,
    ]);
}
echo count($suppliers) . " suppliers ready.\n";

// ---- Materials (material + labor split) ----
$materials = [
    ['Excavation & earthwork', 'Excavation', 'm3', 0, 80, null, 'EXC-101'],
    ['Ready-mix concrete (OPC)', 'Concrete', 'm3', 450, 300, 'Saudi Readymix', 'CON-102'],
    ['Steel reinforcement bars', 'Steel', 'ton', 4200, 800, 'Hadeed Saudi Iron & Steel', 'STL-103'],
    ['Concrete blockwork 200mm', 'Masonry', 'm2', 85, 65, 'Saudi Block', 'MAS-104'],
    ['Timber framing & structure', 'Framing', 'm2', 150, 110, 'ITCO Industries', 'FRM-105'],
    ['Waterproof roofing membrane', 'Roofing', 'm2', 220, 120, 'Sika Saudi Arabia', 'ROF-106'],
    ['Thermal insulation panels', 'Insulation', 'm2', 60, 40, null, 'INS-107'],
    ['Gypsum drywall partitions', 'Drywall', 'm2', 95, 70, 'USG Boral', 'DRY-108'],
    ['Porcelain floor tiles', 'Flooring', 'm2', 180, 80, 'Rak Ceramics KSA', 'FLR-109'],
    ['Marble staircase treads', 'Flooring', 'm2', 620, 180, 'Riyadh Stone', 'FLR-110'],
    ['Interior wall painting (2 coats)', 'Paint', 'm2', 25, 35, 'Jotun Saudi', 'PNT-111'],
    ['Internal solid wood door', 'Doors', 'each', 1200, 400, null, 'DOR-112'],
    ['Aluminium glazed window', 'Windows', 'each', 2500, 600, 'Alupco', 'WIN-113'],
    ['Electrical socket/outlet point', 'Electrical', 'point', 350, 150, 'Saudi Cables Company', 'ELE-114'],
    ['Plumbing fixture connection', 'Plumbing', 'point', 450, 200, null, 'PLM-115'],
    ['Split AC unit supply & install', 'HVAC', 'lot', 25000, 12000, 'Zamil AC', 'HVA-116'],
    ['GRP waterproof tank lining', 'Other', 'lot', 8500, 3200, 'Sika Saudi Arabia', 'OTH-117'],
];
foreach ($materials as [$name, $category, $unit, $mat, $lab, $supplierName, $sku]) {
    $supplierId = $supplierName ? ($supplierIds[$supplierName] ?? null) : null;
    findOrInsert($pdo, 'materials', 'company_id', $companyId, 'name', $name, [
        'company_id' => $companyId, 'supplier_id' => $supplierId, 'sku' => $sku, 'name' => $name,
        'category' => $category, 'unit' => $unit, 'material_cost' => $mat, 'labor_cost' => $lab,
        'unit_cost' => $mat + $lab,
    ]);
}
echo count($materials) . " materials ready.\n";

// ---- Clients ----
$clients = [
    ['Al Faisaliah Development Co.', 'projects@alfaisaliah.sa', '+966 11 234 5678', 'Riyadh, Saudi Arabia'],
    ['Al Andalus Real Estate', 'info@alandalus-re.com', '+966 12 345 6789', 'Jeddah, Saudi Arabia'],
    ['Eastern Province Municipality — Contracts Office', 'tenders@dammam.gov.sa', '+966 13 456 7890', 'Dammam, Saudi Arabia'],
    ['Al Waha Retail Group', 'facilities@alwaha.sa', '+966 11 567 8901', 'Riyadh, Saudi Arabia'],
    ['Taybah Residential Towers', 'pm@taybahresidential.sa', '+966 12 678 9012', 'Jeddah, Saudi Arabia'],
    ['Al Marjan Site Office Contracts', 'vendors@almarjan-sites.sa', '+966 14 789 0123', 'Tabuk, Saudi Arabia'],
];
$clientIds = [];
foreach ($clients as [$name, $email, $phone, $address]) {
    $clientIds[$name] = findOrInsert($pdo, 'clients', 'company_id', $companyId, 'name', $name, [
        'company_id' => $companyId, 'name' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address,
    ]);
}
$origClient = $pdo->prepare("SELECT id, name FROM clients WHERE company_id = ? AND name = 'Jeddah Heights Development'");
$origClient->execute([$companyId]);
if ($row = $origClient->fetch()) {
    $clientIds[$row['name']] = (int) $row['id'];
}
echo count($clients) . " clients ready.\n";

// ---- Projects ----
$projects = [
    ['Al Faisaliah Tower B Fit-out', 'Al Faisaliah Development Co.', 'Interior fit-out of a 12-floor commercial tower, Riyadh.', 'in_progress', 2400000, -40, 140],
    ['Al Andalus Villas Phase 2', 'Al Andalus Real Estate', '18-unit luxury villa compound, Jeddah North.', 'planning', 5200000, 10, 380],
    ['Dammam Corniche Public Facilities', 'Eastern Province Municipality — Contracts Office', 'Municipal contract: public facilities upgrade.', 'in_progress', 680000, -15, 45],
    ['Al Waha Mall Renovation', 'Al Waha Retail Group', 'Retail unit renovation and food court expansion.', 'completed', 1150000, -180, -20],
    ['Taybah Residential Tower — MEP', 'Taybah Residential Towers', 'MEP subcontract for a 22-floor residential tower.', 'in_progress', 3800000, -60, 200],
    ['Al Marjan Site Office Complex', 'Al Marjan Site Office Contracts', 'Temporary site office and worker facilities.', 'planning', 920000, 20, 150],
];
$projectIds = [];
foreach ($projects as [$name, $clientName, $desc, $status, $budget, $startOffset, $endOffset]) {
    $projectIds[$name] = findOrInsert($pdo, 'projects', 'company_id', $companyId, 'name', $name, [
        'company_id' => $companyId, 'client_id' => $clientIds[$clientName], 'name' => $name,
        'description' => $desc, 'status' => $status, 'budget' => $budget,
        'start_date' => date('Y-m-d', strtotime("{$startOffset} days")),
        'end_date' => date('Y-m-d', strtotime("{$endOffset} days")),
    ]);
}
echo count($projects) . " projects ready.\n";

// ---- Estimates (with line items) ----
$estimateDefs = [
    ['Al Faisaliah Tower B — Fit-out Estimate', 'Al Faisaliah Tower B Fit-out', 'Al Faisaliah Development Co.', 'accepted', [
        ['Demolition & strip-out (12 floors)', 1, 180000],
        ['Partitions, ceilings & flooring', 1, 950000],
        ['MEP first & second fix', 1, 820000],
        ['Fit-out finishes & joinery', 1, 450000],
    ]],
    ['Al Andalus Villas Phase 2 — Preliminary Estimate', 'Al Andalus Villas Phase 2', 'Al Andalus Real Estate', 'sent', [
        ['Site works & foundations (18 units)', 1, 1400000],
        ['Structure & shell', 1, 2100000],
        ['MEP rough-in', 1, 900000],
        ['External works & landscaping', 1, 800000],
    ]],
    ['Dammam Corniche — Municipal Facilities Estimate', 'Dammam Corniche Public Facilities', 'Eastern Province Municipality — Contracts Office', 'accepted', [
        ['Demolition of existing structures', 1, 60000],
        ['New construction — restroom blocks', 1, 420000],
        ['Utilities connection & fit-out', 1, 200000],
    ]],
    ['Taybah Tower — MEP Subcontract Estimate', 'Taybah Residential Tower — MEP', 'Taybah Residential Towers', 'accepted', [
        ['Electrical distribution & wiring', 1, 1450000],
        ['Plumbing & drainage systems', 1, 1200000],
        ['HVAC supply & installation', 1, 1150000],
    ]],
];
$estimateIds = [];
foreach ($estimateDefs as [$title, $projectName, $clientName, $status, $items]) {
    $exists = $pdo->prepare('SELECT id FROM estimates WHERE company_id = ? AND title = ?');
    $exists->execute([$companyId, $title]);
    if ($row = $exists->fetch()) {
        $estimateIds[$title] = (int) $row['id'];
        continue;
    }
    $total = array_sum(array_map(fn($i) => $i[1] * $i[2], $items));
    $pdo->prepare('INSERT INTO estimates (company_id, project_id, client_id, title, status, total, share_token) VALUES (?,?,?,?,?,?,?)')
        ->execute([$companyId, $projectIds[$projectName], $clientIds[$clientName], $title, $status, $total, bin2hex(random_bytes(20))]);
    $estimateId = (int) $pdo->lastInsertId();
    $estimateIds[$title] = $estimateId;
    foreach ($items as [$desc, $qty, $cost]) {
        $pdo->prepare('INSERT INTO estimate_items (estimate_id, description, qty, unit_cost, total) VALUES (?,?,?,?,?)')
            ->execute([$estimateId, $desc, $qty, $cost, $qty * $cost]);
    }
}
echo count($estimateDefs) . " estimates ready.\n";

// ---- Invoices (mixed statuses, a couple with retention) ----
$invoiceDefs = [
    ['INV-2001', 'Al Faisaliah Tower B Fit-out', 'Al Faisaliah Development Co.', 'paid', -60, 10, [
        ['Mobilization payment (20%)', 1, 480000],
    ]],
    ['INV-2002', 'Al Faisaliah Tower B Fit-out', 'Al Faisaliah Development Co.', 'paid', -20, 5, [
        ['Progress payment — partitions & ceilings complete', 1, 620000],
    ]],
    ['INV-2003', 'Dammam Corniche Public Facilities', 'Eastern Province Municipality — Contracts Office', 'unpaid', -3, 5, [
        ['Progress payment — restroom blocks 60% complete', 1, 250000],
    ]],
    ['INV-2004', 'Taybah Residential Tower — MEP', 'Taybah Residential Towers', 'overdue', -35, 10, [
        ['Progress payment — electrical distribution complete', 1, 700000],
    ]],
    ['INV-2005', 'Al Waha Mall Renovation', 'Al Waha Retail Group', 'paid', -190, 10, [
        ['Final payment — retention released', 1, 172500],
    ]],
];
foreach ($invoiceDefs as [$number, $projectName, $clientName, $status, $dateOffset, $retentionPct, $items]) {
    $exists = $pdo->prepare('SELECT id FROM invoices WHERE company_id = ? AND invoice_number = ?');
    $exists->execute([$companyId, $number]);
    if ($exists->fetch()) {
        continue;
    }
    $subtotal = array_sum(array_map(fn($i) => $i[1] * $i[2], $items));
    $vatAmount = round($subtotal * 0.15, 2);
    $total = $subtotal + $vatAmount;
    $retentionAmount = round($subtotal * $retentionPct / 100, 2);
    $createdAt = date('Y-m-d H:i:s', strtotime("{$dateOffset} days"));
    $pdo->prepare('INSERT INTO invoices (company_id, project_id, client_id, invoice_number, status, total, vat_rate, vat_amount, due_date, retention_percent, retention_amount, share_token, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$companyId, $projectIds[$projectName], $clientIds[$clientName], $number, $status, $total, 15, $vatAmount,
            date('Y-m-d', strtotime("{$dateOffset} days +30 days")), $retentionPct, $retentionAmount, bin2hex(random_bytes(20)), $createdAt]);
    $invoiceId = (int) $pdo->lastInsertId();
    foreach ($items as [$desc, $qty, $price]) {
        $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, qty, unit_price, total) VALUES (?,?,?,?,?)')
            ->execute([$invoiceId, $desc, $qty, $price, $qty * $price]);
    }
}
echo count($invoiceDefs) . " invoices ready.\n";

// ---- Change orders ----
$changeOrders = [
    ['Al Faisaliah Tower B Fit-out', 'Upgraded lobby finishes', 'Client requested premium stone cladding in the ground floor lobby.', 85000, 'approved'],
    ['Al Faisaliah Tower B Fit-out', 'Additional server room cooling', 'Extra precision AC unit for the new server room.', 32000, 'pending'],
    ['Taybah Residential Tower — MEP', 'Scope reduction — 4th floor deferred', 'Client deferred 4th floor MEP works to a later phase.', -180000, 'approved'],
];
foreach ($changeOrders as [$projectName, $title, $desc, $amount, $status]) {
    $exists = $pdo->prepare('SELECT id FROM change_orders WHERE company_id = ? AND project_id = ? AND title = ?');
    $exists->execute([$companyId, $projectIds[$projectName], $title]);
    if ($exists->fetch()) {
        continue;
    }
    $pdo->prepare('INSERT INTO change_orders (company_id, project_id, title, description, amount, status, approved_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$companyId, $projectIds[$projectName], $title, $desc, $amount, $status, $status === 'approved' ? date('Y-m-d H:i:s') : null]);
}
echo count($changeOrders) . " change orders ready.\n";

// ---- Schedule tasks for the new in-progress projects ----
$scheduleDefs = [
    ['Al Faisaliah Tower B Fit-out', [
        ['Demolition & strip-out', -38, -20, 'done'],
        ['Partition walls & ceilings', -18, 10, 'in_progress'],
        ['MEP first fix', -5, 25, 'in_progress'],
        ['Flooring & finishes', 30, 90, 'pending'],
        ['Final handover walkthrough', 130, 140, 'pending'],
    ]],
    ['Dammam Corniche Public Facilities', [
        ['Site clearance', -14, -8, 'done'],
        ['Restroom block construction', -6, 30, 'in_progress'],
        ['Utilities connection', 32, 42, 'pending'],
    ]],
    ['Taybah Residential Tower — MEP', [
        ['Electrical distribution', -58, -10, 'done'],
        ['Plumbing rough-in', -8, 60, 'in_progress'],
        ['HVAC installation', 65, 150, 'pending'],
    ]],
];
foreach ($scheduleDefs as [$projectName, $tasks]) {
    foreach ($tasks as [$title, $startOffset, $endOffset, $status]) {
        $exists = $pdo->prepare('SELECT id FROM schedule_tasks WHERE company_id = ? AND project_id = ? AND title = ?');
        $exists->execute([$companyId, $projectIds[$projectName], $title]);
        if ($exists->fetch()) {
            continue;
        }
        $pdo->prepare('INSERT INTO schedule_tasks (company_id, project_id, title, start_date, end_date, status) VALUES (?,?,?,?,?,?)')
            ->execute([$companyId, $projectIds[$projectName], $title, date('Y-m-d', strtotime("{$startOffset} days")), date('Y-m-d', strtotime("{$endOffset} days")), $status]);
    }
}
echo "Schedule tasks ready.\n";

// ---- A few public Quick Estimate leads, for the admin's Leads page ----
$region = $pdo->query('SELECT id FROM quick_estimate_regions ORDER BY sort_order ASC LIMIT 1')->fetch();
$foundation = $pdo->query('SELECT id FROM quick_estimate_foundations ORDER BY sort_order ASC LIMIT 1')->fetch();
if ($region && $foundation) {
    $leads = [
        ['Sara Al Qahtani', 'sara.q@example.com', '+966 55 111 2233', 'new'],
        ['Fahad Al Otaibi', 'fahad.otaibi@example.com', '+966 50 222 3344', 'contacted'],
        ['Noura Al Dosari', 'noura.dosari@example.com', '+966 54 333 4455', 'converted'],
    ];
    foreach ($leads as [$name, $email, $phone, $status]) {
        $exists = $pdo->prepare("SELECT id FROM quick_estimates WHERE company_id IS NULL AND contact_email = ?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            continue;
        }
        $area = 250;
        $subtotal = $area * 950;
        $vat = $subtotal * 0.15;
        $pdo->prepare('INSERT INTO quick_estimates (project_name, region_id, foundation_id, total_area, discount_percent, addons_json, subtotal, vat_amount, total, lang, contact_name, contact_email, contact_phone, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(['New family home', $region['id'], $foundation['id'], $area, 0, '[]', $subtotal, $vat, $subtotal + $vat, 'en', $name, $email, $phone, $status]);
    }
    echo count($leads) . " Quick Estimate leads ready.\n";
}

echo "\nShowcase data seeded for {$company['name']}. Log in as owner@buildxact-saudi.local / Demo@12345\n";
