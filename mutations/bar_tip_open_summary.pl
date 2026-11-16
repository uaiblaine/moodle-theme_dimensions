# Show a summary's tooltip while its details is open.
s/(\.dim-line \[open\] > \[data-dim-tip\]::before \{\n        )visibility: hidden;/${1}visibility: visible;/;
