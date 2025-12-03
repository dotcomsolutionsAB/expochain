<html>
<head>
  <style>
    body {
      border: 5px solid red;
    }
    @page {
        header: pageHeader;
        footer: pageFooter;

        margin-top: 45mm;
        header-margin: 1mm;
        margin-bottom: 70mm;
        margin-left: 10mm;
        margin-right: 10mm;

        background-image: url("{{ public_path('storage/uploads/pdf_template/pdf_bg_with_border.jpg') }}");
        background-image-resize: 6;
    }

  </style>
</head>
<body>
  TEST BORDER
</body>
</html>
