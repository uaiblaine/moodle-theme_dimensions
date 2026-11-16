# Listen for Escape on the drawer's root panel alone: Escape on the close button of Boost's drawer band, where the
# focus lands when the drawer opens, would close nothing.
s/drawer\.addEventListener\('keydown', \(event\) => closeOnEscape\(drawer, event\)\);/root.addEventListener('keydown', (event) => closeOnEscape(drawer, event));/;
