<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            margin: 10px;
        }
        /* Border around the entire page */
        .page-border {
            border: 2px solid #000;
            padding: 15px;
        }
        /* Center align text */
        .center {
            text-align: center;
        }
        /* Dashed line separator */
        .dash-line {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        /* Two-column layout for customer and quotation info */
        .info-grid {
            display: flex;
            justify-content: space-between;
        }
        .info-grid .left, .info-grid .right {
            width: 48%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
            font-size: 11px;
        }
        .no-border td {
            border: none;
        }
        .title {
            font-size: 18px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .right-align {
            text-align: right;
        }
    </style>
</head>
<body>
<div class="page-border">

    <div class="title">QUOTATION</div>

    <!-- Company Header (centered) -->
    <div class="header center">
        <strong>EXPO CHAIN & BEARING STORES</strong><br>
        71/D N.S. ROAD, GROUND FLOOR,ROOM NO A-162<br>
        KOLKATA - 700001, WEST BENGAL, India<br>
        GST : 19AAAFE7147G1ZF<br>
        +9133-40064388 | 22431939 , amit@expochain.com, 7059502488
    </div>

    <!-- Dashed separator -->
    <div class="dash-line"></div>

    <!-- Two-column section: Left side for Customer Details and Right side for Quotation Info -->
    <div class="info-grid">
        <div class="left">
            <strong>Customer Details :</strong><br>
            ELECTROSTEEL CASTINGS LTD<br>
            30 B.T ROAD, KHARDAH, P.O - SUKCHAR<br>
            KOLKATA - 700115, WEST BENGAL, INDIA<br>
            GSTIN / UIN : 19AAACE4975B1ZP
        </div>
        <div class="right">
            Quotation No.: <strong>{{ $quotation_no }}</strong><br>
            Dated: <strong>{{ $quotation_date }}</strong><br>
            Enquiry No.: <strong>{{ $enquiry_no }}</strong><br>
            Enquiry Date: <strong>{{ $enquiry_date }}</strong>
        </div>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th>SN</th>
                <th>DESCRIPTION OF GOODS</th>
                <th>HSN</th>
                <th>QTY</th>
                <th>UNIT</th>
                <th>RATE</th>
                <th>DELIVERY</th>
                <th>DISC%</th>
                <th>CGST</th>
                <th>SGST</th>
                <th>AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $item['desc'] }}<br>
                        <small>MAKE {{ $item['make'] }}</small>
                    </td>
                    <td>{{ $item['hsn'] }}</td>
                    <td>{{ $item['qty'] }}</td>
                    <td>{{ $item['unit'] }}</td>
                    <td>{{ $item['rate'] }}</td>
                    <td>{{ $item['delivery'] }}</td>
                    <td>{{ $item['disc'] }}%</td>
                    <td>9%</td>
                    <td>9%</td>
                    <td>{{ number_format($item['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Summary Section -->
    <div class="summary right-align" style="margin-top: 15px;">
        <p><strong>Gross Total:</strong> ₹{{ number_format($gross_total, 2) }}</p>
        <p><strong>Add : CGST</strong> ₹{{ number_format($cgst, 2) }}</p>
        <p><strong>Add : SGST</strong> ₹{{ number_format($sgst, 2) }}</p>
        <p><strong>Less : Rounded Off</strong> (₹{{ number_format($roundoff, 2) }})</p>
        <h3>GRAND TOTAL: ₹{{ number_format($grand_total, 2) }}</h3>
        <p><i>Rupees {{ $grand_total_words }} Only</i></p>
    </div>

    <!-- Tax Summary Table -->
    <h4 style="margin-top: 20px;">Tax Summary:</h4>
    <table>
        <thead>
            <tr>
                <th>HSN/SAC</th>
                <th>Tax Rate</th>
                <th>Taxable Amt.</th>
                <th>CGST</th>
                <th>SGST</th>
                <th>Total Tax</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tax_summary as $tax)
                <tr>
                    <td>{{ $tax['hsn'] }}</td>
                    <td>{{ $tax['rate'] }}%</td>
                    <td>{{ number_format($tax['taxable'], 2) }}</td>
                    <td>{{ number_format($tax['cgst'], 2) }}</td>
                    <td>{{ number_format($tax['sgst'], 2) }}</td>
                    <td>{{ number_format($tax['total_tax'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Bank Details Section -->
    <div class="bank" style="margin-top: 15px;">
        <strong>BANK NAME :</strong> HDFC BANK LTD<br>
        BRANCH : JARDINE HOUSE, CLIVE ROW<br>
        A/C NO : 10152320001963, IFSC : HDFC0001015
    </div>

    <!-- Terms & Conditions Section -->
    <div class="terms" style="margin-top: 10px;">
        <strong>TERMS & CONDITIONS: for EXPO CHAIN & BEARING STORES</strong><br>
        F.O.R. : Kolkata<br>
        P & F : Nil<br>
        Freight : Your Account<br>
        Delivery : REady Stock subject to prior sale balance 3 weeks<br>
        Payment : 30 days - msme<br>
        Validity : 30 DAYS<br>
        Remarks : Authorised Signatory
    </div>

</div>
</body>
</html>
