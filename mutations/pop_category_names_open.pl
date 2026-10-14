# Name every category a page row belongs to, clamped ones included: a viewer rescued into a course of an
# unlisted subcategory reads that subcategory's name on the card's overline.
s/if \(isset\(\$names\[\$categoryid\]\) \|\| !isset\(\$scope->categories\[\$categoryid\]\) \|\| !\$scope->is_open\(\$categoryid\)\) \{/if (isset(\$names[\$categoryid]) || !isset(\$scope->categories[\$categoryid])) {/;
