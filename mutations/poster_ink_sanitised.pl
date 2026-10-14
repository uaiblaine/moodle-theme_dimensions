# Print the poster's ink into its style attribute as the payload holds it. The attribute is a CSS
# context Mustache's escaping does not protect, so a poisoned payload closes the declaration.
s/\$ink = branding::hex\(\(string\) \$branding\['inkcolour'\]\) \?\? branding::DEFAULT_INK;/\$ink = (string) \$branding['inkcolour'];/;
