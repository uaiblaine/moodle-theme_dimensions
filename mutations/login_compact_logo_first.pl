# Ignore the compact logo the renderer resolved: the design rule - wherever the mockups
# print the brand, the image is theme_boost_union | logocompact - stops applying, a flavour's
# own mark never reaches the panel, and a site falls back to the plugin's logo or the wordmark.
s/        if \(\$compactlogo !== ''\) \{\n            return \$compactlogo;\n        \}\n\n//;
