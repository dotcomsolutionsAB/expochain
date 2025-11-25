{{-- resources/views/pdf/sample_sales_invoice.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Invoice (Sample)</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', DejaVu Sans, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }

        .page-border {
            border: 1px solid #8b440c; /* same brown as FPDF */
            padding: 10px;
            min-height: 280mm;
            position: relative;
        }

        .bg-image {
            position: fixed;
            left: 0;
            top: 0;
            width: 210mm;
            height: 297mm;
            z-index: -1;
        }

        .bg-image img {
            width: 100%;
            height: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 8px;
            position: relative;
        }

        .header-logo {
            position: absolute;
            right: 0;
            top: 0;
        }

        .header-logo img {
            width: 46mm;
            height: auto;
        }

        .title {
            font-size: 12px;
            font-weight: 500;
        }

        .company-name {
            font-size: 15px;
            font-weight: 700;
            color: #8b440c;
        }

        .company-lines {
            font-size: 9px;
            line-height: 1.4;
        }

        .divider {
            border-bottom: 1px solid #000;
            margin-top: 4px;
            margin-bottom: 8px;
        }

        .row {
            display: flex;
            width: 100%;
        }
        .col-60 { width: 60%; padding-right: 4px; }
        .col-40 { width: 40%; padding-left: 4px; }

        .box {
            border: 1px solid #000;
            padding: 4px 6px;
            margin-bottom: 4px;
        }

        .box-header {
            font-weight: 600;
            margin-bottom: 3px;
        }

        .label {
            font-weight: 600;
        }

        .mt-2 { margin-top: 2px; }
        .mt-4 { margin-top: 4px; }
        .mt-6 { margin-top: 6px; }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 3px 2px;
            font-size: 7px;
        }

        .items-table thead tr:nth-child(2) th {
            font-weight: normal;
        }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-left   { text-align: left; }

        .no-border {
            border: none !important;
        }

        .totals-table {
            width: 100%;
            margin-top: 4px;
            font-size: 9px;
        }

        .totals-table td {
            padding: 3px 2px;
        }

        .hsn-table {
            margin-top: 6px;
            font-size: 7px;
            width: 100%;
        }

        .hsn-table th,
        .hsn-table td {
            border: 1px solid #000;
            padding: 2px;
        }

        .footer-image {
            position: absolute;
            left: 10px;
            bottom: 15px;
            width: calc(100% - 20px);
        }
        .footer-image img {
            width: 100%;
        }

        .page-number {
            position: absolute;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-style: italic;
            font-size: 8px;
        }
    </style>
</head>
<body>

@php
    /*
     |---------------------------------------------------------
     | SAMPLE DATA (for demo only)
     | In real use, you’ll pass these from your controller
     |---------------------------------------------------------
     */

    $invoice_no   = 'SI/1001/24-25';
    $invoice_date = '25-11-2025';
    $so_no        = 'SO/1234/24-25';

    $company = [
        'name'        => 'DOTCOM ELECTRICALS PVT. LTD.',
        'gstin'       => '19ABCDE1234F1Z5',
        'address1'    => '123, Industrial Area, Near Main Road',
        'address2'    => 'Phase 2',
        'city'        => 'Kolkata',
        'state'       => 'West Bengal',
        'pincode'     => '700001',
        'phone'       => '033-12345678',
        'mobile'      => '+91 9876543210',
        'email'       => 'info@dotcom.com',
        // 🔽 your storage paths as absolute filesystem paths for mPDF
        'bg_image'    => storage_path('app/public/uploads/pdf_template/pdf_bg.jpg'),
        'logo'        => storage_path('app/public/uploads/pdf_template/logo.png'),
        'footer_image'=> storage_path('app/public/uploads/pdf_template/footer.jpg'),
    ];

    $client = [
        'name'    => 'ABC ENGINEERS & CO.',
        'address1'=> '45, Business Park',
        'address2'=> '5th Floor',
        'city'    => 'Kolkata',
        'pincode' => '700020',
        'state'   => 'WEST BENGAL',
        'country' => 'India',
        'gstin'   => '19XYZAB1234C1Z2',
    ];

    // Intra-state (WEST BENGAL) => CGST + SGST like your PHP flag = 0
    $isIntraState = true;

    // Sample items
    $items = [
        [
            'sn'           => 1,
            'product_name' => 'Ceiling Fan 1200mm, Make: HANERI',
            'desc'         => 'Color: Matt White|Model: HN-1200-MW',
            'hsn'          => '84145110',
            'qty'          => 10,
            'unit'         => 'PCS',
            'price'        => 2500.00,
            'discount'     => 5,     // percent
            'tax_rate'     => 18,    // GST %
            'cgst'         => 2250.00,
            'sgst'         => 2250.00,
            'igst'         => 0,
            'line_total'   => 23750.00,
            'so_no'        => 'SO/1234/24-25',
        ],
        [
            'sn'           => 2,
            'product_name' => 'Wall Fan 400mm, Make: HANERI',
            'desc'         => 'Color: Black|Model: WF-400-BLK',
            'hsn'          => '84145120',
            'qty'          => 5,
            'unit'         => 'PCS',
            'price'        => 1800.00,
            'discount'     => 0,
            'tax_rate'     => 18,
            'cgst'         => 1620.00,
            'sgst'         => 1620.00,
            'igst'         => 0,
            'line_total'   => 9000.00,
            'so_no'        => 'SO/1234/24-25',
        ],
    ];

    $grossTotal    = 23750.00 + 9000.00; // example
    $grandTotalQty = 10 + 5;

    // Sample addons (same structure as your JSON)
    $addons = [
        'pf' => [
            'value' => 500.00,
            'cgst'  => 45.00,
            'sgst'  => 45.00,
            'igst'  => 0.00,
        ],
        'freight' => [
            'value' => 750.00,
            'cgst'  => 67.50,
            'sgst'  => 67.50,
            'igst'  => 0.00,
        ],
        'roundoff' => -2.00,
    ];

    // Sample HSN-wise details
    $taxDetails = [
        'rows' => [
            [
                'hsn'       => '84145110',
                'rate'      => 18,
                'taxable'   => 23750.00,
                'cgst'      => 2137.50,
                'sgst'      => 2137.50,
                'igst'      => 0.00,
                'total_tax' => 4275.00,
            ],
            [
                'hsn'       => '84145120',
                'rate'      => 18,
                'taxable'   => 9000.00,
                'cgst'      => 810.00,
                'sgst'      => 810.00,
                'igst'      => 0.00,
                'total_tax' => 1620.00,
            ],
            [
                'hsn'       => '99', // PF + Freight combined
                'rate'      => 18,
                'taxable'   => 1250.00,
                'cgst'      => 112.50,
                'sgst'      => 112.50,
                'igst'      => 0.00,
                'total_tax' => 225.00,
            ],
        ],
        'totals' => [
            'taxable' => 23750.00 + 9000.00 + 1250.00,
            'cgst'    => 2137.50 + 810.00 + 112.50,
            'sgst'    => 2137.50 + 810.00 + 112.50,
            'igst'    => 0.00,
            'total'   => 4275.00 + 1620.00 + 225.00,
        ],
    ];

    $pfValue      = $addons['pf']['value'];
    $freightValue = $addons['freight']['value'];
    $roundoff     = $addons['roundoff'];

    $cgstTotal = $taxDetails['totals']['cgst'];
    $sgstTotal = $taxDetails['totals']['sgst'];
    $igstTotal = $taxDetails['totals']['igst']; // 0 here

    if ($isIntraState) {
        $totalAmount = $grossTotal + $pfValue + $freightValue + $cgstTotal + $sgstTotal + $roundoff;
    } else {
        $totalAmount = $grossTotal + $pfValue + $freightValue + $igstTotal + $roundoff;
    }

    $amountInWords = 'Rupees Forty One Thousand Seven Hundred Forty Three Only'; // sample
@endphp

<div class="page-border">

    {{-- Background image --}}
    <div class="bg-image">
        <img src="{{ $company['bg_image'] }}" alt="">
    </div>

    {{-- Header --}}
    <div class="header">
        <div class="header-logo">
            <img src="{{ $company['logo'] }}" alt="Logo">
        </div>

        <div class="title">SALES INVOICE</div>
        <div class="company-name">{{ $company['name'] }}</div>
        <div class="company-lines">
            {{ $company['address1'] }} {{ $company['address2'] }}<br>
            {{ $company['city'] }} - {{ $company['pincode'] }}, {{ $company['state'] }}, India<br>
            GST : {{ $company['gstin'] }}<br>
            @php
                $temp = [];
                if ($company['phone'])  $temp[] = $company['phone'];
                if ($company['email'])  $temp[] = $company['email'];
                if ($company['mobile']) $temp[] = $company['mobile'];
            @endphp
            {{ implode(', ', $temp) }}
        </div>

        <div class="divider"></div>
    </div>

    {{-- Customer + Invoice meta --}}
    <div class="row">
        <div class="col-60">
            <div class="box">
                <div class="box-header">Customer Details :</div>
                <div>{{ $client['name'] }}</div>
                <div>{{ $client['address1'] }}</div>
                @if($client['address2'])
                    <div>{{ $client['address2'] }}</div>
                @endif
                <div>
                    {{ $client['city'] }} - {{ $client['pincode'] }},
                    {{ $client['state'] }}, {{ $client['country'] }}
                </div>
                <div>GSTIN / UIN : {{ $client['gstin'] }}</div>
            </div>
        </div>

        <div class="col-40">
            <div class="box">
                <table class="no-border" style="width:100%;">
                    <tr>
                        <td class="label">Invoice No.</td>
                        <td>: {{ $invoice_no }}</td>
                    </tr>
                    <tr>
                        <td class="label">Dated</td>
                        <td>: {{ $invoice_date }}</td>
                    </tr>
                    <tr>
                        <td class="label">Sales Order</td>
                        <td>: {{ $so_no }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Items Table --}}
    <table class="items-table mt-4">
        <thead>
        @if($isIntraState)
            <tr>
                <th width="4%">SN</th>
                <th width="44%">DESCRIPTION OF GOODS</th>
                <th width="7%">HSN</th>
                <th width="6%">QTY</th>
                <th width="6%">UNIT</th>
                <th width="10%">RATE</th>
                <th width="6%">DISC</th>
                <th width="6%">CGST</th>
                <th width="6%">SGST</th>
                <th width="11%">AMOUNT (Rs)</th>
            </tr>
            <tr>
                <th class="no-border"></th>
                <th class="no-border"></th>
                <th class="no-border"></th>
                <th class="no-border"></th>
                <th class="no-border"></th>
                <th class="no-border"></th>
                <th>%</th>
                <th>Rate</th>
                <th>Rate</th>
                <th class="no-border"></th>
            </tr>
        @else
            <tr>
                <th width="4%">SN</th>
                <th width="44%">DESCRIPTION OF GOODS</th>
                <th width="7%">HSN</th>
                <th width="6%">QTY</</th>
                <th width="6%">UNIT</th>
                <th width="10%">RATE</th>
                <th width="6%">DISC</th>
                <th width="10%">IGST</th>
                <th width="11%">AMOUNT (Rs)</th>
            </tr>
            <tr>
                <th class="no-border"></th>
                <th class="no-border"></th>
                <th class="no-border"></th>
                <th class="no-border"></th>
                <th class="no-border"></th>
                <th class="no-border"></th>
                <th>%</th>
                <th>Rate</th>
                <th class="no-border"></th>
            </tr>
        @endif
        </thead>
        <tbody>
        @foreach($items as $item)
            @php
                $descLines = [];
                $descLines[] = $item['product_name'];

                if (!empty($item['desc'])) {
                    $extra = explode('|', $item['desc']);
                    $descLines = array_merge($descLines, $extra);
                }
            @endphp

            {{-- main row --}}
            <tr>
                <td class="text-center">{{ $item['sn'] }}</td>
                <td class="text-left">{{ $descLines[0] ?? '' }}</td>
                <td class="text-center">{{ $item['hsn'] }}</td>
                <td class="text-center">{{ $item['qty'] }}</td>
                <td class="text-center">{{ $item['unit'] }}</td>
                <td class="text-right">
                    {{ number_format($item['price'], 2) }}
                </td>
                <td class="text-center">
                    @if($item['discount'])
                        {{ rtrim(rtrim(number_format($item['discount'], 2), '0'), '.') }} %
                    @endif
                </td>

                @if($isIntraState)
                    <td class="text-center">
                        {{ rtrim(rtrim(number_format($item['tax_rate']/2, 2), '0'), '.') }} %
                    </td>
                    <td class="text-center">
                        {{ rtrim(rtrim(number_format($item['tax_rate']/2, 2), '0'), '.') }} %
                    </td>
                @else
                    <td class="text-center">
                        {{ rtrim(rtrim(number_format($item['tax_rate'], 2), '0'), '.') }} %
                    </td>
                @endif

                <td class="text-right">
                    {{ number_format($item['line_total'], 2) }}
                </td>
            </tr>

            {{-- extra description lines --}}
            @if(count($descLines) > 1)
                @foreach(array_slice($descLines, 1) as $line)
                    <tr>
                        <td class="text-center"></td>
                        <td class="text-left"><em>{{ $line }}</em></td>
                        <td class="text-center"></td>
                        <td class="text-center"></td>
                        <td class="text-center"></td>
                        <td class="text-right"></td>
                        <td class="text-center"></td>
                        @if($isIntraState)
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                        @else
                            <td class="text-center"></td>
                        @endif
                        <td class="text-right"></td>
                    </tr>
                @endforeach
            @endif

            {{-- SO number line --}}
            @if(!empty($item['so_no']))
                <tr>
                    <td class="text-center"></td>
                    <td class="text-left"><em>SO : {{ $item['so_no'] }}</em></td>
                    <td class="text-center"></td>
                    <td class="text-center"></td>
                    <td class="text-center"></td>
                    <td class="text-right"></td>
                    <td class="text-center"></td>
                    @if($isIntraState)
                        <td class="text-center"></td>
                        <td class="text-center"></td>
                    @else
                        <td class="text-center"></td>
                    @endif
                    <td class="text-right"></td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>

    {{-- Addons + totals --}}
    <table class="totals-table">
        <tr>
            <td width="50%"></td>
            <td width="30%" class="text-right"><em>Gross Total</em></td>
            <td width="20%" class="text-right">
                {{ number_format($grossTotal, 2) }}
            </td>
        </tr>

        @if($pfValue > 0)
            <tr>
                <td></td>
                <td class="text-right"><em>Add   : Packaging &amp; Forwarding</em></td>
                <td class="text-right">{{ number_format($pfValue, 2) }}</td>
            </tr>
        @endif

        @if($freightValue > 0)
            <tr>
                <td></td>
                <td class="text-right"><em>Add   : Freight</em></td>
                <td class="text-right">{{ number_format($freightValue, 2) }}</td>
            </tr>
        @endif

        @if($isIntraState)
            <tr>
                <td></td>
                <td class="text-right"><em>Add   : CGST</em></td>
                <td class="text-right">{{ number_format($cgstTotal, 2) }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="text-right"><em>Add   : SGST</em></td>
                <td class="text-right">{{ number_format($sgstTotal, 2) }}</td>
            </tr>
        @else
            <tr>
                <td></td>
                <td class="text-right"><em>Add   : IGST</em></td>
                <td class="text-right">{{ number_format($igstTotal, 2) }}</td>
            </tr>
        @endif

        @if($roundoff != 0)
            <tr>
                <td></td>
                <td class="text-right">
                    <em>{{ $roundoff < 0 ? 'Less : Rounded Off (-)' : 'Add : Rounded Off (+)' }}</em>
                </td>
                <td class="text-right">
                    {{ number_format(abs($roundoff), 2) }}
                </td>
            </tr>
        @endif

        <tr>
            <td></td>
            <td class="text-right">
                <strong>GRAND TOTAL (Qty: {{ $grandTotalQty }})</strong>
            </td>
            <td class="text-right">
                <strong>{{ number_format($totalAmount, 2) }}</strong>
            </td>
        </tr>

        <tr>
            <td colspan="3">
                <strong>Amount in Words:</strong> {{ $amountInWords }}
            </td>
        </tr>
    </table>

    {{-- HSN-wise summary --}}
    @if(!empty($taxDetails['rows']))
        <table class="hsn-table">
            <thead>
            @if($isIntraState)
                <tr>
                    <th>HSN/SAC</th>
                    <th>Tax Rate</th>
                    <th>Taxable Amt.</th>
                    <th>CGST</th>
                    <th>SGST</th>
                    <th>Total Tax</th>
                </tr>
            @else
                <tr>
                    <th>HSN/SAC</th>
                    <th>Tax Rate</th>
                    <th>Taxable Amt.</th>
                    <th>IGST</th>
                    <th>Total Tax</th>
                </tr>
            @endif
            </thead>
            <tbody>
            @foreach($taxDetails['rows'] as $row)
                <tr>
                    <td class="text-center">{{ $row['hsn'] }}</td>
                    <td class="text-center">{{ $row['rate'] }}%</td>
                    <td class="text-right">{{ number_format($row['taxable'], 2) }}</td>
                    @if($isIntraState)
                        <td class="text-right">{{ number_format($row['cgst'], 2) }}</td>
                        <td class="text-right">{{ number_format($row['sgst'], 2) }}</td>
                    @else
                        <td class="text-right">{{ number_format($row['igst'], 2) }}</td>
                    @endif
                    <td class="text-right">{{ number_format($row['total_tax'], 2) }}</td>
                </tr>
            @endforeach

            <tr>
                <td class="text-center"><strong>Totals</strong></td>
                <td></td>
                <td class="text-right"><strong>{{ number_format($taxDetails['totals']['taxable'], 2) }}</strong></td>
                @if($isIntraState)
                    <td class="text-right"><strong>{{ number_format($taxDetails['totals']['cgst'], 2) }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($taxDetails['totals']['sgst'], 2) }}</strong></td>
                @else
                    <td class="text-right"><strong>{{ number_format($taxDetails['totals']['igst'], 2) }}</strong></td>
                @endif
                <td class="text-right"><strong>{{ number_format($taxDetails['totals']['total'], 2) }}</strong></td>
            </tr>
            </tbody>
        </table>
    @endif

    {{-- Footer image --}}
    <div class="footer-image">
        <img src="{{ $company['footer_image'] }}" alt="">
    </div>

    {{-- Page number (mpdf replaces {PAGENO}/{nbpg}) --}}
    <div class="page-number">
        Page {PAGENO}/{nbpg}
    </div>
</div>

</body>
</html>
