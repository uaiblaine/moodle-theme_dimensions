# Arm the banner without the body class, so the stylesheet never takes the header out of the column.
s/            \$PAGE->add_body_class\(heroformat::BODY_CLASS\);\n//;
