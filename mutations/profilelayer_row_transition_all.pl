# Transition every property of a row: the layout and the focus ring start to animate too.
s/(\.dimensions-layer-row \{.*?transition:\s*)background-color var\(--dim-layer-motion-base\) var\(--dim-layer-motion-ease\),\s*color /${1}all /s;
