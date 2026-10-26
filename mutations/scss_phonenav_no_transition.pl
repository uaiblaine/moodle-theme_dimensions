# Let the context bar's visibility transition reach a hidden control: it stays on screen for a slide's length.
s/(visibility: hidden;\n)(\s*)transition: none;\n/$1/;
