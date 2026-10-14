# Give the chosen chip the institutional navy as its fill, where the call to action's pair belongs.
# --dim-primary does not follow the colour mode: on the dark page the chosen chip is
# a navy shape on the dark surface, 1.1:1, and the reader cannot see which option is in force. The
# sweep exercises category_scss_test's assertion.
s/(\.dim-pill__input:checked \+ \.dim-pill \{\n\s*border-color: var\(--dim-cta\);\n\s*)background: var\(--dim-cta\);/${1}background: var(--dim-primary);/;
