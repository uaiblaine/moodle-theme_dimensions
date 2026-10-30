# Name the account's shell in a comment of the store, which the visitor's page loads: one edit from importing it.
s/^(import \* as Listing from '\.\/listing';)$/\/\/ The logged-in shell is listing\/account.\n$1/m;
