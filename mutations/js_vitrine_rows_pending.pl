# Take no token for the logged-in client's repaint: the same gap, on the account's shell.
s/^    const token = new Pending\('theme_dimensions\/categoryvitrine:rows'\);$/    const token = {resolve: () => null};/m;
