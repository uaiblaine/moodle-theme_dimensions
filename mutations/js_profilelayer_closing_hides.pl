# End the closing without hiding the layer: it stays on screen, inert, after its motion.
s/\n\s+layer\.hidden = true;\n(\s+backdrop\.hidden = true;)/\n$1/;
