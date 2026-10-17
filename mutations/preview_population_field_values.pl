# Hand the rows a 0 for every filter field: the preview would read every course as holding no value.
s/\$row->fieldvalues\[\(int\) \$fieldid\] = \(int\) \(\$map\[\$courseid\] \?\? 0\);/\$row->fieldvalues[(int) \$fieldid] = 0;/;
