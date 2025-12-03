<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Quotation</title>
  <style>
    @page {
      header: pageHeader;
      footer: pageFooter;

      /* reduce blank space */
      margin-top: 45mm;      
      header-margin: 1mm;    
      margin-bottom: 70mm;
      margin-left: 10mm;
      margin-right: 10mm;

      background-image: url("{{ public_path('storage/uploads/pdf_template/pdf_bg.jpg') }}");
      background-image-resize: 6;
    }

    body {
      font-family: sans-serif;
      font-size: 12px;
      margin: 10;        /* 🔴 remove extra top space */
      padding: 5;
      background-color: #cdbcafe8;
      z-index: 99;
    }

    .content {
      position: relative;
      z-index: 5;
      margin-top: 1mm;  /* small, nice gap under header */
    }

    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #8b440c; padding: 5px; font-size: 11px; text-align: center; }

    .no-border td { border: none !important; }

    .bank-details {
      text-align: center;
      font-size: 11px;
      margin-top: 15px;
      margin-bottom: 15px;
      border-top: 1px dashed #000;
      border-bottom: 1px dashed #000;
      padding: 5px 0;
    }
  </style>
</head>

<body style="padding:10px; border:2px solid #000; z-index: 9;">


  <!-- ============================================================
      ✔ FIXED HEADER (htmlpageheader)
  ============================================================ -->
  <htmlpageheader name="pageHeader">
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
  </htmlpageheader>

  <!-- ============================================================
      ✔ FIXED FOOTER (htmlpagefooter)
  ============================================================ -->
  <htmlpagefooter name="pageFooter">

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

  </htmlpagefooter>

  <!-- ============================================================
      ✔ MAIN CONTENT (COMES BETWEEN HEADER & FOOTER)
  ============================================================ -->
  <div class="content">
      @yield('content')
  </div>

</body>
</html>
