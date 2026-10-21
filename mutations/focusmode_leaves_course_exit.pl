# Give a single activity course that switched focus mode on the in-place exit: the page has no ordinary skin
# to go back to, and the service refuses the switch the button asks for.
s/\$leaveform = !focusmode::leaves_course\(\$cm\);/\$leaveform = true;/;
