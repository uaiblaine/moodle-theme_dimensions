# Start children under designer mode, where every theme cache is off and nothing built is ever read.
s/!empty\(\$CFG->themedesignermode\) \|\| //;
