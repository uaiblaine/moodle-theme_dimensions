# Build the root's request without asking for posters: the population then admits no panel, so an
# open panel of the address is dropped and every poster of the root - the visitor's public root and
# the logged-in one alike, both built by criteria::root_from_request() - opens
# nothing.
s/(\/\/ The root draws its posters, and a panel may be open under one of them\.\n\s+)'withposters' => true,/$1'withposters' => false,/;
