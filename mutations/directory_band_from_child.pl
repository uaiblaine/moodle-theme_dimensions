# Paint every directory card in the institutional navy, whatever the child category's own colour
# is. The cards still render and every count is right, so only a test that brands a child and
# reads the colour back out of the entry can see it.
s/branding::hex\(\(string\) \$branding\['bgcolour'\]\) \?\? branding::DEFAULT_BG/branding::DEFAULT_BG/;
