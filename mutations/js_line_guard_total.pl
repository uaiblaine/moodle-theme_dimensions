# Write the bar's count into a line the component draws.
s/    if \(total && !drawnByLine\(total\)\) \{/    if (total) {/;
