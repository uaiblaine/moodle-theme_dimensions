# Stop hiding the focus-only parts in the ordinary skin: the bar sits over Boost Union's navbar on every page.
s/body\.dimensions-focusable:not\(\.dimensions-focusmode\) \.dimensions-focus-only \{\n    display: none;\n\}\n//;
