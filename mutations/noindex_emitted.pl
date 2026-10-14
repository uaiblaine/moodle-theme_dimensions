# Drop the robots directive: the public page then becomes indexable.
s/\n[ \t]*\$hook->add_html\('<meta name="robots" content="noindex">' \. "\\n"\);(?=\n)//;
