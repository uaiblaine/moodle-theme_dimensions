# Read any zoom the address names.
s/return in_array\(\$zoom, \[self::ZOOM_WEEK, self::ZOOM_MONTH\], true\) \? \$zoom : '';/return \$zoom;/;
