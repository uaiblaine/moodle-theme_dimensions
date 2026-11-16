# Arm a layer that has no main view: the takeover happens, then the first open finds nothing to show.
s/\n\s+const main = layer\.querySelector\(SELECTORS\.MAIN\);\n\s+if \(!main\) \{\n\s+return;\n\s+\}//;
