# Keep the term in the state when a suggestion applies its filter: the one request carries it.
s/(const dropTerm = \(\) => \{\n    cancelSearch\(\);\n)    state\.query = '';\n/$1/;
