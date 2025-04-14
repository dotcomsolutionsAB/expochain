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
        .page-border {
            border: 2px solid black;
            padding: 15px;
        }
        .center {
            text-align: center;
        }
        .header, .customer-section {
            margin-bottom: 10px;
        }
        .dash-line {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
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
        .right { text-align: right; }
    </style>
</head>
<body>
<div class="page-border">

    <div class="title">QUOTATION</div>

    <div class="header center">
        <strong>EXPO CHAIN & BEARING STORES</strong><br>
        71/D N.S. ROAD, GROUND FLOOR,ROOM NO A-162<br>
        KOLKATA - 700001, WEST BENGAL, India<br>
        GST : 19AAAFE7147G1ZF<br>
        +9133-40064388 | 22431939 , amit@expochain.com, 7059502488
    </div>

    <div class="dash-line"></div>

    <div class="info-grid">
        <div class="left">
            <strong>Customer Details :</strong><br>
            ELECTROSTEEL CASTINGS LTD<br>
            30<br>
            B.T ROAD, KHARDAH, P.O - SUKCHAR<br>
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

    {{-- Keep the rest of your existing HTML here below --}}

</div>
</body>
</html>
