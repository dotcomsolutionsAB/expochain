@extends('quotation.layout')

@section('content')

  <!-- Customer and Quotation Info -->
  <table class="no-border">
    <tr>
      <td style="width: 60%; vertical-align: top; padding-right: 10px; text-align:left;">
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
          {{-- IGST case: only one tax column --}}
          <th>IGST</th>
        @else
          {{-- Local case: CGST + SGST --}}
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
          {{-- ONLY IGST column --}}
          <td>{{ $item['igst'] ?: 0 }}%</td>
        @else
          {{-- ONLY CGST + SGST columns --}}
          <td>{{ $item['cgst'] ?: 0 }}%</td>
          <td>{{ $item['sgst'] ?: 0 }}%</td>
        @endif

        <td>{{ number_format($item['amount'], 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  {{-- SUMMARY + TAX SUMMARY --}}
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

@endsection
