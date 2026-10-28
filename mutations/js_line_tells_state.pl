# Stop telling the component the listing's state on Back, Clear all or a removed chip: its field, its
# scope, its star and its view toggle stay on the state the reader left.
s/^    tellLine\(root, LINE\.state, \{query: state\.query, chip: state\.chip, fav: Boolean\(state\.fav\), view: state\.view\}\);\n//m;
