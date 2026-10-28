# Name the logged-in client in a comment of the shared listing, which the visitor's page loads.
s/^(const COMPONENTS = \{\n)/\/\/ The logged-in client is categoryvitrine.\n$1/m;
