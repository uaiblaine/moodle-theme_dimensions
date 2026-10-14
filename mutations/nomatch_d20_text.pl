# Link every entry of the "nothing in N areas" summary: a visitor's merely listed area, which has no
# page a visitor may open, becomes a link to nothing.
s/'haslink' => \(string\) \$entry\['url'\] !== '',/'haslink' => true,/;
