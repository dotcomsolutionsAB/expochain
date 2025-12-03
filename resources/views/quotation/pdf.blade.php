@extends('quotation.layout')

@section('content')

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

  {{-- SUMMARY + TAX SUMMARY --}}
  {{-- (paste the summary + tax summary blocks you already have here) --}}

@endsection
