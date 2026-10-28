# Hear the component's question event and leave it unnamed: the visitor's panel is asked again for
# every answer, a read the site pays for each time.
s/            event\.detail\.question = client\.question\(event\.detail\.poster, event\.detail\.psort\);/            client.question(event.detail.poster, event.detail.psort);/;
