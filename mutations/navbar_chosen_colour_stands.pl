# Fill in the dark pair over any navbar colour, an admin's or a flavour's choice included.
s/if \(\(\$context\['navbarcolorclasses'\] \?\? ''\) !== 'bg-body'\) \{\n\s*return \$context;\n\s*\}\n//;
