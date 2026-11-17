# Record the new menu as open after the others close: closing one runs a deferred pass with no menu recorded open.
s/(    row\.open = details;\n)(    disclosures\(row\)\.forEach\(\(other\) => \{\n.*?\n    \}\);\n)/$2$1/s;
