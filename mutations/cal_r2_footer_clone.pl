# Empty the caller's own block contents.
s/            \$bc = clone\(\$bc\);\n            \$bc->footer = '';/            \$bc->footer = '';/;
