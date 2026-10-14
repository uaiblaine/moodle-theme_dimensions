# Never load again an address that differs from the one on screen by its fragment alone: a panel's
# failure then scrolls to the panel the module could not fill instead of loading the page.
s/if \(bare\.toString\(\) === here\.toString\(\)\) \{/if (bare.toString() === '') {/;
