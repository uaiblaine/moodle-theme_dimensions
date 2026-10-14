# Escape the card text before the template does: a bare ampersand reaches the card as &amp;amp;.
s/(format_string\(\$cardsummary, true, \['context' => \$context, 'escape' => )false/${1}true/;
