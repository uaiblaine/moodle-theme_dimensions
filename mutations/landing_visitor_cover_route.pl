# A visitor's covers point at core's overview files, which answer a visitor with the login page.
s/\$images = \$rows \? cards::images\(\$rows, display::visitor\(\)\) : \[\];/\$images = \$rows ? cards::images(\$rows, false) : [];/;
