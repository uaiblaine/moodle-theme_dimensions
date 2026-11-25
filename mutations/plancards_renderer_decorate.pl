# Render core's export undecorated: the first paint loses the block's cards and the layout.
s/\n[^\n]*plancards::decorate\(\$data, [^\n]*;//;
