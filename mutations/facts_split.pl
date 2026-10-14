# Send every node down the actions branch, so a label and its value collapse into one line
# and the information architecture the renderer exists for is gone.
s/if \(\$context\['content'\] !== ''\) \{/if (false) {/;
