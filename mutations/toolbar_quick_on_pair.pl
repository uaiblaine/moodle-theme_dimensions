# Give the quick date option in force the page's surface as its ink instead of the brand ink: on the
# light page white on the pale tint, on the dark one the dark surface on the dark tint. The sweep
# exercises category_scss_test's assertion.
s/(\n    \.dim-quick--on \{\n(?:        [^\n]*\n)*?        )color: var\(--dim-primary-ink\);/${1}color: var(--dim-surface);/;
