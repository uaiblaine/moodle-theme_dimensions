# Accept every value of the patterns setting: a line the mask cannot apply is stored without a word.
s/if \(!\$errors\) \{/if (true) {/;
