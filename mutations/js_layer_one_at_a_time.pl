# Let a layer of the capsule open beside another.
s/(if \(one && one !== opened && one\.open\) \{\n)                    one\.open = false;\n/$1/;
