# Let the FORM accept a pair under the AA floor. The save still refuses it through
# branding::validate(), so nothing illegible is published - but the manager meets an exception
# page instead of the ratio on the field they typed, which is the whole of what this check buys.
s/if \(\$ratio < branding::FLOOR\) \{/if (false) {/;
