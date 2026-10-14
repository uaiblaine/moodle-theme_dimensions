# Rename the navbar_plugin_output() override so core never calls it, and the way into focus
# mode never reaches the navbar. The tests that go red assert the button's POSITION and not
# its presence, because a theme callback in lib.php would also produce a button - on the right
# of the notifications bell, which is the one place the design rules out.
s/function navbar_plugin_output\(/function navbar_plugin_output_disabled(/;
