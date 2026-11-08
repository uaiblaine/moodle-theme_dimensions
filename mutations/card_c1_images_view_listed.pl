# Answer true for every view: a view missing from the setting's list still shows its images.
s/return in_array\(\$view, array_map\(.trim., explode\(.,., \(string\) \$value\)\), true\);/return true;/;
