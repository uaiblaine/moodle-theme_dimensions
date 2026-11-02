# Draw the export link whatever core offers.
s/'exporturl' => editing::export_url\(\),/'exporturl' => (new \\core\\url('\/calendar\/export.php'))->out(false),/;
