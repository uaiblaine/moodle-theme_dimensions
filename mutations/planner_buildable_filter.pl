# Build every flavour row: a flavour no page is served costs a compile after every reset.
s/if \(\$bycategory \|\| \$bycohort \|\| in_array\(\(int\) \$flavour->id, \$offered, true\)\)/if (true)/;
