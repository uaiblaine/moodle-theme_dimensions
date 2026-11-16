# Leave the layer open after its "Show": neither the Filters panel nor the date layer closes.
s/(        if \(details\) \{\n)            details\.open = false;\n/$1/;
