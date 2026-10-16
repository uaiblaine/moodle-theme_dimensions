# Never arm the line on the logged-in shell: no step, no search over the line, no suggestions.
s/^    Line\.arm\(root, \{dropTerm: dropTerm\}\);\n//m;
