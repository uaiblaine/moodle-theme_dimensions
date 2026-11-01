# Ask core again on every opening.
s/    if \(known === undefined\) \{\n        known = fetchOne/    if (true) {\n        known = fetchOne/;
