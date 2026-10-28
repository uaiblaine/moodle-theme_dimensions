# Never offer a failure to the page's client: the visitor's panel then shows the inline error instead of
# loading the poster's link, which is the page that answers the reader.
s/            if \(unanswered\(poster, st\.psort\)\) \{/            if (unanswered === null) {/;
