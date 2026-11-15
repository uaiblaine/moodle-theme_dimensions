# Give the open Filters summary the old quiet tint instead of the brand tone's ground, apart from its edge
# and ink: the pressed face no longer one tone.
s/(\n    \.dim-toolbar__filters\[open\] \.dim-toolbar__filterstoggle \{\n        border-color: var\(--dim-brand-edge\);\n        )background: var\(--dim-brand-tint\);/${1}background: var(--dim-tint);/;
