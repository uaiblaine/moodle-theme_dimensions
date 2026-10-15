# Stop refusing an ampersand, which is the one character every HTML character reference begins
# with. A browser HTML-decodes an attribute value before the CSS parser reads it, so `&apos;` and
# `&rpar;` are a quote and a parenthesis in another spelling - clean_param() passes them, the rest
# of the character class never sees them, and the url then closes its own url() and writes whatever
# follows over the landing page's hero or the poster's watermark.
#
# Anchored on the constant's NAME and not on the character class alone: `()&` is three characters a
# docblock could grow at any time, and the first match is the one perl takes.
s/REFUSED = (.*)\(\)&/REFUSED = $1()/;
