# Force the notifications popover back to a light colour scheme on the dark page.
s/(\n\.navbar \.popover-region-container \{.*?\n    color: var\(--bs-body-color\);\n)/$1    color-scheme: light;\n/s;
