# Leave the panel open after its "Show".
s/(if \(show && filters\.current\) \{\n)            filters\.current\.open = false;\n/$1/;
