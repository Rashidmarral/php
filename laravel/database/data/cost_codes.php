<?php

/**
 * Platform-wide (company_id = null), practical subset of CSI MasterFormat
 * divisions most relevant to Saudi residential/commercial construction —
 * NOT the full 49-division standard. Two levels only: a division (level 1)
 * with 2-4 sub-codes (level 2). Seeds `cost_codes` via CostCodeSeeder.
 *
 * Keys: code, description, description_ar, children (array of the same
 * shape, one level deep only — no 'children' key on a sub-code).
 */

return [
    [
        'code' => '03 00 00', 'description' => 'Concrete', 'description_ar' => 'الخرسانة',
        'children' => [
            ['code' => '03 10 00', 'description' => 'Concrete Formwork', 'description_ar' => 'أعمال القوالب الخرسانية'],
            ['code' => '03 20 00', 'description' => 'Concrete Reinforcing', 'description_ar' => 'حديد التسليح'],
            ['code' => '03 30 00', 'description' => 'Cast-in-Place Concrete', 'description_ar' => 'الخرسانة المصبوبة في الموقع'],
        ],
    ],
    [
        'code' => '04 00 00', 'description' => 'Masonry', 'description_ar' => 'أعمال البناء',
        'children' => [
            ['code' => '04 20 00', 'description' => 'Unit Masonry (Block & Brick)', 'description_ar' => 'أعمال البناء بالوحدات (بلوك وطابوق)'],
            ['code' => '04 40 00', 'description' => 'Stone Masonry', 'description_ar' => 'أعمال البناء الحجري'],
        ],
    ],
    [
        'code' => '05 00 00', 'description' => 'Metals', 'description_ar' => 'المعادن',
        'children' => [
            ['code' => '05 10 00', 'description' => 'Structural Steel Framing', 'description_ar' => 'الهياكل الفولاذية'],
            ['code' => '05 50 00', 'description' => 'Metal Fabrications', 'description_ar' => 'المصنوعات المعدنية'],
        ],
    ],
    [
        'code' => '06 00 00', 'description' => 'Wood, Plastics & Composites', 'description_ar' => 'الأخشاب والبلاستيك والمواد المركبة',
        'children' => [
            ['code' => '06 10 00', 'description' => 'Rough Carpentry', 'description_ar' => 'النجارة الخشبية الأساسية'],
            ['code' => '06 40 00', 'description' => 'Architectural Woodwork', 'description_ar' => 'النجارة المعمارية'],
        ],
    ],
    [
        'code' => '07 00 00', 'description' => 'Thermal & Moisture Protection', 'description_ar' => 'العزل الحراري والمائي',
        'children' => [
            ['code' => '07 10 00', 'description' => 'Waterproofing', 'description_ar' => 'العزل المائي'],
            ['code' => '07 20 00', 'description' => 'Thermal Insulation', 'description_ar' => 'العزل الحراري'],
            ['code' => '07 50 00', 'description' => 'Membrane Roofing', 'description_ar' => 'أعمال العزل والتسقيف'],
        ],
    ],
    [
        'code' => '08 00 00', 'description' => 'Openings (Doors & Windows)', 'description_ar' => 'الفتحات (الأبواب والنوافذ)',
        'children' => [
            ['code' => '08 10 00', 'description' => 'Doors & Frames', 'description_ar' => 'الأبواب والإطارات'],
            ['code' => '08 50 00', 'description' => 'Windows', 'description_ar' => 'النوافذ'],
            ['code' => '08 80 00', 'description' => 'Glazing', 'description_ar' => 'الزجاج والتزجيج'],
        ],
    ],
    [
        'code' => '09 00 00', 'description' => 'Finishes', 'description_ar' => 'التشطيبات',
        'children' => [
            ['code' => '09 20 00', 'description' => 'Plaster & Gypsum Board', 'description_ar' => 'الجبس والأسقف المعلقة'],
            ['code' => '09 30 00', 'description' => 'Tiling', 'description_ar' => 'أعمال البلاط'],
            ['code' => '09 60 00', 'description' => 'Flooring', 'description_ar' => 'أعمال الأرضيات'],
            ['code' => '09 90 00', 'description' => 'Painting & Coating', 'description_ar' => 'الطلاء والدهانات'],
        ],
    ],
    [
        'code' => '22 00 00', 'description' => 'Plumbing', 'description_ar' => 'السباكة',
        'children' => [
            ['code' => '22 10 00', 'description' => 'Plumbing Piping & Pumps', 'description_ar' => 'مواسير وأنابيب السباكة'],
            ['code' => '22 40 00', 'description' => 'Plumbing Fixtures', 'description_ar' => 'تجهيزات السباكة'],
        ],
    ],
    [
        'code' => '23 00 00', 'description' => 'HVAC', 'description_ar' => 'التكييف والتهوية',
        'children' => [
            ['code' => '23 10 00', 'description' => 'HVAC Ductwork', 'description_ar' => 'مجاري الهواء'],
            ['code' => '23 60 00', 'description' => 'Central Cooling Equipment', 'description_ar' => 'معدات التكييف المركزي'],
        ],
    ],
    [
        'code' => '26 00 00', 'description' => 'Electrical', 'description_ar' => 'الكهرباء',
        'children' => [
            ['code' => '26 05 00', 'description' => 'Electrical Wiring & Conduit', 'description_ar' => 'الأسلاك والقنوات الكهربائية'],
            ['code' => '26 30 00', 'description' => 'Low-Voltage & Standby Power', 'description_ar' => 'الطاقة الاحتياطية والأنظمة منخفضة الجهد'],
            ['code' => '26 50 00', 'description' => 'Lighting', 'description_ar' => 'الإضاءة'],
        ],
    ],
    [
        'code' => '31 00 00', 'description' => 'Earthwork', 'description_ar' => 'أعمال الحفر والترحيل',
        'children' => [
            ['code' => '31 20 00', 'description' => 'Earth Moving (Excavation & Grading)', 'description_ar' => 'أعمال الحفر والتسوية'],
            ['code' => '31 60 00', 'description' => 'Special Foundations & Shoring', 'description_ar' => 'الأساسات الخاصة والدعم المؤقت'],
        ],
    ],
    [
        'code' => '32 00 00', 'description' => 'Exterior Improvements', 'description_ar' => 'التحسينات الخارجية',
        'children' => [
            ['code' => '32 10 00', 'description' => 'Paving (Roads & Parking)', 'description_ar' => 'الرصف (الطرق ومواقف السيارات)'],
            ['code' => '32 30 00', 'description' => 'Site Improvements (Fencing, Signage)', 'description_ar' => 'تحسينات الموقع (أسوار ولوحات)'],
            ['code' => '32 90 00', 'description' => 'Planting & Landscaping', 'description_ar' => 'الزراعة وتنسيق المواقع'],
        ],
    ],
    [
        'code' => '33 00 00', 'description' => 'Utilities', 'description_ar' => 'المرافق العامة',
        'children' => [
            ['code' => '33 10 00', 'description' => 'Water Utilities', 'description_ar' => 'مرافق المياه'],
            ['code' => '33 30 00', 'description' => 'Sanitary Sewerage Utilities', 'description_ar' => 'مرافق الصرف الصحي'],
        ],
    ],
];
