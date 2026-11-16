# Leave the panel open when an answer takes its control away.
s/(if \(answer\.filtergroups === false && filters\.current\) \{\n)            filters\.current\.open = false;\n/$1/;
