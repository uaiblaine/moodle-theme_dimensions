# Forward every anonymous hit on the showcase to the public page, whatever state the
# category is in: the existence of a listed or an unlisted category then leaks through the
# forward, which is the very oracle the one refusal exists to close.
s/\n[ \t]*if \(!categorypublicaccess::is_public\(\$categoryid\)\) \{\n[ \t]*return null;\n[ \t]*\}(?=\n)//;
