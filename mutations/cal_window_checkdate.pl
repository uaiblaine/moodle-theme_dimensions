# Accept an impossible date such as 2026-02-30, which PHP rolls into March.
s/if \(!checkdate\(\(int\) \$parts\[2\], \(int\) \$parts\[3\], \(int\) \$parts\[1\]\)\) \{/if (false) {/;
