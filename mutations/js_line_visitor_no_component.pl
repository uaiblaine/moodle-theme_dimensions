# Hand the visitor's line to the logged-in shell's component: its page would load a module the owner kept
# off the visitor's import graph until stage R4.
s/^    Line\.arm\(root, \{dropTerm: dropTerm\}\);$/    Listing.mountLine(root, '\@moodle\/lms\/theme_dimensions\/filterline\/FilterLine', dropTerm);/m;
