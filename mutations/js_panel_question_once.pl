# Ask the same question again for every answer: the back button over a change of view, or a panel the
# server drew, costs the site a slice's reads for the answer already on screen.
s/if \(answered\(question\)\) \{/if (answered(question) && question === null) {/;
