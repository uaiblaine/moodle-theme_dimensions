# Rename the activity_navigation() override so core never calls it: the completion line and the theme's
# previous and next row never reach the focused page, which looks finished without them.
s/public function activity_navigation\(\) \{/public function activity_navigation_disabled() {/;
