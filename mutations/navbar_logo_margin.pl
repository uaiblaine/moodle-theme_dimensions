# Give the navbar's logo core's 52px back: 4px from the band, closer than the drawer's header over it.
s/\.navbar\.fixed-top \.navbar-brand \.logo \{\n    height: \$dimensions-logo-height;\n    max-height: \$dimensions-logo-height;\n\}\n//;
