# Let a missing predicate read as "public": every category page is then served to the
# internet whenever the dependency is absent or broken.
s/no public category\.\n            return false;/no public category.\n            return true;/;
