# Offer every category of the subtree in the selector, clamped ones included: an unlisted or
# hidden subcategory's name is printed to a viewer who may not know it exists, and - since the
# walk descends through whatever it admits - so is everything underneath it.
s/            if \(!\$scope->is_open\(\$childid\)\) \{/            if (false) {/;
