# Animate the swap for a reader who asked for less motion.
s/\@media \(prefers-reduced-motion: no-preference\) \{\n(\s*\.navbar\.fixed-top \.dimensions-ctxbar,)/\@media all {\n$1/;
