# Raise the user menu's sheet for a reader who asked for less motion.
s/\@media \(prefers-reduced-motion: no-preference\) \{\n(\s*\.dropdown-menu\.show \{)/\@media all {\n$1/;
