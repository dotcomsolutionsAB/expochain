<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>Quotation</title>

    <style>
      @page {
        header: qHeader;
        footer: qFooter;
        margin-top: 50mm;    /* space for header */
        margin-bottom: 60mm; /* space for footer */
        margin-left: 10mm;
        margin-right: 10mm;
      }

      body {
        font-family: sans-serif;
        font-size: 12px;
        margin: 0;
        padding: 0;
      }

      /* === FULL-PAGE BACKGROUND IMAGE (repeats every page) === */
      .bg-img {
        position: fixed;
        top: 0;
        left: 0;
        width: 210mm;      /* A4 width */
        height: 297mm;     /* A4 height */
        z-index: -20;
      }

      /* === FULL-PAGE BORDER (repeats every page) === */
      .page-border {
        position: fixed;
        top: 5mm;
        left: 5mm;
        right: 5mm;
        bottom: 5mm;
        border: 1px solid #8b440c;
        z-index: -10;
      }

      /* MAIN FLOWING CONTENT (inside margins, inside border) */
      .content {
        /* keep inside @page margins, and give a bit of padding */
      }

      /* Dashed line separator */
      .dash-line {
        border-top: 1px dashed #000;
        margin: 10px 0;
      }

      .header-container {
        position: relative;
        margin-bottom: 20px;
      }

      .header { text-align: center; }

      .header-container img {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 140px;
        height: auto;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
      }

      th,
      td {
        border: 1px solid #8b440c;
        padding: 5px;
        text-align: center;
        font-size: 11px;
      }

      .no-border td { border: none; }

      tr { page-break-inside: avoid; }

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

    {{-- BACKGROUND & BORDER (fixed => shown on every page) --}}
    <img src="{{ public_path('storage/uploads/pdf_template/pdf_bg.jpg') }}" class="bg-img" alt="">
    <div class="page-border"></div>

    {{-- REPEATING HEADER & FOOTER --}}
    <htmlpageheader name="qHeader">
      @include('quotation.pdf_header')
    </htmlpageheader>

    <htmlpagefooter name="qFooter">
      @include('quotation.pdf_footer')
    </htmlpagefooter>

    {{-- MAIN PAGE CONTENT --}}
    <div class="content">

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

            @if($show_igst)
              <th>IGST</th>
            @else
              <th>CGST</th>
              <th>SGST</th>
            @endif

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
            <td>{{ $item['delivery'] ?: '-' }}</td>
            <td>{{ $item['disc'] }}%</td>

            @if($show_igst)
              <td>{{ $item['igst'] ?: 0 }}%</td>
            @else
              <td>{{ $item['cgst'] ?: 0 }}%</td>
              <td>{{ $item['sgst'] ?: 0 }}%</td>
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
            <td style="width: 60%;"></td>
            <td style="width: 40%; text-align: right;">
              <table class="no-border" style="width: 100%; border-collapse: collapse;">
                <tr>
                  <td style="padding: 2px 8px; text-align: right;">
                    <strong>Gross Total:</strong>
                  </td>
                  <td style="padding: 2px 0; text-align: right;">
                    ₹{{ number_format($gross_total, 2) }}
                  </td>
                </tr>

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

                <tr>
                  <td style="padding: 2px 8px; text-align: right;">
                    <strong>Less : Rounded Off</strong>
                  </td>
                  <td style="padding: 2px 0; text-align: right;">
                    ₹{{ number_format($roundoff, 2) }}
                  </td>
                </tr>

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
          <td style="width: 40%; border: none;">
            <table style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr>
                  <th>HSN/SAC</th>
                  <th>Tax Rate</th>
                  <th>Taxable Amt.</th>

                  @if($show_igst)
                    <th>IGST</th>
                  @else
                    <th>CGST</th>
                    <th>SGST</th>
                  @endif

                  <th>Total Tax</th>
                </tr>
              </thead>
              <tbody>
                @foreach($tax_summary as $tax)
                <tr>
                  <td>{{ $tax['hsn'] }}</td>
                  <td>{{ $tax['rate'] }}%</td>
                  <td>{{ number_format($tax['taxable'], 2) }}</td>

                  @if($show_igst)
                    <td>{{ number_format($tax['igst'], 2) }}</td>
                  @else
                    <td>{{ number_format($tax['cgst'], 2) }}</td>
                    <td>{{ number_format($tax['sgst'], 2) }}</td>
                  @endif

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

    </div> {{-- /content --}}
  </body>
</html>
