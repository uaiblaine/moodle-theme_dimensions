# Stop asking styleurl: an address carrying a quote or a parenthesis reaches url("...") in the stylesheet.
s/\n[ ]*\$url = styleurl::safe\(\$url\);//;
