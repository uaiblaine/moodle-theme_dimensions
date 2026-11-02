# Read every rated course of the site instead of the page's: the layer is no longer driven by the page slice.
s/"courseid \{\$insql\} AND " \. self::RATED/self::RATED/;
