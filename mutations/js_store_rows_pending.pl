# Take no token for a repaint: Behat reads the template load after the answer as a settled page.
s/^    const pending = new Pending\('theme_dimensions\/listing:rows'\);$/    const pending = {resolve: (): void => undefined};/m;
