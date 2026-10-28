# Never hand the line to its component on the logged-in shell: no step, no search over the line, no
# suggestions, and a line no painter writes into the controls of, since nothing marks it drawn.
s/^    mountLine\(\);\n//m;
