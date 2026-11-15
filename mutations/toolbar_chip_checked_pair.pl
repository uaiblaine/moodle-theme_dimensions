# Leave the solid fill's ink on the chosen option: white (or the primary's contrasting ink) on the brand
# tone's pale ground, where the brand ink belongs, so the option in force is the one nobody can read.
# The sweep exercises category_scss_test's assertion.
s/(\n    \.dim-pill__input:checked \+ \.dim-pill \{\n(?:        [^\n]*\n)*?        )color: var\(--dim-primary-ink\);/${1}color: var(--dim-cta-ink);/;
