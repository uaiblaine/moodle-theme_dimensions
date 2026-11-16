# Take the user button over before anything else can fail: a throw after it leaves Boost Union's dropdown dead and the layer unarmed.
s/\n\s+\/\/ The last step: the user button leaves[^\n]*\n(\s+)takeOver\(toggle, layer\);\n/\n/;
s/(\n\s+)(\/\/ Everything that can throw comes first[^\n]*\n)/$1takeOver(toggle, layer);$1$2/;
