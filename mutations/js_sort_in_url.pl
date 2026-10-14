# Stop writing the organisation into the address. Every state of this listing has an address -
# that is the client's whole contract with the page it runs on - so a reader who picks "A-Z" and
# then shares the page, reloads it or presses back lands on the grouped default instead, with the
# chips still showing the choice that is no longer in force.
s/\n    if \(state\.sort !== '' && state\.sort !== \(state\.defaultsort \|\| ''\)\) \{\n        params\.set\(PARAM\.sort, state\.sort\);\n    \}\n/\n/;
