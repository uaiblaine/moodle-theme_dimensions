# Give the Filter summary's badge the page's surface as its ink again. On the light page that is white
# on the navy and nothing changes, but in the dark mode the surface is the dark body background on
# the same navy, which Bootstrap does not redefine: 1.1:1, the digit unreadable. The sweep exercises
# category_scss_test's assertion.
s/(\.dim-toolbar__filtercount \{\n(?:[^\n]*\n)*?\s*)color: #fff;/${1}color: var(--dim-surface);/;
