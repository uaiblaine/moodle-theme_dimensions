# Stop stopping the film that is already playing. Every card a pointer crosses is then left
# running, and a page walked over once is a dozen films playing at once - one of which may have
# been unmuted, on a card the reader has already left and whose sound control is therefore out of
# reach. Nothing errors and the first card still looks exactly right.
#
# The pattern is the CONDITION, so that a line added to the branch cannot kill it.
s/if \(playing && playing !== band\) \{/if (false) {/;
