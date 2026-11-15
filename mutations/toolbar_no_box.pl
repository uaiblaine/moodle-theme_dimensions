# Put a card round the toolbar: a border, where the design's bar sits on the page with none. The sweep
# exercises category_scss_test's assertion.
s/(\n    \.dim-toolbar \{\n(?:[^\n]*\n)*?\s*gap: \.5rem;\n)/${1}        border: 1px solid var(--dim-line);\n/;
