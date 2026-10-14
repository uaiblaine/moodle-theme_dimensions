# Stop stepping the watermark down: every poster gets the design's .20 whatever its pair, so on a
# pair with little contrast to spare the name falls under AA over its own watermark.
s/if \(branding::contrast\(\$ink, \$under\) >= branding::FLOOR && branding::contrast\(\$ink, \$disc\) >= self::CHEVRON_FLOOR\) \{/if (true) {/;
