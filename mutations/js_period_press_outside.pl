# Leave the date layer (and so every layer of the capsule) open on a press outside it.
s/(if \(layer && layer\.open && !layer\.contains\(target\)\) \{\n)\s+layer\.open = false;\n/$1/;
