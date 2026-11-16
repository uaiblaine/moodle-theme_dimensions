# Accept whatever organisation a client names. The listing then comes back in an order the caller
# never asked for and cannot tell apart from the one it did ask for, because the payload says
# nothing about how it was ordered - and the page's own rule, which drops an unknown value, is no
# longer the same rule the web service enforces. The vocabulary is rows::require_known_organisation();
# this is the call that asks it.
s/        rows::require_known_organisation\(\(string\) \$params\['group'\], \(string\) \$params\['sort'\]\);\n//;
