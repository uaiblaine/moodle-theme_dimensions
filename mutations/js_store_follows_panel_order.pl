# Leave the state behind the panel's own order control: "Sort by" and the next address disagree with the panel.
s/    state\.psort = psort;\n    Listing\.paintOrganise\(root, state\);\n    shell\.changed\('order'\);/    Listing.paintOrganise(root, state);\n    shell.changed('order');/;
