# Put the course index controls back under the 5.2 block name: 5.3's theme_boost/drawer has no
# drawerheadercontent block, so the controls silently vanish from the focus page's course index.
s/\{\{\$drawercontrols\}\}(\{\{> theme_boost\/courseindexdrawercontrols\}\})\{\{\/drawercontrols\}\}/{{\$drawerheadercontent}}${1}{{\/drawerheadercontent}}/;
