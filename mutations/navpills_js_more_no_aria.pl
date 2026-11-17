# Give More aria-current when it holds the current page: a summary announced as the current page (D96).
s/(        summary\.prepend\(row\.dot\);\n)(    \} else \{)/$1        summary.setAttribute('aria-current', 'page');\n$2/;
