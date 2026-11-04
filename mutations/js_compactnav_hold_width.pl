# Hold at every width: once compact, widening the window within the range never gives the full bar back.
s/const held = inrange && bar\.compact && width <= bar\.heldwidth;/const held = inrange && bar.compact;/;
