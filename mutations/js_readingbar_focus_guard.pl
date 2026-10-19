# Forget the focus: a keyboard reader's bar swaps or leaves under the control they are on.
s/if \(bar\.nav\.contains\(document\.activeElement\)\) \{/if (bar.nav === document.activeElement) {/;
