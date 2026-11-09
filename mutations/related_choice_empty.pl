# A pair with an empty idnumber would be read as part of the choice.
s/if \(\$framework !== \x27\x27 && \$idnumber !== \x27\x27\) \{/if (true) {/;
