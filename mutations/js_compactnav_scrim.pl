# Let a press on the page through the scrim: the drawer closes and the link under it is followed.
s/event\.preventDefault\(\);\n(\s*)event\.stopPropagation\(\);\n(\s*)instance\.closeDrawer\(\);/instance.closeDrawer();/;
