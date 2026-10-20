# Keep core's growing transition on the notifications sheet for a reader who asked for less motion.
s/\@media \(prefers-reduced-motion: reduce\) \{\n(\s*\.popover-region-container \{\n\s*transition: none;)/\@media print {\n$1/;
