# Let a hovered row's icon still move for a reader who asked for reduced motion.
s/(prefers-reduced-motion: reduce\).*?)transform: none;/$1/s;
