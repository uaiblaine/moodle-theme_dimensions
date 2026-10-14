# Drop the plugin's category gate from the web service: a category local_unlistedcourses
# withholds from this viewer is listed to them, card by card, through an address the page
# itself would never have rendered.
s/if \(!category_access::is_category_discoverable\(\$categoryid\)\) \{/if (false) {/;
