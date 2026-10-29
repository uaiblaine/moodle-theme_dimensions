# Keep the "(0)" span when every course is withheld, where core prints none at zero: a category that looks
# empty to the outsider is then told apart from one that holds nothing.
s/if \(\$count === 0\) \{/if (false) {/;
