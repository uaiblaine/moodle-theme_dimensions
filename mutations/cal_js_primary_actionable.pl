# Offer core's action when the viewer cannot act on it now.
s/    if \(item\.action !== null && item\.action\.actionable && actionUrl !== ''\) \{/    if (item.action !== null \&\& actionUrl !== '') {/;
