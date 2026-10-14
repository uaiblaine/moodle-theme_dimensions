# Rename the body_attributes() override so core never calls it. The whole page still renders
# through the focus template; only the classes the stylesheet and the AMD module hang off are
# gone, which is invisible to anything but an assertion on the body tag itself.
s/function body_attributes\(/function body_attributes_disabled(/;
