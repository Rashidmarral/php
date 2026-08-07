<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\EstimateTemplate;
use App\Models\EstimateTemplateItem;

class EstimateTemplateAdminController extends Controller
{
    public function index(): void
    {
        $templates = EstimateTemplate::query(
            'SELECT t.*, (SELECT COUNT(*) FROM estimate_template_items i WHERE i.template_id = t.id) AS item_count
             FROM estimate_templates t ORDER BY t.sort_order ASC, t.id ASC'
        )->fetchAll();

        $this->view('admin/estimate-templates/index', [
            'pageTitle' => 'Estimate Template Library',
            'templates' => $templates,
        ], 'layouts/admin');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        EstimateTemplate::create([
            'name_en' => trim((string) $this->input('name_en')),
            'name_ar' => trim((string) $this->input('name_ar')),
            'description_en' => $this->input('description_en', ''),
            'description_ar' => $this->input('description_ar', ''),
            'building_type' => trim((string) $this->input('building_type', '')),
            'icon' => $this->input('icon', '') ?: '🏗️',
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => 1,
        ]);
        $this->flash('success', 'Template created. Add line items to it below.');
        self::redirect('/admin/estimate-templates');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        EstimateTemplate::update((int) $id, [
            'name_en' => trim((string) $this->input('name_en')),
            'name_ar' => trim((string) $this->input('name_ar')),
            'description_en' => $this->input('description_en', ''),
            'description_ar' => $this->input('description_ar', ''),
            'building_type' => trim((string) $this->input('building_type', '')),
            'icon' => $this->input('icon', '') ?: '🏗️',
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => $this->input('is_active') ? 1 : 0,
        ]);
        $this->flash('success', 'Template updated.');
        self::redirect('/admin/estimate-templates');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        EstimateTemplateItem::query('DELETE FROM estimate_template_items WHERE template_id = ?', [(int) $id]);
        EstimateTemplate::delete((int) $id);
        $this->flash('success', 'Template deleted.');
        self::redirect('/admin/estimate-templates');
    }

    /** Only one template can be the default pre-selected choice on the "New Estimate" screen. */
    public function setDefault(string $id): void
    {
        $this->verifyCsrf();
        EstimateTemplate::query('UPDATE estimate_templates SET is_default_choice = 0');
        EstimateTemplate::update((int) $id, ['is_default_choice' => 1]);
        $this->flash('success', 'Default template updated.');
        self::redirect('/admin/estimate-templates');
    }

    public function items(string $id): void
    {
        $template = EstimateTemplate::find((int) $id);
        if (!$template) {
            http_response_code(404);
            die('Template not found.');
        }
        $this->view('admin/estimate-templates/items', [
            'pageTitle' => $template['name_en'] . ' — Line Items',
            'template' => $template,
            'items' => EstimateTemplateItem::forTemplate($template['id']),
            'subtotal' => array_sum(array_map(
                fn($i) => (float) $i['default_qty'] * (float) $i['unit_cost'],
                EstimateTemplateItem::forTemplate($template['id'])
            )),
        ], 'layouts/admin');
    }

    public function storeItem(string $id): void
    {
        $this->verifyCsrf();
        $template = EstimateTemplate::find((int) $id);
        if (!$template) {
            http_response_code(404);
            die('Template not found.');
        }
        EstimateTemplateItem::create([
            'template_id' => $template['id'],
            'section_number' => $this->input('section_number', '1.0') ?: '1.0',
            'section_title_en' => trim((string) $this->input('section_title_en')),
            'section_title_ar' => trim((string) $this->input('section_title_ar')),
            'item_number' => $this->input('item_number', '1.1') ?: '1.1',
            'description_en' => trim((string) $this->input('description_en')),
            'description_ar' => trim((string) $this->input('description_ar')),
            'item_type' => in_array($this->input('item_type'), ['material', 'labor', 'equipment', 'subcontract'], true) ? $this->input('item_type') : 'material',
            'default_qty' => (float) $this->input('default_qty', 0),
            'uom' => $this->input('uom', 'each') ?: 'each',
            'unit_cost' => (float) $this->input('unit_cost', 0),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
        $this->flash('success', 'Line item added.');
        self::redirect('/admin/estimate-templates/' . $template['id'] . '/items');
    }

    public function updateItem(string $id, string $itemId): void
    {
        $this->verifyCsrf();
        $item = EstimateTemplateItem::find((int) $itemId);
        if (!$item || (int) $item['template_id'] !== (int) $id) {
            http_response_code(404);
            die('Line item not found.');
        }
        EstimateTemplateItem::update((int) $itemId, [
            'section_number' => $this->input('section_number', '1.0') ?: '1.0',
            'section_title_en' => trim((string) $this->input('section_title_en')),
            'section_title_ar' => trim((string) $this->input('section_title_ar')),
            'item_number' => $this->input('item_number', '1.1') ?: '1.1',
            'description_en' => trim((string) $this->input('description_en')),
            'description_ar' => trim((string) $this->input('description_ar')),
            'item_type' => in_array($this->input('item_type'), ['material', 'labor', 'equipment', 'subcontract'], true) ? $this->input('item_type') : 'material',
            'default_qty' => (float) $this->input('default_qty', 0),
            'uom' => $this->input('uom', 'each') ?: 'each',
            'unit_cost' => (float) $this->input('unit_cost', 0),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
        $this->flash('success', 'Line item updated.');
        self::redirect('/admin/estimate-templates/' . $id . '/items');
    }

    public function destroyItem(string $id, string $itemId): void
    {
        $this->verifyCsrf();
        $item = EstimateTemplateItem::find((int) $itemId);
        if ($item && (int) $item['template_id'] === (int) $id) {
            EstimateTemplateItem::delete((int) $itemId);
            $this->flash('success', 'Line item removed.');
        }
        self::redirect('/admin/estimate-templates/' . $id . '/items');
    }
}
