# Give the Filter summary's badge the page's surface as its ink again. On the light page that is white
# on the primary and nothing changes, but in the dark mode the surface is the dark body background on
# the badge's fill, which Bootstrap does not redefine: 1.1:1, the digit unreadable. The sweep exercises
# category_scss_test's assertion.
s/(\n    \.dim-toolbar__filtercount \{\n(?:        [^\n]*\n)*?        )color: var\(--dim-cta-ink\);/${1}color: var(--dim-surface);/;
