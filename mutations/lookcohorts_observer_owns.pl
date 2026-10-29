# Any deleted cohort hides the menus it restricted, not a look cohort only.
s/if \(!\$cohort \|\| !self::owns\(\$cohort\)\) \{/if (!\$cohort) {/;
