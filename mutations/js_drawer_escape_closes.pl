# Escape at the drawer's root hides it by a class instead of through Boost's instance: Boost's backdrop stays, its
# events never fire and the focus is not given back to the menu button.
s/Drawers\.getDrawerInstanceForNode\(drawer\)\.closeDrawer\(\);/drawer.classList.remove(SHOWN);/;
