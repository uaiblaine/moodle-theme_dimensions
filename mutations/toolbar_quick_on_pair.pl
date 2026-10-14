# Give the quick date option in force the page's surface as its ink instead of white.
# On the light page that is white and nothing changes; on the dark one it is the dark surface on the
# fill, which is the Filter badge's defect (1.1:1). The sweep exercises
# category_scss_test's assertion.
s/(\.dim-quick--on \{\n(?:[^\n]*\n)*?\s*)color: #fff;/${1}color: var(--dim-surface);/;
