# Hear the component's question event and leave it unnamed: the visitor's panel is asked again for
# every answer, a read the site pays for each time.
s/            detail\.question = question\(detail\.poster, detail\.psort\);/            question(detail.poster, detail.psort);/;
