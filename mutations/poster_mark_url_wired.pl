# Print the watermark's crest address into the style attribute without the style url guard: a url
# holding a quote or a parenthesis then closes url() and the declaration after it.
s/\$crest = \$badges \? styleurl::safe\(\(string\) \$badges\[0\]\['url'\]\) : '';/\$crest = \$badges ? (string) \$badges[0]['url'] : '';/;
