# Forget the layer that arms after the phone menu: the entries stay in Boost Union's dropdown, which the button no
# longer opens, and the navbar's controls are hidden with no way to them.
s/layer\.addEventListener\(LAYER\.ARMEDEVENT, \(\) => intoLayer\(layer, entries\), \{once: true\}\);/void layer;/;
