# Give the date boxes the page's surface instead of the inset ground of the open search field.
s/(\.dim-daterange__field \{\n(?:        [^\n]*\n)*?        background: )var\(--dim-surface-strong\);/${1}var(--dim-surface);/;
