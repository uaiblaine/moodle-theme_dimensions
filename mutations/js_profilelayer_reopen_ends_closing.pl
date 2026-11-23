# Open a layer still on its way out without ending the closing: its end hides the layer just opened.
s/\n    if \(state\.closing\) \{\n\s+state\.closing\(\);\n\s+\}//;
