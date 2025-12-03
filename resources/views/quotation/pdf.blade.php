<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>Quotation</title>
    <style>
      body {
        font-family: sans-serif;
        font-size: 12px;
        padding: 0;
        margin: 0px;
      }

      .page-border {
        border: 1px solid #8b440c;
        margin: 10px;
        padding: 5px;
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
      th, td { border: 1px solid #8b440c; }
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
            <th>IGST</th> <!-- NEW -->
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

            {{-- TAX: If IGST present, hide CGST & SGST --}}
            @if($item['igst'] > 0)
              <td>-</td>
              <td>-</td>
              <td>{{ $item['igst'] }} ({{ $item['cgst'] ?: '-' }}%)</td>
            @else
              <td>{{ $item['cgst'] ?: '-' }} ({{ $item['tax'] ?: '-' }}%)</td>
              <td>{{ $item['sgst'] ?: '-' }} ({{ $item['tax'] ?: '-' }}%)</td>
              <td>-</td>
            @endif

            <td>{{ number_format($item['amount'], 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>


      <!-- Summary Section -->
      <div class="summary" style="margin-top: 5px; text-align: right;">
        <table class="no-border" style="width: 100%; border-collapse: collapse; margin-top: 5px;">
          <tr>
            <!-- Left empty space -->
            <td style="width: 60%;"></td>

            <!-- Right block with totals -->
            <td style="width: 40%; text-align: right;">
              <table class="no-border" style="width: 100%; border-collapse: collapse;">          
                <!-- Gross Total -->
                <tr>
                  <td style="padding: 2px 8px; text-align: right;">
                    <strong>Gross Total:</strong>
                  </td>
                  <td style="padding: 2px 0; text-align: right;">
                    ₹{{ number_format($gross_total, 2) }}
                  </td>
                </tr>

                <!-- PF (Packaging & Forwarding) - only if > 0 -->
                @if(!empty($pf_amount) && $pf_amount > 0)
                <tr>
                  <td style="padding: 2px 8px; text-align: right;">
                    <strong>Add : Packaging &amp; Forwarding</strong>
                  </td>
                  <td style="padding: 2px 0; text-align: right;">
                    ₹{{ number_format($pf_amount, 2) }}
                  </td>
                </tr>
                @endif

                <!-- Freight - only if > 0 -->
                @if(!empty($freight_amount) && $freight_amount > 0)
                <tr>
                  <td style="padding: 2px 8px; text-align: right;">
                    <strong>Add : Freight</strong>
                  </td>
                  <td style="padding: 2px 0; text-align: right;">
                    ₹{{ number_format($freight_amount, 2) }}
                  </td>
                </tr>
                @endif

                <!-- Tax rows: IF IGST show only IGST, else show CGST + SGST -->
                @if($show_igst)
                  <tr>
                    <td style="padding: 2px 8px; text-align: right;">
                      <strong>Add : IGST</strong>
                    </td>
                    <td style="padding: 2px 0; text-align: right;">
                      ₹{{ number_format($igst, 2) }}
                    </td>
                  </tr>
                @else
                  <tr>
                    <td style="padding: 2px 8px; text-align: right;">
                      <strong>Add : CGST</strong>
                    </td>
                    <td style="padding: 2px 0; text-align: right;">
                      ₹{{ number_format($cgst, 2) }}
                    </td>
                  </tr>
                  <tr>
                    <td style="padding: 2px 8px; text-align: right;">
                      <strong>Add : SGST</strong>
                    </td>
                    <td style="padding: 2px 0; text-align: right;">
                      ₹{{ number_format($sgst, 2) }}
                    </td>
                  </tr>
                @endif

                <!-- Round off -->
                <tr>
                  <td style="padding: 2px 8px; text-align: right;">
                    <strong>Less : Rounded Off</strong>
                  </td>
                  <td style="padding: 2px 0; text-align: right;">
                    ₹{{ number_format($roundoff, 2) }}
                  </td>
                </tr>

                <!-- Grand Total -->
                <tr>
                  <td style="padding: 2px 8px; text-align: right;">
                    <h3 style="margin-top: 4px; text-align: right;">
                      GRAND TOTAL:
                    </h3>
                  </td>
                  <td style="padding: 2px 0; text-align: right;">
                    <h3 style="margin-top: 4px; text-align: right;">
                      ₹{{ number_format($grand_total, 2) }}
                    </h3>
                  </td>
                </tr>
              </table>              
            </td>
          </tr>
        </table>

        <div style="text-align: right;">
          <i>Rupees {{ $grand_total_words }} Only</i>
        </div>
      </div>


      <!-- Tax Summary Table -->
      <h4 style="margin-top: 5px">Tax Summary:</h4>

      <table style="width: 100%; border-collapse: collapse; margin-top: 3px; border: none;">
        <tr>
          <!-- left side tax table -->
          <td style="width: 40%; border: none;">
            <table style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr>
                  <th>HSN/SAC</th>
                  <th>Tax Rate</th>
                  <th>Taxable Amt.</th>
                  <th>CGST</th>
                  <th>SGST</th>
                  <th>IGST</th>
                  <th>Total Tax</th>
                </tr>
              </thead>
              <tbody>
                @foreach($tax_summary as $tax)
                <tr>
                  <td>{{ $tax['hsn'] }}</td>
                  <td>{{ $tax['rate'] }}%</td>
                  <td>{{ number_format($tax['taxable'], 2) }}</td>
                  <td>{{ $tax['cgst'] > 0 ? number_format($tax['cgst'], 2) : '-' }}</td>
                  <td>{{ $tax['sgst'] > 0 ? number_format($tax['sgst'], 2) : '-' }}</td>
                  <td>{{ $tax['igst'] > 0 ? number_format($tax['igst'], 2) : '-' }}</td>
                  <td>{{ number_format($tax['total_tax'], 2) }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </td>
          <td style="width: 60%; border: none;"></td>
        </tr>
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
            <td style="width: 60%; vertical-align: top; padding-right: 10px; border-right: 1px dotted #000; text-align:left;">
              <strong>TERMS &amp; CONDITIONS:</strong><br />
              <table class="no-border" style="width: 100%; border-collapse: collapse; margin-top:5px;">
                <tr>
                  <td style="width: 35%; padding: 2px 0; text-align: left;"><strong>F.O.R. :</strong></td>
                  <td style="padding: 2px 0; text-align: left;">Kolkata</td>
                </tr>
                <tr>
                  <td style="padding: 2px 0; text-align: left;"><strong>P &amp; F :</strong></td>
                  <td style="padding: 2px 0; text-align: left;">Nil</td>
                </tr>
                <tr>
                  <td style="padding: 2px 0; text-align: left;"><strong>Freight :</strong></td>
                  <td style="padding: 2px 0; text-align: left;">Your Account</td>
                </tr>
                <tr>
                  <td style="padding: 2px 0; text-align: left;"><strong>Delivery :</strong></td>
                  <td style="padding: 2px 0; text-align: left;">Ready Stock subject to prior sale balance 3 weeks</td>
                </tr>
                <tr>
                  <td style="padding: 2px 0; text-align: left;"><strong>Payment :</strong></td>
                  <td style="padding: 2px 0; text-align: left;">30 days - msme</td>
                </tr>
                <tr>
                  <td style="padding: 2px 0; text-align: left;"><strong>Validity :</strong></td>
                  <td style="padding: 2px 0; text-align: left;">30 DAYS</td>
                </tr>
              </table>
            </td>
            <td style="width: 40%; vertical-align: middle; padding-left: 10px">
              <table class="no-border" style="width: 100%; border-collapse: collapse">
                <tr>
                  <td style="width: 60%; text-align: left">
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
