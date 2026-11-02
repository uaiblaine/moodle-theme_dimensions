# Leave the theme's dialogue open under tool_courserating's reviews popup: two dialogues stack.
s/(closest\(SELECTORS\.reviews\)\) \{\n)            modal\.destroy\(\);\n/$1/;
