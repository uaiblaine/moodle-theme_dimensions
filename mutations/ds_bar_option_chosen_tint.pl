# Fill the chosen option with the call to action again instead of the brand tone's ground: a solid
# shape under the brand ink, which is then the one option nobody can read.
s/(\n    \.dim-pill__input:checked \+ \.dim-pill \{\n        border-color: var\(--dim-brand-edge\);\n        )background: var\(--dim-brand-tint\);/${1}background: var(--dim-cta);/;
