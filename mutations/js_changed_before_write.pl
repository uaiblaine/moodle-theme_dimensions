# Tell the shell a change after the address is written: the first change of a category writes a bare address, which
# from then on means the remembered state to this reader, and a reload brings back the state before the change.
s/    shell\.changed\(kind\);\n    Listing\.writeUrl\(state, true\);\n/    Listing.writeUrl(state, true);\n    shell.changed(kind);\n/;
