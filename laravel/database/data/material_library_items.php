<?php

/**
 * Shared, admin-curated material catalog for the Saudi construction market:
 * ~100 realistic materials with bilingual EN/AR names, sensible units, and
 * SAR unit costs, grouped into a fixed set of trade categories. Used to seed
 * `material_library_items` from MaterialLibrarySeeder, and browsed/imported
 * per-company from MaterialController::libraryIndex()/importFromLibrary().
 *
 * Keys: sku, name, name_ar, category, unit, material_cost, labor_cost (SAR), notes.
 */

return [
    // ---------------- Concrete & Steel ----------------
    ['sku' => 'CEM-425', 'name' => 'Cement OPC 42.5N (50kg bag)', 'name_ar' => 'أسمنت بورتلاندي عادي 42.5 (كيس 50 كجم)', 'category' => 'Concrete & Steel', 'unit' => 'bag', 'material_cost' => 18.50, 'labor_cost' => 0],
    ['sku' => 'CEM-SRC', 'name' => 'Sulfate Resisting Cement (50kg bag)', 'name_ar' => 'أسمنت مقاوم للكبريتات (كيس 50 كجم)', 'category' => 'Concrete & Steel', 'unit' => 'bag', 'material_cost' => 21.00, 'labor_cost' => 0, 'notes' => 'Required for foundations in high-sulfate soil, common in Eastern Province.'],
    ['sku' => 'REBAR-10', 'name' => 'Deformed Rebar 10mm (ton)', 'name_ar' => 'حديد تسليح مشرشر 10مم (طن)', 'category' => 'Concrete & Steel', 'unit' => 'ton', 'material_cost' => 2750.00, 'labor_cost' => 0],
    ['sku' => 'REBAR-12', 'name' => 'Deformed Rebar 12mm (ton)', 'name_ar' => 'حديد تسليح مشرشر 12مم (طن)', 'category' => 'Concrete & Steel', 'unit' => 'ton', 'material_cost' => 2800.00, 'labor_cost' => 0],
    ['sku' => 'REBAR-16', 'name' => 'Deformed Rebar 16mm (ton)', 'name_ar' => 'حديد تسليح مشرشر 16مم (طن)', 'category' => 'Concrete & Steel', 'unit' => 'ton', 'material_cost' => 2850.00, 'labor_cost' => 0],
    ['sku' => 'REBAR-20', 'name' => 'Deformed Rebar 20mm (ton)', 'name_ar' => 'حديد تسليح مشرشر 20مم (طن)', 'category' => 'Concrete & Steel', 'unit' => 'ton', 'material_cost' => 2900.00, 'labor_cost' => 0],
    ['sku' => 'WIREMESH-66', 'name' => 'Welded Wire Mesh 6x6 150x150 (sheet)', 'name_ar' => 'شبك لحام 6×6 مقاس 150×150 (لوح)', 'category' => 'Concrete & Steel', 'unit' => 'sheet', 'material_cost' => 85.00, 'labor_cost' => 0],
    ['sku' => 'RMX-C25', 'name' => 'Ready-Mix Concrete C25/30 (m3)', 'name_ar' => 'خرسانة جاهزة C25/30 (م3)', 'category' => 'Concrete & Steel', 'unit' => 'm3', 'material_cost' => 265.00, 'labor_cost' => 0],
    ['sku' => 'RMX-C35', 'name' => 'Ready-Mix Concrete C35/45 (m3)', 'name_ar' => 'خرسانة جاهزة C35/45 (م3)', 'category' => 'Concrete & Steel', 'unit' => 'm3', 'material_cost' => 305.00, 'labor_cost' => 0],
    ['sku' => 'FORMPLY-18', 'name' => 'Formwork Plywood 18mm (sheet)', 'name_ar' => 'خشب قوالب أبلكاش 18مم (لوح)', 'category' => 'Concrete & Steel', 'unit' => 'sheet', 'material_cost' => 95.00, 'labor_cost' => 0],

    // ---------------- Masonry ----------------
    ['sku' => 'BLK-CONC20', 'name' => 'Concrete Hollow Block 20cm', 'name_ar' => 'بلوك خرساني مفرغ 20 سم', 'category' => 'Masonry', 'unit' => 'each', 'material_cost' => 3.20, 'labor_cost' => 0],
    ['sku' => 'BLK-CONC15', 'name' => 'Concrete Hollow Block 15cm', 'name_ar' => 'بلوك خرساني مفرغ 15 سم', 'category' => 'Masonry', 'unit' => 'each', 'material_cost' => 2.60, 'labor_cost' => 0],
    ['sku' => 'BLK-AAC', 'name' => 'Autoclaved Aerated Concrete (AAC) Block', 'name_ar' => 'بلوك خرساني مبخر (AAC)', 'category' => 'Masonry', 'unit' => 'each', 'material_cost' => 6.50, 'labor_cost' => 0],
    ['sku' => 'BRICK-CLAY', 'name' => 'Red Clay Brick', 'name_ar' => 'طوب أحمر طيني', 'category' => 'Masonry', 'unit' => 'each', 'material_cost' => 0.85, 'labor_cost' => 0],
    ['sku' => 'MORTAR-RM', 'name' => 'Ready-Mix Masonry Mortar (40kg bag)', 'name_ar' => 'مونة بناء جاهزة (كيس 40 كجم)', 'category' => 'Masonry', 'unit' => 'bag', 'material_cost' => 14.00, 'labor_cost' => 0],
    ['sku' => 'LINTEL-PC', 'name' => 'Precast Concrete Lintel 1.2m', 'name_ar' => 'عتبة خرسانية جاهزة 1.2م', 'category' => 'Masonry', 'unit' => 'each', 'material_cost' => 45.00, 'labor_cost' => 0],
    ['sku' => 'TIEBAR-MSNRY', 'name' => 'Masonry Tie Bar / Wall Starter', 'name_ar' => 'سيخ ربط بناء', 'category' => 'Masonry', 'unit' => 'each', 'material_cost' => 4.50, 'labor_cost' => 0],
    ['sku' => 'PLASTER-GYP', 'name' => 'Gypsum Plaster (40kg bag)', 'name_ar' => 'جبس بناء (كيس 40 كجم)', 'category' => 'Masonry', 'unit' => 'bag', 'material_cost' => 16.00, 'labor_cost' => 0],

    // ---------------- Finishing & Tiling ----------------
    ['sku' => 'TILE-CER60', 'name' => 'Ceramic Floor Tile 60x60cm', 'name_ar' => 'بلاط أرضيات سيراميك 60×60 سم', 'category' => 'Finishing & Tiling', 'unit' => 'sqm', 'material_cost' => 42.00, 'labor_cost' => 0],
    ['sku' => 'TILE-POR80', 'name' => 'Porcelain Floor Tile 80x80cm', 'name_ar' => 'بلاط أرضيات بورسلين 80×80 سم', 'category' => 'Finishing & Tiling', 'unit' => 'sqm', 'material_cost' => 68.00, 'labor_cost' => 0],
    ['sku' => 'TILE-WALLGLZ', 'name' => 'Glazed Wall Tile 30x60cm', 'name_ar' => 'بلاط جدران مزجج 30×60 سم', 'category' => 'Finishing & Tiling', 'unit' => 'sqm', 'material_cost' => 38.00, 'labor_cost' => 0],
    ['sku' => 'TILE-GRAN60', 'name' => 'Granite Tile Polished 60x60cm', 'name_ar' => 'بلاط جرانيت مصقول 60×60 سم', 'category' => 'Finishing & Tiling', 'unit' => 'sqm', 'material_cost' => 145.00, 'labor_cost' => 0],
    ['sku' => 'MARBLE-SLAB', 'name' => 'Marble Slab, Local, Polished', 'name_ar' => 'ألواح رخام محلي مصقول', 'category' => 'Finishing & Tiling', 'unit' => 'sqm', 'material_cost' => 280.00, 'labor_cost' => 0],
    ['sku' => 'GROUT-TILE', 'name' => 'Tile Grout (5kg bag)', 'name_ar' => 'مادة تعبئة فواصل البلاط (كيس 5 كجم)', 'category' => 'Finishing & Tiling', 'unit' => 'bag', 'material_cost' => 22.00, 'labor_cost' => 0],
    ['sku' => 'TILEADH-C2', 'name' => 'Tile Adhesive C2 (25kg bag)', 'name_ar' => 'لاصق بلاط C2 (كيس 25 كجم)', 'category' => 'Finishing & Tiling', 'unit' => 'bag', 'material_cost' => 28.00, 'labor_cost' => 0],
    ['sku' => 'GYPBOARD-STD', 'name' => 'Gypsum Board Standard 12mm (1.2x2.4m sheet)', 'name_ar' => 'لوح جبس بورد عادي 12مم (1.2×2.4م)', 'category' => 'Finishing & Tiling', 'unit' => 'sheet', 'material_cost' => 32.00, 'labor_cost' => 0],
    ['sku' => 'GYPBOARD-MR', 'name' => 'Gypsum Board Moisture Resistant (sheet)', 'name_ar' => 'لوح جبس بورد مقاوم للرطوبة', 'category' => 'Finishing & Tiling', 'unit' => 'sheet', 'material_cost' => 42.00, 'labor_cost' => 0],
    ['sku' => 'SKIRTING-POR', 'name' => 'Porcelain Skirting Board 10cm', 'name_ar' => 'وزرة بورسلين 10 سم', 'category' => 'Finishing & Tiling', 'unit' => 'lm', 'material_cost' => 18.00, 'labor_cost' => 0],

    // ---------------- Plumbing ----------------
    ['sku' => 'PVC-110', 'name' => 'PVC Drainage Pipe 110mm (3m length)', 'name_ar' => 'مواسير صرف PVC 110مم (طول 3م)', 'category' => 'Plumbing', 'unit' => 'each', 'material_cost' => 45.00, 'labor_cost' => 0],
    ['sku' => 'PVC-160', 'name' => 'PVC Drainage Pipe 160mm (3m length)', 'name_ar' => 'مواسير صرف PVC 160مم (طول 3م)', 'category' => 'Plumbing', 'unit' => 'each', 'material_cost' => 78.00, 'labor_cost' => 0],
    ['sku' => 'PPR-20', 'name' => 'PPR Cold Water Pipe 20mm (4m length)', 'name_ar' => 'مواسير مياه باردة PPR 20مم (طول 4م)', 'category' => 'Plumbing', 'unit' => 'each', 'material_cost' => 12.00, 'labor_cost' => 0],
    ['sku' => 'PPR-25', 'name' => 'PPR Cold Water Pipe 25mm (4m length)', 'name_ar' => 'مواسير مياه باردة PPR 25مم (طول 4م)', 'category' => 'Plumbing', 'unit' => 'each', 'material_cost' => 18.00, 'labor_cost' => 0],
    ['sku' => 'CPVC-15', 'name' => 'CPVC Hot Water Pipe 15mm (4m length)', 'name_ar' => 'مواسير مياه ساخنة CPVC 15مم (طول 4م)', 'category' => 'Plumbing', 'unit' => 'each', 'material_cost' => 22.00, 'labor_cost' => 0],
    ['sku' => 'GATEVALVE-1', 'name' => 'Brass Gate Valve 1 inch', 'name_ar' => 'محبس بوابة نحاس 1 بوصة', 'category' => 'Plumbing', 'unit' => 'each', 'material_cost' => 35.00, 'labor_cost' => 0],
    ['sku' => 'FLOORDRAIN-SS', 'name' => 'Stainless Steel Floor Drain 10x10cm', 'name_ar' => 'بالوعة أرضية ستانلس ستيل 10×10 سم', 'category' => 'Plumbing', 'unit' => 'each', 'material_cost' => 28.00, 'labor_cost' => 0],
    ['sku' => 'WATERHEATER-50', 'name' => 'Electric Water Heater 50L', 'name_ar' => 'سخان مياه كهربائي 50 لتر', 'category' => 'Plumbing', 'unit' => 'each', 'material_cost' => 650.00, 'labor_cost' => 80.00],
    ['sku' => 'SUBMPUMP-1HP', 'name' => 'Submersible Water Pump 1HP', 'name_ar' => 'مضخة مياه غاطسة 1 حصان', 'category' => 'Plumbing', 'unit' => 'each', 'material_cost' => 850.00, 'labor_cost' => 150.00],
    ['sku' => 'PIPEFIT-KIT', 'name' => 'PVC Pipe Fittings Assortment Kit', 'name_ar' => 'طقم وصلات مواسير PVC متنوعة', 'category' => 'Plumbing', 'unit' => 'box', 'material_cost' => 65.00, 'labor_cost' => 0],

    // ---------------- Electrical ----------------
    ['sku' => 'CABLE-25', 'name' => 'Copper Cable 2.5mm² (100m roll)', 'name_ar' => 'كابل نحاس 2.5مم² (لفة 100م)', 'category' => 'Electrical', 'unit' => 'roll', 'material_cost' => 320.00, 'labor_cost' => 0],
    ['sku' => 'CABLE-40', 'name' => 'Copper Cable 4mm² (100m roll)', 'name_ar' => 'كابل نحاس 4مم² (لفة 100م)', 'category' => 'Electrical', 'unit' => 'roll', 'material_cost' => 480.00, 'labor_cost' => 0],
    ['sku' => 'CABLE-60', 'name' => 'Copper Cable 6mm² (100m roll)', 'name_ar' => 'كابل نحاس 6مم² (لفة 100م)', 'category' => 'Electrical', 'unit' => 'roll', 'material_cost' => 690.00, 'labor_cost' => 0],
    ['sku' => 'CONDUIT-20', 'name' => 'PVC Conduit Pipe 20mm (3m length)', 'name_ar' => 'مواسير كهرباء PVC 20مم (طول 3م)', 'category' => 'Electrical', 'unit' => 'each', 'material_cost' => 6.50, 'labor_cost' => 0],
    ['sku' => 'MCB-20A', 'name' => 'Miniature Circuit Breaker 20A', 'name_ar' => 'قاطع دائرة مصغر 20 أمبير', 'category' => 'Electrical', 'unit' => 'each', 'material_cost' => 22.00, 'labor_cost' => 0],
    ['sku' => 'DB-12WAY', 'name' => 'Distribution Board 12-Way', 'name_ar' => 'لوحة توزيع كهربائية 12 خط', 'category' => 'Electrical', 'unit' => 'each', 'material_cost' => 320.00, 'labor_cost' => 0],
    ['sku' => 'SOCKET-STD', 'name' => 'Wall Socket Outlet, Switched', 'name_ar' => 'مقبس كهرباء حائطي مع مفتاح', 'category' => 'Electrical', 'unit' => 'each', 'material_cost' => 18.00, 'labor_cost' => 0],
    ['sku' => 'SWITCH-1G', 'name' => 'Single Gang Light Switch', 'name_ar' => 'مفتاح إضاءة أحادي', 'category' => 'Electrical', 'unit' => 'each', 'material_cost' => 12.00, 'labor_cost' => 0],
    ['sku' => 'LED-DOWNLIGHT', 'name' => 'LED Downlight 12W', 'name_ar' => 'كشاف إضاءة LED مدمج 12 واط', 'category' => 'Electrical', 'unit' => 'each', 'material_cost' => 25.00, 'labor_cost' => 0],
    ['sku' => 'LED-PANEL60', 'name' => 'LED Panel Light 60x60cm', 'name_ar' => 'لوح إضاءة LED 60×60 سم', 'category' => 'Electrical', 'unit' => 'each', 'material_cost' => 95.00, 'labor_cost' => 0],

    // ---------------- HVAC ----------------
    ['sku' => 'AC-SPLIT18', 'name' => 'Split AC Unit 18,000 BTU', 'name_ar' => 'مكيف سبليت 18000 وحدة حرارية', 'category' => 'HVAC', 'unit' => 'each', 'material_cost' => 1650.00, 'labor_cost' => 250.00],
    ['sku' => 'AC-SPLIT24', 'name' => 'Split AC Unit 24,000 BTU', 'name_ar' => 'مكيف سبليت 24000 وحدة حرارية', 'category' => 'HVAC', 'unit' => 'each', 'material_cost' => 2100.00, 'labor_cost' => 300.00],
    ['sku' => 'AC-DUCTED5T', 'name' => 'Ducted Split AC Unit 5 Ton', 'name_ar' => 'مكيف دكت سبليت 5 طن', 'category' => 'HVAC', 'unit' => 'each', 'material_cost' => 9500.00, 'labor_cost' => 1200.00],
    ['sku' => 'DUCT-GI', 'name' => 'Galvanized Iron Ductwork', 'name_ar' => 'دكت صاج مجلفن', 'category' => 'HVAC', 'unit' => 'sqm', 'material_cost' => 145.00, 'labor_cost' => 0],
    ['sku' => 'DUCT-INSUL', 'name' => 'Duct Thermal Insulation Wrap (roll)', 'name_ar' => 'عزل حراري للدكت (لفة)', 'category' => 'HVAC', 'unit' => 'roll', 'material_cost' => 220.00, 'labor_cost' => 0],
    ['sku' => 'DIFFUSER-AC', 'name' => 'AC Ceiling Diffuser 600x600mm', 'name_ar' => 'فتحة تكييف سقفية 600×600مم', 'category' => 'HVAC', 'unit' => 'each', 'material_cost' => 85.00, 'labor_cost' => 0],
    ['sku' => 'REFPIPE-INSUL', 'name' => 'Insulated Refrigerant Copper Pipe Set (3m)', 'name_ar' => 'مواسير تبريد نحاس معزولة (طقم 3م)', 'category' => 'HVAC', 'unit' => 'each', 'material_cost' => 180.00, 'labor_cost' => 0],
    ['sku' => 'THERMOSTAT-DIG', 'name' => 'Digital AC Thermostat', 'name_ar' => 'ثرموستات تكييف رقمي', 'category' => 'HVAC', 'unit' => 'each', 'material_cost' => 220.00, 'labor_cost' => 0],

    // ---------------- Paint & Coatings ----------------
    ['sku' => 'PAINT-EMLINT', 'name' => 'Interior Emulsion Paint (18L gallon)', 'name_ar' => 'دهان إيمولشن داخلي (جالون 18 لتر)', 'category' => 'Paint & Coatings', 'unit' => 'gallon', 'material_cost' => 165.00, 'labor_cost' => 0],
    ['sku' => 'PAINT-EMLEXT', 'name' => 'Exterior Weather Shield Paint (18L gallon)', 'name_ar' => 'دهان خارجي مقاوم للعوامل الجوية (جالون 18 لتر)', 'category' => 'Paint & Coatings', 'unit' => 'gallon', 'material_cost' => 210.00, 'labor_cost' => 0],
    ['sku' => 'PAINT-PRIMER', 'name' => 'Wall Primer Sealer (18L gallon)', 'name_ar' => 'برايمر عازل للجدران (جالون 18 لتر)', 'category' => 'Paint & Coatings', 'unit' => 'gallon', 'material_cost' => 130.00, 'labor_cost' => 0],
    ['sku' => 'PAINT-EPOXYFL', 'name' => 'Epoxy Floor Coating Kit (20kg)', 'name_ar' => 'دهان أرضيات إيبوكسي (طقم 20 كجم)', 'category' => 'Paint & Coatings', 'unit' => 'drum', 'material_cost' => 420.00, 'labor_cost' => 0],
    ['sku' => 'PUTTY-WALL', 'name' => 'Wall Putty Filler (40kg bag)', 'name_ar' => 'معجون حائط (كيس 40 كجم)', 'category' => 'Paint & Coatings', 'unit' => 'bag', 'material_cost' => 45.00, 'labor_cost' => 0],
    ['sku' => 'PAINT-ENAMEL', 'name' => 'Enamel Gloss Paint (4L can)', 'name_ar' => 'دهان إينامل لامع (علبة 4 لتر)', 'category' => 'Paint & Coatings', 'unit' => 'gallon', 'material_cost' => 85.00, 'labor_cost' => 0],
    ['sku' => 'THINNER-PAINT', 'name' => 'Paint Thinner (4L can)', 'name_ar' => 'مخفف دهان (علبة 4 لتر)', 'category' => 'Paint & Coatings', 'unit' => 'gallon', 'material_cost' => 35.00, 'labor_cost' => 0],
    ['sku' => 'TEXTURECOAT', 'name' => 'Decorative Texture Coating (25kg bag)', 'name_ar' => 'دهان تكستشر ديكوري (كيس 25 كجم)', 'category' => 'Paint & Coatings', 'unit' => 'bag', 'material_cost' => 95.00, 'labor_cost' => 0],

    // ---------------- Doors & Windows ----------------
    ['sku' => 'DOOR-FLUSHINT', 'name' => 'Interior Flush Door, Solid Core (90x210cm)', 'name_ar' => 'باب داخلي مصمت (90×210 سم)', 'category' => 'Doors & Windows', 'unit' => 'each', 'material_cost' => 450.00, 'labor_cost' => 120.00],
    ['sku' => 'DOOR-WOODEXT', 'name' => 'Exterior Solid Wood Door (100x210cm)', 'name_ar' => 'باب خارجي خشب مصمت (100×210 سم)', 'category' => 'Doors & Windows', 'unit' => 'each', 'material_cost' => 1250.00, 'labor_cost' => 150.00],
    ['sku' => 'DOOR-FIRERATE', 'name' => 'Fire-Rated Steel Door, 90-Minute', 'name_ar' => 'باب حديد مقاوم للحريق 90 دقيقة', 'category' => 'Doors & Windows', 'unit' => 'each', 'material_cost' => 1800.00, 'labor_cost' => 180.00],
    ['sku' => 'DOOR-GARAGE', 'name' => 'Sectional Garage Door, up to 3x2.5m', 'name_ar' => 'باب جراج قطاعات (حتى 3×2.5م)', 'category' => 'Doors & Windows', 'unit' => 'each', 'material_cost' => 3200.00, 'labor_cost' => 400.00],
    ['sku' => 'WIN-ALUSLD', 'name' => 'Aluminum Sliding Window (1.5x1.2m)', 'name_ar' => 'نافذة ألمنيوم سحاب (1.5×1.2م)', 'category' => 'Doors & Windows', 'unit' => 'each', 'material_cost' => 650.00, 'labor_cost' => 100.00],
    ['sku' => 'WIN-UPVCDG', 'name' => 'uPVC Double-Glazed Window (1.5x1.2m)', 'name_ar' => 'نافذة يو بي في سي زجاج مزدوج (1.5×1.2م)', 'category' => 'Doors & Windows', 'unit' => 'each', 'material_cost' => 950.00, 'labor_cost' => 150.00],
    ['sku' => 'DOORFRAME-STL', 'name' => 'Steel Door Frame', 'name_ar' => 'برواز باب حديد', 'category' => 'Doors & Windows', 'unit' => 'each', 'material_cost' => 220.00, 'labor_cost' => 0],
    ['sku' => 'DOORHANDLE-LOCK', 'name' => 'Door Handle and Lock Set', 'name_ar' => 'طقم يد ومقبض باب مع قفل', 'category' => 'Doors & Windows', 'unit' => 'each', 'material_cost' => 85.00, 'labor_cost' => 0],

    // ---------------- Waterproofing & Insulation ----------------
    ['sku' => 'WPMEMB-BIT', 'name' => 'Bituminous Waterproofing Membrane (10sqm roll)', 'name_ar' => 'غشاء عزل مائي بيتوميني (لفة 10 م2)', 'category' => 'Waterproofing & Insulation', 'unit' => 'roll', 'material_cost' => 320.00, 'labor_cost' => 0],
    ['sku' => 'WPCOAT-CEM', 'name' => 'Cementitious Waterproofing Coating (25kg bag)', 'name_ar' => 'دهان عزل مائي أسمنتي (كيس 25 كجم)', 'category' => 'Waterproofing & Insulation', 'unit' => 'bag', 'material_cost' => 85.00, 'labor_cost' => 0],
    ['sku' => 'WPLIQUID-PU', 'name' => 'Liquid-Applied Polyurethane Waterproofing (20kg drum)', 'name_ar' => 'عزل مائي بولي يوريثين سائل (برميل 20 كجم)', 'category' => 'Waterproofing & Insulation', 'unit' => 'drum', 'material_cost' => 480.00, 'labor_cost' => 0],
    ['sku' => 'INSUL-XPS5', 'name' => 'XPS Insulation Board 5cm (sheet)', 'name_ar' => 'لوح عزل حراري XPS سمك 5 سم (لوح)', 'category' => 'Waterproofing & Insulation', 'unit' => 'sheet', 'material_cost' => 55.00, 'labor_cost' => 0],
    ['sku' => 'INSUL-ROCKWL', 'name' => 'Rockwool Insulation Board 5cm', 'name_ar' => 'لوح صوف صخري عزل حراري 5 سم', 'category' => 'Waterproofing & Insulation', 'unit' => 'sqm', 'material_cost' => 35.00, 'labor_cost' => 0],
    ['sku' => 'SEALANT-SILI', 'name' => 'Silicone Sealant Cartridge (300ml)', 'name_ar' => 'مادة سيليكون عازلة (خرطوشة 300 مل)', 'category' => 'Waterproofing & Insulation', 'unit' => 'each', 'material_cost' => 15.00, 'labor_cost' => 0],
    ['sku' => 'VAPORBARRIER', 'name' => 'Polyethylene Vapor Barrier Sheet (roll)', 'name_ar' => 'غشاء حاجز بخار بولي إيثيلين (لفة)', 'category' => 'Waterproofing & Insulation', 'unit' => 'roll', 'material_cost' => 110.00, 'labor_cost' => 0],
    ['sku' => 'EXPJOINT-FILL', 'name' => 'Expansion Joint Filler Strip', 'name_ar' => 'شريط حشو فواصل التمدد', 'category' => 'Waterproofing & Insulation', 'unit' => 'lm', 'material_cost' => 12.00, 'labor_cost' => 0],

    // ---------------- Aggregates ----------------
    ['sku' => 'SAND-FINE', 'name' => 'Fine Washed Sand (m3)', 'name_ar' => 'رمل ناعم مغسول (م3)', 'category' => 'Aggregates', 'unit' => 'm3', 'material_cost' => 45.00, 'labor_cost' => 0],
    ['sku' => 'SAND-COARSE', 'name' => 'Coarse Plastering Sand (m3)', 'name_ar' => 'رمل خشن للتلييس (م3)', 'category' => 'Aggregates', 'unit' => 'm3', 'material_cost' => 50.00, 'labor_cost' => 0],
    ['sku' => 'AGG-CRUSH20', 'name' => 'Crushed Aggregate 20mm (m3)', 'name_ar' => 'ركام مكسر 20مم (م3)', 'category' => 'Aggregates', 'unit' => 'm3', 'material_cost' => 55.00, 'labor_cost' => 0],
    ['sku' => 'AGG-CRUSH10', 'name' => 'Crushed Aggregate 10mm (m3)', 'name_ar' => 'ركام مكسر 10مم (م3)', 'category' => 'Aggregates', 'unit' => 'm3', 'material_cost' => 58.00, 'labor_cost' => 0],
    ['sku' => 'BASECOURSE', 'name' => 'Road Base Course Material (m3)', 'name_ar' => 'مواد طبقة الأساس للطرق (م3)', 'category' => 'Aggregates', 'unit' => 'm3', 'material_cost' => 40.00, 'labor_cost' => 0],
    ['sku' => 'GRAVEL-PEA', 'name' => 'Decorative Pea Gravel (m3)', 'name_ar' => 'حصى زينة (م3)', 'category' => 'Aggregates', 'unit' => 'm3', 'material_cost' => 65.00, 'labor_cost' => 0],

    // ---------------- Sanitaryware ----------------
    ['sku' => 'WC-WALLHUNG', 'name' => 'Wall-Hung Toilet Suite with Concealed Cistern', 'name_ar' => 'طقم مرحاض معلق مع صندوق طرد مخفي', 'category' => 'Sanitaryware', 'unit' => 'each', 'material_cost' => 950.00, 'labor_cost' => 150.00],
    ['sku' => 'WC-FLOORMTD', 'name' => 'Floor-Mounted Toilet Suite', 'name_ar' => 'طقم مرحاض أرضي', 'category' => 'Sanitaryware', 'unit' => 'each', 'material_cost' => 650.00, 'labor_cost' => 100.00],
    ['sku' => 'BASIN-COUNTER', 'name' => 'Countertop Wash Basin', 'name_ar' => 'حوض مغسلة سطح كاونتر', 'category' => 'Sanitaryware', 'unit' => 'each', 'material_cost' => 320.00, 'labor_cost' => 0],
    ['sku' => 'BASIN-PEDESTAL', 'name' => 'Pedestal Wash Basin', 'name_ar' => 'حوض مغسلة قائم', 'category' => 'Sanitaryware', 'unit' => 'each', 'material_cost' => 280.00, 'labor_cost' => 0],
    ['sku' => 'MIXER-BASIN', 'name' => 'Basin Mixer Tap, Chrome', 'name_ar' => 'خلاط مغسلة كروم', 'category' => 'Sanitaryware', 'unit' => 'each', 'material_cost' => 180.00, 'labor_cost' => 0],
    ['sku' => 'MIXER-SHOWER', 'name' => 'Shower Mixer Set with Rain Head', 'name_ar' => 'طقم خلاط دش مع رأس ممطر', 'category' => 'Sanitaryware', 'unit' => 'each', 'material_cost' => 420.00, 'labor_cost' => 80.00],
    ['sku' => 'BIDETSPRAY', 'name' => 'Bidet Spray Set (Muslim Shower)', 'name_ar' => 'طقم رشاش صحي (دش عربي)', 'category' => 'Sanitaryware', 'unit' => 'each', 'material_cost' => 65.00, 'labor_cost' => 0],
    ['sku' => 'KITCHENSINK-SS', 'name' => 'Stainless Steel Kitchen Sink, Double Bowl', 'name_ar' => 'حوض مطبخ ستانلس ستيل بحوضين', 'category' => 'Sanitaryware', 'unit' => 'each', 'material_cost' => 380.00, 'labor_cost' => 0],

    // ---------------- Glass & Aluminum ----------------
    ['sku' => 'GLASS-CLEAR6', 'name' => 'Clear Float Glass 6mm', 'name_ar' => 'زجاج فلوت شفاف 6مم', 'category' => 'Glass & Aluminum', 'unit' => 'sqm', 'material_cost' => 65.00, 'labor_cost' => 0],
    ['sku' => 'GLASS-TEMP10', 'name' => 'Tempered Glass 10mm', 'name_ar' => 'زجاج مقسى 10مم', 'category' => 'Glass & Aluminum', 'unit' => 'sqm', 'material_cost' => 145.00, 'labor_cost' => 0],
    ['sku' => 'GLASS-DBLPANE', 'name' => 'Double-Glazed Unit (DGU)', 'name_ar' => 'وحدة زجاج مزدوج', 'category' => 'Glass & Aluminum', 'unit' => 'sqm', 'material_cost' => 220.00, 'labor_cost' => 0],
    ['sku' => 'ALUPROFILE-WIN', 'name' => 'Aluminum Window Profile', 'name_ar' => 'بروفايل ألمنيوم للنوافذ', 'category' => 'Glass & Aluminum', 'unit' => 'lm', 'material_cost' => 55.00, 'labor_cost' => 0],
    ['sku' => 'ALUPROFILE-CURT', 'name' => 'Aluminum Curtain Wall Profile', 'name_ar' => 'بروفايل ألمنيوم للواجهات الزجاجية', 'category' => 'Glass & Aluminum', 'unit' => 'lm', 'material_cost' => 145.00, 'labor_cost' => 0],
    ['sku' => 'ALUCOMPOSITE', 'name' => 'Aluminum Composite Panel (ACP) Cladding', 'name_ar' => 'ألواح كسوة ألمنيوم مركب', 'category' => 'Glass & Aluminum', 'unit' => 'sqm', 'material_cost' => 165.00, 'labor_cost' => 45.00],
    ['sku' => 'GLASSRAILING', 'name' => 'Frameless Glass Railing System', 'name_ar' => 'نظام درابزين زجاجي بدون إطار', 'category' => 'Glass & Aluminum', 'unit' => 'lm', 'material_cost' => 380.00, 'labor_cost' => 60.00],
    ['sku' => 'ALUDOORFRAME', 'name' => 'Aluminum Door Frame Section', 'name_ar' => 'قطاع برواز باب ألمنيوم', 'category' => 'Glass & Aluminum', 'unit' => 'lm', 'material_cost' => 65.00, 'labor_cost' => 0],
];
