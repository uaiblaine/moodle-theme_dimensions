# Apply the two-column grid to the colours fieldset whether or not the panel was ever mounted.
# With JavaScript off the section then has a 22rem track holding nothing beside four rows squeezed
# into what is left, which is the state the class exists to keep off the page.
s/fieldset\.dim-colours--split > \.fcontainer/fieldset.collapsible > .fcontainer/g;
