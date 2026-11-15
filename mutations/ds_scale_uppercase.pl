# Set a showcase label in capitals: the filter group's eyebrow, which sentence case put back in its string.
s/(\n    \.dim-toolbar__grouplabel \{[^}]*?\n        )white-space: nowrap;/${1}text-transform: uppercase;\n        white-space: nowrap;/;
