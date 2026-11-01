# Move the focus while the popover is still hidden.
s/if \(\(sheet \|\| at !== null\) && focused\.current !== key\) \{/if (focused.current !== key) {/;
