# Forget the focus: a keyboard reader's bar swaps or leaves under the control they are on.
s/if \(!focused \|\| !bar\.nav\.contains\(focused\)\) \{/if (!focused || bar.nav !== focused) {/;
