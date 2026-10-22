# Keep the compact drawer's shadow under core's backdrop too: the page is dimmed twice below 991px.
s/\n            &:has\(\.modal-backdrop\.show\) \.drawer-primary\.show \{\n                box-shadow: none;\n            \}\n//;
