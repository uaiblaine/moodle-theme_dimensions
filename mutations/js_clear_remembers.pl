# Clear the filters in place without clearing the remembered ones: told as a search, which the account's shell does
# not remember, so the next bare visit brings them back.
s/(    Listing\.paintControls\(root, state\);\n)    apply\('filter'\);\n(    focusSearch\(\);\n\};)/$1    apply('term');\n$2/;
