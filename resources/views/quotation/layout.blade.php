<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Quotation</title>
  <style>
      @page {
        margin-top: 60mm;
        margin-bottom: 70mm;
        margin-left: 10mm;
        margin-right: 10mm;

        header: pageHeader;
        footer: pageFooter;

        /* 🔥 mPDF page background image */
        background-image: url("{{ public_path('storage/uploads/pdf_template/pdf_bg.jpg') }}");
        background-image-resize: 6; /* scale to full page */
      }

      body {
        font-family: sans-serif;
        font-size: 12px;
        margin: 10px;
        padding: 0;
        border: 4px solid #8b440c;
      }

      /* Only BORDER now, NO background here */
      /* .page-border {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          border: 1px solid #8b440c;
          z-index: -1;
      } */

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

<body>

  <!-- ✔ Background & Border -->
  <!-- <div class="page-border"></div> -->

  <!-- ============================================================
      ✔ FIXED HEADER (htmlpageheader)
  ============================================================ -->
  <htmlpageheader name="pageHeader">
      <table style="width:100%; border-collapse:collapse;">
          <tr>
              <td style="text-align:center; border:none;">
                  <div style="font-weight:bold; font-size:16px;">QUOTATION</div>
                  <strong style="color:#8b440c; font-size:18px;">EXPO CHAIN &amp; BEARING STORES</strong><br>
                  71/D N.S. ROAD, GROUND FLOOR, ROOM NO A-162<br>
                  KOLKATA - 700001, WEST BENGAL, India<br>
                  GST : 19AAAFE7147G1ZF<br>
                  +9133-40064388 | 22431939 , amit@expochain.com, 7059502488
              </td>

              <td style="width:120px; text-align:right; border:none;">
                  <img src="{{ public_path('storage/uploads/pdf_template/logo.png') }}"
                      style="width:100px; height:auto;">
              </td>
          </tr>
      </table>

      <!-- dashed line -->
      <div style="border-top:1px dashed #000; margin-top:10px;"></div>
  </htmlpageheader>

  <!-- ============================================================
      ✔ FIXED FOOTER (htmlpagefooter)
  ============================================================ -->
  <htmlpagefooter name="pageFooter">

      <div class="bank-details">
          <strong>BANK NAME :</strong> HDFC BANK LTD, BRANCH : JARDINE HOUSE,
          CLIVE ROW, A/C NO : 10152320001963, IFSC : HDFC0001015
      </div>

      <table class="no-border">
          <tr>
              <td style="width:60%; padding-right:10px; border-right:1px dotted #000;">
                  <strong>TERMS &amp; CONDITIONS:</strong><br><br>

                  <table class="no-border">
                      <tr><td><strong>F.O.R. :</strong></td><td>Kolkata</td></tr>
                      <tr><td><strong>P &amp; F :</strong></td><td>Nil</td></tr>
                      <tr><td><strong>Freight :</strong></td><td>Your Account</td></tr>
                      <tr><td><strong>Delivery :</strong></td><td>Ready Stock subject to prior sale balance 3 weeks</td></tr>
                      <tr><td><strong>Payment :</strong></td><td>30 days - msme</td></tr>
                      <tr><td><strong>Validity :</strong></td><td>30 DAYS</td></tr>
                  </table>
              </td>

              <td style="width:40%; padding-left:10px; vertical-align:middle;">
                  <strong>for EXPO CHAIN &amp; BEARING STORES</strong><br>
                  <strong>Authorised Signatory</strong>
              </td>
          </tr>

          <tr>
              <td colspan="2" style="text-align:center; padding-top:10px;">
                  <img src="{{ public_path('storage/uploads/pdf_template/footer.jpg') }}"
                      style="width:100%; height:auto;">
              </td>
          </tr>
      </table>

  </htmlpagefooter>

  <!-- ============================================================
      ✔ MAIN CONTENT (COMES BETWEEN HEADER & FOOTER)
  ============================================================ -->
  <div class="content">
      @yield('content')
  </div>

</body>
</html>
