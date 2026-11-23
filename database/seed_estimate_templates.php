<?php

/**
 * Returns the built-in estimate template library: ~12 realistic Saudi-priced
 * project templates, each with grouped sections and material/labor line items —
 * used to seed `estimate_templates` / `estimate_template_items` from migrate.php,
 * and by EstimateController when creating an estimate from a template.
 */

return [
    [
        'name_en' => '2400sf Two Story Villa Build', 'name_ar' => 'بناء فيلا دورين 2400 قدم مربع',
        'description_en' => 'Full two-story villa build from foundation to roof.', 'description_ar' => 'بناء فيلا دورين كامل من الأساسات إلى السطح.',
        'building_type' => 'Residential', 'icon' => '🏠',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Site Prep & Foundations', 'title_ar' => 'تجهيز الموقع والأساسات', 'items' => [
                ['no' => '1.1', 'en' => 'Excavation and site clearance', 'ar' => 'أعمال الحفر وتجهيز الموقع', 'type' => 'labor', 'qty' => 240, 'uom' => 'm3', 'cost' => 80],
                ['no' => '1.2', 'en' => 'Ready-mix concrete footings 3000 psi', 'ar' => 'خرسانة جاهزة للقواعد 3000 رطل/بوصة', 'type' => 'material', 'qty' => 60, 'uom' => 'm3', 'cost' => 450],
                ['no' => '1.3', 'en' => 'Steel reinforcement mesh and rebar', 'ar' => 'شبك تسليح وحديد تسليح', 'type' => 'material', 'qty' => 6, 'uom' => 'ton', 'cost' => 4200],
                ['no' => '1.4', 'en' => 'Foundation waterproofing membrane', 'ar' => 'عزل مائي للأساسات', 'type' => 'material', 'qty' => 220, 'uom' => 'sqm', 'cost' => 65],
            ]],
            ['no' => '2.0', 'title_en' => 'Structure & Framing', 'title_ar' => 'الهيكل الإنشائي', 'items' => [
                ['no' => '2.1', 'en' => 'Concrete blockwork 200mm walls', 'ar' => 'مباني بلوك خرساني 200 مم', 'type' => 'material', 'qty' => 480, 'uom' => 'sqm', 'cost' => 85],
                ['no' => '2.2', 'en' => 'Reinforced concrete columns and beams', 'ar' => 'أعمدة وجسور خرسانية مسلحة', 'type' => 'material', 'qty' => 30, 'uom' => 'm3', 'cost' => 620],
                ['no' => '2.3', 'en' => 'Second floor slab casting', 'ar' => 'صب سقف الطابق الثاني', 'type' => 'labor', 'qty' => 240, 'uom' => 'sqm', 'cost' => 95],
                ['no' => '2.4', 'en' => 'Structural steelwork labour', 'ar' => 'أعمال الحديد الإنشائي', 'type' => 'labor', 'qty' => 6, 'uom' => 'ton', 'cost' => 800],
            ]],
            ['no' => '3.0', 'title_en' => 'Roofing & MEP Rough-in', 'title_ar' => 'الأسطح والتمديدات الأولية', 'items' => [
                ['no' => '3.1', 'en' => 'Roof waterproofing and thermal insulation', 'ar' => 'عزل مائي وحراري للسطح', 'type' => 'material', 'qty' => 260, 'uom' => 'sqm', 'cost' => 90],
                ['no' => '3.2', 'en' => 'Electrical first-fix wiring and conduits', 'ar' => 'تمديدات كهربائية أولية', 'type' => 'labor', 'qty' => 40, 'uom' => 'point', 'cost' => 150],
                ['no' => '3.3', 'en' => 'Plumbing first-fix pipework', 'ar' => 'تمديدات صحية أولية', 'type' => 'labor', 'qty' => 25, 'uom' => 'point', 'cost' => 200],
                ['no' => '3.4', 'en' => 'HVAC ductwork rough-in', 'ar' => 'تمديدات تكييف أولية', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 18000],
            ]],
        ],
    ],
    [
        'name_en' => 'Kitchen Remodel', 'name_ar' => 'تجديد المطبخ',
        'description_en' => 'Remodel a 220 sqft kitchen — cabinetry, countertops, and finishes.', 'description_ar' => 'تجديد مطبخ 220 قدم مربع — خزائن، أسطح عمل، وتشطيبات.',
        'building_type' => 'Renovation', 'icon' => '🍳',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Demolition & Disposal', 'title_ar' => 'الهدم والتخلص من المخلفات', 'items' => [
                ['no' => '1.1', 'en' => 'Remove existing cabinetry and countertops', 'ar' => 'إزالة الخزائن وأسطح العمل الحالية', 'type' => 'labor', 'qty' => 20, 'uom' => 'sqm', 'cost' => 45],
                ['no' => '1.2', 'en' => 'Debris removal and disposal', 'ar' => 'إزالة والتخلص من الأنقاض', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 1200],
            ]],
            ['no' => '2.0', 'title_en' => 'Cabinetry & Countertops', 'title_ar' => 'الخزائن وأسطح العمل', 'items' => [
                ['no' => '2.1', 'en' => 'Custom kitchen cabinets, upper and lower', 'ar' => 'خزائن مطبخ مخصصة، علوية وسفلية', 'type' => 'material', 'qty' => 12, 'uom' => 'lm', 'cost' => 1400],
                ['no' => '2.2', 'en' => 'Quartz countertop supply and install', 'ar' => 'توريد وتركيب سطح كوارتز', 'type' => 'material', 'qty' => 9, 'uom' => 'sqm', 'cost' => 900],
                ['no' => '2.3', 'en' => 'Kitchen sink and mixer tap', 'ar' => 'حوض مطبخ وخلاط', 'type' => 'material', 'qty' => 1, 'uom' => 'each', 'cost' => 1500],
            ]],
            ['no' => '3.0', 'title_en' => 'Plumbing, Electrical & Finishes', 'title_ar' => 'السباكة والكهرباء والتشطيبات', 'items' => [
                ['no' => '3.1', 'en' => 'Relocate plumbing for sink and dishwasher', 'ar' => 'نقل السباكة للحوض وغسالة الأطباق', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 2200],
                ['no' => '3.2', 'en' => 'Electrical points for appliances and lighting', 'ar' => 'نقاط كهربائية للأجهزة والإضاءة', 'type' => 'labor', 'qty' => 10, 'uom' => 'point', 'cost' => 150],
                ['no' => '3.3', 'en' => 'Ceramic backsplash tiling', 'ar' => 'تبليط سيراميك للجدار الخلفي', 'type' => 'material', 'qty' => 8, 'uom' => 'sqm', 'cost' => 140],
                ['no' => '3.4', 'en' => 'Porcelain floor tile installation', 'ar' => 'تركيب بلاط أرضيات بورسلين', 'type' => 'material', 'qty' => 20, 'uom' => 'sqm', 'cost' => 180],
            ]],
        ],
    ],
    [
        'name_en' => '130sf Bathroom Remodel', 'name_ar' => 'تجديد دورة مياه 130 قدم مربع',
        'description_en' => 'Includes categories such as interior walls, shower, and tiles.', 'description_ar' => 'يشمل الجدران الداخلية والدش والبلاط.',
        'building_type' => 'Renovation', 'icon' => '🛁',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Demolition & Disposal', 'title_ar' => 'الهدم والتخلص من المخلفات', 'items' => [
                ['no' => '1.1', 'en' => 'Remove existing fixtures, tile, and tub', 'ar' => 'إزالة التجهيزات والبلاط والمغطس الحالي', 'type' => 'labor', 'qty' => 12, 'uom' => 'sqm', 'cost' => 55],
                ['no' => '1.2', 'en' => 'Debris removal and disposal', 'ar' => 'إزالة والتخلص من الأنقاض', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 600],
            ]],
            ['no' => '2.0', 'title_en' => 'Plumbing & Fixtures', 'title_ar' => 'السباكة والتجهيزات', 'items' => [
                ['no' => '2.1', 'en' => 'Plumbing rework for shower and vanity', 'ar' => 'إعادة تمديد السباكة للدش والمغسلة', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 1800],
                ['no' => '2.2', 'en' => 'Rainfall shower set and glass enclosure', 'ar' => 'طقم دش ممطر وحاجز زجاجي', 'type' => 'material', 'qty' => 1, 'uom' => 'each', 'cost' => 2800],
                ['no' => '2.3', 'en' => 'Vanity unit, basin and mixer', 'ar' => 'وحدة مغسلة وحوض وخلاط', 'type' => 'material', 'qty' => 1, 'uom' => 'each', 'cost' => 2200],
                ['no' => '2.4', 'en' => 'Wall-hung toilet suite', 'ar' => 'طقم مرحاض معلق', 'type' => 'material', 'qty' => 1, 'uom' => 'each', 'cost' => 1600],
            ]],
            ['no' => '3.0', 'title_en' => 'Tiling & Waterproofing', 'title_ar' => 'التبليط والعزل المائي', 'items' => [
                ['no' => '3.1', 'en' => 'Waterproofing membrane, floor and walls', 'ar' => 'عزل مائي للأرضية والجدران', 'type' => 'material', 'qty' => 24, 'uom' => 'sqm', 'cost' => 60],
                ['no' => '3.2', 'en' => 'Porcelain wall and floor tiling', 'ar' => 'تبليط بورسلين للجدران والأرضية', 'type' => 'material', 'qty' => 24, 'uom' => 'sqm', 'cost' => 160],
                ['no' => '3.3', 'en' => 'Tiling installation labour', 'ar' => 'أجرة تركيب البلاط', 'type' => 'labor', 'qty' => 24, 'uom' => 'sqm', 'cost' => 70],
            ]],
        ],
    ],
    [
        'name_en' => '1500sf Two Story Exterior Cladding Replacement', 'name_ar' => 'استبدال الكسوة الخارجية لمبنى دورين 1500 قدم مربع',
        'description_en' => 'Two story template to help speed up the estimation process.', 'description_ar' => 'قالب لمبنى من دورين لتسريع عملية التسعير.',
        'building_type' => 'Renovation', 'icon' => '🏚️',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Removal', 'title_ar' => 'الإزالة', 'items' => [
                ['no' => '1.1', 'en' => 'Remove existing exterior cladding', 'ar' => 'إزالة الكسوة الخارجية الحالية', 'type' => 'labor', 'qty' => 260, 'uom' => 'sqm', 'cost' => 35],
                ['no' => '1.2', 'en' => 'Debris removal and disposal', 'ar' => 'إزالة والتخلص من الأنقاض', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 1800],
            ]],
            ['no' => '2.0', 'title_en' => 'Cladding Installation', 'title_ar' => 'تركيب الكسوة', 'items' => [
                ['no' => '2.1', 'en' => 'Weather-resistant house wrap', 'ar' => 'طبقة عزل مقاومة للعوامل الجوية', 'type' => 'material', 'qty' => 260, 'uom' => 'sqm', 'cost' => 25],
                ['no' => '2.2', 'en' => 'Aluminium composite cladding panels', 'ar' => 'ألواح كسوة من الألمنيوم المركب', 'type' => 'material', 'qty' => 260, 'uom' => 'sqm', 'cost' => 210],
                ['no' => '2.3', 'en' => 'Cladding installation labour', 'ar' => 'أجرة تركيب الكسوة', 'type' => 'labor', 'qty' => 260, 'uom' => 'sqm', 'cost' => 80],
            ]],
            ['no' => '3.0', 'title_en' => 'Finishing Trim', 'title_ar' => 'أعمال التشطيب النهائية', 'items' => [
                ['no' => '3.1', 'en' => 'Corner trims and flashing', 'ar' => 'زوايا التشطيب وحواف العزل', 'type' => 'material', 'qty' => 90, 'uom' => 'lm', 'cost' => 45],
                ['no' => '3.2', 'en' => 'Sealant and joint finishing', 'ar' => 'مادة عازلة وتشطيب الفواصل', 'type' => 'material', 'qty' => 90, 'uom' => 'lm', 'cost' => 20],
                ['no' => '3.3', 'en' => 'Scaffolding hire', 'ar' => 'استئجار سقالات', 'type' => 'material', 'qty' => 1, 'uom' => 'lot', 'cost' => 4500],
            ]],
        ],
    ],
    [
        'name_en' => 'Home Addition / Room Extension', 'name_ar' => 'إضافة غرفة / توسعة المنزل',
        'description_en' => 'Build a 500 sqft family room addition to an existing home.', 'description_ar' => 'بناء إضافة غرفة عائلية 500 قدم مربع لمنزل قائم.',
        'building_type' => 'Residential', 'icon' => '➕',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Foundation', 'title_ar' => 'الأساسات', 'items' => [
                ['no' => '1.1', 'en' => 'Excavation and slab-on-grade foundation', 'ar' => 'حفر وأساسات على مستوى الأرض', 'type' => 'labor', 'qty' => 46, 'uom' => 'm3', 'cost' => 85],
                ['no' => '1.2', 'en' => 'Ready-mix concrete and rebar', 'ar' => 'خرسانة جاهزة وحديد تسليح', 'type' => 'material', 'qty' => 12, 'uom' => 'm3', 'cost' => 480],
            ]],
            ['no' => '2.0', 'title_en' => 'Framing & Roof Tie-in', 'title_ar' => 'الهيكل وربط السطح', 'items' => [
                ['no' => '2.1', 'en' => 'Concrete blockwork walls', 'ar' => 'مباني بلوك خرساني للجدران', 'type' => 'material', 'qty' => 90, 'uom' => 'sqm', 'cost' => 85],
                ['no' => '2.2', 'en' => 'Roof structure tie-in to existing house', 'ar' => 'ربط هيكل السطح بالمنزل الحالي', 'type' => 'labor', 'qty' => 46, 'uom' => 'sqm', 'cost' => 130],
                ['no' => '2.3', 'en' => 'Roof waterproofing and insulation', 'ar' => 'عزل مائي وحراري للسطح', 'type' => 'material', 'qty' => 46, 'uom' => 'sqm', 'cost' => 90],
            ]],
            ['no' => '3.0', 'title_en' => 'MEP & Finishes', 'title_ar' => 'الكهرباء والسباكة والتشطيبات', 'items' => [
                ['no' => '3.1', 'en' => 'Electrical wiring and points', 'ar' => 'تمديدات ونقاط كهربائية', 'type' => 'labor', 'qty' => 12, 'uom' => 'point', 'cost' => 150],
                ['no' => '3.2', 'en' => 'Interior wall painting, two coats', 'ar' => 'دهان داخلي طبقتين', 'type' => 'material', 'qty' => 90, 'uom' => 'sqm', 'cost' => 25],
                ['no' => '3.3', 'en' => 'Porcelain floor tiling', 'ar' => 'تبليط أرضيات بورسلين', 'type' => 'material', 'qty' => 46, 'uom' => 'sqm', 'cost' => 180],
            ]],
        ],
    ],
    [
        'name_en' => 'Outdoor Majlis / Deck Construction', 'name_ar' => 'بناء مجلس خارجي / سطح خشبي',
        'description_en' => 'Build a 300 sqft outdoor majlis or deck structure.', 'description_ar' => 'بناء مجلس خارجي أو سطح خشبي بمساحة 300 قدم مربع.',
        'building_type' => 'Outdoor', 'icon' => '🪵',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Foundation & Posts', 'title_ar' => 'الأساسات والأعمدة', 'items' => [
                ['no' => '1.1', 'en' => 'Concrete footings for support posts', 'ar' => 'قواعد خرسانية لأعمدة الدعم', 'type' => 'material', 'qty' => 12, 'uom' => 'each', 'cost' => 220],
                ['no' => '1.2', 'en' => 'Pressure-treated support posts', 'ar' => 'أعمدة دعم معالجة بالضغط', 'type' => 'material', 'qty' => 12, 'uom' => 'each', 'cost' => 180],
            ]],
            ['no' => '2.0', 'title_en' => 'Framing & Decking', 'title_ar' => 'الهيكل والأرضية الخشبية', 'items' => [
                ['no' => '2.1', 'en' => 'Structural steel or timber framing', 'ar' => 'هيكل من الحديد أو الخشب الإنشائي', 'type' => 'material', 'qty' => 28, 'uom' => 'sqm', 'cost' => 150],
                ['no' => '2.2', 'en' => 'WPC decking boards, supply and install', 'ar' => 'توريد وتركيب ألواح أرضية WPC', 'type' => 'material', 'qty' => 28, 'uom' => 'sqm', 'cost' => 220],
            ]],
            ['no' => '3.0', 'title_en' => 'Railing, Shade & Finishing', 'title_ar' => 'الدرابزين والتظليل والتشطيب', 'items' => [
                ['no' => '3.1', 'en' => 'Aluminium railing system', 'ar' => 'نظام درابزين من الألمنيوم', 'type' => 'material', 'qty' => 20, 'uom' => 'lm', 'cost' => 320],
                ['no' => '3.2', 'en' => 'Shade sail or pergola structure', 'ar' => 'هيكل مظلة أو برجولة', 'type' => 'material', 'qty' => 1, 'uom' => 'lot', 'cost' => 8500],
                ['no' => '3.3', 'en' => 'Outdoor lighting points', 'ar' => 'نقاط إضاءة خارجية', 'type' => 'labor', 'qty' => 6, 'uom' => 'point', 'cost' => 180],
            ]],
        ],
    ],
    [
        'name_en' => 'Landscaping & Garden Works', 'name_ar' => 'أعمال تنسيق الحدائق',
        'description_en' => 'Landscape an 800 sqft backyard with irrigation and planting.', 'description_ar' => 'تنسيق حديقة خلفية بمساحة 800 قدم مربع مع الري والزراعة.',
        'building_type' => 'Outdoor', 'icon' => '🌿',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Site Grading & Irrigation', 'title_ar' => 'تسوية الموقع والري', 'items' => [
                ['no' => '1.1', 'en' => 'Site grading and soil preparation', 'ar' => 'تسوية الموقع وتجهيز التربة', 'type' => 'labor', 'qty' => 74, 'uom' => 'sqm', 'cost' => 25],
                ['no' => '1.2', 'en' => 'Automated drip irrigation system', 'ar' => 'نظام ري بالتنقيط الآلي', 'type' => 'material', 'qty' => 1, 'uom' => 'lot', 'cost' => 6500],
            ]],
            ['no' => '2.0', 'title_en' => 'Softscape (Planting)', 'title_ar' => 'الزراعة (النباتات)', 'items' => [
                ['no' => '2.1', 'en' => 'Natural or artificial turf, supply and lay', 'ar' => 'توريد وفرش عشب طبيعي أو صناعي', 'type' => 'material', 'qty' => 74, 'uom' => 'sqm', 'cost' => 95],
                ['no' => '2.2', 'en' => 'Trees, shrubs and planting beds', 'ar' => 'أشجار وشجيرات وأحواض زراعة', 'type' => 'material', 'qty' => 1, 'uom' => 'lot', 'cost' => 4200],
            ]],
            ['no' => '3.0', 'title_en' => 'Hardscape (Paving)', 'title_ar' => 'الأرضيات الصلبة (الرصف)', 'items' => [
                ['no' => '3.1', 'en' => 'Interlock paving for pathways', 'ar' => 'رصف بلاط إنترلوك للممرات', 'type' => 'material', 'qty' => 30, 'uom' => 'sqm', 'cost' => 85],
                ['no' => '3.2', 'en' => 'Garden lighting points', 'ar' => 'نقاط إضاءة الحديقة', 'type' => 'labor', 'qty' => 8, 'uom' => 'point', 'cost' => 150],
                ['no' => '3.3', 'en' => 'Boundary wall or fencing', 'ar' => 'سياج أو جدار محيطي', 'type' => 'material', 'qty' => 40, 'uom' => 'lm', 'cost' => 190],
            ]],
        ],
    ],
    [
        'name_en' => 'Roofing Replacement', 'name_ar' => 'استبدال السطح',
        'description_en' => 'Replace a 2,000 sqft roof — tear-off through to finished flashing.', 'description_ar' => 'استبدال سطح بمساحة 2000 قدم مربع من الإزالة حتى العزل النهائي.',
        'building_type' => 'Renovation', 'icon' => '🏘️',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Tear-off & Disposal', 'title_ar' => 'الإزالة والتخلص من المخلفات', 'items' => [
                ['no' => '1.1', 'en' => 'Remove existing roofing material', 'ar' => 'إزالة مواد السطح الحالية', 'type' => 'labor', 'qty' => 186, 'uom' => 'sqm', 'cost' => 30],
                ['no' => '1.2', 'en' => 'Debris removal and disposal', 'ar' => 'إزالة والتخلص من الأنقاض', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 2200],
            ]],
            ['no' => '2.0', 'title_en' => 'Underlayment & Insulation', 'title_ar' => 'طبقة العزل التحتية والحراري', 'items' => [
                ['no' => '2.1', 'en' => 'Waterproof roofing membrane', 'ar' => 'غشاء عزل مائي للسطح', 'type' => 'material', 'qty' => 186, 'uom' => 'sqm', 'cost' => 120],
                ['no' => '2.2', 'en' => 'Thermal insulation boards', 'ar' => 'ألواح عزل حراري', 'type' => 'material', 'qty' => 186, 'uom' => 'sqm', 'cost' => 65],
            ]],
            ['no' => '3.0', 'title_en' => 'Roofing & Flashing', 'title_ar' => 'مواد السطح والعزل الطرفي', 'items' => [
                ['no' => '3.1', 'en' => 'Roofing material installation', 'ar' => 'تركيب مواد السطح', 'type' => 'labor', 'qty' => 186, 'uom' => 'sqm', 'cost' => 90],
                ['no' => '3.2', 'en' => 'Flashing, gutters and downspouts', 'ar' => 'العزل الطرفي والمزاريب', 'type' => 'material', 'qty' => 55, 'uom' => 'lm', 'cost' => 75],
            ]],
        ],
    ],
    [
        'name_en' => 'Majlis / Basement Finishing', 'name_ar' => 'تشطيب مجلس / قبو',
        'description_en' => 'Finish a 600 sqft majlis or basement space — framing through finishes.', 'description_ar' => 'تشطيب مجلس أو قبو بمساحة 600 قدم مربع من الهيكل حتى التشطيبات.',
        'building_type' => 'Renovation', 'icon' => '🛋️',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Framing & Insulation', 'title_ar' => 'الهيكل والعزل', 'items' => [
                ['no' => '1.1', 'en' => 'Partition wall framing', 'ar' => 'هيكل الجدران الفاصلة', 'type' => 'material', 'qty' => 56, 'uom' => 'sqm', 'cost' => 95],
                ['no' => '1.2', 'en' => 'Thermal and acoustic insulation', 'ar' => 'عزل حراري وصوتي', 'type' => 'material', 'qty' => 56, 'uom' => 'sqm', 'cost' => 45],
            ]],
            ['no' => '2.0', 'title_en' => 'Electrical & Plumbing Rough-in', 'title_ar' => 'التمديدات الكهربائية والصحية الأولية', 'items' => [
                ['no' => '2.1', 'en' => 'Electrical wiring and points', 'ar' => 'تمديدات ونقاط كهربائية', 'type' => 'labor', 'qty' => 14, 'uom' => 'point', 'cost' => 150],
                ['no' => '2.2', 'en' => 'Powder room plumbing rough-in', 'ar' => 'تمديدات صحية أولية لدورة مياه', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 3200],
            ]],
            ['no' => '3.0', 'title_en' => 'Drywall, Flooring & Finishes', 'title_ar' => 'الجبس والأرضيات والتشطيبات', 'items' => [
                ['no' => '3.1', 'en' => 'Gypsum drywall partitions and ceiling', 'ar' => 'قواطع وأسقف جبسية', 'type' => 'material', 'qty' => 56, 'uom' => 'sqm', 'cost' => 95],
                ['no' => '3.2', 'en' => 'Vinyl or laminate flooring', 'ar' => 'أرضيات فينيل أو لامينيت', 'type' => 'material', 'qty' => 56, 'uom' => 'sqm', 'cost' => 85],
                ['no' => '3.3', 'en' => 'Wall painting, two coats', 'ar' => 'دهان الجدران طبقتين', 'type' => 'material', 'qty' => 56, 'uom' => 'sqm', 'cost' => 25],
            ]],
        ],
    ],
    [
        'name_en' => 'Villa Complete Renovation', 'name_ar' => 'تجديد فيلا بالكامل',
        'description_en' => 'Full interior and exterior renovation of an existing villa.', 'description_ar' => 'تجديد داخلي وخارجي كامل لفيلا قائمة.',
        'building_type' => 'Renovation', 'icon' => '🏡',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Demolition', 'title_ar' => 'الهدم', 'items' => [
                ['no' => '1.1', 'en' => 'Selective interior demolition', 'ar' => 'هدم داخلي انتقائي', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 15000],
                ['no' => '1.2', 'en' => 'Debris removal and disposal', 'ar' => 'إزالة والتخلص من الأنقاض', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 6000],
            ]],
            ['no' => '2.0', 'title_en' => 'Structural & MEP Upgrades', 'title_ar' => 'الترميم الإنشائي وتحديث الكهرباء والسباكة', 'items' => [
                ['no' => '2.1', 'en' => 'Electrical system rewiring', 'ar' => 'إعادة تمديد النظام الكهربائي', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 35000],
                ['no' => '2.2', 'en' => 'Plumbing system upgrade', 'ar' => 'تحديث نظام السباكة', 'type' => 'labor', 'qty' => 1, 'uom' => 'lot', 'cost' => 28000],
                ['no' => '2.3', 'en' => 'Central AC system replacement', 'ar' => 'استبدال نظام التكييف المركزي', 'type' => 'material', 'qty' => 1, 'uom' => 'lot', 'cost' => 55000],
            ]],
            ['no' => '3.0', 'title_en' => 'Interior & Exterior Finishes', 'title_ar' => 'التشطيبات الداخلية والخارجية', 'items' => [
                ['no' => '3.1', 'en' => 'Full interior painting and finishes', 'ar' => 'دهان وتشطيبات داخلية كاملة', 'type' => 'material', 'qty' => 420, 'uom' => 'sqm', 'cost' => 60],
                ['no' => '3.2', 'en' => 'Porcelain and marble flooring', 'ar' => 'أرضيات بورسلين ورخام', 'type' => 'material', 'qty' => 420, 'uom' => 'sqm', 'cost' => 210],
                ['no' => '3.3', 'en' => 'Exterior facade refresh', 'ar' => 'تجديد الواجهة الخارجية', 'type' => 'material', 'qty' => 260, 'uom' => 'sqm', 'cost' => 180],
            ]],
        ],
    ],
    [
        'name_en' => 'Residential Swimming Pool', 'name_ar' => 'مسبح سكني',
        'description_en' => 'Standard residential swimming pool construction, 40 sqm.', 'description_ar' => 'بناء مسبح سكني قياسي بمساحة 40 متر مربع.',
        'building_type' => 'Outdoor', 'icon' => '🏊',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Excavation & Shell', 'title_ar' => 'الحفر والهيكل', 'items' => [
                ['no' => '1.1', 'en' => 'Pool excavation', 'ar' => 'حفر المسبح', 'type' => 'labor', 'qty' => 90, 'uom' => 'm3', 'cost' => 90],
                ['no' => '1.2', 'en' => 'Reinforced concrete pool shell', 'ar' => 'هيكل مسبح خرساني مسلح', 'type' => 'material', 'qty' => 40, 'uom' => 'sqm', 'cost' => 950],
            ]],
            ['no' => '2.0', 'title_en' => 'Waterproofing, Tiling & Filtration', 'title_ar' => 'العزل والتبليط والفلترة', 'items' => [
                ['no' => '2.1', 'en' => 'Pool waterproofing membrane', 'ar' => 'عزل مائي للمسبح', 'type' => 'material', 'qty' => 70, 'uom' => 'sqm', 'cost' => 110],
                ['no' => '2.2', 'en' => 'Mosaic pool tiling', 'ar' => 'تبليط فسيفساء للمسبح', 'type' => 'material', 'qty' => 70, 'uom' => 'sqm', 'cost' => 220],
                ['no' => '2.3', 'en' => 'Filtration and circulation system', 'ar' => 'نظام الفلترة والدوران', 'type' => 'material', 'qty' => 1, 'uom' => 'lot', 'cost' => 22000],
            ]],
            ['no' => '3.0', 'title_en' => 'Decking & Finishing', 'title_ar' => 'الأرضية المحيطة والتشطيب', 'items' => [
                ['no' => '3.1', 'en' => 'Pool surround decking', 'ar' => 'أرضية محيطة بالمسبح', 'type' => 'material', 'qty' => 45, 'uom' => 'sqm', 'cost' => 160],
                ['no' => '3.2', 'en' => 'Pool lighting and heating points', 'ar' => 'نقاط إضاءة وتسخين المسبح', 'type' => 'labor', 'qty' => 6, 'uom' => 'point', 'cost' => 350],
            ]],
        ],
    ],
    [
        'name_en' => 'Commercial Office Fit-out', 'name_ar' => 'تجهيز مكاتب تجارية',
        'description_en' => 'Fit-out of a 1,000 sqft commercial office space.', 'description_ar' => 'تجهيز مساحة مكتبية تجارية بمساحة 1000 قدم مربع.',
        'building_type' => 'Commercial', 'icon' => '🏢',
        'sections' => [
            ['no' => '1.0', 'title_en' => 'Demolition & Partitions', 'title_ar' => 'الهدم والقواطع', 'items' => [
                ['no' => '1.1', 'en' => 'Strip-out of existing fit-out', 'ar' => 'إزالة التجهيزات الحالية', 'type' => 'labor', 'qty' => 93, 'uom' => 'sqm', 'cost' => 45],
                ['no' => '1.2', 'en' => 'Glass and drywall office partitions', 'ar' => 'قواطع مكتبية زجاجية وجبسية', 'type' => 'material', 'qty' => 60, 'uom' => 'sqm', 'cost' => 320],
            ]],
            ['no' => '2.0', 'title_en' => 'MEP Installation', 'title_ar' => 'تمديدات الكهرباء والسباكة والتكييف', 'items' => [
                ['no' => '2.1', 'en' => 'Data and power points', 'ar' => 'نقاط بيانات وكهرباء', 'type' => 'labor', 'qty' => 25, 'uom' => 'point', 'cost' => 180],
                ['no' => '2.2', 'en' => 'Central AC ductwork extension', 'ar' => 'تمديد دكت التكييف المركزي', 'type' => 'material', 'qty' => 1, 'uom' => 'lot', 'cost' => 32000],
            ]],
            ['no' => '3.0', 'title_en' => 'Flooring, Ceiling & Finishes', 'title_ar' => 'الأرضيات والأسقف والتشطيبات', 'items' => [
                ['no' => '3.1', 'en' => 'Raised access flooring with carpet tiles', 'ar' => 'أرضية مرفوعة مع بلاط سجاد', 'type' => 'material', 'qty' => 93, 'uom' => 'sqm', 'cost' => 210],
                ['no' => '3.2', 'en' => 'Suspended ceiling with lighting grid', 'ar' => 'سقف معلق مع شبكة إضاءة', 'type' => 'material', 'qty' => 93, 'uom' => 'sqm', 'cost' => 145],
                ['no' => '3.3', 'en' => 'Reception and joinery fixtures', 'ar' => 'استقبال وأعمال نجارة', 'type' => 'material', 'qty' => 1, 'uom' => 'lot', 'cost' => 18000],
            ]],
        ],
    ],
];
