<?php

namespace App\Models;

use App\Core\Model;

class ComplianceDocument extends Model
{
    protected static string $table = 'compliance_documents';

    public const TYPES = [
        'cr' => 'Commercial Registration (CR)',
        'vat' => 'VAT Certificate',
        'zakat' => 'Zakat Certificate',
        'gosi' => 'GOSI Certificate of Good Standing',
        'chamber' => 'Chamber of Commerce Membership',
        'nitaqat' => 'Nitaqat (Saudization) Certificate',
        'classification' => 'Contractor Classification Certificate',
        'other' => 'Other',
    ];

    public static function expiringWithin(int $companyId, int $days): array
    {
        $cutoff = date('Y-m-d', strtotime("+{$days} days"));
        return static::query(
            "SELECT * FROM compliance_documents WHERE company_id = ? AND expiry_date IS NOT NULL AND expiry_date != '' AND expiry_date <= ? ORDER BY expiry_date ASC",
            [$companyId, $cutoff]
        )->fetchAll();
    }
}
