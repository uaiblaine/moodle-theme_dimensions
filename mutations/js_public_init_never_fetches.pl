# Fetch from init. A service that refuses then sends the page to its own address, which arms the
# module again, which fetches again: a loop, where the design has a page load end every failure.
s/\n    Listing\.armTreebox\(root\);\n    listen\(\);\n/\n    Listing.armTreebox(root);\n    listen();\n    requestRows(0, false);\n/;
