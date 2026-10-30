# Name the account's entry in a comment of the shared listing, which the visitor's page loads: a name in a comment
# is no import, but nothing a visitor's page loads may say what the logged-in shell is made of.
s/^(const COMPONENTS = \{\n)/\/\/ The logged-in client is listingaccount.\n$1/m;
