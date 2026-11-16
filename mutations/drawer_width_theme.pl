# Leave the navigation drawer at core's 285px below md: the side layer's width is 320px (D72).
s/(\n    #theme_boost-drawers-primary\.drawer-primary\.drawer-left \{\n[^\n]*\n)        width: \$dimensions-drawer-width;\n/$1/;
