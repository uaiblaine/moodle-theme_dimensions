# Leave an open panel open when its control goes.
s/(if \(!shown\) \{\n)        control\.open = false;\n/$1/;
