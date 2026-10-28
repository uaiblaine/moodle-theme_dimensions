# Name the line for the component without putting it back: react_autoinit hears no added node and never
# mounts it, so the logged-in shell keeps a line nothing drives.
s/^    line\.parentNode\.insertBefore\(line, line\.nextSibling\);\n//m;
