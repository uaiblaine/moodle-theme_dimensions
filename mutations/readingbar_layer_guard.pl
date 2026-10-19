# Draw the layer wherever the band is armed: the setting and the visitor's actions stop applying to it.
s/if \(readingbar::context_applies\(\$this->page\)\) \{/if (true) {/;
