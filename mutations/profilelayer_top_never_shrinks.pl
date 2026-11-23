# Let the identity header shrink: a layer lower than its content cuts the profile buttons.
s/(\.dimensions-layer-top \{\n\s+position: relative;\n)\s+flex: none;\n/$1/;
