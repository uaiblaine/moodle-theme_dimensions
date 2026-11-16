# Cap the compact navbar's drawer at core's width while it is drawn at the theme's: its copy of the geometry drifts.
s/max-width: \$dimensions-drawer-width;/max-width: \$drawer-left-width;/;
