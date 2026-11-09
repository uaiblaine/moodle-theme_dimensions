# Let every child of .main-inner span the page, so the summary and the card leave the column.
s/(\.main-inner > :not\(#page-header\) \{\n)            max-width: \$dim-landing-column;\n/$1/;
