# Stop refusing a url that could break out of the CSS context. Both sinks print the url inside
# url('...') in a style attribute through a double stash - the landing page hero's background-image and
# the category poster's watermark - so a quote or a parenthesis in it closes the url and writes
# whatever follows straight into the page's CSS.
s/preg_match\(self::REFUSED, \$clean\)/false/;
