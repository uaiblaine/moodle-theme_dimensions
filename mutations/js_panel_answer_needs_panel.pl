# Paint any answer as the panel, including one that carries none: a slice the server dropped is then
# drawn from the parent's flat listing, a panel of courses that are not the area's.
s/if \(!response\.panel \|\| Number\(response\.panel\.id\) !== id\) \{/if (!response && id < 0) {/;
