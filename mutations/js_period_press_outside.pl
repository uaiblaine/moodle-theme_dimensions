# Leave the date layer open on a press outside it.
s/(if \(period\.current && period\.current\.open && !period\.current\.contains\(target\)\) \{\n)            period\.current\.open = false;\n/$1/;
