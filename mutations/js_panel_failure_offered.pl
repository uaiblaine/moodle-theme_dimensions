# Never offer a failure to the client: the visitor's panel then shows the inline error instead of
# loading the poster's link, which is the page that answers the reader.
s/if \(client\.unanswered\(poster, state\.psort\)\) \{/if (client.unanswered === null) {/;
