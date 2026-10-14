# Let a missing predicate read as "public": the page is then served to the internet
# whenever the dependency is absent or broken.
s/no public page\.\n            return false;/no public page.\n            return true;/;
