# Centre the far end tooltips on their control again.
s/(\.dim-line__searchclose\[data-dim-tip\]::after \{\n        )right: 0;\n        left: auto;\n        transform: none;/$1left: 50%;/;
