<?php

namespace App\Exports;

use App\Models\ProductsModel;
use App\Models\ClosingStockModel;
use App\Models\PurchaseOrderModel;
use App\Models\SalesOrderModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class DashboardStockExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    protected array $params;
    protected int $companyId;
    protected array $rows = [];

    protected array $levelHex = [
        'critical'   => 'FFFFCDD2',
        'sufficient' => 'FFB3E5FC',
        'excessive'  => 'FFC8E6C9',
    ];

    // A..O (15 columns now)
    private string $lastCol = 'O';

    private array $headings = [
        'S.No',
        'Name',
        'Alias',
        'Group',
        'Category',
        'Sub Category',
        'Unit',
        'SI1',
        'SI2',
        'Product Total Qty',
        'Alias Total Qty',
        'Stock Level',
        'Pending PO',
        'Pending SO',
        'Stock Value',
    ];

    public function __construct(array $params)
    {
        $this->params    = $params;
        $this->companyId = Auth::user()->company_id;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function collection(): Collection
    {
        // ---- Inputs (ignore pagination) ----
        $filterGroup    = $this->params['group']        ?? null;
        $filterCategory = $this->params['category']     ?? null;
        $filterSubCat   = $this->params['sub_category'] ?? null;
        $filterAlias    = $this->params['alias']        ?? null;
        $search         = $this->params['search']       ?? null;
        $sortBy         = $this->params['sort_by']      ?? 'name';
        $sortOrder      = strtolower($this->params['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $stockLevelReq  = $this->params['stock_level']  ?? null;

        $validLevels = ['critical','sufficient','excessive'];
        $stockLevel  = in_array(strtolower((string) $stockLevelReq), $validLevels, true)
            ? strtolower($stockLevelReq)
            : null;

        // ---- Base query ----
        $baseQuery = ProductsModel::with([
                'groupRelation:id,name',
                'categoryRelation:id,name',
                'subCategoryRelation:id,name'
            ])
            ->where('company_id', $this->companyId);

        if (!empty($search)) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('alias', 'like', "%{$search}%");
            });
        }
        if (!empty($filterGroup)) {
            $groupIds = array_filter(explode(',', $filterGroup));
            $baseQuery->whereIn('group', $groupIds);
        }
        if (!empty($filterCategory)) {
            $catIds = array_filter(explode(',', $filterCategory));
            $baseQuery->whereIn('category', $catIds);
        }
        if (!empty($filterSubCat)) {
            $subCatIds = array_filter(explode(',', $filterSubCat));
            $baseQuery->whereIn('sub_category', $subCatIds);
        }
        if (!empty($filterAlias)) {
            $aliasValues = array_map('trim', array_filter(explode(',', $filterAlias)));
            $baseQuery->whereIn('alias', $aliasValues);
        }

        $sortable = ['name','group','category','sub_category','alias'];
        $sortCol  = in_array($sortBy, $sortable, true) ? $sortBy : 'name';

        // Build alias list for filtered set
        $aliasList = (clone $baseQuery)->distinct()->pluck('alias');

        // Total qty per PRODUCT
        $filteredIdsSub = (clone $baseQuery)->select('id');
        $totalQtyByProduct = ClosingStockModel::where('company_id', $this->companyId)
            ->whereIn('product_id', $filteredIdsSub)
            ->select('product_id', DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('product_id')
            ->pluck('total_qty', 'product_id');

        // Total qty per ALIAS
        $aliasQty = DB::table((new ClosingStockModel)->getTable().' as cs')
            ->join((new ProductsModel)->getTable().' as p', 'p.id', '=', 'cs.product_id')
            ->where('p.company_id', $this->companyId)
            ->when(!empty($aliasList), fn($q) => $q->whereIn('p.alias', $aliasList))
            ->select('p.alias', DB::raw('SUM(cs.quantity) as total_qty'))
            ->groupBy('p.alias')
            ->pluck('total_qty', 'alias');

        // Effective SI by alias (pb_alias preferred; else max)
        $pbSi = ProductsModel::where('company_id', $this->companyId)
            ->when(!empty($aliasList), fn($q) => $q->whereIn('alias', $aliasList))
            ->where('pb_alias', 1)
            ->select('alias','si1','si2')
            ->get()
            ->keyBy('alias');

        $maxSi = ProductsModel::where('company_id', $this->companyId)
            ->when(!empty($aliasList), fn($q) => $q->whereIn('alias', $aliasList))
            ->select('alias', DB::raw('MAX(si1) as max_si1'), DB::raw('MAX(si2) as max_si2'))
            ->groupBy('alias')
            ->get()
            ->keyBy('alias');

        $effectiveSiByAlias = [];
        foreach ($aliasList as $alias) {
            if (isset($pbSi[$alias])) {
                $effectiveSiByAlias[$alias] = [
                    'si1' => (float) $pbSi[$alias]->si1,
                    'si2' => (float) $pbSi[$alias]->si2,
                ];
            } elseif (isset($maxSi[$alias])) {
                $effectiveSiByAlias[$alias] = [
                    'si1' => (float) $maxSi[$alias]->max_si1,
                    'si2' => (float) $maxSi[$alias]->max_si2,
                ];
            } else {
                $effectiveSiByAlias[$alias] = ['si1' => 0.0, 'si2' => 0.0];
            }
        }

        // Alias-level classification
        $levelByAlias = [];
        foreach ($aliasList as $alias) {
            $qty  = (float) ($aliasQty[$alias] ?? 0);
            $esi1 = (float) ($effectiveSiByAlias[$alias]['si1'] ?? 0);
            $esi2 = (float) ($effectiveSiByAlias[$alias]['si2'] ?? 0);

            if ($qty < $esi1) {
                $levelByAlias[$alias] = 'critical';
            } elseif ($qty <= $esi2) {
                $levelByAlias[$alias] = 'sufficient';
            } else {
                $levelByAlias[$alias] = 'excessive';
            }
        }

        if (!empty($stockLevel)) {
            $aliasesMatching = array_keys(array_filter($levelByAlias, fn ($lvl) => $lvl === $stockLevel));
            $baseQuery->whereIn('alias', !empty($aliasesMatching) ? $aliasesMatching : ['__none__']);
        }

        // Pending PO/SO counts
        $pendingPurchase = PurchaseOrderModel::where('company_id', $this->companyId)
            ->where('status', 'pending')
            ->with('products:id')
            ->get()
            ->flatMap(fn ($order) => $order->products->pluck('id'))
            ->countBy();

        $pendingSales = SalesOrderModel::where('company_id', $this->companyId)
            ->where('status', 'pending')
            ->with('products:id')
            ->get()
            ->flatMap(fn ($order) => $order->products->pluck('id'))
            ->countBy();

        // Stock value per product (ALL)
        $productsForValue = (clone $baseQuery)->pluck('id');
        $stockValueByProduct = ClosingStockModel::where('company_id', $this->companyId)
            ->whereIn('product_id', $productsForValue)
            ->select('product_id', DB::raw('SUM(value) as total_value'))
            ->groupBy('product_id')
            ->pluck('total_value', 'product_id');

        // ---- Fetch all filtered products ----
        $products = (clone $baseQuery)
            ->select('id','name','alias','group','category','sub_category','unit','si1','si2')
            ->orderBy($sortCol, $sortOrder)
            ->get();

        // ---- Build rows ----
        $i = 0;
        foreach ($products as $p) {
            $i++;
            $alias    = $p->alias;
            $prodQty  = (float) ($totalQtyByProduct[$p->id] ?? 0.0);
            $aliasQtyTotal = (float) ($aliasQty[$alias] ?? 0.0);
            $level    = $levelByAlias[$alias] ?? 'sufficient';
            $stockVal = (float) ($stockValueByProduct[$p->id] ?? 0.0);

            $this->rows[] = [
                'sno'             => $i,
                'name'            => $p->name,
                'alias'           => $alias,
                'group'           => optional($p->groupRelation)->name,
                'category'        => optional($p->categoryRelation)->name,
                'sub_category'    => optional($p->subCategoryRelation)->name,
                'unit'            => $p->unit,
                'si1'             => (float) ($p->si1 ?? 0),
                'si2'             => (float) ($p->si2 ?? 0),
                'product_total'   => $prodQty,
                'alias_total'     => $aliasQtyTotal,
                'stock_level'     => $level,
                'pending_po'      => (int) ($pendingPurchase[$p->id] ?? 0),
                'pending_so'      => (int) ($pendingSales[$p->id] ?? 0),
                'stock_value'     => $stockVal,
            ];
        }

        return collect($this->rows);
    }

    public function map($row): array
    {
        return [
            $row['sno'],
            $row['name'],
            $row['alias'],
            $row['group'],
            $row['category'],
            $row['sub_category'],
            $row['unit'],
            $row['si1'],
            $row['si2'],
            $row['product_total'],
            $row['alias_total'],
            ucfirst($row['stock_level']),
            $row['pending_po'],
            $row['pending_so'],
            $row['stock_value'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $count = count($this->rows);
                $headerRow = 3;
                $dataStart = $headerRow + 1;
                $dataEnd   = $headerRow + $count;

                // Title
                $sheet->insertNewRowBefore(1, 2);
                $sheet->mergeCells("A1:{$this->lastCol}1");
                $sheet->setCellValue('A1', 'Stock Dashboard Export');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Filters summary
                $filtersText = $this->buildFiltersSummary();
                $sheet->mergeCells("A2:{$this->lastCol}2");
                $sheet->setCellValue('A2', $filtersText);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);

                // Header
                $sheet->getStyle("A{$headerRow}:{$this->lastCol}{$headerRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$headerRow}:{$this->lastCol}{$headerRow}")
                      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                      ->setVertical(Alignment::VERTICAL_CENTER);

                // Freeze header
                $sheet->freezePane("A{$dataStart}");
                $sheet->setAutoFilter("A{$headerRow}:{$this->lastCol}{$headerRow}");

                if ($count > 0) {
                    // Fill colors
                    foreach ($this->rows as $idx => $r) {
                        $excelRow = $headerRow + 1 + $idx;
                        $lvl = strtolower($r['stock_level']);
                        $fillColor = $this->levelHex[$lvl] ?? null;
                        if ($fillColor) {
                            $sheet->getStyle("A{$excelRow}:{$this->lastCol}{$excelRow}")
                                ->getFill()->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB($fillColor);
                        }
                    }

                    // Borders
                    $sheet->getStyle("A{$headerRow}:{$this->lastCol}{$dataEnd}")
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);

                    // Alignment
                    $sheet->getStyle("A{$dataStart}:A{$dataEnd}")
                          ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$dataStart}:G{$dataEnd}")
                          ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("H{$dataStart}:J{$dataEnd}")
                          ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("K{$dataStart}:O{$dataEnd}")
                          ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("A{$dataStart}:{$this->lastCol}{$dataEnd}")
                          ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                    // Format stock value
                    for ($r = $dataStart; $r <= $dataEnd; $r++) {
                        $sheet->getStyle("O{$r}")
                              ->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }
            },
        ];
    }

    private function buildFiltersSummary(): string
    {
        $parts = [];
        $dateStr = now()->format('d M Y, h:i A');
        $parts[] = "Generated: {$dateStr}";

        foreach (['group','category','sub_category','alias','search','stock_level','sort_by','sort_order'] as $k) {
            if (isset($this->params[$k]) && $this->params[$k] !== '' && $this->params[$k] !== null) {
                $parts[] = ucfirst(str_replace('_',' ', $k)) . ': ' . $this->params[$k];
            }
        }

        return implode('  |  ', $parts);
    }
}