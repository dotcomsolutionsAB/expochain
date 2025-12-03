<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>Quotation</title>
    <style>
      body {
        font-family: sans-serif;
        font-size: 12px;
        margin: 5px;
        padding: 5px;
      }

      .page-border {
        border: 1px solid #8b440c;
        padding: 10px;
        object-fit: contain;
        /* PDF background image */
        background-image: url("{{ public_path('storage/uploads/pdf_template/pdf_bg.jpg') }}");
        background-size: cover;
        background-position: center center;
        background-repeat: no-repeat;
      }

      /* Center alignment */
      .center {
        text-align: center;
      }
      .left {
        text-align: end;
      }

      /* Dashed line separator */
      .dash-line {
        border-top: 1px dashed #000;
        margin: 10px 0;
      }

      /* Header styling */
      .header-container {
        position: relative; /* Allow absolute positioning of image within this container */
        margin-bottom: 20px;
      }

      .header {
        text-align: center;
      }

      /* Image styling for top right corner */
      .header-container img {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 140px;
        height: auto;
      }

      /* Two-column layout for info */
      .info-grid {
        display: flex;
        justify-content: space-between;
        page-break-inside: avoid;
      }

      .info-grid .left,
      .info-grid .right {
        width: 48%;
      }

      .info-grid .right {
        text-align: right;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
      }

      th,
      td {
        border: 1px solid #8b440c;
        /* border-bottom:0px solid; */
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
        align-items: center;
        display: flex;
      }
      .left-align {
        text-align: left;
      }

      /* Avoid page-breaks within table rows */
      tr {
        page-break-inside: avoid;
      }

      /* Bank details in one line */
      .bank-details {
        text-align: center;
        font-size: 11px;
        margin-top: 15px;
        margin-bottom: 15px;
        border-top: 1px dashed #000;
        border-bottom: 1px dashed #000;
        padding-top: 5px;
        padding-bottom: 5px;
      }
    </style>
  </head>
  <body>
    <div class="page-border">
      <div class="header-container" style="margin-bottom: 20px">
        <table style="width: 100%; border-collapse: collapse">
          <tr>
            <!-- Company Info: Use all but 120px -->
            <td style="text-align: center; vertical-align: top; border: none">
              <div style="font-weight: bold">QUOTATION</div>
              <div>
                <strong style="color:#8b440c; font-size:18px">EXPO CHAIN & BEARING STORES</strong><br />
                71/D N.S. ROAD, GROUND FLOOR, ROOM NO A-162<br />
                KOLKATA - 700001, WEST BENGAL, India<br />
                GST : 19AAAFE7147G1ZF<br />
                +9133-40064388 | 22431939 , amit@expochain.com, 7059502488
              </div>
            </td>
            <!-- Logo on the Top Right -->
            <td
              style="
                width: 1%;
                text-align: right;
                vertical-align: top;
                border: none;
              "
            >
              <img
                src="{{ public_path('storage/uploads/pdf_template/logo.png') }}"
                alt="Logo"
                style="width: 100px; height: auto; display: block"
              />
            </td>
          </tr>
        </table>
      </div>

      <!-- Dashed separator -->
      <div class="dash-line"></div>

      <!-- Customer and Quotation Info -->
      <table class="no-border">
        <tr>
          <td
            style="
              width: 60%;
              vertical-align: top;
              padding-right: 10px;
              border-right: 1px dotted #000;
              text-align:left;
            "
          >
            <strong>Customer Details :</strong><br />
            ELECTROSTEEL CASTINGS LTD<br />
            30 B.T ROAD, Kharadah, P.O - Sukchar<br />
            KOLKATA - 700115, WEST BENGAL, INDIA<br />
            GSTIN / UIN : 19AAACE4975B1ZP
          </td>
          <td style="width: 40%; vertical-align: top; padding-left: 20px; text-align:left;">
            <table class="no-border" style="width: 100%; border-collapse: collapse;">
              <tr>
                <td style="width: 40%; padding: 2px 0; text-align: left;">Quotation No.:</td>
                <td style="padding: 2px 0; text-align: left;"><strong>{{ $quotation_no }}</strong></td>
              </tr>
              <tr>
                <td style="padding: 2px 0; text-align: left;">Dated:</td>
                <td style="padding: 2px 0; text-align: left;"><strong>{{ $quotation_date }}</strong></td>
              </tr>
              <tr>
                <td style="padding: 2px 0; text-align: left;">Enquiry No.:</td>
                <td style="padding: 2px 0; text-align: left;"><strong>{{ $enquiry_no }}</strong></td>
              </tr>
              <tr>
                <td style="padding: 2px 0; text-align: left;">Enquiry Date:</td>
                <td style="padding: 2px 0; text-align: left;"><strong>{{ $enquiry_date }}</strong></td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

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
              {{ $item['desc'] }}<br />
              <small>MAKE {{ $item['make'] }}</small>
            </td>
            <td>{{ $item['hsn'] }}</td>
            <td>{{ $item['qty'] }}</td>
            <td>{{ $item['unit'] }}</td>
            <td>{{ $item['rate'] }}</td>
            <td>{{ $item['delivery'] }}</td>
            <td>{{ $item['disc'] }}%</td>
            <td>{{ $item['cgst'] }}%</td>
            <td>{{ $item['sgst'] }}%</td>
            <td>{{ number_format($item['amount'], 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>

      <!-- Summary Section -->
      <div class="summary" style="margin-top: 5px; width: 100%; text-align: right;">
        <div style="display: inline-block; text-align: left;">
          <table class="no-border" style="border-collapse: collapse; width: auto; margin-left: auto;">
            <tr>
              <td style="padding: 2px 8px; text-align: left;"><strong>Gross Total:</strong></td>
              <td style="padding: 2px 0; text-align: right;">₹{{ number_format($gross_total, 2) }}</td>
            </tr>
            <tr>
              <td style="padding: 2px 8px; text-align: left;"><strong>Add : CGST</strong></td>
              <td style="padding: 2px 0; text-align: right;">₹{{ number_format($cgst, 2) }}</td>
            </tr>
            <tr>
              <td style="padding: 2px 8px; text-align: left;"><strong>Add : SGST</strong></td>
              <td style="padding: 2px 0; text-align: right;">₹{{ number_format($sgst, 2) }}</td>
            </tr>
            <tr>
              <td style="padding: 2px 8px; text-align: left;"><strong>Less : Rounded Off</strong></td>
              <td style="padding: 2px 0; text-align: right;">₹{{ number_format($roundoff, 2) }}</td>
            </tr>
          </table>

          <h3 style="margin-top: 4px; text-align: right;">
            GRAND TOTAL: ₹{{ number_format($grand_total, 2) }}
          </h3>
          <div style="text-align: right;">
            <i>Rupees {{ $grand_total_words }} Only</i>
          </div>
        </div>
      </div>

      <!-- Tax Summary Table -->
      <h4 style="margin-top: 5px">Tax Summary:</h4>
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

      <!-- Bank Details in a single line -->
      <div class="bank-details">
        <strong>BANK NAME :</strong> HDFC BANK LTD, BRANCH : JARDINE HOUSE,
        CLIVE ROW, A/C NO : 10152320001963, IFSC : HDFC0001015
      </div>

      <!-- Terms & Conditions -->
      <div class="terms" style="margin-top: 5px">
        <table class="no-border" style="width: 100%; border-collapse: collapse">
          <tr>
            <td
              style="
                width: 50%;
                vertical-align: top;
                padding-right: 10px;
                border-right: 1px dotted #000;
                text-align:left;
              "
            >
              <strong>TERMS &amp; CONDITIONS:</strong><br />
              <strong>F.O.R.</strong> : Kolkata<br />
              <strong>P &amp; F</strong> : Nil<br />
              <strong>Freight</strong> : Your Account<br />
              <strong>Delivery</strong> : REady Stock subject to prior sale
              balance 3 weeks<br />
              <strong>Payment</strong> : 30 days - msme<br />
              <strong>Validity</strong> : 30 DAYS<br />
            </td>
            <td style="width: 50%; vertical-align: middle; padding-left: 10px">
              <table
                class="no-border"
                style="width: 100%; border-collapse: collapse"
              >
                <tr>
                  <td style="width: 50%; text-align: left">
                    <strong>for EXPO CHAIN &amp; BEARING STORES</strong><br />
                    <strong>Authorised Signatory</strong>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td colspan="2" style="text-align: center; padding-top: 5px">
              <img
                src="{{ public_path('storage/uploads/pdf_template/footer.jpg') }}"
                alt="Footer Logo"
                style="width: 100%; height: auto"
              />
            </td>
          </tr>
        </table>
      </div>
    </div>
  </body>
</html>
