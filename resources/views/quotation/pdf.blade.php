<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>Quotation</title>

    <style>
      @page {
        header: qHeader;
        footer: qFooter;
        /* space reserved for header/footer */
        margin-top: 55mm;
        margin-bottom: 65mm;
        margin-left: 10mm;
        margin-right: 10mm;
      }

      body {
        font-family: sans-serif;
        font-size: 12px;
        margin: 0;
        padding: 0;
        /* FULL-PAGE BG (for every page) */
        background-image: url("{{ public_path('storage/uploads/pdf_template/pdf_bg.jpg') }}");
        background-size: cover;
        background-position: center center;
        background-repeat: no-repeat;
      }

      /* Main content box inside the page margins */
      .content-area {
        /* optional: if you want a soft inner border, uncomment: */
        /* border: 1px solid #8b440c; */
        padding: 5px;
        box-sizing: border-box;
      }

      .header-table {
        width: 100%;
        border-collapse: collapse;
      }

      .dash-line {
        border-top: 1px dashed #000;
        margin-top: 5px;
      }

      .center {
        text-align: center;
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

      .no-border td,
      .no-border th {
        border: none !important;
      }

      .left-align {
        text-align: left;
      }
      .right-align {
        text-align: right;
      }

      tr {
        page-break-inside: avoid;
      }

      .bank-details {
        text-align: center;
        font-size: 11px;
        margin-top: 5px;
        margin-bottom: 5px;
        border-top: 1px dashed #000;
        padding-top: 3px;
        padding-bottom: 3px;
      }

      .terms {
        border-bottom: 1px dashed #000;
      }
    </style>
  </head>
  <body>
    {{-- ================== HEADER (REPEATS EVERY PAGE) ================== --}}
    <htmlpageheader name="qHeader">
      <div class="header-container">
        <table class="header-table">
          <tr>
            <td style="text-align: center; vertical-align: top; border: none">
              <div style="font-weight: bold">QUOTATION</div>
              <div>
                <strong style="color:#8b440c; font-size:18px">
                  EXPO CHAIN &amp; BEARING STORES
                </strong><br />
                71/D N.S. ROAD, GROUND FLOOR, ROOM NO A-162<br />
                KOLKATA - 700001, WEST BENGAL, India<br />
                GST : 19AAAFE7147G1ZF<br />
                +9133-40064388 | 22431939 , amit@expochain.com, 7059502488
              </div>
            </td>

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

        <div class="dash-line"></div>
      </div>
    </htmlpageheader>

    {{-- ================== FOOTER (REPEATS EVERY PAGE) ================== --}}
    <htmlpagefooter name="qFooter">
      {{-- Bank Details in a single line --}}
      <div class="bank-details">
        <strong>BANK NAME :</strong> HDFC BANK LTD, BRANCH : JARDINE HOUSE,
        CLIVE ROW, A/C NO : 10152320001963, IFSC : HDFC0001015
      </div>

      {{-- Terms + Footer image --}}
      <div class="terms">
        <table class="no-border" style="width: 100%; border-collapse: collapse">
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
              <strong>TERMS &amp; CONDITIONS:</strong><br />
              <table
                class="no-border"
                style="width: 100%; border-collapse: collapse; margin-top:5px;"
              >
                <tr>
                  <td
                    style="width: 35%; padding: 2px 0; text-align: left;"
                  ><strong>F.O.R. :</strong></td>
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
                  <td style="padding: 2px 0; text-align: left;">
                    Ready Stock subject to prior sale balance 3 weeks
                  </td>
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
                    <strong>for EXPO CHAIN &amp; BEARING STORES</strong><br /><br />
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
    </htmlpagefooter>

    {{-- ================== MAIN CONTENT (AUTO FLOWS TO NEXT PAGES) ================== --}}
    <div class="content-area">
      {{-- Customer + Quotation info --}}
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
          <td
            style="
              width: 40%;
              vertical-align: top;
              padding-left: 20px;
              text-align:left;
            "
          >
            <table class="no-border" style="width: 100%; border-collapse: collapse;">
              <tr>
                <td style="width: 40%; padding: 2px 0; text-align: left;">Quotation No.:</td>
                <td style="padding: 2px 0; text-align: left;">
                  <strong>{{ $quotation_no }}</strong>
                </td>
              </tr>
              <tr>
                <td style="padding: 2px 0; text-align: left;">Dated:</td>
                <td style="padding: 2px 0; text-align: left;">
                  <strong>{{ $quotation_date }}</strong>
                </td>
              </tr>
              <tr>
                <td style="padding: 2px 0; text-align: left;">Enquiry No.:</td>
                <td style="padding: 2px 0; text-align: left;">
                  <strong>{{ $enquiry_no }}</strong>
                </td>
              </tr>
              <tr>
                <td style="padding: 2px 0; text-align: left;">Enquiry Date:</td>
                <td style="padding: 2px 0; text-align: left;">
                  <strong>{{ $enquiry_date }}</strong>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      {{-- ITEMS TABLE --}}
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
            <td class="left-align">
              {{ $item['desc'] }}<br />
              <small>MAKE {{ $item['make'] }}</small>
            </td>
            <td>{{ $item['hsn'] }}</td>
            <td>{{ $item['qty'] }}</td>
            <td>{{ $item['unit'] }}</td>
            <td class="right-align">{{ number_format($item['rate'], 2) }}</td>
            <td>{{ $item['delivery'] ?: '-' }}</td>
            <td>{{ $item['disc'] }}%</td>

            @if($show_igst)
              <td class="right-align">{{ $item['igst'] ?: 0 }}</td>
            @else
              <td class="right-align">{{ $item['cgst'] ?: 0 }}</td>
              <td class="right-align">{{ $item['sgst'] ?: 0 }}</td>
            @endif

            <td class="right-align">{{ number_format($item['amount'], 2) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>

      {{-- SUMMARY BLOCK (RIGHT SIDE) --}}
      <table class="no-border" style="width: 100%; margin-top: 5px;">
        <tr>
          <td style="width: 60%;"></td>
          <td style="width: 40%; text-align: right;">
            <table class="no-border" style="width: 100%;">
              <tr>
                <td class="right-align" style="padding: 2px 8px;">
                  <strong>Gross Total:</strong>
                </td>
                <td class="right-align">
                  ₹{{ number_format($gross_total, 2) }}
                </td>
              </tr>

              @if(!empty($pf_amount) && $pf_amount > 0)
              <tr>
                <td class="right-align" style="padding: 2px 8px;">
                  <strong>Add : Packaging &amp; Forwarding</strong>
                </td>
                <td class="right-align">
                  ₹{{ number_format($pf_amount, 2) }}
                </td>
              </tr>
              @endif

              @if(!empty($freight_amount) && $freight_amount > 0)
              <tr>
                <td class="right-align" style="padding: 2px 8px;">
                  <strong>Add : Freight</strong>
                </td>
                <td class="right-align">
                  ₹{{ number_format($freight_amount, 2) }}
                </td>
              </tr>
              @endif

              @if($show_igst)
                <tr>
                  <td class="right-align" style="padding: 2px 8px;">
                    <strong>Add : IGST</strong>
                  </td>
                  <td class="right-align">
                    ₹{{ number_format($igst, 2) }}
                  </td>
                </tr>
              @else
                <tr>
                  <td class="right-align" style="padding: 2px 8px;">
                    <strong>Add : CGST</strong>
                  </td>
                  <td class="right-align">
                    ₹{{ number_format($cgst, 2) }}
                  </td>
                </tr>
                <tr>
                  <td class="right-align" style="padding: 2px 8px;">
                    <strong>Add : SGST</strong>
                  </td>
                  <td class="right-align">
                    ₹{{ number_format($sgst, 2) }}
                  </td>
                </tr>
              @endif

              <tr>
                <td class="right-align" style="padding: 2px 8px;">
                  <strong>Less : Rounded Off</strong>
                </td>
                <td class="right-align">
                  ₹{{ number_format($roundoff, 2) }}
                </td>
              </tr>

              <tr>
                <td class="right-align" style="padding: 2px 8px;">
                  <h3 style="margin-top: 4px;">GRAND TOTAL:</h3>
                </td>
                <td class="right-align">
                  <h3 style="margin-top: 4px;">
                    ₹{{ number_format($grand_total, 2) }}
                  </h3>
                </td>
              </tr>
            </table>

            <div class="right-align">
              <i>Rupees {{ $grand_total_words }} Only</i>
            </div>
          </td>
        </tr>
      </table>

      {{-- TAX SUMMARY --}}
      <h4 style="margin-top: 5px">Tax Summary:</h4>

      <table
        style="
          width: 100%;
          border-collapse: collapse;
          margin-top: 3px;
          border: none;
        "
      >
        <tr>
          <td style="width: 40%; border: none; vertical-align: top;">
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
                  <td class="right-align">
                    {{ number_format($tax['taxable'], 2) }}
                  </td>

                  @if($show_igst)
                    <td class="right-align">
                      {{ number_format($tax['igst'], 2) }}
                    </td>
                  @else
                    <td class="right-align">
                      {{ number_format($tax['cgst'], 2) }}
                    </td>
                    <td class="right-align">
                      {{ number_format($tax['sgst'], 2) }}
                    </td>
                  @endif

                  <td class="right-align">
                    {{ number_format($tax['total_tax'], 2) }}
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </td>
          <td style="width: 60%; border: none;"></td>
        </tr>
      </table>
    </div>
  </body>
</html>
