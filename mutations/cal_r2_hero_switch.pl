# Offer the public calendar while it is off, a link to the login page.
s/\|\| !visitor::enabled\(\) \|\| !coreview::gregorian\(\)\)/|| !coreview::gregorian())/;
