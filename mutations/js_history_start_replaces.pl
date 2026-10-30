# Push the remembered address an account lands on instead of replacing it: Back then stops on the bare address, which
# draws the same page again.
s/Listing\.writeUrl\(current\(\), false\);/Listing.writeUrl(current(), true);/;
