# Leave the navbar at its own level while the primary drawer is open: the drawer opens under it.
s/body:has\(#theme_boost-drawers-primary\.show\) \.navbar\.fixed-top \{\n    z-index: \$zindex-sticky;\n\}\n//;
