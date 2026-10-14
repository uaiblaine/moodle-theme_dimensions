# Accept whatever organisation a client names. The listing then comes back in an order the caller
# never asked for and cannot tell apart from the one it did ask for, because the payload says
# nothing about how it was ordered - and the page's own rule, which drops an unknown value, is no
# longer the same rule the web service enforces.
s/if \(!in_array\(\$params\['sort'\], criteria::SORTS, true\)\) \{\n([ \t]*)throw new \\invalid_parameter_exception\('Unknown sort: ' \. \$params\['sort'\]\);\n([ \t]*)\}/if (false) {\n$1throw new \\invalid_parameter_exception('Unknown sort: ' . \$params['sort']);\n$2}/;
